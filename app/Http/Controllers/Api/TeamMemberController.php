<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;

class TeamMemberController extends Controller
{
    // GET /api/v1/team-members — đội ngũ đang hiển thị (để trang Về chúng tôi dựng)
    public function index()
    {
        // Ai có trang tác giả (có bài cẩm nang đã đăng) thì trang Về chúng tôi gắn link
        $coTrang = TeamMember::query()->coTrangTacGia()->pluck('id')->all();

        $members = TeamMember::query()
            ->dangHienThi()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TeamMember $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'position' => $m->position,
                'email' => $m->email,
                'photo' => $this->anh($m->photo),
                'bio' => $m->bio,
                'author_slug' => in_array($m->id, $coTrang, true) ? $m->slug : null,
            ]);

        return response()->json(['data' => $members]);
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
