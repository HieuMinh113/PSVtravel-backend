<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Áp quy tắc "chấm ở ngoài công ty phải được duyệt, không duyệt thì cả ngày 0
 * công" cho các lượt chấm từ 01/11/2026 (trước khi có quy tắc, lượt đúng giờ ở
 * ngoài chỉ phải ghi lý do, không cần duyệt).
 *
 * Lượt chấm vào / ra bằng khuôn mặt ở ngoài bán kính, hoặc không lấy được vị
 * trí (không có toạ độ), được gắn cờ phải duyệt. Ngày chưa ai xét thì chuyển
 * sang "Chờ duyệt" để quản lý xem lại; ngày đã chấp nhận / không chấp nhận giữ
 * nguyên quyết định cũ. "Sai số lớn" (có toạ độ, GPS yếu) không bị gắn cờ.
 */
return new class extends Migration
{
    private const TU_NGAY = '2026-11-01';

    public function up(): void
    {
        foreach (['in', 'out'] as $p) {
            DB::table('attendances')
                ->where('work_date', '>=', self::TU_NGAY)
                ->whereNotNull("{$p}_at")
                ->where("{$p}_source", 'cham')
                ->where(fn ($q) => $q->where("{$p}_location", 'ngoai')
                    ->orWhere(fn ($q) => $q->where("{$p}_location", 'khong_ro')->whereNull("{$p}_lat")))
                ->update(["{$p}_requires_approval" => true]);
        }

        DB::table('attendances')
            ->where('work_date', '>=', self::TU_NGAY)
            ->whereNull('review_status')
            ->where(fn ($q) => $q->where('in_requires_approval', true)->orWhere('out_requires_approval', true))
            ->update(['review_status' => 'cho_duyet']);
    }

    public function down(): void
    {
        // Chỉ bỏ cờ; trạng thái "Chờ duyệt" đã gán không phân biệt được với chờ duyệt thật nên giữ
        DB::table('attendances')
            ->where('work_date', '>=', self::TU_NGAY)
            ->update(['in_requires_approval' => false, 'out_requires_approval' => false]);
    }
};
