<?php

namespace Modules\Booking\Console;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

/**
 * Mỗi sáng: đơn tới hạn nhắn khách đóng phần còn lại (mặc định ngày đi − 7)
 * mà chưa nhắn → báo chuông cho NGƯỜI TẠO đơn. Đơn khách tự đặt trên web chưa
 * ai xác nhận (chưa có người phụ trách) thì báo mọi người được sửa đơn tour.
 * Mỗi đơn báo một lần (remind_notified_at); đổi hạn thì được báo lại.
 */
class NhacNhanKhachCommand extends Command
{
    protected $signature = 'don-tour:nhac-nhan-khach';

    protected $description = 'Báo chuông các đơn tour tới hạn nhắn khách đóng tiền';

    public function handle(): int
    {
        $soDon = 0;

        Booking::toiHanNhac()
            ->whereNull('remind_notified_at')
            ->with(['tour:id,name', 'departure:id,start_date', 'nguoiTao'])
            ->chunkById(100, function ($dsDon) use (&$soDon) {
                foreach ($dsDon as $don) {
                    $nguoiNhan = $don->nguoiTao ? collect([$don->nguoiTao]) : $this->nguoiSuaDon();
                    if ($nguoiNhan->isEmpty()) {
                        continue;
                    }

                    try {
                        Notification::make()
                            ->title('Tới hạn nhắn khách đóng tiền: '.$don->customer_name)
                            ->body(collect([
                                $don->booking_code.' · '.($don->tour?->name ?? ''),
                                'Khởi hành: '.($don->departure?->start_date?->format('d/m/Y') ?? '—').' · SĐT: '.$don->customer_phone,
                                'Còn lại: '.number_format($don->conLai(), 0, ',', '.').'đ',
                            ])->implode('<br>'))
                            ->icon('heroicon-o-bell-alert')
                            ->status('warning')
                            ->actions([
                                Action::make('xem')->label('Mở đơn')->button()->markAsRead()
                                    ->url(BookingResource::getUrl('view', ['record' => $don], panel: 'admin')),
                            ])
                            ->sendToDatabase($nguoiNhan, isEventDispatched: true);

                        $don->forceFill(['remind_notified_at' => now()])->saveQuietly();
                        $soDon++;
                    } catch (\Throwable $e) {
                        Log::warning('Nhắc khách: không gửi được thông báo', ['don' => $don->booking_code, 'loi' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Đã báo {$soDon} đơn tới hạn nhắn khách.");

        return self::SUCCESS;
    }

    private function nguoiSuaDon()
    {
        try {
            return User::permission('Update:Booking')->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
