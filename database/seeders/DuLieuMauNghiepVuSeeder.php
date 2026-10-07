<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Models\VisaProvider;
use Modules\Visa\Services\BaoHoSoVisaMoi;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dữ liệu mẫu cho các việc nghiệp vụ: tài khoản theo vai trò, đơn tour có tiền
 * cọc + hạn nhắn khách, hồ sơ visa đủ các trạng thái (có file mẫu để thử xuất
 * ZIP). Gọi từ DemoSeeder — chạy sau khi đã có tour.
 *
 * Mọi tên / số điện thoại / hộ chiếu ở đây là BỊA, không phải khách thật.
 * Chạy lại nhiều lần không sinh trùng.
 */
class DuLieuMauNghiepVuSeeder extends Seeder
{
    public const MAT_KHAU_NHAN_VIEN = 'NhanVien@123456';

    public const MAT_KHAU_KHACH = 'Khach@123456';

    private ?int $adminId;

    public function run(): void
    {
        $this->adminId = User::where('email', 'admin@psvtravel.com')->value('id');

        [$visa1, $visa2, $khach, $sale, $keToan] = $this->taiKhoan();
        $this->donTour($khach, $sale);
        $this->hoSoVisa($visa1, $visa2, $khach);

        // Bật chuông "tới hạn nhắn khách" ngay, khỏi đợi lịch 8h/13h
        Artisan::call('don-tour:nhac-nhan-khach');
    }

    /** @return array{0: ?User, 1: ?User, 2: ?User, 3: ?User, 4: ?User} */
    private function taiKhoan(): array
    {
        // Mật khẩu ai cũng biết — KHÔNG tạo trên máy chủ thật
        if (app()->isProduction()) {
            $this->command?->warn('APP_ENV=production: bỏ qua tạo tài khoản mẫu (mật khẩu công khai).');

            return [null, null, null, null, null];
        }

        $tao = function (string $email, string $ten, string $matKhau, string $vaiTro): User {
            $u = User::firstOrCreate(['email' => $email], [
                'name' => $ten, 'password' => Hash::make($matKhau),
                'email_verified_at' => now(), 'locale' => 'vi',
            ]);
            $u->syncRoles([$vaiTro]);

            return $u;
        };

        $visa1 = $tao('visa1@psvtravel.com', 'Visa — Ngọc Hân', self::MAT_KHAU_NHAN_VIEN, 'visa');
        $visa2 = $tao('visa2@psvtravel.com', 'Visa — Minh Thư', self::MAT_KHAU_NHAN_VIEN, 'visa');
        $tao('dieuhanh@psvtravel.com', 'Điều hành — Quốc Bảo', self::MAT_KHAU_NHAN_VIEN, 'dieu_hanh');
        $khach = $tao('khach@example.com', 'Khách Mẫu Thử', self::MAT_KHAU_KHACH, 'customer');
        $khach->forceFill(['phone' => '0900000001'])->save();

        // Nhân viên kinh doanh (vai trò staff: xử lý đơn) + kế toán duyệt khoản thu
        Role::findByName('staff', 'web')->givePermissionTo(
            collect(['ViewAny:Booking', 'View:Booking', 'Create:Booking', 'Update:Booking'])
                ->map(fn ($q) => Permission::findOrCreate($q, 'web'))
        );
        $sale = $tao('sale@psvtravel.com', 'Kinh doanh — Thu Trang', self::MAT_KHAU_NHAN_VIEN, 'staff');
        $keToan = $tao('ketoan@psvtravel.com', 'Kế toán — Mỹ Linh', self::MAT_KHAU_NHAN_VIEN, 'ke_toan');

        return [$visa1, $visa2, $khach, $sale, $keToan];
    }

    private function donTour(?User $khach, ?User $sale): void
    {
        $tour1 = Tour::where('slug', 'da-nang-hoi-an-3n2d')->first();
        $tour2 = Tour::where('slug', 'thai-lan-bangkok-pattaya-5n4d')->first() ?? Tour::where('type', 'abroad')->first();
        if (! $tour1 || ! $tour2) {
            return;
        }
        $dot1 = $tour1->departures()->orderBy('start_date')->first();
        $dot2 = $tour2->departures()->orderBy('start_date')->first();

        // Hai đơn cũ của DemoSeeder: thêm tỷ lệ cọc + người phụ trách
        Booking::where('customer_email', 'khach2@example.com')->get()
            ->each(fn (Booking $b) => $b->forceFill(['created_by' => $b->created_by ?? $this->adminId, 'assigned_to' => $b->assigned_to ?? $this->adminId])
                ->fill(['deposit_percent' => 50])->save());
        Booking::where('customer_email', 'khach1@example.com')->get()
            ->each(fn (Booking $b) => $b->forceFill(['source' => 'web'])->fill(['deposit_percent' => 30])->save());

        $saleId = $sale?->id ?? $this->adminId;
        $don = function (array $khoa, array $giaTri): Booking {
            $b = Booking::firstOrNew($khoa);
            $b->fill($giaTri);
            $b->forceFill([
                'source' => $giaTri['source'] ?? 'quan_tri',
                'created_by' => $giaTri['created_by'] ?? $b->created_by,
                'assigned_to' => $giaTri['created_by'] ?? $b->assigned_to,
                'confirmed_by' => $b->confirmed_by ?? ($giaTri['status'] === 'confirmed' ? $this->adminId : null),
            ]);
            $b->save();

            return $b;
        };

        // Tới hạn nhắn khách HÔM NAY, mới cọc 1 phần → hiện nút "Nhắc đóng
        // tiền", lọc "Cần nhắc khách", huy hiệu menu và chuông cho admin
        $toiHan = $don(['customer_email' => 'khach3@example.com', 'tour_id' => $tour2->id], [
            'tour_departure_id' => $dot2?->id,
            'customer_name' => 'Lâm Gia Huy (mẫu)', 'customer_phone' => '0900000003',
            'adults' => 3, 'children' => 0,
            'unit_price_adult' => $tour2->adult_price, 'unit_price_child' => 0,
            'total_price' => 3 * $tour2->adult_price,
            'status' => 'confirmed', 'payment_status' => 'unpaid',
            'deposit_percent' => 30, 'remind_on' => today()->toDateString(),
            'created_by' => $saleId,
        ]);
        Payment::updateOrCreate(
            ['booking_id' => $toiHan->id, 'transaction_ref' => 'DEMO-COC-003'],
            ['method' => 'bank_transfer', 'amount' => 5_000_000, 'status' => 'success',
                'received_by' => $this->adminId, 'paid_at' => now()->subDays(4), 'note' => 'Cọc đợt 1 (chưa đủ 30%).'],
        );
        // Nhân viên vừa ghi nhận cọc đợt 2 kèm ảnh chuyển khoản → chờ kế toán duyệt
        $choDuyet = Payment::firstOrNew(['booking_id' => $toiHan->id, 'transaction_ref' => 'DEMO-COC-003B']);
        if (! $choDuyet->exists) {
            $choDuyet->fill(['method' => 'bank_transfer', 'amount' => 3_000_000, 'status' => 'pending',
                'received_by' => $saleId, 'paid_at' => now()->subHours(2), 'note' => 'Cọc đợt 2 — khách gửi ảnh qua Zalo.',
                'proof_images' => [$this->anhChuyenKhoan($toiHan->booking_code, 3_000_000)]])->save();
        }

        // Đã trả đủ → không còn bị nhắc
        $daDu = $don(['customer_email' => 'khach4@example.com', 'tour_id' => $tour1->id], [
            'tour_departure_id' => $dot1?->id,
            'customer_name' => 'Võ Thanh Tâm (mẫu)', 'customer_phone' => '0900000004',
            'adults' => 2, 'children' => 0,
            'unit_price_adult' => $tour1->adult_price, 'unit_price_child' => 0,
            'total_price' => 2 * $tour1->adult_price,
            'status' => 'confirmed', 'payment_status' => 'unpaid',
            'deposit_percent' => 50, 'created_by' => $saleId,
        ]);
        Payment::updateOrCreate(
            ['booking_id' => $daDu->id, 'transaction_ref' => 'DEMO-DU-004'],
            ['method' => 'bank_transfer', 'amount' => $daDu->total_price, 'status' => 'success',
                'received_by' => $this->adminId, 'paid_at' => now()->subDay(), 'note' => 'Thanh toán đủ.'],
        );

        // Khách có tài khoản đặt trên web, chưa ai xác nhận
        if ($khach) {
            $don(['customer_email' => $khach->email, 'tour_id' => $tour1->id], [
                'tour_departure_id' => $dot1?->id, 'user_id' => $khach->id,
                'customer_name' => $khach->name, 'customer_phone' => $khach->phone,
                'adults' => 1, 'children' => 1,
                'unit_price_adult' => $tour1->adult_price, 'unit_price_child' => $tour1->child_price ?? 0,
                'total_price' => $tour1->adult_price + ($tour1->child_price ?? 0),
                'status' => 'pending', 'payment_status' => 'unpaid', 'source' => 'web',
                'note' => 'Đặt từ website (dữ liệu mẫu).',
            ]);
        }
    }

    private function hoSoVisa(?User $visa1, ?User $visa2, ?User $khach): void
    {
        $doiTac = VisaProvider::first();
        $nv1 = $visa1?->id ?? $this->adminId;
        $nv2 = $visa2?->id ?? $this->adminId;

        $phieu = fn (string $gioiTinh, string $noiLam) => [
            'gioi_tinh' => $gioiTinh, 'noi_sinh' => 'TP. Hồ Chí Minh', 'hon_nhan' => 'da_ket_hon',
            'so_cccd' => '079000000000', 'dia_chi_thuong_tru' => '1 Đường Mẫu, Phường Mẫu, TP.HCM',
            'nghe_nghiep' => 'Nhân viên văn phòng', 'noi_lam_viec' => $noiLam,
            'da_den_nuoc_nay' => 'khong', 'bi_tu_choi_nuoc_nay' => 'khong', 'bi_tu_choi_nuoc_khac' => 'khong',
            'khan_cap_ten' => 'Người Thân Mẫu', 'khan_cap_sdt' => '0900000099', 'khan_cap_quan_he' => 'Vợ/chồng',
            'an_ket_an' => 'khong', 'an_truc_xuat' => 'khong',
        ];

        // 1. Khách tự nộp trên web, CHƯA AI NHẬN — thử nút "Nhận hồ sơ"
        $web = $this->hoSo('mau.web@example.com', [
            'full_name' => 'TRẦN MẪU WEB', 'phone' => '0900000011', 'birth_date' => '1992-05-10',
            'passport_no' => 'C0000011', 'passport_expiry' => now()->addYears(6)->toDateString(),
            'country' => 'Nhật Bản', 'purpose' => 'du_lich', 'profile' => 'nhan_vien',
            'travel_date' => now()->addMonths(2)->toDateString(),
            'status' => 'moi', 'assigned_to' => null,
            'thong_tin' => $phieu('nu', 'Công ty TNHH Mẫu'),
        ], nguon: 'website', khach: $khach, coFile: ['Hộ chiếu', 'Ảnh']);

        // 2. Đang gom giấy, hẹn nộp trong 2 ngày, còn nợ phí — của visa1
        $this->hoSo('mau.trungquoc@example.com', [
            'full_name' => 'NGUYỄN MẪU MỘT', 'phone' => '0900000012', 'birth_date' => '1988-01-20',
            'passport_no' => 'C0000012', 'passport_expiry' => now()->addMonths(5)->toDateString(), // sắp hết hạn
            'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'nhan_vien',
            'travel_date' => now()->addWeeks(5)->toDateString(),
            'status' => 'dang_gom', 'assigned_to' => $nv1, 'visa_provider_id' => $doiTac?->id,
            'appointment_at' => now()->addDays(2)->setTime(9, 30),
            'fee' => 1_800_000, 'cost' => 1_200_000, 'paid' => 500_000,
            'thong_tin' => $phieu('nam', 'Công ty CP Mẫu Một'),
        ], coFile: ['Hộ chiếu', 'Ảnh', 'Căn cước']);

        // 3. Đoàn Hàn Quốc 3 người (3 đối tượng khác nhau) — thử xuất ZIP cả đoàn
        $doan = 'Đoàn Hàn Quốc mẫu '.now()->addMonth()->format('d/m');
        foreach ([
            ['HOÀNG MẪU HAI', 'nhan_vien', '0900000013', 'nam', 'dang_gom'],
            ['PHẠM MẪU BA', 'chu_doanh_nghiep', '0900000014', 'nu', 'cho_nop'],
            ['ĐỖ MẪU BỐN', 'hoc_sinh', '0900000015', 'nam', 'moi'],
        ] as $i => [$ten, $doiTuong, $sdt, $gt, $trangThai]) {
            $this->hoSo("mau.doanhan{$i}@example.com", [
                'full_name' => $ten, 'phone' => $sdt, 'birth_date' => (1980 + $i * 9).'-03-1'.$i,
                'passport_no' => 'C00001'.(3 + $i), 'passport_expiry' => now()->addYears(4)->toDateString(),
                'group_name' => $doan,
                'country' => 'Hàn Quốc', 'purpose' => 'du_lich', 'profile' => $doiTuong,
                'travel_date' => now()->addMonth()->toDateString(),
                'status' => $trangThai, 'assigned_to' => $nv2,
                'fee' => 1_500_000, 'paid' => $i === 0 ? 1_500_000 : 0,
                'thong_tin' => $phieu($gt, 'Đơn vị mẫu '.($i + 2)),
            ], coFile: $i === 2 ? [] : ['Hộ chiếu', 'Ảnh', 'Sao kê', 'Căn cước']);
        }

        // 4. Đã nộp chờ kết quả, 5. Đậu, 6. Trượt — của visa1
        $this->hoSo('mau.dai-loan@example.com', [
            'full_name' => 'LÊ MẪU NĂM', 'phone' => '0900000016',
            'country' => 'Đài Loan', 'purpose' => 'du_lich', 'profile' => 'tu_do',
            'status' => 'da_nop', 'assigned_to' => $nv1,
            'submitted_on' => now()->subDays(3)->toDateString(), 'result_expected_on' => now()->addDays(4)->toDateString(),
            'fee' => 1_200_000, 'paid' => 1_200_000,
        ], tatCaDaNhan: true);
        $this->hoSo('mau.my@example.com', [
            'full_name' => 'VŨ MẪU SÁU', 'phone' => '0900000017',
            'country' => 'Mỹ', 'purpose' => 'du_lich', 'profile' => 'chu_doanh_nghiep',
            'status' => 'dau', 'assigned_to' => $nv1,
            'submitted_on' => now()->subWeeks(3)->toDateString(), 'result_on' => now()->subWeek()->toDateString(),
            'visa_expiry' => now()->addYear()->subWeek()->toDateString(),
            'fee' => 4_500_000, 'cost' => 4_000_000, 'paid' => 4_500_000,
        ], tatCaDaNhan: true);
        $this->hoSo('mau.uc@example.com', [
            'full_name' => 'BÙI MẪU BẢY', 'phone' => '0900000018',
            'country' => 'Hong Kong', 'purpose' => 'du_lich', 'profile' => 'huu_tri',
            'status' => 'truot', 'assigned_to' => $nv1,
            'submitted_on' => now()->subWeeks(2)->toDateString(), 'result_on' => now()->subDays(2)->toDateString(),
            'fee' => 1_000_000, 'paid' => 1_000_000, 'note' => 'Trượt do thiếu chứng minh tài chính (mẫu).',
        ], tatCaDaNhan: true);

        // Chuông "hồ sơ mới từ website" cho nhân viên visa + admin
        if ($web->wasRecentlyCreated) {
            app(BaoHoSoVisaMoi::class)->guiNoiBo($web);
        }
    }

    /**
     * @param  list<string>  $coFile  gắn file mẫu vào giấy tờ có tên chứa các chữ này
     */
    private function hoSo(string $email, array $duLieu, string $nguon = 'quan_tri', ?User $khach = null,
        array $coFile = [], bool $tatCaDaNhan = false): VisaCase
    {
        $hs = VisaCase::withTrashed()->firstOrNew(['email' => $email]);
        if ($hs->exists) {
            return $hs; // giữ nguyên những gì người thử đã sửa
        }

        $mau = VisaChecklist::timMau($duLieu['country'], $duLieu['purpose'], $duLieu['profile'] ?? null);
        $giay = VisaCase::chepMau($mau, $duLieu['profile'] ?? null);

        $hs->fill([...$duLieu, 'email' => $email, 'visa_checklist_id' => $mau?->id, 'created_by' => $nguon === 'website' ? null : $this->adminId]);
        $hs->forceFill(['source' => $nguon, 'user_id' => $khach?->id]);
        $hs->save(); // có id rồi mới đặt tên thư mục file

        foreach ($giay as $i => $g) {
            if ($tatCaDaNhan) {
                $giay[$i]['trang_thai'] = 'da_nhan';
            }
            foreach ($coFile as $tu) {
                if (mb_stripos($g['ten'], $tu) !== false && empty($giay[$i]['tep'])) {
                    [$duong, $tenGoc] = $this->fileMau($hs, $g['ten'], $i);
                    $giay[$i]['tep'] = [$duong];
                    $giay[$i]['ten_tep'] = [$duong => $tenGoc];
                }
            }
        }

        // Một file "giấy tờ khác" không thuộc mục nào
        $files = [];
        $names = [];
        if ($coFile) {
            [$duong, $tenGoc] = $this->fileMau($hs, 'Giấy tờ bổ sung', 99);
            $files[] = $duong;
            $names[$duong] = $tenGoc;
        }

        $hs->forceFill(['checklist' => $giay, 'files' => $files, 'file_names' => $names])->save();
        if ($nguon === 'website') {
            $hs->forceFill(['web_files_count' => count($files) + count(array_filter(array_column($giay, 'tep')))])->saveQuietly();
        }

        return $hs;
    }

    /** Ảnh biên lai chuyển khoản giả (ghi rõ MẪU) để thử màn hình kế toán duyệt. */
    private function anhChuyenKhoan(string $maDon, int $soTien): string
    {
        $anh = imagecreatetruecolor(600, 900);
        imagefill($anh, 0, 0, imagecolorallocate($anh, 245, 250, 245));
        $chu = imagecolorallocate($anh, 20, 90, 50);
        foreach (['BIEN LAI CHUYEN KHOAN - MAU', 'KHONG PHAI GIAO DICH THAT', '', 'So tien: '.number_format($soTien, 0, ',', '.').' VND',
            'Noi dung: '.$maDon, 'Thoi gian: '.now()->format('H:i d/m/Y')] as $i => $dong) {
            imagestring($anh, 5, 40, 60 + $i * 40, $dong, $chu);
        }
        ob_start();
        imagejpeg($anh, null, 80);
        $noiDung = ob_get_clean();
        imagedestroy($anh);

        $duong = 'chung-tu-thanh-toan/mau-'.$maDon.'.jpg';
        Storage::disk('rieng')->put($duong, $noiDung);

        return $duong;
    }

    /** Ảnh JPG có ghi chữ "MẪU" — đủ để xem trước, xuất ZIP. @return array{0: string, 1: string} */
    private function fileMau(VisaCase $hs, string $tenGiay, int $i): array
    {
        $anh = imagecreatetruecolor(800, 500);
        imagefill($anh, 0, 0, imagecolorallocate($anh, 235, 242, 250));
        $chu = imagecolorallocate($anh, 30, 60, 110);
        imagestring($anh, 5, 40, 40, 'PSV TRAVEL - FILE MAU (KHONG PHAI GIAY TO THAT)', $chu);
        imagestring($anh, 5, 40, 90, $hs->code.' - '.Str::ascii($hs->full_name), $chu);
        imagestring($anh, 5, 40, 130, Str::ascii($tenGiay), $chu);
        ob_start();
        imagejpeg($anh, null, 80);
        $noiDung = ob_get_clean();
        imagedestroy($anh);

        $duong = 'ho-so-visa/mau/'.$hs->code.'-'.$i.'.jpg';
        Storage::disk('rieng')->put($duong, $noiDung);

        return [$duong, Str::limit($tenGiay, 60, '').'.jpg'];
    }
}
