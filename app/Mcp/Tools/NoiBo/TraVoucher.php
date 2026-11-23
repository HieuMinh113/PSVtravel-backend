<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Voucher;
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

#[Name('lookup_voucher')]
#[Title('Tra mã voucher')]
#[Description('Tra một mã voucher (gõ có / không gạch nối, hoặc dán link quét): còn dùng được không, đợt phát hành, mệnh giá, hạn dùng, điều kiện, đã dùng lúc nào và ai quét. Chỉ xem — đánh dấu đã dùng thì làm ở trang Quét voucher.')]
#[IsReadOnly]
#[IsIdempotent]
class TraVoucher extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'ma' => $schema->string()->max(200)->required()->description('Mã voucher, ví dụ PSV-7KQ3-MZ8T'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'viewAny', 'xem voucher', Voucher::class)) {
            return Response::error($loi);
        }
        $d = $request->validate(['ma' => ['required', 'string', 'max:200']]);

        $v = Voucher::tim($d['ma']);
        if (! $v) {
            return Response::error('Không có mã voucher '.$d['ma'].'. Kiểm tra lại từng ký tự (mã không dùng số 0, 1 và chữ I, L, O).');
        }
        $v->load('nguoiQuet:id,name');
        $dot = $v->batch;

        return Response::structured([
            'ma' => $v->code,
            'trang_thai' => Voucher::TRANG_THAI[$v->trangThai()],
            'dot_phat_hanh' => $dot?->name,
            'menh_gia_vnd' => $dot?->value,
            'het_han' => QuanTri::ngay($dot?->expires_at),
            'ap_dung_cho' => $dot?->applies_to,
            'dieu_kien' => $dot?->conditions,
            'phat_cho' => $v->issued_to,
            'da_dung_luc' => QuanTri::gio($v->used_at),
            'nguoi_quet' => $v->nguoiQuet?->name,
            'ghi_chu_khi_dung' => $v->used_note,
            'ly_do_huy' => $v->cancel_reason,
            'link_admin' => VoucherResource::getUrl('index', panel: 'admin'),
        ]);
    }
}
