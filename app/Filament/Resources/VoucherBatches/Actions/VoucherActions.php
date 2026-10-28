<?php

namespace App\Filament\Resources\VoucherBatches\Actions;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Voucher\InVoucher;
use App\Services\Voucher\XuatVoucher;
use Database\Seeders\QuyenVoucherSeeder;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Nút thao tác voucher dùng chung cho trang đợt, danh sách mã và bảng mã
 * trong đợt: tạo mã, tải file in / ảnh, đánh dấu đã dùng, bỏ đánh dấu, huỷ.
 */
class VoucherActions
{
    /** Số ảnh tối đa mỗi lần tải ZIP (mỗi ảnh ~1 MB) */
    public const TOI_DA_ANH = 200;

    public static function taoMa(): Action
    {
        return Action::make('taoMa')
            ->label('Tạo mã')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('success')
            ->visible(fn (VoucherBatch $record) => ! $record->trashed() && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading(fn (VoucherBatch $record) => 'Tạo mã — '.$record->name)
            ->modalDescription(fn (VoucherBatch $record) => 'Mỗi mã dùng một lần. Số thứ tự nối tiếp các mã đã có ('.$record->vouchers()->count().' mã).')
            ->schema([
                TextInput::make('so_luong')->label('Số mã cần tạo')->numeric()->integer()->minValue(1)->maxValue(VoucherBatch::TOI_DA_MOI_LAN)->default(100)->required()
                    ->helperText('1 – '.VoucherBatch::TOI_DA_MOI_LAN.' mã mỗi lần.'),
                TextInput::make('phat_cho')->label('Phát cho (không bắt buộc)')->maxLength(255)
                    ->placeholder('Hội chợ du lịch 11/2026, khách đoàn công ty ABC…')
                    ->helperText('Voucher không ghi tên — ai cầm cũng dùng được. Ghi để biết lô mã này phát ở đâu.'),
            ])
            ->modalSubmitActionLabel('Tạo mã')
            ->action(function (VoucherBatch $record, array $data) {
                $n = (int) $data['so_luong'];
                $dau = $record->taoMa($n, $data['phat_cho'] ?? null);
                Notification::make()->success()
                    ->title('Đã tạo '.$n.' mã')
                    ->body('Số thứ tự '.self::so($dau).' – '.self::so($dau + $n - 1).'. Bấm "Tải file in" để in, chọn "Theo số thứ tự" nếu chỉ in lô vừa tạo.')
                    ->send();
            });
    }

    /** Tải file in / ảnh cả đợt (chọn phạm vi mã) */
    public static function xuatFile(): Action
    {
        return Action::make('xuatFile')
            ->label('Tải file in')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('primary')
            ->visible(fn (VoucherBatch $record) => auth()->user()?->can('update', $record) ?? false)
            ->modalHeading(fn (VoucherBatch $record) => 'Tải file in — '.$record->name)
            ->modalDescription('Nhà in thường cần bản tràn lề. In ở văn phòng: A4, 2 voucher / tờ, in 2 mặt lật cạnh dài, tỉ lệ 100%, rồi cắt theo vạch.')
            ->schema(fn (VoucherBatch $record) => [
                self::chonKieu(),
                Radio::make('pham')->label('Mã cần in')->required()->default('chua_dung')->live()
                    ->options([
                        'chua_dung' => 'Mã chưa dùng, chưa huỷ ('.$record->vouchers()->where('status', 'unused')->count().')',
                        'khoang' => 'Theo số thứ tự',
                        'tat_ca' => 'Tất cả mã ('.$record->vouchers()->count().')',
                    ]),
                Grid::make(2)->visible(fn (Get $get) => $get('pham') === 'khoang')->schema([
                    TextInput::make('tu')->label('Từ số')->numeric()->integer()->minValue(1)->default(1)->required(),
                    TextInput::make('den')->label('Đến số')->numeric()->integer()->minValue(1)
                        ->default(fn () => (int) $record->vouchers()->max('serial') ?: 1)->required(),
                ]),
            ])
            ->modalSubmitActionLabel('Tạo file')
            ->action(function (VoucherBatch $record, array $data, Action $action) {
                $q = $record->vouchers()->orderBy('serial');
                match ($data['pham']) {
                    'chua_dung' => $q->where('status', 'unused'),
                    'khoang' => $q->whereBetween('serial', [min((int) $data['tu'], (int) $data['den']), max((int) $data['tu'], (int) $data['den'])]),
                    default => null,
                };
                self::xuat($record, $data['kieu'], $q->pluck('id')->all(), $action);
            });
    }

    /** Tải file in các mã đang chọn trong bảng mã */
    public static function xuatMaChon(): BulkAction
    {
        return BulkAction::make('xuatMaChon')
            ->label('Tải file in mã đã chọn')
            ->icon(Heroicon::OutlinedPrinter)
            ->visible(fn () => auth()->user()?->can('Update:VoucherBatch') ?? false)
            ->schema([self::chonKieu()])
            ->modalSubmitActionLabel('Tạo file')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action) {
                $dot = $records->pluck('batch_id')->unique();
                if ($dot->count() !== 1) {
                    Notification::make()->warning()->title('Chọn mã của cùng một đợt')
                        ->body('Mỗi đợt một mẫu in khác nhau — lọc theo đợt rồi chọn lại.')->send();

                    return;
                }
                self::xuat(VoucherBatch::withTrashed()->findOrFail($dot->first()), $data['kieu'], $records->pluck('id')->all(), $action);
            });
    }

    /** Ảnh JPG một mã — gửi khách qua Zalo / Facebook */
    public static function taiAnh(): Action
    {
        return Action::make('taiAnh')
            ->label('Tải ảnh')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->visible(fn (Voucher $record) => $record->status === 'unused' && (auth()->user()?->can('Update:VoucherBatch') ?? false))
            ->action(function (Voucher $record, Action $action) {
                try {
                    $jpg = (new InVoucher)->jpgMot($record->batch, $record);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('Chưa tải được ảnh')->body($e->getMessage())->send();

                    return;
                }
                $tep = tempnam(sys_get_temp_dir(), 'voucher');
                file_put_contents($tep, $jpg);
                $action->redirect(XuatVoucher::cat($tep, $record->code.'.jpg', (int) auth()->id(), 10));
            });
    }

    public static function daDung(): Action
    {
        return Action::make('daDung')
            ->label('Đánh dấu đã dùng')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Voucher $record) => $record->trangThai() === 'con_dung' && (auth()->user()?->can(QuyenVoucherSeeder::QUET) ?? false))
            ->modalHeading(fn (Voucher $record) => 'Xác nhận '.$record->code.' đã dùng')
            ->modalDescription(fn (Voucher $record) => 'Mệnh giá '.self::tien($record->batch->value).'. Sau khi xác nhận, mã không dùng lại được.')
            ->schema([self::oGhiChu()])
            ->action(function (Voucher $record, array $data) {
                /** @var User $u */
                $u = auth()->user();
                $record->danhDauDaDung($u, $data['ghi_chu'] ?? null)
                    ? Notification::make()->success()->title('Đã xác nhận '.$record->code.' đã dùng')->send()
                    : Notification::make()->danger()->title('Mã này vừa được dùng hoặc không còn hiệu lực')->send();
            });
    }

    public static function boDanhDau(): Action
    {
        return Action::make('boDanhDau')
            ->label('Bỏ đánh dấu đã dùng')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Voucher $record) => $record->status === 'used' && self::duocBoDanhDau($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Voucher $record) => 'Trả '.$record->code.' về chưa dùng?')
            ->modalDescription('Chỉ dùng khi quét nhầm. Việc này được ghi vào nhật ký.')
            ->action(function (Voucher $record) {
                /** @var User $u */
                $u = auth()->user();
                $record->boDanhDau($u);
                Notification::make()->success()->title($record->code.' đã trả về chưa dùng')->send();
            });
    }

    public static function huy(): Action
    {
        return Action::make('huy')
            ->label('Huỷ mã')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Voucher $record) => $record->status === 'unused' && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading(fn (Voucher $record) => 'Huỷ '.$record->code.'?')
            ->modalDescription('Khách kiểm tra sẽ thấy "Đã huỷ". Dùng khi voucher bị mất, in hỏng, phát nhầm.')
            ->schema([self::oLyDo()])
            ->action(function (Voucher $record, array $data) {
                /** @var User $u */
                $u = auth()->user();
                $record->huy($u, $data['ly_do'] ?? null);
                Notification::make()->success()->title('Đã huỷ '.$record->code)->send();
            });
    }

    public static function huyHangLoat(): BulkAction
    {
        return BulkAction::make('huyHangLoat')
            ->label('Huỷ mã đã chọn')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn () => auth()->user()?->can('Update:Voucher') ?? false)
            ->modalDescription('Chỉ huỷ mã chưa dùng. Khách kiểm tra sẽ thấy "Đã huỷ".')
            ->schema([self::oLyDo()])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) {
                /** @var User $u */
                $u = auth()->user();
                $n = 0;
                foreach ($records->where('status', 'unused') as $v) {
                    $v->huy($u, $data['ly_do'] ?? null);
                    $n++;
                }
                Notification::make()->success()->title('Đã huỷ '.$n.' mã')->send();
            });
    }

    public static function ghiPhatCho(): BulkAction
    {
        return BulkAction::make('ghiPhatCho')
            ->label('Ghi "phát cho"')
            ->icon(Heroicon::OutlinedUserGroup)
            ->visible(fn () => auth()->user()?->can('Update:Voucher') ?? false)
            ->schema([TextInput::make('phat_cho')->label('Phát cho')->maxLength(255)->placeholder('Khách đoàn công ty ABC')])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) {
                Voucher::query()->whereKey($records->modelKeys())->update(['issued_to' => $data['phat_cho'] ?: null, 'updated_at' => now()]);
                Notification::make()->success()->title('Đã ghi cho '.$records->count().' mã')->send();
            });
    }

    /** Người quét được bỏ đánh dấu của chính mình trong 30 phút; quá thời gian thì cần quyền sửa mã */
    public static function duocBoDanhDau(Voucher $v): bool
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }

        return $u->can('update', $v)
            || ($u->can(QuyenVoucherSeeder::QUET) && (int) $v->used_by === (int) $u->id && $v->used_at?->gt(now()->subMinutes(30)));
    }

    /** @param list<int> $ids */
    private static function xuat(VoucherBatch $d, string $kieu, array $ids, Action|BulkAction $action): void
    {
        if (! $ids) {
            Notification::make()->warning()->title('Không có mã nào để in')->body('Bấm "Tạo mã" trước, hoặc chọn phạm vi khác.')->send();

            return;
        }
        if (! $d->front_image || ! Storage::disk('public')->exists($d->front_image)) {
            Notification::make()->danger()->title('Đợt này chưa có ảnh mặt trước')->body('Tải ảnh lên ở phần "Mẫu in" rồi bấm Lưu.')->send();

            return;
        }
        if ($kieu === 'anh' && count($ids) > self::TOI_DA_ANH) {
            Notification::make()->warning()->title('Tối đa '.self::TOI_DA_ANH.' ảnh mỗi lần')
                ->body('Chọn "Theo số thứ tự" để tải từng phần. In số lượng lớn thì dùng file PDF.')->send();

            return;
        }
        /** @var User $u */
        $u = auth()->user();
        try {
            $kq = XuatVoucher::chay($d, $kieu, $ids, $u);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Chưa tạo được file')->body($e->getMessage())->send();

            return;
        }
        if ($kq['nen']) {
            Notification::make()->info()->title('Đang tạo file '.count($ids).' mã')
                ->body('Mất khoảng 1 phút. Xong sẽ báo ở chuông 🔔 góc trên bên phải, kèm nút tải.')->send();

            return;
        }
        $action->redirect($kq['url']);
    }

    private static function chonKieu(): Radio
    {
        return Radio::make('kieu')->label('Loại file')->options(XuatVoucher::kieu())->default('tran_le')->required();
    }

    private static function oGhiChu(): Textarea
    {
        return Textarea::make('ghi_chu')->label('Ghi chú (không bắt buộc)')->rows(2)->maxLength(500)
            ->placeholder('Mã đơn / tên khách / bàn số…');
    }

    private static function oLyDo(): TextInput
    {
        return TextInput::make('ly_do')->label('Lý do')->maxLength(250)->placeholder('Mất, in hỏng, phát nhầm…');
    }

    public static function so(int $n): string
    {
        return str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function tien(?int $n): string
    {
        return number_format((int) $n, 0, ',', '.').'₫';
    }
}
