<?php

$models = [
    \Modules\Tour\Models\Tour::class,
    \Modules\Tour\Models\TourDeparture::class,
    \Modules\Tour\Models\TourItinerary::class,
    \Modules\Tour\Models\TourImage::class,
    \Modules\Booking\Models\Booking::class,
    \Modules\Banner\Models\Banner::class,
    \Modules\Category\Models\Category::class,
    \Modules\Guide\Models\Guide::class,
    \Modules\Moment\Models\Moment::class,
    \Modules\Review\Models\Review::class,
    \Spatie\Activitylog\Models\Activity::class,
    \App\Models\User::class,
];

foreach ($models as $m) {
    $p = \Illuminate\Support\Facades\Gate::getPolicyFor($m);
    echo str_pad(class_basename($m), 20).' => '.($p ? get_class($p) : '*** THIEU POLICY ***').PHP_EOL;
}