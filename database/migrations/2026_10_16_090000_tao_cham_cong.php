<?php

use Database\Seeders\QuyenChamCongSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công bằng khuôn mặt + vị trí:
 *  - attendance_faces: khuôn mặt nhân viên đăng ký (128 số đặc trưng do trình
 *    duyệt tính + ảnh), thời điểm đồng ý cho dùng ảnh khuôn mặt (NĐ 13/2023)
 *  - attendances: mỗi người mỗi ngày một dòng — giờ vào / ra, vị trí, ảnh,
 *    độ khớp khuôn mặt, phút trễ / về sớm, lý do, kế toán / quản lý duyệt
 *  - cấu hình (bảng settings, nhóm cham_cong): toạ độ công ty, bán kính, giờ làm
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('descriptor');
            $table->string('photo')->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            foreach (['in', 'out'] as $p) {
                $table->timestamp("{$p}_at")->nullable();
                $table->string("{$p}_source", 10)->nullable();        // cham | bo_sung
                $table->decimal("{$p}_lat", 10, 7)->nullable();
                $table->decimal("{$p}_lng", 10, 7)->nullable();
                $table->unsignedInteger("{$p}_accuracy")->nullable(); // sai số GPS (m)
                $table->unsignedInteger("{$p}_distance")->nullable(); // cách công ty (m)
                $table->string("{$p}_location", 10)->nullable();      // trong | ngoai | khong_ro
                $table->string("{$p}_photo")->nullable();
                $table->decimal("{$p}_face_distance", 5, 3)->nullable(); // càng nhỏ càng giống
                $table->boolean("{$p}_face_ok")->nullable();          // null = không thấy mặt
                $table->text("{$p}_reason")->nullable();
            }
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->string('review_status', 12)->nullable();          // cho_duyet | chap_nhan | tu_choi
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date', 'review_status']);
        });

        $cauHinh = [
            ['cham_cong_lat', 'Toạ độ công ty — vĩ độ (lat)', null],
            ['cham_cong_lng', 'Toạ độ công ty — kinh độ (lng)', null],
            ['cham_cong_ban_kinh', 'Bán kính được tính là ở công ty (mét)', '150'],
            ['cham_cong_vao', 'Giờ vào thứ 2 – thứ 6', '08:00'],
            ['cham_cong_ra', 'Giờ ra thứ 2 – thứ 6', '17:30'],
            ['cham_cong_vao_t7', 'Giờ vào thứ 7', '08:00'],
            ['cham_cong_ra_t7', 'Giờ ra thứ 7 (làm nửa ngày)', '12:00'],
            ['cham_cong_phut_tre', 'Số phút được phép trễ / về sớm', '5'],
        ];
        foreach ($cauHinh as $i => [$key, $label, $value]) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key, 'label' => $label, 'value' => $value, 'group' => 'cham_cong',
                    'type' => 'text', 'sort_order' => 900 + $i, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        (new QuyenChamCongSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_faces');
        DB::table('settings')->where('group', 'cham_cong')->delete();
    }
};
