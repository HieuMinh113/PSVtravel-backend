<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Thêm bộ ảnh phụ (ảnh chính vẫn là cột image, đây là các ảnh kèm theo).
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
    }
};
