<?php

/**
 * Kiểm tra SEO các tour đang hiển thị (CHỈ ĐỌC, không sửa gì). Cùng bộ gợi ý
 * với khung "Gợi ý SEO" trong form tour và cột "SEO" ở danh sách tour
 * (App\Services\KiemTraSeoTour) — lệnh này để xem tất cả một lượt.
 *
 * Chạy trên VPS (mỗi lệnh một dòng):
 *   cd /opt/psvtravel/psvtravel-backend
 *   docker compose -f docker-compose.prod.yml exec app php artisan tinker scripts/kiem-tra-seo-tour.php
 */

use App\Services\KiemTraSeoTour;
use Modules\Tour\Models\Tour;

$tours = Tour::query()->published()->with(['itineraries:id,tour_id', 'categories:id,name'])
    ->orderBy('type')->orderBy('name')->get();

$theoLoai = $tours->countBy('type');
echo 'Tour đang hiển thị: trong nước '.($theoLoai['domestic'] ?? 0).', nước ngoài '.($theoLoai['abroad'] ?? 0)."\n";
foreach (['domestic' => 'trong nước', 'abroad' => 'nước ngoài'] as $loai => $nhan) {
    if (($theoLoai[$loai] ?? 0) > 50) {
        echo "   !! Tour {$nhan} quá 50: trang danh sách tour {$nhan} hiện chỉ lấy 50 tour đầu — báo IT.\n";
    }
}

$dat = 0;
foreach ($tours as $t) {
    $goiY = KiemTraSeoTour::goiY($t);
    if (! $goiY) {
        $dat++;

        continue;
    }
    echo "\n#{$t->id} {$t->name}\n";
    foreach ($goiY as $g) {
        echo '   '.($g['muc'] === 'loi' ? '[Nên sửa] ' : '[Nên có]  ').$g['noi_dung']."\n";
    }
}

echo "\nĐạt hết: {$dat}/".$tours->count()." tour. Lệnh này chỉ đọc, không sửa dữ liệu.\n";
