<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Trang tác giả bài cẩm nang (/tac-gia/{slug}): người viết là một thành viên
    // Đội ngũ, có ảnh, chức danh, số năm kinh nghiệm, những nước đã đi — giúp
    // Google / ChatGPT tin nội dung hơn (E-E-A-T). Chỉ ghi thông tin người đó
    // đồng ý công khai; để trống thì trang ẩn mục đó.
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('name');
            // Tài khoản admin của người này — bài họ viết tự chọn họ làm tác giả
            $table->foreignId('user_id')->nullable()->after('slug')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->string('expertise')->nullable();
            $table->json('countries')->nullable();
            $table->text('profile')->nullable();
            $table->json('social_links')->nullable();
        });

        Schema::table('guides', function (Blueprint $table) {
            $table->foreignId('team_member_id')->nullable()->after('author_id')->constrained('team_members')->nullOnDelete();
        });

        // Đường dẫn cho thành viên đang có
        $daDung = [];
        foreach (DB::table('team_members')->orderBy('id')->get(['id', 'name']) as $m) {
            $goc = Str::slug((string) $m->name) ?: 'tac-gia';
            $slug = $goc;
            for ($i = 2; in_array($slug, $daDung, true); $i++) {
                $slug = "{$goc}-{$i}";
            }
            $daDung[] = $slug;
            DB::table('team_members')->where('id', $m->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_member_id');
        });
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'years_experience', 'expertise', 'countries', 'profile', 'social_links']);
        });
    }
};
