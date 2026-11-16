<?php

namespace App\Mcp\Tools;

use App\Mcp\HoTro;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Modules\Tour\Models\Tour;
use Modules\Tour\Transformers\TourDetailResource;

/**
 * Chi tiết một tour — cùng dữ liệu trang tour trên website (TourDetailResource),
 * rút gọn còn chữ: lịch trình, bao gồm / không bao gồm, các đợt còn đặt được.
 */
#[Name('get_tour')]
#[Title('Chi tiết tour')]
#[Description('Xem chi tiết một tour PSV Travel theo slug (lấy từ search_tours): lịch trình từng ngày, giá người lớn / trẻ em / em bé, giá bao gồm và không bao gồm, chính sách huỷ, lưu ý, các đợt khởi hành còn nhận khách (giá, số chỗ, hạn nộp hồ sơ visa) và link trang tour. Get full details of one PSV Travel tour.')]
#[IsReadOnly]
#[IsIdempotent]
class ChiTietTour extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->max(200)->required()
                ->description('Mã tour (slug) lấy từ kết quả search_tours, VD "da-nang-hoi-an-3n2d"'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate(['slug' => ['required', 'string', 'max:200']]);

        $tour = Tour::query()->published()
            ->with(['images', 'itineraries', 'departures', 'categories', 'reviews'])
            ->where('slug', $d['slug'])
            ->first();

        if (! $tour) {
            return Response::error("Không tìm thấy tour \"{$d['slug']}\". Dùng search_tours để lấy slug đúng.");
        }

        $r = (new TourDetailResource($tour))->resolve();
        $muc = fn ($ds) => collect($ds)->map(fn ($x) => HoTro::chu(is_string($x) ? $x : json_encode($x, JSON_UNESCAPED_UNICODE), 300))->filter()->values()->all();

        return Response::structured([
            'ten' => $r['name'],
            'url' => HoTro::urlTour($r['slug'], $r['type']),
            'loai' => $r['type'] === 'abroad' ? 'Nước ngoài' : 'Trong nước',
            'diem_den' => implode(' · ', array_filter([$r['region'], $r['country']])) ?: null,
            'thoi_gian' => $r['duration_days'] ? trim("{$r['duration_days']} ngày ".($r['duration_nights'] ? "{$r['duration_nights']} đêm" : '')) : null,
            'khoi_hanh_tu' => $r['departure_from'],
            'gia_vnd' => [
                'nguoi_lon' => $r['adult_price'],
                'tre_em' => $r['child_price'],
                'em_be' => $r['infant_price'],
                'gia_cu' => $r['old_price'],
            ],
            'visa' => $r['visa_kind_label'] ? [
                'dang' => $r['visa_kind_label'],
                'ghi_chu' => $r['visa_kind_note'],
                'nop_ho_so_truoc_ngay_khoi_hanh' => $r['visa_deadline_days'],
            ] : null,
            'diem_noi_bat' => $muc($r['highlights']),
            'gioi_thieu' => HoTro::chu($r['description'], 800),
            'lich_trinh' => collect($r['itineraries'])->map(fn ($n) => [
                'ngay' => $n['day_number'],
                'tieu_de' => $n['title'],
                'noi_dung' => HoTro::chu($n['description'], 700),
            ])->values()->all(),
            'bao_gom' => $muc($r['included']),
            'khong_bao_gom' => $muc($r['excluded']),
            'chinh_sach_huy' => HoTro::chu($r['cancellation_policy'], 800),
            'luu_y' => collect($r['notes'])->map(fn ($n) => $n['title'].': '.HoTro::chu($n['content'], 400))->values()->all(),
            'het_han_nhan_ho_so_visa' => $r['het_han_visa'],
            'dot_khoi_hanh' => collect($r['departures'])->map(fn ($x) => [
                'ngay' => $x['start_date'],
                'gia_nguoi_lon_vnd' => $x['price'],
                'gia_tre_em_vnd' => $x['price_child'],
                'con_cho' => $x['seats_left'],
                'han_nop_ho_so_visa' => $x['visa_deadline'],
            ])->values()->all(),
            'danh_gia' => $r['review_count'] ? ['diem' => $r['rating'], 'so_luot' => $r['review_count']] : null,
            'cach_dat' => 'Đặt trên trang tour (url), gọi hotline, hoặc dùng submit_inquiry để gửi yêu cầu tư vấn khi khách đồng ý để lại thông tin.',
        ]);
    }
}
