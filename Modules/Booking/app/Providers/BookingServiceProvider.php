<?php

namespace Modules\Booking\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class BookingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Booking';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'booking';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Booking\Console\NhacNhanKhachCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // 8 giờ sáng: báo chuông đơn tới hạn nhắn khách đóng tiền. Chạy thêm
        // 13 giờ để đơn tạo trong buổi sáng với hạn hôm nay cũng được báo.
        $schedule->command('don-tour:nhac-nhan-khach')->dailyAt('08:00')->withoutOverlapping();
        $schedule->command('don-tour:nhac-nhan-khach')->dailyAt('13:00')->withoutOverlapping();
    }
}
