<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    /**
     * GET /api/v1/vouchers/{ma} — khách kiểm tra voucher còn dùng được không
     * (gõ mã hoặc quét QR in trên voucher). Chỉ trả thông tin in sẵn trên
     * voucher + trạng thái; không lộ ai đã dùng, dùng cho việc gì, phát cho ai.
     * Giới hạn số lần / phút / IP để không dò được mã.
     */
    public function show(string $ma): JsonResponse
    {
        $v = Voucher::tim($ma);
        if (! $v) {
            return response()->json(['message' => 'Không tìm thấy voucher này. Kiểm tra lại mã in trên voucher.', 'trang_thai' => 'khong_co'], 404);
        }
        $d = $v->batch;
        $trangThai = $v->trangThai();

        return response()->json(['data' => [
            'ma' => $v->code,
            'trang_thai' => $trangThai,
            'nhan' => Voucher::TRANG_THAI[$trangThai],
            'gia_tri' => $d?->value,
            'het_han' => $d?->expires_at?->format('Y-m-d'),
            'ap_dung' => $d?->applies_to,
            'dieu_kien' => $d?->conditions,
            'ten_dot' => $d?->name,
            'da_dung_ngay' => $trangThai === 'da_dung' ? $v->used_at?->format('Y-m-d') : null,
            'anh' => $d?->front_image ? asset('storage/'.$d->front_image) : null,
        ]]);
    }
}
