<?php

use App\Http\Controllers\TaiFileVoucherController;
use App\Http\Controllers\XemThuVoucherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Bot gõ sitemap ở tên miền API (api.psvtravel.com/sitemap.xml) → sitemap thật ở
// website. Trước đây trả 404, hơn 300 lượt / tháng trong nhật ký.
Route::permanentRedirect('/sitemap.xml', rtrim((string) config('app.frontend_url'), '/').'/sitemap.xml');
Route::permanentRedirect('/sitemap_index.xml', rtrim((string) config('app.frontend_url'), '/').'/sitemap.xml');

// Voucher: tải file in / ZIP ảnh vừa xuất trong admin (link có chữ ký, chỉ người
// bấm xuất tải được) và ảnh xem thử vị trí ô mã trong form đợt voucher.
Route::get('quan-tri/voucher/tai/{ma}', TaiFileVoucherController::class)
    ->middleware('signed')
    ->where('ma', '[A-Za-z0-9-]{36}')
    ->name('voucher.tai');
Route::get('quan-tri/voucher/xem-thu/{dot}', XemThuVoucherController::class)
    ->whereNumber('dot')
    ->name('voucher.xem-thu');
