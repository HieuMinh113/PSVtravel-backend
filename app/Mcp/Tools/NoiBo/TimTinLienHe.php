<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_contact_messages')]
#[Title('Xem tin liên hệ của khách')]
#[Description('Tin khách gửi từ form liên hệ, Team Building, trợ lý AI… Mặc định chỉ tin CHƯA xong (mới + đang xử lý), mới nhất trước. Số điện thoại / email đã che bớt; mở link_admin để xem đầy đủ và gọi khách.')]
#[IsReadOnly]
#[IsIdempotent]
class TimTinLienHe extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'trang_thai' => $schema->string()->enum([...array_keys(ContactMessage::TRANG_THAI), 'tat_ca'])
                ->description('new = mới, handling = đang xử lý, done = đã xong, tat_ca = mọi tin. Bỏ trống = chưa xong'),
            'nguon' => $schema->string()->enum(array_keys(ContactMessage::NGUON))
                ->description(collect(ContactMessage::NGUON)->map(fn ($v, $k) => "$k = $v")->implode(', ')),
            'tu_khoa' => $schema->string()->max(100)->description('Tên khách, chủ đề hoặc số điện thoại'),
            'so_ket_qua' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'viewAny', 'xem tin liên hệ', ContactMessage::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'trang_thai' => ['nullable', 'in:'.implode(',', [...array_keys(ContactMessage::TRANG_THAI), 'tat_ca'])],
            'nguon' => ['nullable', 'in:'.implode(',', array_keys(ContactMessage::NGUON))],
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $trangThai = $d['trang_thai'] ?? null;

        $q = QuanTri::timTuKhoa(ContactMessage::query(), $d['tu_khoa'] ?? null, ['name', 'subject'], 'phone')
            ->when($trangThai === null, fn ($q) => $q->whereIn('status', ['new', 'handling']))
            ->when($trangThai && $trangThai !== 'tat_ca', fn ($q) => $q->where('status', $trangThai))
            ->when(isset($d['nguon']), fn ($q) => $q->where('source', $d['nguon']));

        $tong = (clone $q)->count();
        $ds = $q->with('handledBy:id,name')->latest('id')->limit($d['so_ket_qua'] ?? 20)->get();

        return Response::structured([
            'so_tin_tim_thay' => $tong,
            'tin' => $ds->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'ngay_gui' => QuanTri::gio($m->created_at),
                'ten' => $m->name,
                'sdt' => QuanTri::sdt($m->phone),
                'email' => QuanTri::email($m->email),
                'chu_de' => $m->subject,
                'noi_dung' => Str::limit((string) $m->message, 1500),
                'nguon' => ContactMessage::NGUON[$m->source] ?? $m->source,
                'trang_thai' => ContactMessage::TRANG_THAI[$m->status] ?? $m->status,
                'nguoi_xu_ly' => $m->handledBy?->name,
                'ghi_chu_noi_bo' => $m->admin_note,
                'link_admin' => ContactMessageResource::getUrl('view', ['record' => $m->id], panel: 'admin'),
            ])->all(),
        ]);
    }
}
