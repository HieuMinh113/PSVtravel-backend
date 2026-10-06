<?php

namespace Modules\Alliance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Alliance\Console\DongBoLienMinhCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AllianceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Alliance';

    protected string $nameLower = 'alliance';

    protected array $commands = [
        DongBoLienMinhCommand::class,
    ];

    /**
     * Cần container "scheduler" (php artisan schedule:work) đang chạy — xem
     * docker-compose.yml / docker-compose.prod.yml.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('lien-minh:dong-bo')
            ->everyFiveMinutes()
            ->withoutOverlapping(30);
    }
}
