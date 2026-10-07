<?php

namespace Modules\Visa\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Visa\Services\XuatTam;
use Nwidart\Modules\Support\ModuleServiceProvider;

class VisaServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Visa';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'visa';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Dọn file ZIP hồ sơ đã xuất mà không ai tải (chứa giấy tờ khách)
        $schedule->call(fn () => XuatTam::donDep())
            ->name('visa:don-zip-tam')
            ->daily();
    }
}
