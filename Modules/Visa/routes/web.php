<?php

use Illuminate\Support\Facades\Route;
use Modules\Visa\Http\Controllers\TaiZipHoSoController;

// Trang khách do Next.js đảm nhiệm, admin do Filament. Route web duy nhất của
// module: tải file ZIP hồ sơ vừa xuất trong trang quản trị (link có chữ ký,
// hết hạn sau 10 phút, chỉ đúng người bấm xuất mới tải được).
Route::get('quan-tri/ho-so-visa/tai-zip/{ma}', TaiZipHoSoController::class)
    ->middleware('signed')
    ->where('ma', '[A-Za-z0-9-]{36}')
    ->name('visa.tai-zip');
