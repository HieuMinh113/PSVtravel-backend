<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giá em bé (dưới 2 tuổi) — chương trình tour của đối tác thường có cột này
 * cạnh giá người lớn / trẻ em. Chỉ để hiện cho khách xem; đặt tour online
 * vẫn tính người lớn + trẻ em như cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->unsignedBigInteger('infant_price')->nullable()->after('child_price');
        });
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $table) => $table->dropColumn('infant_price'));
    }
};
