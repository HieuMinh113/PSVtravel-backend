<?php

namespace Modules\Visa\Support;

use Illuminate\Support\Carbon;

/**
 * Câu hỏi của "Phiếu thông tin xin visa" — lấy theo phiếu Nhật Bản (bản đầy đủ
 * nhất anh gửi); các nước khác (Hàn, Trung, Đài…) hỏi tập con của phiếu này.
 *
 * Lưu ở visa_cases.thong_tin dưới dạng { khoá: giá trị }. Họ tên, ngày sinh,
 * số / hạn hộ chiếu, SĐT, email đã có cột riêng nên không lặp ở đây.
 *
 * Kiểu: text | textarea | date | chon (kèm 'lua_chon') | co_khong
 */
class PhieuThongTin
{
    public const CO_KHONG = ['co' => 'Có', 'khong' => 'Không'];

    public const NHOM = [
        'Nhân thân' => [
            'gioi_tinh' => ['Giới tính', 'chon', ['nam' => 'Nam', 'nu' => 'Nữ']],
            'noi_sinh' => ['Nơi sinh (trùng thông tin trên hộ chiếu)', 'text'],
            'quoc_tich_khac' => ['Quốc tịch khác (nếu có)', 'text'],
            'hon_nhan' => ['Tình trạng hôn nhân', 'chon', ['doc_than' => 'Độc thân', 'da_ket_hon' => 'Đã kết hôn', 'ly_di' => 'Ly dị', 'goa' => 'Góa']],
            'ngay_cap_ho_chieu' => ['Ngày cấp hộ chiếu', 'date'],
            'so_cccd' => ['Số CCCD', 'text'],
            'ngay_cap_cccd' => ['Ngày cấp CCCD', 'date'],
            'mang_xa_hoi' => ['Link mạng xã hội (Facebook, Zalo…)', 'text'],
            'dia_chi_thuong_tru' => ['Địa chỉ thường trú (hộ khẩu)', 'textarea'],
            'dia_chi_tam_tru' => ['Địa chỉ tạm trú (hiện tại)', 'textarea'],
        ],
        'Công việc' => [
            'nghe_nghiep' => ['Nghề nghiệp / chức vụ', 'text'],
            'noi_lam_viec' => ['Tên công ty / nơi làm việc / trường học', 'text'],
            'dia_chi_noi_lam_viec' => ['Địa chỉ công ty / nơi làm việc / trường học', 'textarea'],
            'sdt_noi_lam_viec' => ['SĐT công ty / nơi làm việc / trường học', 'text'],
            'nghe_nghiep_vo_chong' => ['Nghề nghiệp của vợ / chồng (nếu đã kết hôn)', 'text'],
        ],
        'Lịch sử đi lại' => [
            'da_den_nuoc_nay' => ['Đã từng đến nước này chưa?', 'co_khong'],
            'cac_lan_den' => ['Nếu có: những năm nào, số ngày lưu trú trong 1 năm gần nhất, ngày đi – về chuyến gần nhất', 'textarea'],
            'bi_tu_choi_nuoc_nay' => ['Đã bị từ chối visa nước này chưa?', 'co_khong'],
            'bi_tu_choi_nuoc_nay_khi' => ['Nếu có: khi nào?', 'text'],
            'bi_tu_choi_nuoc_khac' => ['Đã bị từ chối visa nước khác chưa?', 'co_khong'],
            'bi_tu_choi_nuoc_khac_khi' => ['Nếu có: nước nào, khi nào?', 'text'],
            'cac_nuoc_5_nam' => ['Những nước đã đi trong 5 năm gần nhất', 'textarea'],
            'nguoi_di_cung' => ['Người đi cùng (họ tên, năm sinh, SĐT, quan hệ)', 'textarea'],
        ],
        'Liên hệ khẩn cấp ở Việt Nam (không phải người đi cùng)' => [
            'khan_cap_ten' => ['Họ tên', 'text'],
            'khan_cap_dia_chi' => ['Địa chỉ hiện tại', 'text'],
            'khan_cap_sdt' => ['Số điện thoại', 'text'],
            'khan_cap_nghe_nghiep' => ['Nghề nghiệp', 'text'],
            'khan_cap_quan_he' => ['Mối quan hệ', 'text'],
        ],
        'Anh/chị đã từng' => [
            'an_ket_an' => ['Bị kết án về tội ác / hành vi phạm tội ở bất kỳ quốc gia nào?', 'co_khong'],
            'an_tu_1_nam' => ['Bị kết án tù từ 1 năm trở lên ở bất kỳ quốc gia nào?', 'co_khong'],
            'an_truc_xuat' => ['Bị trục xuất khỏi nước này hoặc quốc gia khác vì quá hạn thị thực / vi phạm luật?', 'co_khong'],
            'an_ma_tuy' => ['Bị kết án về tội phạm ma túy ở bất kỳ quốc gia nào?', 'co_khong'],
            'an_mai_dam' => ['Tham gia hoạt động mại dâm hoặc liên quan đến mại dâm?', 'co_khong'],
            'an_buon_nguoi' => ['Phạm tội buôn bán người hoặc tiếp tay cho tội phạm đó?', 'co_khong'],
            'an_chi_tiet' => ['Nếu trả lời “Có” ở trên: chi tiết', 'textarea'],
        ],
    ];

    /** @return array<string, array{0:string,1:string,2?:array}> khoá → định nghĩa */
    public static function tatCa(): array
    {
        return array_merge(...array_values(self::NHOM));
    }

    /** Giá trị hiển thị (đổi mã lựa chọn sang chữ, ngày sang d/m/Y). */
    public static function hienThi(string $khoa, $giaTri): string
    {
        if ($giaTri === null || $giaTri === '') {
            return '';
        }
        $dn = self::tatCa()[$khoa] ?? null;

        return match ($dn[1] ?? 'text') {
            'chon' => $dn[2][$giaTri] ?? (string) $giaTri,
            'co_khong' => self::CO_KHONG[$giaTri] ?? (string) $giaTri,
            'date' => self::ngay($giaTri),
            default => (string) $giaTri,
        };
    }

    /** Chỉ giữ khoá hợp lệ, cắt chuỗi quá dài — dùng cho dữ liệu khách gửi từ web. */
    public static function loc(?array $vao): array
    {
        $ra = [];
        foreach (self::tatCa() as $khoa => $dn) {
            $v = $vao[$khoa] ?? null;
            if (! is_scalar($v) || trim((string) $v) === '') {
                continue;
            }
            $v = trim((string) $v);
            $hopLe = match ($dn[1]) {
                'chon' => array_key_exists($v, $dn[2]),
                'co_khong' => array_key_exists($v, self::CO_KHONG),
                'date' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v),
                default => true,
            };
            if ($hopLe) {
                $ra[$khoa] = mb_substr($v, 0, $dn[1] === 'textarea' ? 2000 : 300);
            }
        }

        return $ra;
    }

    private static function ngay(string $v): string
    {
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return $v;
        }
    }
}
