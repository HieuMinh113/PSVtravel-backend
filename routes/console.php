<?php

use App\Services\Voucher\XuatVoucher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('activitylog:clean')->daily();
Schedule::command('cham-cong:don-anh')->dailyAt('02:30');
// File in voucher đã xuất mà không ai tải (mã voucher dùng được như tiền)
Schedule::call(fn () => XuatVoucher::donDep())->name('voucher:don-file-xuat')->daily();
