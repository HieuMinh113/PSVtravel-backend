<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\User;
use App\Services\TepTaiLen;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Payment;

/**
 * Phần dùng chung cho khoản thu ở tab Thanh toán của đơn và trang "Duyệt
 * khoản thu" của kế toán: ảnh chứng minh chuyển khoản, nút duyệt / từ chối,
 * chuông báo.
 *
 * Luồng: nhân viên ghi khoản thu + ảnh → "Chờ kế toán duyệt" (chưa tính vào
 * đã thu / tiền cọc) → kế toán xem ảnh, bấm "Đã nhận tiền" hoặc "Từ chối".
 */
class KhoanThu
{
    public static function laKeToan(): bool
    {
        return (bool) auth()->user()?->can('Approve:Payment');
    }

    public static function oChungTu(): FileUpload
    {
        return FileUpload::make('proof_images')
            ->label('Ảnh chứng minh chuyển khoản')
            ->image()
            ->acceptedFileTypes(TepTaiLen::ANH)
            ->multiple()
            ->maxFiles(3)
            ->maxSize(10240)
            ->disk(Payment::DIA_CHUNG_TU)
            ->directory('chung-tu-thanh-toan')
            ->visibility('private')
            ->openable()
            ->downloadable()
            ->required(fn (Get $get, string $operation) => $operation === 'create' && $get('method') === 'bank_transfer')
            ->helperText('Chụp màn hình biên lai chuyển khoản, tối đa 3 ảnh. Bắt buộc khi khách chuyển khoản.')
            ->columnSpanFull();
    }

    public static function cotChungTu(): ImageColumn
    {
        return ImageColumn::make('proof_images')
            ->label('Chứng từ')
            ->disk(Payment::DIA_CHUNG_TU)
            ->visibility('private')
            ->stacked()
            ->limit(3)
            ->imageHeight(36)
            ->placeholder('—')
            ->action(self::xemChungTu());
    }

    /** Mở ảnh to để kế toán đối chiếu. */
    public static function xemChungTu(): Action
    {
        return Action::make('xemChungTu')
            ->label('Xem chứng từ')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading(fn (Payment $record) => 'Chứng từ — '.number_format($record->amount, 0, ',', '.').'đ')
            ->modalContent(fn (Payment $record) => view('filament.khoan-thu.chung-tu', ['anh' => self::linkAnh($record)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng')
            ->visible(fn (Payment $record) => ! empty($record->proof_images));
    }

    /** @return list<string> link xem ảnh có chữ ký, sống 30 phút */
    public static function linkAnh(Payment $p): array
    {
        return collect($p->proof_images ?? [])
            ->map(fn ($duong) => Storage::disk(Payment::DIA_CHUNG_TU)->temporaryUrl($duong, now()->addMinutes(30)))
            ->all();
    }

    public static function nutDuyet(): Action
    {
        return Action::make('duyetKhoanThu')
            ->label('Đã nhận tiền')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Payment $record) => auth()->user()?->can('duyet', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (Payment $record) => 'Xác nhận đã nhận '.number_format($record->amount, 0, ',', '.').'đ?')
            ->modalDescription(fn (Payment $record) => 'Đơn '.$record->booking?->booking_code.' — '.$record->booking?->customer_name
                .'. Khoản này sẽ được tính vào đã thu và tiền cọc của đơn.')
            ->modalContent(fn (Payment $record) => $record->proof_images
                ? view('filament.khoan-thu.chung-tu', ['anh' => self::linkAnh($record)])
                : null)
            ->modalSubmitActionLabel('Đã nhận tiền')
            ->action(function (Payment $record) {
                $record->update(['status' => 'success']);
                self::baoNguoiNhap($record, 'Kế toán đã xác nhận nhận tiền', 'success');
                Notification::make()->title('Đã duyệt khoản thu')->success()->send();
            });
    }

    public static function nutTuChoi(): Action
    {
        return Action::make('tuChoiKhoanThu')
            ->label('Từ chối')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Payment $record) => auth()->user()?->can('duyet', $record) ?? false)
            ->schema([
                Textarea::make('ly_do')->label('Lý do (nhân viên sẽ thấy)')->required()->rows(2),
            ])
            ->modalHeading('Từ chối khoản thu')
            ->modalSubmitActionLabel('Từ chối')
            ->action(function (Payment $record, array $data) {
                $record->forceFill([
                    'status' => 'failed',
                    'note' => trim(($record->note ? $record->note."\n" : '').'Kế toán từ chối: '.$data['ly_do']),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ])->save();
                self::baoNguoiNhap($record, 'Kế toán TỪ CHỐI khoản thu — '.$data['ly_do'], 'danger');
                Notification::make()->title('Đã từ chối khoản thu')->success()->send();
            });
    }

    /** Nhân viên vừa ghi khoản thu chờ duyệt → chuông cho kế toán. */
    public static function baoKeToan(Payment $p): void
    {
        try {
            $keToan = User::permission('Approve:Payment')->whereKeyNot(auth()->id())->get();
            if ($keToan->isEmpty()) {
                return;
            }
            $don = $p->booking;
            Notification::make()
                ->title('Khoản thu chờ duyệt: '.number_format($p->amount, 0, ',', '.').'đ')
                ->body(collect([
                    $don?->booking_code.' · '.$don?->customer_name,
                    'Người nhập: '.(auth()->user()?->name ?? '—').(empty($p->proof_images) ? ' · CHƯA có ảnh chứng từ' : ''),
                ])->implode('<br>'))
                ->icon('heroicon-o-banknotes')
                ->status('warning')
                ->actions([
                    Action::make('duyet')->label('Mở danh sách chờ duyệt')->button()->markAsRead()
                        ->url(PaymentResource::getUrl('index', panel: 'admin')),
                ])
                ->sendToDatabase($keToan, isEventDispatched: true);
        } catch (\Throwable $e) {
            Log::warning('Khoản thu: không báo được kế toán', ['payment' => $p->id, 'loi' => $e->getMessage()]);
        }
    }

    private static function baoNguoiNhap(Payment $p, string $tieuDe, string $mau): void
    {
        $nguoi = $p->receivedBy;
        if (! $nguoi || $nguoi->id === auth()->id()) {
            return;
        }
        $don = $p->booking;
        Notification::make()
            ->title($tieuDe)
            ->body(number_format($p->amount, 0, ',', '.').'đ · '.$don?->booking_code.' · '.$don?->customer_name)
            ->status($mau)
            ->actions([
                Action::make('xem')->label('Mở đơn')->button()->markAsRead()
                    ->url($don ? BookingResource::getUrl('view', ['record' => $don], panel: 'admin') : null),
            ])
            ->sendToDatabase($nguoi, isEventDispatched: true);
    }
}
