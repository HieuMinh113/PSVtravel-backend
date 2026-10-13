<?php

namespace Database\Seeders;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\BaoHoSoUngTuyen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu cho Tuyển dụng: 4 tin (1 tin đã hết hạn — không hiện ngoài web)
 * và 5 hồ sơ ứng tuyển đủ trạng thái, có CV / portfolio PDF mẫu (ổ riêng tư
 * "rieng") để thử mở CV, cập nhật xử lý, xoá hồ sơ. Gọi từ DemoSeeder.
 *
 * Tên / SĐT / email đều BỊA (…@example.com). Chạy lại không sinh trùng.
 * Xoá: `psv:don-du-lieu-mau` xoá các tin mẫu này; thêm --don-hang để xoá hồ sơ.
 */
class DuLieuMauTuyenDungSeeder extends Seeder
{
    /** @return list<array<string, mixed>> */
    public static function mau(): array
    {
        return [
            [
                'slug' => 'nhan-vien-kinh-doanh-tour-nuoc-ngoai', 'title' => 'Nhân viên kinh doanh tour nước ngoài',
                'department' => 'Kinh doanh', 'location' => 'TP.HCM', 'employment_type' => 'Toàn thời gian',
                'salary_range' => '8–15 triệu + hoa hồng', 'quantity' => 3, 'han' => 30,
                'description' => '<p>Tư vấn và bán các tour Hàn Quốc, Nhật Bản, Châu Âu cho khách lẻ và khách đoàn. Chăm sóc khách hàng cũ, báo giá và chốt tour.</p>',
                'requirements' => '<ul><li>Giao tiếp tốt, chủ động.</li><li>Ưu tiên có kinh nghiệm sale tour outbound.</li><li>Dùng thành thạo Zalo, Facebook, Excel.</li></ul>',
                'benefits' => '<ul><li>Lương cơ bản + hoa hồng theo doanh số.</li><li>Đi khảo sát tour nước ngoài.</li><li>Bảo hiểm đầy đủ, thưởng lễ Tết.</li></ul>',
            ],
            [
                'slug' => 'chuyen-vien-tu-van-visa', 'title' => 'Chuyên viên tư vấn visa',
                'department' => 'Visa', 'location' => 'TP.HCM', 'employment_type' => 'Toàn thời gian',
                'salary_range' => '9–14 triệu', 'quantity' => 2, 'han' => 20,
                'description' => '<p>Tư vấn hồ sơ visa du lịch, công tác, thăm thân; kiểm tra giấy tờ, đặt lịch hẹn nộp hồ sơ, theo dõi kết quả.</p>',
                'requirements' => '<ul><li>Cẩn thận, tỉ mỉ.</li><li>Ưu tiên đã làm hồ sơ visa Hàn, Nhật, Schengen.</li></ul>',
                'benefits' => '<ul><li>Thưởng theo hồ sơ đậu.</li><li>Đào tạo nghiệp vụ visa từng nước.</li></ul>',
            ],
            [
                'slug' => 'thiet-ke-do-hoa-marketing', 'title' => 'Thiết kế đồ hoạ (Marketing)',
                'department' => 'Marketing', 'location' => 'TP.HCM / làm từ xa 2 ngày/tuần', 'employment_type' => 'Toàn thời gian',
                'salary_range' => 'Thoả thuận', 'quantity' => 1, 'han' => null,
                'description' => '<p>Thiết kế ấn phẩm quảng cáo tour, banner website, bài đăng mạng xã hội, video ngắn.</p>',
                'requirements' => '<ul><li>Thành thạo Photoshop / Illustrator / Canva.</li><li><strong>Gửi kèm portfolio</strong> (PDF hoặc link Behance / Google Drive).</li></ul>',
                'benefits' => '<ul><li>Môi trường trẻ, được thử ý tưởng mới.</li><li>Máy tính cấu hình cao.</li></ul>',
            ],
            [
                // Hạn đã qua → không hiện ngoài website (thử ẩn tin hết hạn)
                'slug' => 'huong-dan-vien-tour-he', 'title' => 'Hướng dẫn viên tour hè (thời vụ)',
                'department' => 'Điều hành', 'location' => 'Hà Nội', 'employment_type' => 'Thời vụ',
                'salary_range' => 'Theo tour', 'quantity' => 5, 'han' => -5,
                'description' => '<p>Dẫn đoàn các tour hè miền Bắc.</p>',
                'requirements' => '<ul><li>Có thẻ hướng dẫn viên nội địa.</li></ul>',
                'benefits' => '<ul><li>Công tác phí theo tour.</li></ul>',
            ],
        ];
    }

    private const HO_SO = [
        // [email, tin, họ tên, SĐT, trạng thái, lời nhắn, ghi chú nội bộ, có portfolio PDF, link portfolio, gửi cách đây (giờ)]
        ['ungvien1@example.com', 'nhan-vien-kinh-doanh-tour-nuoc-ngoai', 'Nguyễn Minh Anh (mẫu)', '0900000101', 'moi',
            'Em có 2 năm bán tour Hàn – Nhật, đi làm được ngay.', null, false, null, 2],
        ['ungvien2@example.com', 'nhan-vien-kinh-doanh-tour-nuoc-ngoai', 'Trần Quốc Bảo (mẫu)', '0900000102', 'da_lien_he',
            null, 'Đã gọi, hẹn gửi thêm bảng doanh số cũ.', false, null, 30],
        ['ungvien3@example.com', 'thiet-ke-do-hoa-marketing', 'Lê Thảo Vy (mẫu)', '0900000103', 'moi',
            'Portfolio bản đầy đủ ở link Behance.', null, true, 'https://www.behance.net/', 5],
        ['ungvien4@example.com', 'chuyen-vien-tu-van-visa', 'Phạm Gia Khánh (mẫu)', '0900000104', 'phong_van',
            'Từng làm hồ sơ visa Schengen 1 năm.', 'Hẹn phỏng vấn 9h thứ Sáu tại văn phòng.', false, null, 72],
        ['ungvien5@example.com', 'chuyen-vien-tu-van-visa', 'Hoàng Bích Ngọc (mẫu)', '0900000105', 'khong_phu_hop',
            null, 'Chưa có kinh nghiệm visa, hẹn lần tuyển sau.', false, null, 120],
    ];

    public function run(): void
    {
        $tin = [];
        foreach (self::mau() as $i => $m) {
            $tin[$m['slug']] = JobPosting::withTrashed()->updateOrCreate(['slug' => $m['slug']], [
                ...collect($m)->except(['slug', 'han'])->all(),
                'deadline' => $m['han'] === null ? null : today()->addDays($m['han'])->toDateString(),
                'status' => 'published', 'sort_order' => $i + 1,
            ]);
            if ($tin[$m['slug']]->trashed()) {
                $tin[$m['slug']]->restore();
            }
        }

        $adminId = User::where('email', 'admin@psvtravel.com')->value('id');
        $moiNhat = null;
        foreach (self::HO_SO as [$email, $slugTin, $ten, $sdt, $trangThai, $loiNhan, $ghiChu, $coPortfolio, $link, $gioTruoc]) {
            $job = $tin[$slugTin];
            $hs = JobApplication::firstOrNew(['email' => $email, 'job_posting_id' => $job->id]);
            $moi = ! $hs->exists;
            $ma = Str::slug(Str::before($ten, ' ('));
            $hs->forceFill([
                'job_title' => $job->title, 'full_name' => $ten, 'phone' => $sdt, 'message' => $loiNhan,
                'cv_path' => $this->pdf("ung-tuyen/mau-{$ma}-cv.pdf", ['CV MAU - DU LIEU GIA', Str::ascii(Str::before($ten, ' (')), 'Vi tri: '.Str::ascii($job->title), 'SDT: '.$sdt, 'Email: '.$email]),
                'cv_name' => 'CV '.Str::ascii(Str::before($ten, ' (')).'.pdf',
                'portfolio_path' => $coPortfolio ? $this->pdf("ung-tuyen/mau-{$ma}-portfolio.pdf", ['PORTFOLIO MAU - DU LIEU GIA', Str::ascii(Str::before($ten, ' ('))]) : null,
                'portfolio_name' => $coPortfolio ? 'Portfolio '.Str::ascii(Str::before($ten, ' (')).'.pdf' : null,
                'portfolio_url' => $link,
                'status' => $trangThai, 'admin_note' => $ghiChu,
                'handled_by' => $trangThai === 'moi' ? null : $adminId,
                'handled_at' => $trangThai === 'moi' ? null : now()->subHours(max(1, $gioTruoc - 1)),
                'ip' => '127.0.0.1',
            ]);
            $hs->created_at = $hs->updated_at = now()->subHours($gioTruoc);
            $hs->save();
            if ($moi && $trangThai === 'moi') {
                $moiNhat = $hs;
            }
        }

        // Chuông "Hồ sơ ứng tuyển mới" cho admin — chỉ lần chạy đầu, chạy lại không báo trùng
        if ($moiNhat) {
            app(BaoHoSoUngTuyen::class)->guiNoiBo($moiNhat);
        }
    }

    /** PDF một trang ghi vài dòng chữ — đủ để mở thử trong trình duyệt. */
    private function pdf(string $duong, array $dong): string
    {
        $noiDung = "BT /F1 16 Tf 60 780 Td 22 TL\n";
        foreach ($dong as $d) {
            $noiDung .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $d).") Tj T*\n";
        }
        $noiDung .= 'ET';
        $doiTuong = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($noiDung)." >>\nstream\n{$noiDung}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $viTri = [];
        foreach ($doiTuong as $i => $o) {
            $viTri[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($doiTuong) + 1)."\n0000000000 65535 f \n";
        foreach ($viTri as $v) {
            $pdf .= sprintf("%010d 00000 n \n", $v);
        }
        $pdf .= 'trailer << /Size '.(count($doiTuong) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        Storage::disk(JobApplication::DIA)->put($duong, $pdf);

        return $duong;
    }
}
