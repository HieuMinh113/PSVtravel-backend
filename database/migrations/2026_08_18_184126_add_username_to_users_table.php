<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tên đăng nhập — cho phép null vì tài khoản cũ (admin) chưa có
            $table->string('username', 32)->nullable()->unique()->after('name');

            // SĐT phải là duy nhất thì mới dùng để đăng nhập được
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};