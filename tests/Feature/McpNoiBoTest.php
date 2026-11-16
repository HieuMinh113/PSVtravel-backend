<?php

namespace Tests\Feature;

use App\Filament\Pages\KetNoiAi;
use App\Mcp\MaKhoaMcp;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Modules\Guide\Models\Guide;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * MCP nội bộ /mcp/noi-bo: chỉ mã khoá MCP của Admin vào được; AI chỉ tạo / sửa
 * bài NHÁP và tải ảnh qua link dùng một lần — không đăng bài được.
 */
class McpNoiBoTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(string $vaiTro, bool $quyenCamNang = true): User
    {
        $vt = Role::findOrCreate($vaiTro, 'web');
        if ($quyenCamNang) {
            $vt->givePermissionTo(collect(['ViewAny', 'View', 'Create', 'Update'])
                ->map(fn ($q) => Permission::findOrCreate("$q:Guide", 'web')));
        }
        $u = User::factory()->create();
        $u->assignRole($vt);

        return $u;
    }

    private function goi(?string $ma, string $method, array $params = []): TestResponse
    {
        // Mỗi request thật là một lần chạy riêng; trong test phải xoá người dùng
        // mà guard đã nhớ từ request trước
        $this->app['auth']->forgetGuards();

        return $this->postJson('/mcp/noi-bo', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], array_filter([
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => $ma ? 'Bearer '.$ma : null,
        ]));
    }

    private function congCu(string $ma, string $ten, array $thamSo): TestResponse
    {
        return $this->goi($ma, 'tools/call', ['name' => $ten, 'arguments' => $thamSo]);
    }

    private function noiDung(int $doDai = 260): string
    {
        return "# Đà Lạt mùa nào đẹp\n\nĐà Lạt đẹp quanh năm.<script>alert(1)</script>\n\n## Ăn gì\n\n- Bánh căn\n- Lẩu gà lá é\n\n"
            .str_repeat('Thông tin thêm cho khách. ', (int) ceil($doDai / 25));
    }

    public function test_chi_ma_khoa_mcp_cua_admin_moi_vao_duoc(): void
    {
        $admin = $this->nguoi('admin');

        // Không mã → 401
        $this->goi(null, 'tools/list')->assertUnauthorized();

        // Mã đăng nhập website (quyền "*") của chính Admin → không vào được
        $maWeb = $admin->createToken('web', ['*'], now()->addDays(7))->plainTextToken;
        $this->goi($maWeb, 'tools/list')->assertForbidden();

        // Mã MCP nhưng không phải Admin (nhân viên, marketing) → không vào được
        $this->goi(MaKhoaMcp::tao($this->nguoi('staff'), 'May NV'), 'tools/list')->assertForbidden();
        $this->goi(MaKhoaMcp::tao($this->nguoi('marketing'), 'May MKT'), 'tools/list')->assertForbidden();

        // Mã MCP của Admin → vào được; không có công cụ nào đăng bài
        $ma = MaKhoaMcp::tao($admin, 'Laptop');
        $ten = collect($this->goi($ma, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['create_guide_draft', 'get_guide', 'get_tour', 'guide_options', 'request_image_upload',
            'search_guides_admin', 'search_tours', 'update_guide_draft'], $ten);

        // Admin bị hạ quyền → mã cũ hết tác dụng ngay
        $admin->syncRoles([Role::findOrCreate('staff', 'web')]);
        $this->goi($ma, 'tools/list')->assertForbidden();

        // Mã hết hạn → không vào được
        $admin2 = $this->nguoi('super_admin');
        $maCu = MaKhoaMcp::tao($admin2, 'May cu');
        $this->travel(MaKhoaMcp::SO_NGAY_HIEU_LUC + 1)->days();
        $this->goi($maCu, 'tools/list')->assertUnauthorized();
    }

    public function test_tao_va_sua_bai_luon_la_nhap(): void
    {
        $admin = $this->nguoi('admin');
        $ma = MaKhoaMcp::tao($admin, 'Laptop');
        Guide::create(['title' => 'Kinh nghiệm Đà Lạt', 'slug' => 'kinh-nghiem-da-lat', 'status' => 'published', 'content' => '<p>Cũ</p>']);

        $kq = $this->congCu($ma, 'create_guide_draft', [
            'tieu_de' => 'Kinh nghiệm Đà Lạt',
            'noi_dung_markdown' => $this->noiDung(),
            'tom_tat' => 'Đà Lạt mùa nào đẹp, ăn gì, đi đâu.',
            'chuyen_muc' => 'kinh-nghiem',
        ])->assertOk()->json('result');

        $this->assertNotTrue($kq['isError'] ?? false, json_encode($kq, JSON_UNESCAPED_UNICODE));
        $bai = Guide::findOrFail($kq['structuredContent']['id']);
        $this->assertSame('draft', $bai->status);
        $this->assertNull($bai->published_at);
        $this->assertSame($admin->id, $bai->author_id);
        $this->assertSame('kinh-nghiem-da-lat-2', $bai->slug);           // trùng bài cũ → thêm -2
        $this->assertStringContainsString('<h2>Đà Lạt mùa nào đẹp</h2>', $bai->content); // # hạ thành H2
        $this->assertStringContainsString('<li>Bánh căn</li>', $bai->content);
        $this->assertStringNotContainsString('<script', $bai->content);      // HTML viết tay bị bỏ
        $this->assertStringContainsString('/admin/guides/'.$bai->id.'/edit', $kq['structuredContent']['link_sua_trong_admin']);

        // Sửa bài nháp được
        $this->congCu($ma, 'update_guide_draft', ['id' => $bai->id, 'tieu_de_seo' => 'Kinh nghiệm du lịch Đà Lạt tự túc 2026'])->assertOk();
        $this->assertSame('Kinh nghiệm du lịch Đà Lạt tự túc 2026', $bai->fresh()->seo_title);
        $this->assertSame('draft', $bai->fresh()->status);

        // Bài đã đăng thì không sửa qua MCP
        $daDang = Guide::where('slug', 'kinh-nghiem-da-lat')->first();
        $loi = $this->congCu($ma, 'update_guide_draft', ['id' => $daDang->id, 'tieu_de' => 'Đổi tên bài đã đăng'])->json('result');
        $this->assertTrue($loi['isError']);
        $this->assertSame('Kinh nghiệm Đà Lạt', $daDang->fresh()->title);

        // Nội dung quá ngắn → báo lỗi, không tạo bài
        $ngan = $this->congCu($ma, 'create_guide_draft', ['tieu_de' => 'Bài quá ngắn để thử', 'noi_dung_markdown' => 'Ngắn quá'])->json('result');
        $this->assertTrue($ngan['isError']);
        $this->assertSame(2, Guide::count());
    }

    public function test_admin_chua_duoc_tick_quyen_cam_nang_khong_tao_duoc_bai(): void
    {
        $ma = MaKhoaMcp::tao($this->nguoi('admin', quyenCamNang: false), 'Laptop');

        $kq = $this->congCu($ma, 'create_guide_draft', ['tieu_de' => 'Bài thử khi chưa có quyền', 'noi_dung_markdown' => $this->noiDung()])->json('result');
        $this->assertTrue($kq['isError']);
        $this->assertStringContainsString('chưa có quyền', $kq['content'][0]['text']);
        $this->assertTrue($this->congCu($ma, 'search_guides_admin', [])->json('result.isError'));
        $this->assertSame(0, Guide::count());
    }

    public function test_tai_anh_bia_qua_link_dung_mot_lan(): void
    {
        Storage::fake('public');
        $ma = MaKhoaMcp::tao($this->nguoi('admin'), 'Laptop');
        $bai = Guide::create(['title' => 'Bài nháp có ảnh', 'slug' => 'bai-nhap-co-anh', 'status' => 'draft', 'content' => '<p>x</p>']);

        $link = $this->congCu($ma, 'request_image_upload', ['bai_id' => $bai->id, 'loai' => 'bia', 'mo_ta_anh' => 'Đồi chè Cầu Đất'])
            ->assertOk()->json('result.structuredContent.link_tai_len');
        $duongDan = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

        // Không có chữ ký → bị chặn
        $this->post(parse_url($link, PHP_URL_PATH).'?ma=bua', ['anh' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

        $this->post($duongDan, ['anh' => UploadedFile::fake()->image('doi-che.jpg', 1200, 800)])
            ->assertCreated()
            ->assertJsonPath('loai', 'bia');
        $bai->refresh();
        $this->assertStringStartsWith('guides/', $bai->cover_image);
        Storage::disk('public')->assertExists($bai->cover_image);

        // Dùng lại link → 410
        $this->post($duongDan, ['anh' => UploadedFile::fake()->image('lan-2.jpg')])->assertStatus(410);

        // Bài đã đăng → không cấp link
        $bai->update(['status' => 'published']);
        $this->assertTrue($this->congCu($ma, 'request_image_upload', ['bai_id' => $bai->id, 'loai' => 'bia'])->json('result.isError'));
    }

    public function test_trang_ket_noi_ai_chi_admin_tao_va_thu_hoi_ma(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($this->nguoi('marketing'));
        $this->assertFalse(KetNoiAi::canAccess());

        $admin = $this->nguoi('admin');
        $this->actingAs($admin);
        $this->assertTrue(KetNoiAi::canAccess());

        $trang = Livewire::test(KetNoiAi::class)
            ->callAction('taoMa', ['ten' => 'Laptop văn phòng'])
            ->assertHasNoActionErrors();
        $maMoi = $trang->get('maMoi');
        $this->assertNotEmpty($maMoi);
        $trang->assertSee('Laptop văn phòng');
        // Hướng dẫn cài cho từng ứng dụng, mã đã điền sẵn (không còn chỗ trống <MÃ KHOÁ>)
        $trang->assertSee(['claude mcp add --scope user', 'codex mcp add psv-noi-bo', 'gemini mcp add -s user', 'MCP: Open User Configuration'])
            ->assertSee('Bearer '.$maMoi)
            ->assertDontSee('Bearer &lt;MÃ KHOÁ&gt;', false);

        // Mã vừa tạo dùng được ngay (bỏ phiên đăng nhập admin của test để chỉ còn mã khoá)
        $this->app['auth']->forgetGuards();
        $this->goi($maMoi, 'tools/list')->assertOk();

        // Thu hồi → mất kết nối
        $token = MaKhoaMcp::cuaNguoi($admin)->sole();
        $trang->callTableAction('thuHoi', $token);
        $this->assertSame(0, MaKhoaMcp::cuaNguoi($admin)->count());
        $this->app['auth']->forgetGuards();
        $this->goi($maMoi, 'tools/list')->assertUnauthorized();
    }
}
