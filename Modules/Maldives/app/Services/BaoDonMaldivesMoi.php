<?php

namespace Modules\Maldives\Services;

use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Maldives\Models\MaldivesBooking;
use Modules\Page\Models\Setting;

/**
 * Khách vừa đặt combo Maldives trên website → chuông trên trang quản trị cho
 * ai xử lý được đơn Maldives + thư báo về hộp thư công ty + thư xác nhận cho
 * khách (nếu có email). Lỗi gửi không được làm hỏng việc đặt: đơn đã lưu rồi.
 */
class BaoDonMaldivesMoi
{
    public static function gui(MaldivesBooking $don): void
    {
        $tomTat = self::tomTat($don);

        try {
            $nguoiNhan = User::permission('Update:MaldivesBooking')->get();
            if ($nguoiNhan->isNotEmpty()) {
                Notification::make()
                    ->title('Đơn combo Maldives mới: '.$don->customer_name)
                    ->body(implode('<br>', array_map('e', [...$tomTat, 'Chưa ai nhận — mở đơn và bấm "Nhận đơn".'])))
                    ->icon('heroicon-o-sun')
                    ->status('success')
                    ->actions([
                        Action::make('xem')->label('Mở đơn')->button()->markAsRead()
                            ->url(MaldivesBookingResource::getUrl('view', ['record' => $don], panel: 'admin')),
                    ])
                    ->sendToDatabase($nguoiNhan, isEventDispatched: true);
            }
        } catch (\Throwable $e) {
            Log::warning('Đơn Maldives: không gửi được chuông', ['don' => $don->code, 'loi' => $e->getMessage()]);
        }

        try {
            $hopThu = Setting::lay('email') ?: config('mail.from.address');
            if ($hopThu) {
                Mail::raw("Đơn combo Maldives mới từ website\n\n".implode("\n", $tomTat)
                    ."\nKhách: {$don->customer_name} · {$don->customer_phone}".($don->customer_email ? " · {$don->customer_email}" : '')
                    .($don->note ? "\nGhi chú: {$don->note}" : ''), fn ($m) => $m->to($hopThu)->subject("[Maldives] Đơn mới {$don->code}"));
            }
            if ($don->customer_email) {
                $hotline = Setting::lay('hotline') ?: '0907 870 707';
                Mail::raw("Chào anh/chị {$don->customer_name},\n\nPSV Travel đã nhận yêu cầu đặt combo Maldives của anh/chị:\n".implode("\n", $tomTat)
                    ."\n\nĐây là giá tạm tính. Nhân viên sẽ gọi lại để xác nhận còn phòng và báo số tiền chính xác."
                    ."\nCần hỗ trợ ngay: gọi {$hotline}.\n\nCảm ơn anh/chị!\nPSV Travel",
                    fn ($m) => $m->to($don->customer_email)->subject("PSV Travel đã nhận yêu cầu {$don->code}"));
            }
        } catch (\Throwable $e) {
            Log::error('Không gửi được mail đơn Maldives '.$don->code.': '.$e->getMessage());
        }
    }

    /** @return list<string> */
    public static function tomTat(MaldivesBooking $don): array
    {
        $khach = $don->adults.' người lớn'.($don->children ? ", {$don->children} trẻ em" : '').($don->infants ? ", {$don->infants} em bé" : '');

        return array_values(array_filter([
            'Mã đơn: '.$don->code,
            'Combo: '.$don->combo_name,
            'Khởi hành: '.($don->start_date?->format('d/m/Y') ?? 'chưa chọn ngày').($don->departure_city ? ' từ '.$don->departure_city : ''),
            $don->room_type_name ? 'Hạng phòng: '.$don->room_type_name.($don->rooms ? " ({$don->rooms} phòng)" : '') : null,
            'Số khách: '.$khach,
            $don->total_usd ? 'Tạm tính: $'.number_format($don->total_usd, 0, ',', '.')
                .($don->total_vnd ? ' ≈ '.number_format($don->total_vnd, 0, ',', '.').'đ' : '') : 'Tạm tính: nhân viên báo giá',
        ]));
    }
}
