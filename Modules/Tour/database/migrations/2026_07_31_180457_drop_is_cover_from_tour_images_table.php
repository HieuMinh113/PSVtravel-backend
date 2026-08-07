<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_images', function (Blueprint $table) {
            $table->dropColumn('is_cover');
        });
    }

    public function down(): void
    {
        Schema::table('tour_images', function (Blueprint $table) {
            $table->boolean('is_cover')->default(false);
        });
    }
};
