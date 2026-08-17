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
        Gate::policy(\Modules\Page\Models\Page::class, \Modules\Page\Policies\PagePolicy::class);
        Gate::policy(\Modules\Page\Models\Setting::class, \Modules\Page\Policies\SettingPolicy::class);
        Gate::policy(\Modules\Flight\Models\Airline::class, \Modules\Flight\Policies\AirlinePolicy::class);
        Gate::policy(\Modules\Flight\Models\FlightDeal::class, \Modules\Flight\Policies\FlightDealPolicy::class);
        Gate::policy(\Modules\Visa\Models\VisaCountry::class, \Modules\Visa\Policies\VisaCountryPolicy::class);
        Gate::policy(\Modules\Visa\Models\VisaProvider::class, \Modules\Visa\Policies\VisaProviderPolicy::class);
        Gate::policy(\Modules\Category\Models\Category::class, \Modules\Category\Policies\CategoryPolicy::class);
        Gate::policy(\Modules\Guide\Models\Guide::class, \Modules\Guide\Policies\GuidePolicy::class);
        Gate::policy(\Modules\Moment\Models\Moment::class, \Modules\Moment\Policies\MomentPolicy::class);
        Gate::policy(\Modules\Review\Models\Review::class, \Modules\Review\Policies\ReviewPolicy::class);
        Gate::policy(\Modules\Banner\Models\Banner::class, \Modules\Banner\Policies\BannerPolicy::class);
        Gate::policy(\Modules\Tour\Models\Tour::class, \Modules\Tour\Policies\TourPolicy::class);
        Gate::policy(\Modules\Booking\Models\Booking::class, \Modules\Booking\Policies\BookingPolicy::class);
        Gate::policy(\Modules\Tour\Models\TourDeparture::class, \Modules\Tour\Policies\TourDeparturePolicy::class);
        Gate::policy(\Modules\Tour\Models\TourItinerary::class, \Modules\Tour\Policies\TourItineraryPolicy::class);
        Gate::policy(\Modules\Tour\Models\TourImage::class, \Modules\Tour\Policies\TourImagePolicy::class);
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);
        Gate::policy(\Modules\Booking\Models\Payment::class, \Modules\Booking\Policies\PaymentPolicy::class);
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('booking', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });
        }

}
