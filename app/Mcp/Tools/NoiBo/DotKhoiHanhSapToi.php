<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\Tours\TourResource;
use App\Mcp\HoTro;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
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
use Modules\Tour\Models\TourDeparture;

#[Name('upcoming_departures')]
#[Title('Đợt khởi hành sắp tới')]
#[Description('Các đợt khởi hành trong khoảng ngày tới (mặc định 60 ngày) của mọi tour, kể cả tour nháp: số chỗ còn, số đơn / khách đã chốt và đang chờ xử lý, hạn nộp visa. Dùng để xem đợt nào sắp đi còn trống nhiều, đợt nào sắp hết hạn visa.')]
#[IsReadOnly]
#[IsIdempotent]
class DotKhoiHanhSapToi extends Tool
{
    private const TRANG_THAI = ['open' => 'Còn nhận', 'full' => 'Hết chỗ', 'closed' => 'Đã đóng'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_ngay' => $schema->string()->description('Từ ngày (YYYY-MM-DD). Bỏ trống = hôm nay'),
            'so_ngay' => $schema->integer()->min(1)->max(365)->default(60)->description('Xem bao nhiêu ngày tới'),
            'tu_khoa' => $schema->string()->max(100)->description('Lọc theo tên tour (không dấu cũng được)'),
            'chi_con_cho' => $schema->boolean()->description('true = chỉ đợt còn nhận khách'),
            'so_ket_qua' => $schema->integer()->min(1)->max(100)->default(40),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'viewAny', 'xem tour', Tour::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'tu_ngay' => ['nullable', 'date_format:Y-m-d'],
            'so_ngay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'chi_con_cho' => ['nullable', 'boolean'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], QuanTri::THONG_BAO);
        $tu = isset($d['tu_ngay']) ? Carbon::parse($d['tu_ngay'])->startOfDay() : today();
        $den = $tu->copy()->addDays($d['so_ngay'] ?? 60);

        $chot = fn ($q) => $q->whereIn('status', ['confirmed', 'completed']);
        $ds = TourDeparture::query()
            ->whereHas('tour')
            ->whereDate('start_date', '>=', $tu)->whereDate('start_date', '<=', $den)
            ->when(! empty($d['chi_con_cho']), fn ($q) => $q->where('status', 'open'))
            ->withCount(['bookings as don_chot' => $chot, 'bookings as don_cho' => fn ($q) => $q->where('status', 'pending')])
            ->withSum(['bookings as khach_lon_chot' => $chot], 'adults')
            ->withSum(['bookings as tre_em_chot' => $chot], 'children')
            ->with('tour')
            ->orderBy('start_date')->limit(1000)->get()
            ->filter(fn (TourDeparture $t) => HoTro::khop($d['tu_khoa'] ?? null, $t->tour->name))
            ->values();

        return Response::structured([
            'tu_ngay' => $tu->format('Y-m-d'),
            'den_ngay' => $den->format('Y-m-d'),
            'so_dot' => $ds->count(),
            'dot' => $ds->take($d['so_ket_qua'] ?? 40)->map(function (TourDeparture $t) {
                $han = $t->hanVisa($t->tour);

                return [
                    'tour' => $t->tour->name,
                    'tour_dang_ban' => $t->tour->status === 'published',
                    'ngay_di' => QuanTri::ngay($t->start_date),
                    'trang_thai' => self::TRANG_THAI[$t->status] ?? $t->status,
                    'tong_cho' => $t->seats_total,
                    'con_cho' => $t->seats_left,
                    'don_da_chot' => (int) $t->don_chot,
                    'khach_da_chot' => (int) $t->khach_lon_chot + (int) $t->tre_em_chot,
                    'don_cho_xu_ly' => (int) $t->don_cho,
                    'han_nop_visa' => QuanTri::ngay($han),
                    'con_ngay_nop_visa' => TourDeparture::conNgay($han),
                    'link_admin' => TourResource::getUrl('edit', ['record' => $t->tour_id], panel: 'admin'),
                ];
            })->all(),
        ]);
    }
}
