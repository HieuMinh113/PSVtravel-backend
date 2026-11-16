<?php

namespace App\Mcp\Tools;

use App\Mcp\HoTro;
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
use Modules\Page\Models\Setting;

/**
 * Thông tin liên hệ & pháp lý đang công khai trên website (chân trang, trang
 * liên hệ). Đọc từ Cài đặt trong admin; ô nào trống thì dùng đúng thông tin
 * website đang ghi (giấy phép lữ hành, địa chỉ văn phòng, giờ làm việc).
 */
#[Name('company_info')]
#[Title('Thông tin công ty PSV Travel')]
#[Description('Thông tin liên hệ và pháp lý của PSV Travel: hotline, email, địa chỉ văn phòng, giờ làm việc, giấy phép lữ hành quốc tế, mạng xã hội và các trang chính sách (thanh toán, huỷ & hoàn tiền). Dùng khi khách hỏi cách liên hệ, độ uy tín hoặc chính sách. PSV Travel contact and licensing details.')]
#[IsReadOnly]
#[IsIdempotent]
class ThongTinCongTy extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $s = fn (string $key, ?string $macDinh = null) => filled($v = Setting::lay($key)) ? trim($v) : $macDinh;

        return Response::structured(array_filter([
            'ten' => $s('company_name', 'PSV Travel'),
            'ten_phap_ly' => $s('legal_name', 'CÔNG TY CỔ PHẦN DU LỊCH P.S.V TRAVEL'),
            'website' => HoTro::web(),
            'hotline' => $s('hotline', '0907 870 707'),
            'hotline_ghi_chu' => 'Hotline hỗ trợ 24/7',
            'email' => $s('email'),
            'dia_chi' => $s('address', '529 Huỳnh Tấn Phát, Quận 7, Thành phố Hồ Chí Minh'),
            'chi_nhanh' => $s('branch_addresses'),
            'gio_lam_viec' => $s('working_hours', 'Thứ 2 – Chủ nhật: 8:00 – 17:00'),
            'giay_phep_lu_hanh_quoc_te' => $s('license_number', '79-769/2020/CDLQGVN-GP LHQT'),
            'co_quan_cap_phep' => $s('license_issuer', 'Cục Du lịch Quốc gia Việt Nam'),
            'ma_so_thue' => $s('tax_code'),
            'nam_thanh_lap' => $s('founded_year', '2013'),
            'mang_xa_hoi' => array_filter([
                'facebook' => $s('facebook'),
                'zalo' => $s('zalo'),
                'tiktok' => $s('tiktok'),
                'youtube' => $s('youtube'),
                'instagram' => $s('instagram'),
            ]) ?: null,
            'trang' => [
                'lien_he' => HoTro::web('lien-he'),
                've_chung_toi' => HoTro::web('ve-chung-toi'),
                'chinh_sach_thanh_toan' => HoTro::web('chinh-sach-thanh-toan'),
                'chinh_sach_huy_hoan' => HoTro::web('chinh-sach-huy-hoan'),
                'cau_hoi_thuong_gap' => HoTro::web('cau-hoi-thuong-gap'),
                'tra_cuu_booking' => HoTro::web('tra-cuu-booking'),
            ],
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
