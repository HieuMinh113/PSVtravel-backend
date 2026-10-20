<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tour\Services\HanNopVisa;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Danh sách hạn nộp hồ sơ visa theo nước ("Trung Quốc — 7 ngày") do vai trò visa
 * quản lý. Đợt khởi hành tour nước ngoài quá hạn thì tự ẩn trên website.
 */
return new class extends Migration
{
    private const QUYEN = ['ViewAny:VisaDeadline', 'View:VisaDeadline', 'Create:VisaDeadline', 'Update:VisaDeadline', 'Delete:VisaDeadline', 'DeleteAny:VisaDeadline'];

    public function up(): void
    {
        Schema::create('visa_deadlines', function (Blueprint $table) {
            $table->id();
            $table->string('country', 100)->unique();
            $table->unsignedSmallInteger('days');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        // Quyền cho vai trò visa, admin, super_admin (QuyenVisaSeeder đã có các quyền này)
        (new QuyenVisaSeeder)->run();

        // Tính hạn chót cho các đợt đang có (quy tắc visa của tour / hạn riêng của đợt)
        HanNopVisa::tinhLaiTatCa();
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_deadlines');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::QUYEN)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
