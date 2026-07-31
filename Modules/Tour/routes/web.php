<?php

use Illuminate\Support\Facades\Route;
use Modules\Tour\Http\Controllers\TourController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tours', TourController::class)->names('tour');
});
