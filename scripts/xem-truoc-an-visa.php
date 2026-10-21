<?php

/**
 * Xem trước (CHỈ ĐỌC, không sửa gì): ngày khởi hành nào sẽ tự ẩn trên website vì
 * đã quá hạn nộp visa — theo số ngày riêng của tour ("trước N ngày") và hạn riêng
 * của từng ngày. Chưa tính danh sách "Hạn nộp visa theo nước" (lúc mới triển khai
 * danh sách còn trống).
 *
 * Chạy được cả trước khi triển khai bản có tính năng ẩn (lấy file từ GitHub):
 *   git fetch origin main
 *   git show origin/main:scripts/xem-truoc-an-visa.php > storage/xem-an.php
 *   docker compose -f docker-compose.prod.yml exec app php artisan tinker storage/xem-an.php
 *   rm storage/xem-an.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasColumn('tours', 'visa_deadline_days') || ! Schema::hasColumn('tour_departures', 'visa_deadline')) {
    echo "Chưa có cột hạn visa: không ngày nào bị ẩn.\n";

    return;
}

$homNay = today()->toDateString();
$dong = DB::table('tour_departures as d')->join('tours as t', 't.id', '=', 'd.tour_id')
    ->whereNull('t.deleted_at')->where('t.status', 'published')->where('d.status', 'open')
    ->whereDate('d.start_date', '>=', $homNay)
    // "trước N ngày": từ ngày bay − N đã ẩn; hạn riêng: ẩn từ ngày sau hạn
    ->selectRaw('t.name, t.type, d.start_date, d.visa_deadline, t.visa_deadline_days,
        (d.visa_deadline < ? or (coalesce(t.visa_deadline_days, 0) > 0 and d.start_date - t.visa_deadline_days <= ?)) as an', [$homNay, $homNay])
    ->orderBy('t.name')->orderBy('d.start_date')->get();

$an = $dong->filter(fn ($r) => (bool) $r->an);
foreach ($an as $r) {
    echo '[AN] '.$r->name.' ('.($r->type === 'abroad' ? 'nước ngoài' : 'TRONG NƯỚC').') — bay '.$r->start_date
        .($r->visa_deadline ? ' | hạn riêng '.$r->visa_deadline : '')
        .($r->visa_deadline_days ? ' | trước '.$r->visa_deadline_days.' ngày' : '')."\n";
}
$tourAn = $dong->groupBy('name')->filter(fn ($g) => $g->every(fn ($r) => (bool) $r->an))->keys();
foreach ($tourAn as $t) {
    echo '[TOUR ẨN KHỎI DANH SÁCH] '.$t."\n";
}
echo "\nTổng: {$an->count()} ngày khởi hành sẽ ẩn, {$tourAn->count()} tour ẩn khỏi danh sách (trên {$dong->count()} ngày khởi hành sắp tới đang mở).\n";
