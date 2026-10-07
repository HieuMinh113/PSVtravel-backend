<?php

namespace Modules\Visa\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Modules\Visa\Models\VisaChecklist;

/**
 * Vài mẫu checklist khởi đầu, chép từ thư mục Drive thủ tục visa (Trung Quốc,
 * Ai Cập, Hàn Quốc). Chỉ tạo khi bảng còn trống — đã có mẫu thì không đụng.
 * Bộ phận visa sửa / thêm mẫu ngay trong trang quản trị.
 */
class MauChecklistVisaSeeder extends Seeder
{
    public function run(): void
    {
        if (VisaChecklist::withTrashed()->exists()) {
            return;
        }

        foreach ([...self::mau(), ...self::mauDoanHan()] as $i => $m) {
            VisaChecklist::create($m + ['sort_order' => $i]);
        }
    }

    /**
     * 35 mẫu của 17 nước chép từ bộ "FILE THỦ TỤC HỒ SƠ NOLOGO" (xem
     * database/data/mau-thu-tuc-nlg.php). File thủ tục / form mẫu đi kèm được
     * chép vào ổ riêng tư làm "File mẫu" của mẫu.
     */
    public static function themMauThuTucNlg(): void
    {
        $thuMucNguon = module_path('Visa', 'database/data/thu-tuc');
        $dia = Storage::disk('rieng');

        $dsMau = collect(require module_path('Visa', 'database/data/mau-thu-tuc-nlg.php'))
            ->map(function (array $m) use ($thuMucNguon, $dia) {
                $duong = [];
                $ten = [];
                foreach ($m['tep'] ?? [] as $file => $tenHienThi) {
                    $dich = 'mau-visa/'.$file;
                    if (! $dia->exists($dich) && is_file($thuMucNguon.'/'.$file)) {
                        $dia->put($dich, file_get_contents($thuMucNguon.'/'.$file));
                    }
                    $duong[] = $dich;
                    $ten[$dich] = $tenHienThi;
                }
                unset($m['tep']);

                return $m + ['attachments' => $duong ?: null, 'attachment_names' => $ten ?: null];
            })->all();

        self::themMauNeuChuaCo($dsMau);
    }

    /** Thêm các mẫu chưa có (so theo tên) — dùng cho migration bổ sung mẫu về sau. */
    public static function themMauNeuChuaCo(array $dsMau): void
    {
        $thuTu = (int) VisaChecklist::withTrashed()->max('sort_order');
        foreach ($dsMau as $m) {
            if (! VisaChecklist::withTrashed()->where('name', $m['name'])->exists()) {
                VisaChecklist::create($m + ['sort_order' => ++$thuTu]);
            }
        }
    }

    /**
     * Đoàn Hàn Quốc — theo checklist "VISA ĐOÀN HÀN": nhân thân + tài chính +
     * nghề nghiệp (khác nhau theo đối tượng) + tờ khai theo mẫu.
     */
    public static function mauDoanHan(): array
    {
        $nhanThan = [
            self::g('Hồ sơ nhân thân', 'Hộ chiếu', 'hộ chiếu gốc — scan trang thông tin + các trang có visa / dấu xuất nhập cảnh'),
            self::g('Hồ sơ nhân thân', 'Ảnh thẻ 3.5x4.5', 'nền trắng, gửi file'),
            self::g('Hồ sơ nhân thân', 'Căn cước', 'chụp ảnh'),
            self::g('Hồ sơ nhân thân', 'Đăng ký kết hôn - quyết định ly hôn', 'chụp ảnh — nếu đi cùng gia đình, để chứng minh quan hệ'),
            self::g('Hồ sơ nhân thân', 'Giấy khai sinh', 'chụp ảnh / scan — nếu đi cùng gia đình, để chứng minh quan hệ'),
        ];
        $taiChinh = [
            self::g('Hồ sơ tài chính', 'Sao kê ngân hàng', '3–6 tháng gần nhất, tài khoản nhận lương; miễn nếu đã đi Mỹ, Canada, châu Âu, Úc'),
            self::g('Hồ sơ tài chính', 'Sổ tiết kiệm', 'sổ gốc chụp ảnh; miễn nếu đã đi Mỹ, Canada, châu Âu, Úc'),
        ];
        $toKhai = [self::g('Tờ khai', 'Tờ khai', 'theo mẫu')];
        $luuY = 'Hồ sơ đã đi Mỹ, Canada, châu Âu, Úc… được miễn phần tài chính.';
        $mau = fn (string $doiTuong, string $ten, array $ngheNghiep) => [
            'name' => 'Hàn Quốc — Đoàn du lịch — '.$ten,
            'country' => 'Hàn Quốc', 'purpose' => 'du_lich', 'profile' => $doiTuong,
            'items' => [...$nhanThan, ...$taiChinh, ...$ngheNghiep, ...$toKhai],
            'note' => $luuY,
        ];

        return [
            $mau('nhan_vien', 'Nhân viên', [
                self::g('Hồ sơ nghề nghiệp', 'Hợp đồng lao động - quyết định bổ nhiệm', 'chụp ảnh / scan'),
                self::g('Hồ sơ nghề nghiệp', 'VssID', 'chụp màn hình bảo hiểm xã hội'),
            ]),
            $mau('chu_doanh_nghiep', 'Chủ doanh nghiệp', [
                self::g('Hồ sơ nghề nghiệp', 'Đăng ký kinh doanh', 'chụp ảnh / scan'),
                self::g('Hồ sơ nghề nghiệp', 'Giấy tờ thuế 3 tháng gần nhất', 'chụp ảnh / scan'),
            ]),
            $mau('huu_tri', 'Hưu trí', [
                self::g('Hồ sơ nghề nghiệp', 'Quyết định hưu trí', 'chụp ảnh / scan'),
            ]),
        ];
    }

    private static function g(string $nhom, string $ten, ?string $ghiChu = null): array
    {
        return ['nhom' => $nhom, 'ten' => $ten, 'ghi_chu' => $ghiChu];
    }

    public static function mau(): array
    {
        $caNhanTq = [
            self::g('Giấy tờ cá nhân', 'Hộ chiếu bản gốc', 'kèm hộ chiếu cũ nếu có'),
            self::g('Giấy tờ cá nhân', '2 ảnh 4x6 nền trắng', 'tóc không che trán'),
            self::g('Giấy tờ cá nhân', 'CCCD photo'),
            self::g('Giấy tờ cá nhân', 'Hộ khẩu sao y công chứng trên A4', 'đã thu hồi hộ khẩu thì nộp CT07 hoặc CT08 bản gốc'),
            self::g('Giấy tờ cá nhân', 'Tờ khai thông tin cá nhân'),
        ];
        $luuYTq = 'Cần tư vấn thêm: gọi chuyên viên visa.';

        return [
            [
                'name' => 'Trung Quốc — Du lịch — Nhân viên',
                'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'nhan_vien',
                'items' => [...$caNhanTq, self::g('Công việc', 'Xác nhận việc làm', 'hoặc xác nhận số dư ngân hàng trên 50 triệu')],
                'note' => $luuYTq,
            ],
            [
                'name' => 'Trung Quốc — Du lịch — Chủ doanh nghiệp',
                'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'chu_doanh_nghiep',
                'items' => [...$caNhanTq, self::g('Công việc', 'Giấy phép kinh doanh', 'hoặc xác nhận số dư ngân hàng trên 50 triệu')],
                'note' => $luuYTq,
            ],
            [
                'name' => 'Trung Quốc — Du lịch — Lao động tự do',
                'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'tu_do',
                'items' => [...$caNhanTq, self::g('Tài chính', 'Xác nhận số dư ngân hàng trên 50 triệu')],
                'note' => $luuYTq,
            ],
            [
                'name' => 'Trung Quốc — Du lịch — Hưu trí',
                'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'huu_tri',
                'items' => [...$caNhanTq, self::g('Tài chính', 'Xác nhận số dư ngân hàng trên 50 triệu')],
                'note' => $luuYTq,
            ],
            [
                'name' => 'Trung Quốc — Công tác',
                'country' => 'Trung Quốc', 'purpose' => 'cong_tac', 'profile' => null,
                'items' => [
                    ...$caNhanTq,
                    self::g('Công việc', 'Giấy phép kinh doanh sao y công chứng', 'công ty Việt Nam'),
                    self::g('Công việc', 'Quyết định cử đi công tác', 'nhân viên'),
                    self::g('Phía người mời', 'Thư mời', 'khi công ty Trung Quốc mời'),
                    self::g('Phía người mời', 'Giấy phép kinh doanh công ty mời', 'khi công ty Trung Quốc mời'),
                ],
                'note' => $luuYTq,
            ],
            [
                'name' => 'Trung Quốc — Thăm thân',
                'country' => 'Trung Quốc', 'purpose' => 'tham_than', 'profile' => null,
                'items' => [
                    self::g('Giấy tờ cá nhân', 'Hộ chiếu bản gốc', 'kèm hộ chiếu cũ nếu có'),
                    self::g('Giấy tờ cá nhân', '2 ảnh 4x6 nền trắng'),
                    self::g('Giấy tờ cá nhân', 'CCCD sao y công chứng trên A4'),
                    self::g('Giấy tờ cá nhân', 'Hộ khẩu sao y công chứng trên A4', 'mỗi người 1 bản; đã thu hồi hộ khẩu thì CT07/CT08 bản gốc'),
                    self::g('Giấy tờ cá nhân', 'Giấy tờ chứng minh quan hệ', 'khai sinh / giấy kết hôn tiếng Trung — tuỳ quan hệ'),
                    self::g('Phía người mời', 'Thư mời viết theo mẫu', 'ghi địa chỉ + SĐT ở Trung Quốc'),
                    self::g('Phía người mời', 'Mặt hộ chiếu người mời'),
                    self::g('Phía người mời', 'ID người mời'),
                    self::g('Phía người mời', 'Giấy kết hôn tiếng Trung bản scan', 'kèm visa còn hạn nếu người thân là người Việt ở TQ'),
                ],
                'note' => 'Thăm con gái lấy chồng TQ, vợ thăm chồng, con thăm cha… — giấy chứng minh quan hệ khác nhau, sửa theo từng case.',
            ],
            [
                'name' => 'Ai Cập — Du lịch',
                'country' => 'Ai Cập', 'purpose' => 'du_lich', 'profile' => null,
                'items' => [
                    self::g('Giấy tờ cá nhân', 'Hộ chiếu gốc', 'kèm hộ chiếu cũ nếu có'),
                    self::g('Giấy tờ cá nhân', '2 ảnh mới 4x6 nền trắng'),
                    self::g('Công việc', 'Hợp đồng lao động'),
                    self::g('Công việc', 'Sao kê tài khoản lương 3 tháng gần nhất', 'nhận lương tiền mặt thì bảng lương 3 tháng'),
                    self::g('Công việc', 'Đăng ký kinh doanh + thuế 3 tháng gần nhất', 'chủ doanh nghiệp'),
                    self::g('Tài chính', 'Xác nhận sổ tiết kiệm từ 100 triệu', 'hoặc sao kê 3 tháng, số dư cuối kỳ từ 85 triệu'),
                    self::g('Thông tin cần', 'Số điện thoại, email, địa chỉ cư trú hiện tại, ngày đi dự kiến'),
                ],
                'note' => 'Sao y công chứng không quá 3 tháng, in A4 một mặt, không cắt rời.',
            ],
            [
                'name' => 'Hàn Quốc — Hộ khẩu TP.HCM — Học sinh / sinh viên',
                'country' => 'Hàn Quốc', 'purpose' => 'du_lich', 'profile' => 'hoc_sinh',
                'items' => [
                    self::g('Giấy tờ cá nhân', 'Hộ chiếu gốc cũ + mới', 'còn hạn trên 6 tháng, có nơi sinh'),
                    self::g('Giấy tờ cá nhân', '2 ảnh 3.5x4.5 nền trắng', 'không trùng ảnh hộ chiếu'),
                    self::g('Giấy tờ cá nhân', 'Form khai'),
                    self::g('Giấy tờ cá nhân', 'CT07 bản gốc', 'đánh máy, đủ thành viên hộ khẩu, mục 8 ghi thường trú trên 1 năm'),
                    self::g('Giấy tờ cá nhân', 'Giấy tờ nhà đất / HĐ mua bán chung cư tại TP.HCM', 'bản thân hoặc bố, mẹ, vợ, chồng, con đứng tên'),
                    self::g('Giấy tờ cá nhân', 'Xác nhận học sinh / sinh viên bản gốc + thẻ sinh viên gốc', 'lấy trong 2 tháng gần nhất'),
                    self::g('Giấy tờ cá nhân', 'CCCD photo trên A4 + ảnh chụp rõ QR', 'không nhận bản sao y'),
                    self::g('Giấy tờ cá nhân', 'Bản sao giấy khai sinh', 'cấp trong 3 tháng gần nhất'),
                    self::g('Công việc của bố / mẹ', 'HĐLĐ sao y công chứng 1 mặt'),
                    self::g('Công việc của bố / mẹ', 'Sao kê tài khoản lương 6 tháng gần nhất'),
                    self::g('Công việc của bố / mẹ', 'Ảnh chụp BHXH trên app VssID'),
                    self::g('Tài chính', 'Tài sản của bố / mẹ sao y', 'nhà đất (A3), HĐ mua bán chung cư, sổ tiết kiệm, cà vẹt xe, cổ phần…'),
                ],
                'note' => 'Photo đều phải sao y công chứng một mặt A4, không cắt rời (trừ CCCD chỉ photo, sổ đất khổ A3). Giấy sao y chỉ lấy 3 tháng gần nhất.',
            ],
        ];
    }
}
