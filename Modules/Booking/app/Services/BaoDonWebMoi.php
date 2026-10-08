<?php

namespace Modules\Booking\Services;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

/**
 * Khách vừa đặt tour trên website → chuông (kèm tiếng báo trên trang quản trị)
 * cho mọi người xử lý được đơn (quyền Update:Booking). Ai xác nhận trước thì
 * đơn thành của người đó.
 *
 * Lỗi gửi không được làm hỏng việc đặt tour: đơn đã lưu rồi.
 */
class BaoDonWebMoi
{
    public static function gui(Booking $don): void
    {
        try {
            $nguoiNhan = User::permission('Update:Booking')->get();
            if ($nguoiNhan->isEmpty()) {
                return;
            }
            $don->loadMissing(['tour:id,name', 'departure:id,start_date']);

            Notification::make()
                ->title('Đơn đặt tour mới từ website: '.$don->customer_name)
                ->body(collect([
                    $don->booking_code.' · '.($don->tour?->name ?? ''),
                    'Khởi hành: '.($don->departure?->start_date?->format('d/m/Y') ?? '—')
                        .' · '.($don->adults + $don->children).' khách · SĐT: '.$don->customer_phone,
                    'Chưa ai nhận — bấm Xác nhận để nhận đơn.',
                ])->implode('<br>'))
                ->icon('heroicon-o-shopping-cart')
                ->status('success')
                ->actions([
                    Action::make('xem')->label('Mở đơn')->button()->markAsRead()
                        ->url(BookingResource::getUrl('view', ['record' => $don], panel: 'admin')),
                ])
                ->sendToDatabase($nguoiNhan, isEventDispatched: true);
        } catch (\Throwable $e) {
            Log::warning('Đơn tour web: không gửi được chuông', ['don' => $don->booking_code, 'loi' => $e->getMessage()]);
        }
    }
}
