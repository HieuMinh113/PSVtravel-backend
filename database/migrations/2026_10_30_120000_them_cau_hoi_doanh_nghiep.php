<?php

use App\Models\Faq;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Câu hỏi thường gặp của khách doanh nghiệp, hiện ở trang Team building.
    // Tạo sẵn ở trạng thái ĐÃ ẨN: câu trả lời về hoá đơn, cọc, quy mô đoàn phải do
    // người phụ trách điền đúng thực tế rồi mới bật "Đang hiển thị" trong admin
    // (Nội dung → Câu hỏi thường gặp). Câu đã có câu trả lời là điều website
    // đang cam kết sẵn ở trang Team building.
    private const CAU = [
        ['PSV Travel có xuất hoá đơn VAT cho doanh nghiệp không?', '[Cần điền: có / không, mức thuế VAT, thời gian xuất hoá đơn]'],
        ['Cần đặt trước bao lâu cho chuyến du lịch công ty, team building?', '[Cần điền: thời gian nên đặt trước, mùa cao điểm cần sớm hơn bao lâu]'],
        ['Đoàn ít nhất bao nhiêu người thì PSV nhận tổ chức?', '[Cần điền: số khách tối thiểu, đoàn lớn nhất từng tổ chức]'],
        ['Đặt cọc bao nhiêu và thanh toán phần còn lại khi nào?', '[Cần điền: % cọc khi ký hợp đồng, hạn thanh toán phần còn lại]'],
        ['Chương trình team building có được thiết kế riêng cho công ty không?', 'Có. PSV Travel thiết kế kịch bản riêng theo văn hoá và mục tiêu của từng doanh nghiệp — trò chơi, chủ đề, gala dinner — rồi gửi báo giá chi tiết trong 24 giờ làm việc sau khi nhận yêu cầu.'],
        ['PSV có lo trọn gói MC, âm thanh, sân khấu và hậu cần không?', 'Có. Một đầu mối lo tất cả: MC, trò chơi, âm thanh, sân khấu và hậu cần cho cả chuyến đi.'],
    ];

    public function up(): void
    {
        foreach (self::CAU as $i => [$hoi, $dap]) {
            Faq::withTrashed()->firstOrCreate(
                ['question' => $hoi],
                ['answer' => $dap, 'category' => 'doanh-nghiep', 'status' => 'hidden', 'sort_order' => $i + 1],
            );
        }
    }

    public function down(): void
    {
        Faq::withTrashed()->whereIn('question', array_column(self::CAU, 0))->forceDelete();
    }
};
