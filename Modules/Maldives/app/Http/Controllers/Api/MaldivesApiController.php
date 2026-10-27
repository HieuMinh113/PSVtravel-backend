<?php

namespace Modules\Maldives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NguonKhach;
use Illuminate\Http\JsonResponse;
use Modules\Maldives\Http\Requests\DatComboRequest;
use Modules\Maldives\Models\MaldivesBooking;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Services\BaoDonMaldivesMoi;
use Modules\Maldives\Services\BaoGiaMaldives;
use Modules\Maldives\Transformers\ComboDetailResource;
use Modules\Maldives\Transformers\ComboListResource;

class MaldivesApiController extends Controller
{
    // GET /api/v1/maldives — danh sách combo đang bán
    public function index(): JsonResponse
    {
        $ds = MaldivesCombo::query()->dangBan()->with(['resort', 'dates'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => ComboListResource::collection($ds)->resolve()]);
    }

    // GET /api/v1/maldives-sitemap — [{slug, updated_at}] cho sitemap / dựng sẵn trang
    public function sitemap(): JsonResponse
    {
        return response()->json(['data' => MaldivesCombo::query()->dangBan()->get(['slug', 'updated_at'])
            ->map(fn ($c) => ['slug' => $c->slug, 'updated_at' => $c->updated_at?->toIso8601String()])]);
    }

    // GET /api/v1/maldives/{slug}
    public function show(string $slug): ComboDetailResource
    {
        $c = MaldivesCombo::query()->dangBan()->where('slug', $slug)
            ->with(['resort.roomTypes', 'dates', 'days'])->firstOrFail();

        return new ComboDetailResource($c);
    }

    // POST /api/v1/maldives/bookings — khách gửi yêu cầu đặt combo
    public function dat(DatComboRequest $request): JsonResponse
    {
        $d = $request->validated();
        $combo = MaldivesCombo::query()->dangBan()->with('resort.roomTypes')->find($d['combo_id']);
        if (! $combo) {
            return response()->json(['message' => 'Combo không còn mở bán.'], 422);
        }

        // Ngày, hạng phòng, nơi đi phải thuộc đúng combo này — không tin số khách gửi lên
        $ngay = null;
        if (! empty($d['combo_date_id'])) {
            $ngay = $combo->dates()->whereKey($d['combo_date_id'])->sapToi()->first();
            if (! $ngay || $ngay->status !== 'open') {
                return response()->json(['message' => 'Ngày khởi hành này đã đóng hoặc hết phòng. Vui lòng chọn ngày khác.'], 422);
            }
        }
        $phong = null;
        if (! empty($d['room_type_id'])) {
            $phong = $combo->resort->roomTypes->firstWhere('id', (int) $d['room_type_id']);
            if (! $phong || ($ngay && $ngay->gia($phong->id) === null)) {
                return response()->json(['message' => 'Hạng phòng này không có ở ngày đã chọn.'], 422);
            }
        }
        $noiDi = filled($d['departure_city'] ?? null) ? $combo->noiKhoiHanh($d['departure_city']) : null;
        if (filled($d['departure_city'] ?? null) && ! $noiDi) {
            return response()->json(['message' => 'Nơi khởi hành không hợp lệ.'], 422);
        }

        $gia = BaoGiaMaldives::tinh($combo, $ngay, $phong?->id, $noiDi['city'] ?? null,
            (int) $d['adults'], (int) ($d['children'] ?? 0), (int) ($d['infants'] ?? 0));

        $nguon = NguonKhach::tuDuLieu($request->input('nguon'));
        $don = (new MaldivesBooking)->forceFill([
            'source' => 'web', 'kenh' => $nguon['kenh'], 'utm' => $nguon['utm'], 'user_id' => auth('sanctum')->id(),
        ])->fill([
            'combo_id' => $combo->id,
            'combo_date_id' => $ngay?->id,
            'room_type_id' => $phong?->id,
            'combo_name' => $combo->name,
            'start_date' => $ngay?->start_date,
            'room_type_name' => $phong?->name,
            'departure_city' => $noiDi['city'] ?? null,
            'surcharge_usd' => $gia['phu_thu_usd'],
            'adults' => $d['adults'],
            'children' => $d['children'] ?? 0,
            'infants' => $d['infants'] ?? 0,
            'rooms' => $d['rooms'] ?? null,
            'unit_price_usd' => $gia['don_gia_usd'],
            'child_percent' => $combo->child_percent,
            'total_usd' => $gia['tong_usd'],
            'exchange_rate' => $gia['ty_gia'],
            'total_vnd' => $gia['tong_vnd'],
            'customer_name' => $d['customer_name'],
            'customer_phone' => $d['customer_phone'],
            'customer_email' => $d['customer_email'] ?? null,
            'note' => $d['note'] ?? null,
            'status' => 'pending',
        ]);
        $don->save();

        BaoDonMaldivesMoi::gui($don);

        return response()->json([
            'message' => 'Đã nhận yêu cầu đặt combo! Nhân viên sẽ gọi lại để xác nhận còn phòng và báo giá chính xác.',
            'data' => ['code' => $don->code, 'total_usd' => $don->total_usd, 'total_vnd' => $don->total_vnd, 'chua_gom' => $gia['chua_gom']],
        ], 201);
    }
}
