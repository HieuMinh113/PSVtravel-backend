<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Gắn 1 tour cho bài cẩm nang để hiện ô đặt tour bên cạnh bài viết.
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->foreignId('tour_id')->nullable()->after('category')
                ->constrained('tours')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tour_id');
        });
    }
};
