<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xác thực 2 lớp (2FA) cho trang quản trị — mã 6 số từ app Google Authenticator.
 *
 * Cột riêng của Filament, KHÔNG dùng chung cột two_factor_* của Fortify: hai hệ
 * lưu khoá theo định dạng khác nhau, dùng chung dễ làm hỏng khoá của nhau.
 * Cả hai cột đều được MÃ HOÁ khi lưu (cast encrypted) — lộ database cũng không
 * lấy được khoá bí mật hay mã khôi phục.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
