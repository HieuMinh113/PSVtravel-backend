<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Visa\Database\Seeders\MauChecklistVisaSeeder;
use Modules\Visa\Models\VisaChecklist;

/**
 * 35 mẫu checklist của 17 nước (Nhật, Hàn, Đài Loan, Hong Kong, Macao, Úc,
 * New Zealand, Canada, Anh, Pháp, Đức, Thụy Sĩ, Hà Lan, Ba Lan, Ấn Độ, Dubai,
 * Mỹ) từ bộ "FILE THỦ TỤC HỒ SƠ NOLOGO", kèm file thủ tục làm file mẫu.
 * Mẫu đã có (trùng tên) thì không đụng tới — chạy lại an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        MauChecklistVisaSeeder::themMauThuTucNlg();
    }

    public function down(): void
    {
        $ten = collect(require module_path('Visa', 'database/data/mau-thu-tuc-nlg.php'))->pluck('name');
        VisaChecklist::whereIn('name', $ten)->forceDelete();
    }
};
