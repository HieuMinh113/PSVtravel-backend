<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;

/**
 * Khách tự nộp hồ sơ visa trên website + nhân viên chỉ thấy hồ sơ của mình.
 *
 *  - source: hồ sơ do nhân viên tạo (quan_tri) hay khách nộp trên web (website)
 *  - user_id: tài khoản khách (nếu khách đang đăng nhập lúc nộp) — để khách
 *    xem trạng thái và giấy tờ còn thiếu ở trang Tài khoản
 *  - customer_note: lời nhắn của khách lúc nộp (tách khỏi ghi chú nội bộ)
 *  - upload_token_hash / upload_token_expires_at / web_files_count: khách gửi
 *    từng file một sau khi tạo hồ sơ (mỗi lần ≤ 10MB, vừa giới hạn 20MB của
 *    nginx), bằng mã tạm chỉ dùng được cho đúng hồ sơ đó, tối đa 10 file
 *  - quyền ViewAll:VisaCase (xem mọi hồ sơ + giao hồ sơ) cho super_admin, admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_cases', function (Blueprint $table) {
            $table->string('source')->default('quan_tri')->after('code');
            $table->foreignId('user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->text('customer_note')->nullable()->after('note');
            $table->string('upload_token_hash', 64)->nullable();
            $table->timestamp('upload_token_expires_at')->nullable();
            $table->unsignedTinyInteger('web_files_count')->default(0);
        });

        (new QuyenVisaSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('visa_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['source', 'customer_note', 'upload_token_hash', 'upload_token_expires_at', 'web_files_count']);
        });
    }
};
