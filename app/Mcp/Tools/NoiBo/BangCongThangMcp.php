<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Pages\BangCongThang;
use App\Models\Attendance;
use App\Models\User;
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

#[Name('attendance_month')]
#[Title('Bảng công tháng')]
#[Description('Tổng hợp chấm công một tháng của từng nhân viên, giống trang admin "Bảng công tháng": ngày công, đi trễ, về sớm, chấm ngoài công ty, nghi vấn khuôn mặt, thiếu chấm vào / ra, ngày chờ duyệt. Chỉ con số — không có ảnh, vị trí hay giờ từng lần chấm.')]
#[IsReadOnly]
#[IsIdempotent]
class BangCongThangMcp extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'thang' => $schema->string()->description('Tháng dạng YYYY-MM, ví dụ 2026-11. Bỏ trống = tháng này.'),
            'ten_nhan_vien' => $schema->string()->max(100)->description('Chỉ xem một người (một phần tên cũng được)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'ViewAll:Attendance', 'xem chấm công toàn công ty')) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'thang' => QuanTri::LUAT_THANG,
            'ten_nhan_vien' => ['nullable', 'string', 'max:100'],
        ], QuanTri::THONG_BAO);
        $dau = Carbon::createFromFormat('!Y-m', $d['thang'] ?? now()->format('Y-m'));

        $cham = Attendance::whereBetween('work_date', [$dau->toDateString(), $dau->copy()->endOfMonth()->toDateString()])
            ->get()->groupBy('user_id');
        $nguoi = QuanTri::timTuKhoa(BangCongThang::nhanVien(), $d['ten_nhan_vien'] ?? null, ['name'])
            ->orderBy('name')->get(['id', 'name']);

        return Response::structured([
            'thang' => $dau->format('m/Y'),
            'y_nghia_cot' => BangCongThang::COT,
            'nhan_vien' => $nguoi->map(fn (User $u) => [
                'ten' => $u->name,
                ...BangCongThang::cong($cham->get($u->id, collect())),
            ])->all(),
            'link_admin' => BangCongThang::getUrl(['filters' => ['thang' => ['value' => $dau->format('Y-m')]]], panel: 'admin'),
        ]);
    }
}
