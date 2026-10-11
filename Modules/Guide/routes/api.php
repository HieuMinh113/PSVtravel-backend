<?php

use Illuminate\Support\Facades\Route;
use Modules\Guide\Http\Controllers\Api\GuideApiController;
use Modules\Guide\Http\Controllers\Api\GuideMapApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('guides', [GuideApiController::class, 'index']);
    Route::get('guides-slugs', [GuideApiController::class, 'slugs']);
    Route::get('guides/{slug}', [GuideApiController::class, 'show']);

    // Bản đồ cẩm nang: quả địa cầu → nước → thành phố → điểm → bài viết
    Route::get('guide-map', [GuideMapApiController::class, 'index']);

    // Ghi nhận lượt đọc — trình duyệt gọi, nên có giới hạn tần suất riêng
    Route::post('guides/{slug}/view', [GuideApiController::class, 'ghiNhanLuotXem'])
        ->middleware('throttle:view');
});
