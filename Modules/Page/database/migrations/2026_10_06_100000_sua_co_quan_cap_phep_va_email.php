<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sửa hai thông tin công ty đang sai trong Cài đặt (theo xác nhận của chủ
 * doanh nghiệp, 06/10/2026):
 *
 *  - Cơ quan cấp giấy phép lữ hành: chân trang hiện "BỘ CÔNG AN" — sai. Giấy
 *    phép số 79-769/2020/CDLQGVN-GP LHQT do Cục Du lịch Quốc gia Việt Nam cấp
 *    (CDLQGVN chính là viết tắt tên cơ quan này).
 *  - Email liên hệ chính thức: nguyendusit399@gmail.com (thống nhất giữa
 *    website và dữ liệu doanh nghiệp gửi cho Google).
 *
 * Chỉ sửa ĐÚNG hai ô này, ô nào chưa có thì tạo. Sau này muốn đổi thì sửa
 * trong trang quản trị (Cài đặt) như bình thường — migration chỉ chạy một lần.
 */
return new class extends Migration
{
    // khoá => [giá trị, nhãn, nhóm] — nhãn/nhóm chỉ dùng khi phải tạo ô mới
    private const GIA_TRI = [
        'license_issuer' => ['Cục Du lịch Quốc gia Việt Nam', 'Cơ quan cấp giấy phép lữ hành', 'legal'],
        'email' => ['nguyendusit399@gmail.com', 'Email liên hệ', 'contact'],
    ];

    public function up(): void
    {
        foreach (self::GIA_TRI as $khoa => [$giaTri, $nhan, $nhom]) {
            $daCo = DB::table('settings')->where('key', $khoa)->exists();
            if ($daCo) {
                DB::table('settings')->where('key', $khoa)->update(['value' => $giaTri, 'updated_at' => now()]);
            } else {
                DB::table('settings')->insert([
                    'key' => $khoa, 'value' => $giaTri, 'label' => $nhan, 'group' => $nhom,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Cập nhật thẳng bảng nên sự kiện của model không chạy — tự xoá bộ nhớ
        // đệm để website hiện ngay giá trị mới.
        Cache::forget('psv_settings');
    }

    public function down(): void
    {
        // Không khôi phục giá trị sai cũ.
    }
};
