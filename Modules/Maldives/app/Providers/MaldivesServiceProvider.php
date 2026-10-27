<?php

namespace Modules\Maldives\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class MaldivesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Maldives';

    protected string $nameLower = 'maldives';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
