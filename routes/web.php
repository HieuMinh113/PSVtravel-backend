<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Bot gõ sitemap ở tên miền API (api.psvtravel.com/sitemap.xml) → sitemap thật ở
// website. Trước đây trả 404, hơn 300 lượt / tháng trong nhật ký.
Route::permanentRedirect('/sitemap.xml', rtrim((string) config('app.frontend_url'), '/').'/sitemap.xml');
Route::permanentRedirect('/sitemap_index.xml', rtrim((string) config('app.frontend_url'), '/').'/sitemap.xml');
