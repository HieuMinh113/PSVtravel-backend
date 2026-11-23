<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Pages\ThongKeDoanhSo;
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
use Modules\Booking\Models\Booking;

#[Name('sales_report')]
#[Title('Báo cáo doanh số tour theo tháng')]
#[Description('Doanh số đơn tour của một tháng, giống trang admin "Thống kê doanh số": tính theo tháng CHỐT đơn (ngày xác nhận), đơn huỷ không tính vào doanh số. Có tổng cộng, từng người phụ trách (nếu tài khoản xem được của mọi người), đơn mới tạo trong tháng theo trạng thái / nguồn và số đơn tới hạn nhắc khách đóng tiền. Đơn Maldives xem bằng search_maldives_bookings.')]
#[IsReadOnly]
#[IsIdempotent]
class BaoCaoDoanhSo extends Tool
{
    private const TRANG_THAI = ['pending' => 'Chờ xử lý', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'thang' => $schema->string()->description('Tháng dạng YYYY-MM, ví dụ 2026-11. Bỏ trống = tháng này.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $u = $request->user();
        if ($loi = QuanTri::thieuQuyen($u, 'viewAny', 'xem đơn đặt tour', Booking::class)) {
            return Response::error($loi);
        }
        $d = $request->validate(['thang' => QuanTri::LUAT_THANG], QuanTri::THONG_BAO);
        $thang = $d['thang'] ?? now()->format('Y-m');
        $dau = Carbon::createFromFormat('!Y-m', $thang);

        // Không có quyền xem của mọi người → chỉ đơn mình phụ trách (như trang admin)
        $tatCa = (bool) $u->can('giao', Booking::class);
        $cuaToi = fn ($q) => $tatCa ? $q : $q->where('assigned_to', $u->id);

        $chot = $cuaToi(Booking::query()->chotTrongThang($thang))->with('nguoiPhuTrach:id,name')->get();
        $moi = $cuaToi(Booking::query()->where('created_at', '>=', $dau)->where('created_at', '<', $dau->copy()->addMonth()))
            ->get(['id', 'status', 'source']);

        $bo = fn (array $dong) => collect($dong)->except(['tong'])->all();

        return Response::structured([
            'thang' => $dau->format('m/Y'),
            'pham_vi' => $tatCa ? 'Mọi nhân viên' : 'Chỉ đơn bạn phụ trách',
            'y_nghia_cot' => collect(ThongKeDoanhSo::COT_TONG_HOP)->except('ten')->all(),
            'tong_cong' => $bo(ThongKeDoanhSo::cong($chot, 'Tổng cộng', true)),
            'theo_nguoi_phu_trach' => $tatCa
                ? $chot->groupBy(fn (Booking $b) => $b->assigned_to ?? 0)
                    ->map(fn ($g) => $bo(ThongKeDoanhSo::cong($g, $g->first()->nguoiPhuTrach?->name ?? '(Chưa ai phụ trách)')))
                    ->sortByDesc('doanh_so')->values()->all()
                : [],
            'don_moi_tao_trong_thang' => [
                'tong' => $moi->count(),
                'theo_trang_thai' => $moi->countBy(fn (Booking $b) => self::TRANG_THAI[$b->status] ?? $b->status)->all(),
                'theo_nguon' => $moi->countBy(fn (Booking $b) => Booking::NGUON[$b->source] ?? 'Khác')->all(),
            ],
            'don_toi_han_nhac_dong_tien' => $cuaToi(Booking::query()->toiHanNhac())->count(),
            'link_admin' => ThongKeDoanhSo::getUrl(['filters' => ['thang' => ['value' => $thang]]], panel: 'admin'),
        ]);
    }
}
