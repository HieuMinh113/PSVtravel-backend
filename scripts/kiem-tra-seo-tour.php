<?php

/**
 * Kiểm tra SEO các tour đang hiển thị (CHỈ ĐỌC, không sửa gì): tiêu đề trùng,
 * tiêu đề dài bị Google cắt, thiếu ảnh bìa, thiếu nơi khởi hành, giá liên hệ,
 * thiếu điểm nổi bật, thiếu chính sách huỷ.
 *
 * Chạy trên VPS (mỗi lệnh một dòng):
 *   cd /opt/psvtravel/psvtravel-backend
 *   docker compose -f docker-compose.prod.yml exec app php artisan tinker scripts/kiem-tra-seo-tour.php
 */

use Illuminate\Support\Facades\DB;

// Phải khớp app/lib/seo.js bên frontend: tiêu đề + " | PSV Travel" tối đa 70 ký tự
const DUOI = ' | PSV Travel';
const TOI_DA = 70;

$tours = DB::table('tours')->whereNull('deleted_at')->where('status', 'published')
    ->orderBy('type')->orderBy('name')
    ->get(['id', 'name', 'seo_title', 'type', 'cover_image', 'departure_from', 'adult_price', 'highlights', 'cancellation_policy']);

$tieuDe = fn ($t) => trim((string) ($t->seo_title ?: $t->name));
$ten = fn ($t) => '#'.$t->id.' '.$t->name;
$in = function (string $tieuDeMuc, $ds, string $goiY) use ($ten) {
    echo "\n== {$tieuDeMuc}: ".count($ds)."\n";
    if (count($ds)) {
        echo "   → {$goiY}\n";
    }
    foreach (collect($ds)->take(30) as $t) {
        echo '   - '.(is_string($t) ? $t : $ten($t))."\n";
    }
    if (count($ds) > 30) {
        echo '   … và '.(count($ds) - 30)." tour khác\n";
    }
};

$theoLoai = $tours->countBy('type');
echo 'Tour đang hiển thị: trong nước '.($theoLoai['domestic'] ?? 0).', nước ngoài '.($theoLoai['abroad'] ?? 0)."\n";
foreach (['domestic' => 'trong nước', 'abroad' => 'nước ngoài'] as $loai => $nhan) {
    if (($theoLoai[$loai] ?? 0) > 50) {
        echo "   !! Tour {$nhan} quá 50: trang danh sách tour {$nhan} hiện chỉ lấy 50 tour đầu — báo IT.\n";
    }
}

$trung = $tours->groupBy(fn ($t) => mb_strtolower($tieuDe($t)))->filter(fn ($g) => $g->count() > 1);
$in('Tiêu đề trùng nhau (Google khó phân biệt các trang)',
    $trung->map(fn ($g) => $g->count().' tour cùng tiêu đề "'.$tieuDe($g->first()).'": '.$g->pluck('id')->map(fn ($id) => '#'.$id)->implode(', '))->values(),
    'Viết "Tiêu đề SEO" riêng cho từng tour (VD thêm nơi khởi hành / số ngày).');

$in('Tiêu đề dài quá '.TOI_DA.' ký tự (Google hiện bị cắt "…")',
    $tours->filter(fn ($t) => mb_strlen($tieuDe($t)) > TOI_DA)->values(),
    'Viết "Tiêu đề SEO" ngắn hơn, 50–60 ký tự, từ khoá chính đặt đầu.');

$in('Tiêu đề không còn chỗ cho đuôi " | PSV Travel"',
    $tours->filter(fn ($t) => mb_strlen($tieuDe($t)) <= TOI_DA && mb_strlen($tieuDe($t).DUOI) > TOI_DA)->values(),
    'Không lỗi, chỉ mất tên thương hiệu ở cuối; muốn có thì rút gọn "Tiêu đề SEO".');

$in('Thiếu ảnh bìa (chia sẻ Facebook / Zalo hiện logo thay ảnh tour)',
    $tours->filter(fn ($t) => blank($t->cover_image))->values(),
    'Tải ảnh bìa cho tour.');

$in('Chưa có giá người lớn (Google không hiện giá)',
    $tours->filter(fn ($t) => (int) $t->adult_price <= 0)->values(),
    'Nhập giá người lớn nếu đã có.');

$in('Thiếu nơi khởi hành (mô tả trên Google thiếu "khởi hành từ …")',
    $tours->filter(fn ($t) => blank($t->departure_from))->values(),
    'Điền ô "Khởi hành từ".');

$in('Thiếu điểm nổi bật (mô tả trên Google ngắn, kém hấp dẫn)',
    $tours->filter(fn ($t) => empty(array_filter((array) json_decode((string) $t->highlights, true))))->values(),
    'Thêm 3–5 điểm nổi bật.');

$in('Thiếu chính sách huỷ (câu hỏi thường gặp dùng câu trả lời chung)',
    $tours->filter(fn ($t) => blank($t->cancellation_policy))->values(),
    'Điền "Chính sách huỷ" của tour.');

echo "\nXong. Lệnh này chỉ đọc, không sửa dữ liệu.\n";
