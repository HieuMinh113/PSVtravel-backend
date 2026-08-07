<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(\Modules\Tour\Models\Tour::class, \Modules\Tour\Policies\TourPolicy::class);
        Gate::policy(\Modules\Booking\Models\Booking::class, \Modules\Booking\Policies\BookingPolicy::class);
        Gate::policy(\Modules\Tour\Models\TourDeparture::class, \Modules\Tour\Policies\TourDeparturePolicy::class);
        Gate::policy(\Modules\Tour\Models\TourItinerary::class, \Modules\Tour\Policies\TourItineraryPolicy::class);
        Gate::policy(\Modules\Tour\Models\TourImage::class, \Modules\Tour\Policies\TourImagePolicy::class);
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);
    }
}
