<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Máy chủ Next.js dựng trang gửi kèm khoá nội bộ (X-Psv-Noi-Bo) → API không tính
 * giới hạn 600 lượt/phút/IP. Khoá sai / thiếu / khoá cấu hình quá ngắn → tính
 * như mọi người. Cấu hình nginx + docker phải đi cùng (xem prod.conf.template).
 */
class GioiHanNoiBoTest extends TestCase
{
    use RefreshDatabase;

    private const KHOA = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718';

    public function test_may_chu_next_co_khoa_dung_thi_khong_tinh_gioi_han(): void
    {
        config(['services.noi_bo.khoa' => self::KHOA]);

        $this->getJson('/api/v1/settings', ['X-Psv-Noi-Bo' => self::KHOA])
            ->assertOk()
            ->assertHeaderMissing('X-RateLimit-Limit');

        foreach (['', 'sai-khoa', substr(self::KHOA, 0, -1)] as $khoa) {
            $this->getJson('/api/v1/settings', $khoa === '' ? [] : ['X-Psv-Noi-Bo' => $khoa])
                ->assertOk()
                ->assertHeader('X-RateLimit-Limit', '600');
        }
    }

    public function test_khoa_cau_hinh_rong_hoac_ngan_thi_khong_ai_duoc_mien(): void
    {
        foreach ([null, '', 'ngan'] as $cauHinh) {
            config(['services.noi_bo.khoa' => $cauHinh]);
            foreach ([[], ['X-Psv-Noi-Bo' => ''], ['X-Psv-Noi-Bo' => 'ngan']] as $header) {
                $this->getJson('/api/v1/settings', $header)->assertHeader('X-RateLimit-Limit', '600');
            }
        }
    }

    public function test_nginx_va_docker_cung_dung_khoa_noi_bo(): void
    {
        $nginx = file_get_contents(base_path('docker/nginx/prod.conf.template'));
        // Lượt không có header luôn bị tính, kể cả khi khoá rỗng
        $this->assertStringContainsString('map $http_x_psv_noi_bo $psv_co_noi_bo { default 1; "" 0; }', $nginx);
        $this->assertStringContainsString('"${PSV_NOI_BO}" 1;', $nginx);
        $this->assertStringContainsString('limit_req_zone $psv_khoa_gioi_han zone=psv_chung', $nginx);
        // Không còn giới hạn chung cho cả server (từng tính cả ảnh / JS / CSS)
        $this->assertStringNotContainsString('burst=60 nodelay', $nginx);
        $this->assertStringContainsString('rt=$request_time', $nginx);

        $compose = file_get_contents(base_path('docker-compose.prod.yml'));
        $this->assertSame(2, substr_count($compose, 'PSV_NOI_BO: ${PSV_NOI_BO:?'), 'nginx + frontend phải nhận khoá, thiếu thì dừng');
        $this->assertStringContainsString('psv_next_anh:/app/.next/cache/images', $compose);
        $this->assertStringContainsString('PSV_NOI_BO=', file_get_contents(base_path('scripts/trien-khai.sh')));
    }
}
