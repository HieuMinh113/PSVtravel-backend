<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Guide\Models\Guide;
use Tests\TestCase;

/**
 * Danh sách cẩm nang trên website: bài đăng tự có ngày đăng, bài cũ thiếu ngày
 * được bù, API trả đủ bài (trước đây tối đa 30) và xếp mới nhất lên đầu.
 */
class CamNangDanhSachTest extends TestCase
{
    use RefreshDatabase;

    private function bai(string $slug, array $them = []): Guide
    {
        return Guide::create(['title' => 'Bài '.$slug, 'slug' => $slug, 'status' => 'published', 'excerpt' => 'Tóm tắt', ...$them]);
    }

    public function test_chuyen_sang_da_dang_ma_de_trong_ngay_thi_tu_lay_gio_luc_dang(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 09:15'));
        $this->assertSame('2026-11-03 09:15', $this->bai('dang-ngay')->published_at->format('Y-m-d H:i'));

        $nhap = $this->bai('nhap', ['status' => 'draft']);
        $this->assertNull($nhap->published_at);
        $this->travelTo(Carbon::parse('2026-11-05 14:00'));
        $nhap->update(['status' => 'published']);
        $this->assertSame('2026-11-05 14:00', $nhap->fresh()->published_at->format('Y-m-d H:i'));

        // Ngày đã chọn (kể cả hẹn giờ đăng sau) giữ nguyên
        $hen = $this->bai('hen-gio', ['published_at' => '2026-12-01 08:00:00']);
        $this->assertSame('2026-12-01 08:00', $hen->fresh()->published_at->format('Y-m-d H:i'));
    }

    public function test_migration_bu_ngay_dang_cho_bai_cu_bang_ngay_tao(): void
    {
        $cu = $this->bai('bai-cu');
        $nhap = $this->bai('nhap', ['status' => 'draft']);
        $coNgay = $this->bai('co-ngay', ['published_at' => '2026-09-01 10:00:00']);
        // Bài cũ trước bản sửa: đã đăng mà ô ngày trống
        DB::table('guides')->whereIn('id', [$cu->id, $nhap->id])->update(['published_at' => null, 'created_at' => '2026-10-20 08:30:00']);

        (require base_path('Modules/Guide/database/migrations/2026_11_03_120000_bu_ngay_dang_bai_cam_nang.php'))->up();

        $this->assertSame('2026-10-20 08:30', $cu->fresh()->published_at->format('Y-m-d H:i'));
        $this->assertNull($nhap->fresh()->published_at);
        $this->assertSame('2026-09-01 10:00', $coNgay->fresh()->published_at->format('Y-m-d H:i'));
    }

    public function test_api_tra_du_bai_moi_nhat_len_dau_bai_thieu_ngay_xuong_cuoi(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00'));
        for ($i = 1; $i <= 35; $i++) {
            $this->bai("bai-{$i}", ['published_at' => now()->subDays(40 - $i)]);
        }
        $thieuNgay = $this->bai('thieu-ngay');
        DB::table('guides')->where('id', $thieuNgay->id)->update(['published_at' => null]);
        $this->bai('nhap', ['status' => 'draft']);

        $ds = $this->getJson('/api/v1/guides?per_page=200')->assertOk()->json('data');
        $this->assertCount(36, $ds); // trước đây tối đa 30
        $this->assertSame('bai-35', $ds[0]['slug']);
        $this->assertSame('bai-34', $ds[1]['slug']);
        $this->assertSame('thieu-ngay', $ds[35]['slug']);

        $this->assertCount(9, $this->getJson('/api/v1/guides')->json('data')); // mặc định không đổi
        $this->assertSame(36, $this->getJson('/api/v1/guides?per_page=5000')->json('meta.total'));
        $this->assertCount(36, $this->getJson('/api/v1/guides?per_page=5000')->json('data'));
    }
}
