<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Voucher\InVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Ảnh xem thử mặt có mã của đợt voucher, với mã mẫu. Form đợt voucher gọi lại
 * mỗi khi đổi góc / cỡ / lề / mã vạch (truyền qua query) để thấy ngay vị trí ô
 * mã trên ảnh thật trước khi in.
 */
class XemThuVoucherController extends Controller
{
    public function __invoke(Request $request, int $dot): Response
    {
        $d = VoucherBatch::query()->findOrFail($dot);
        abort_unless($request->user()?->can('view', $d), 403);

        $q = $request->validate([
            'goc' => ['nullable', 'in:'.implode(',', array_keys(VoucherBatch::GOC))],
            'mat' => ['nullable', 'in:front,back'],
            'le' => ['nullable', 'numeric', 'between:3,20'],
            'co' => ['nullable', 'numeric', 'between:20,45'],
            'vach' => ['nullable', 'boolean'],
        ]);
        $d->forceFill(array_filter([
            'code_corner' => $q['goc'] ?? null,
            'code_side' => $q['mat'] ?? null,
            'code_margin_mm' => $q['le'] ?? null,
            'code_size_mm' => $q['co'] ?? null,
        ], fn ($v) => $v !== null));
        if (isset($q['vach'])) {
            $d->show_barcode = (bool) $q['vach'];
        }

        abort_unless($d->front_image && Storage::disk('public')->exists($d->front_image), 404, 'Chưa có ảnh mặt trước.');
        try {
            $anh = (new InVoucher)->jpgMot($d, new Voucher(['code' => $d->code_prefix.'-ABCD-2345', 'serial' => 1]), 1100);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return response($anh, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }
}
