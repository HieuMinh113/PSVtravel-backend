<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công — ghi thêm để quản lý biết vì sao "Không rõ vị trí" / quét lâu:
 *  - attendances: loại máy (điện thoại / máy tính), lý do không có vị trí
 *    (chưa cho phép, chờ GPS quá lâu, sai số lớn…), thời gian quét (ms)
 *  - attendance_faces: loại máy lúc đăng ký + mẫu mặt thêm cho loại máy khác
 *    (đăng ký bằng điện thoại, quét webcam máy tính khớp lần đầu → lưu thêm
 *    một mẫu webcam, lần sau so nhanh hơn)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (['in', 'out'] as $p) {
                $table->string("{$p}_device", 10)->nullable();          // dien_thoai | may_tinh
                $table->string("{$p}_location_error", 20)->nullable();  // chua_cho_phep | het_gio | khong_lay_duoc | khong_ho_tro | sai_so_lon
                $table->unsignedInteger("{$p}_scan_ms")->nullable();    // từ lúc bấm đến lúc nhận ra mặt
            }
        });

        Schema::table('attendance_faces', function (Blueprint $table) {
            $table->string('device', 10)->nullable();  // loại máy lúc đăng ký (null = đăng ký trước khi có cột này)
            $table->json('extra_descriptors')->nullable(); // {"may_tinh": {"descriptor": [...128], "at": "..."}}
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (['in', 'out'] as $p) {
                $table->dropColumn(["{$p}_device", "{$p}_location_error", "{$p}_scan_ms"]);
            }
        });
        Schema::table('attendance_faces', function (Blueprint $table) {
            $table->dropColumn(['device', 'extra_descriptors']);
        });
    }
};
