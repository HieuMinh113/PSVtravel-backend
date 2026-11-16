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
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesComboDate;
use Modules\Maldives\Transformers\AnhMaldives;
use Modules\Page\Models\Setting;

/**
 * Combo Maldives đang bán (/maldives). Không có slug: danh sách; có slug: chi
 * tiết một combo (hạng phòng, giá từng ngày theo hạng phòng, bao gồm…).
 */
#[Name('maldives_combos')]
#[Title('Combo du lịch Maldives')]
#[Description('Xem các combo nghỉ dưỡng Maldives PSV Travel đang bán (vé máy bay + khách sạn + resort): resort, số sao, hãng bay, cách di chuyển ra đảo, giá từ (USD / người lớn), ngày khởi hành, nơi khởi hành. Truyền slug để xem chi tiết một combo: hạng phòng, giá theo từng ngày và hạng phòng, giá bao gồm / không bao gồm, lịch trình. Maldives holiday packages.')]
#[IsReadOnly]
#[IsIdempotent]
class ComboMaldives extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)
                ->description('Tên resort hoặc hãng bay, VD "Sun Siyam", "Singapore Airlines". Bỏ trống để xem tất cả.'),
            'slug' => $schema->string()->max(200)
                ->description('Mã combo (slug) lấy từ danh sách, để xem chi tiết một combo'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:200'],
        ]);
        $tyGia = (int) Setting::lay('maldives_ty_gia', '0');

        if (! empty($d['slug'])) {
            $c = MaldivesCombo::query()->dangBan()->where('slug', $d['slug'])
                ->with(['resort.roomTypes', 'dates', 'days'])->first();

            return $c
                ? Response::structured($this->chiTiet($c, $tyGia))
                : Response::error("Không tìm thấy combo \"{$d['slug']}\". Gọi maldives_combos không có slug để xem danh sách.");
        }

        $ds = MaldivesCombo::query()->dangBan()->with(['resort', 'dates'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (MaldivesCombo $c) => HoTro::khop($d['tu_khoa'] ?? null, $c->name, (string) $c->resort?->name, (string) $c->airline))
            ->map(fn (MaldivesCombo $c) => $this->tomTat($c))
            ->values();

        return Response::structured([
            'so_combo' => $ds->count(),
            'combo' => $ds->all(),
            'ty_gia_tham_khao_vnd_moi_usd' => $tyGia ?: null,
            'ghi_chu' => 'Giá theo USD / người lớn, chưa gồm phụ thu nơi khởi hành và các mục "không bao gồm". Nhân viên xác nhận còn phòng và báo giá chính xác khi khách đặt.',
            'xem_tat_ca' => HoTro::web('maldives'),
        ]);
    }

    private function tomTat(MaldivesCombo $c): array
    {
        $r = $c->resort;

        return [
            'slug' => $c->slug,
            'ten' => $c->name,
            'tom_tat' => HoTro::chu($c->summary, 300),
            'resort' => $r ? trim($r->name.($r->sao() ? " {$r->sao()} sao" : '').($r->atoll ? " ({$r->atoll})" : '')) : null,
            'thoi_gian' => $c->duration_days ? "{$c->duration_days} ngày {$c->duration_nights} đêm" : null,
            'luu_tru' => $c->stay_summary,
            'hang_bay' => $c->airline,
            'di_chuyen_ra_dao' => $c->transfer,
            'gia_tu_usd' => $c->giaTu(),
            'noi_khoi_hanh' => array_values(array_filter(array_map(fn ($n) => $n['city'] ?? null, $c->departure_cities ?? []))),
            'ngay_khoi_hanh' => $this->ngaySapToi($c)->take(8)->map(fn (MaldivesComboDate $x) => $x->start_date->toDateString())->values()->all(),
            'url' => HoTro::web('maldives/'.$c->slug),
        ];
    }

    private function chiTiet(MaldivesCombo $c, int $tyGia): array
    {
        $phong = $c->resort?->roomTypes?->keyBy('id') ?? collect();

        return [
            ...$this->tomTat($c),
            'ty_gia_tham_khao_vnd_moi_usd' => $tyGia ?: null,
            'diem_noi_bat' => AnhMaldives::dong($c->highlights),
            'hang_phong' => $phong->map(fn ($p) => [
                'ten' => $p->name,
                'so_nguoi' => $p->capacity,
                'chi_nguoi_lon' => (bool) $p->adults_only,
            ])->values()->all(),
            'gia_theo_ngay_usd' => $this->ngaySapToi($c)->map(fn (MaldivesComboDate $x) => [
                'ngay' => $x->start_date->toDateString(),
                'ghi_chu' => $x->label,
                'gia_theo_hang_phong' => collect($x->prices ?? [])
                    ->filter(fn ($g) => is_numeric($g) && (int) $g > 0)
                    ->mapWithKeys(fn ($g, $id) => [($phong[$id]->name ?? "Hạng phòng {$id}") => (int) $g])
                    ->all(),
            ])->values()->all(),
            'phu_thu_noi_khoi_hanh_usd' => collect($c->departure_cities ?? [])
                ->filter(fn ($n) => filled($n['city'] ?? null))
                ->mapWithKeys(fn ($n) => [trim($n['city']) => is_numeric($n['surcharge_usd'] ?? null) ? (int) $n['surcharge_usd'] : 0])
                ->all(),
            'tre_em' => array_filter([
                'phan_tram_gia' => $c->child_percent,
                'do_tuoi' => $c->child_age,
                'em_be' => $c->infant_note,
            ]) ?: null,
            'bao_gom' => AnhMaldives::dong($c->included),
            'khong_bao_gom' => AnhMaldives::dong($c->excluded),
            'lich_trinh' => $c->days->map(fn ($n) => [
                'ngay' => $n->day_number,
                'tieu_de' => $n->title,
                'bua_an' => $n->meals,
                'noi_dung' => HoTro::chu($n->description, 500),
            ])->values()->all(),
            'cach_dat' => 'Đặt trên trang combo (url), gọi hotline, hoặc dùng submit_inquiry khi khách đồng ý để lại thông tin.',
        ];
    }

    private function ngaySapToi(MaldivesCombo $c)
    {
        return $c->dates
            ->filter(fn (MaldivesComboDate $x) => $x->status === 'open' && ! $x->start_date->lt(today()))
            ->sortBy('start_date')->values();
    }
}
