<?php

use Illuminate\Support\Facades\Route;
use Modules\Guide\Http\Controllers\Api\GuideApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('guides', [GuideApiController::class, 'index']);
    Route::get('guides-slugs', [GuideApiController::class, 'slugs']);
    Route::get('guides/{slug}', [GuideApiController::class, 'show']);

    // Ghi nhận lượt đọc — trình duyệt gọi, nên có giới hạn tần suất riêng
    Route::post('guides/{slug}/view', [GuideApiController::class, 'ghiNhanLuotXem'])
        ->middleware('throttle:view');
});