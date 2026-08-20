<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;
use Modules\Page\Models\Setting;

/**
 * Ba loại thư liên quan tới đơn đặt tour, gom vào một lớp vì chúng dùng chung
 * dữ liệu và chỉ khác giao diện:
 *
 *   nhan_don   → gửi khách ngay khi đặt xong, mang MÃ ĐƠN
 *   xac_nhan   → gửi khách khi nhân viên bấm Xác nhận (đã giữ chỗ)
 *   bao_admin  → gửi nội bộ báo có đơn mới cần xử lý
 *
 * Đưa vào hàng đợi (ShouldQueue) để khách bấm "Đặt tour" không phải chờ
 * SMTP phản hồi, và nếu nhà cung cấp mail chết thì đơn vẫn được lưu.
 */
class BookingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const NHAN_DON = 'nhan_don';
    public const XAC_NHAN = 'xac_nhan';
    public const BAO_ADMIN = 'bao_admin';

    public function __construct(
        public Booking $booking,
        public string $loai = self::NHAN_DON,
    ) {}

    public function envelope(): Envelope
    {
        $ma = $this->booking->booking_code;

        return new Envelope(
            subject: match ($this->loai) {
                self::XAC_NHAN => "Đã xác nhận chỗ — đơn {$ma} | PSV Travel",
                self::BAO_ADMIN => "[Đơn mới] {$ma} — ".$this->booking->customer_name,
                default => "Đã nhận đơn đặt tour {$ma} | PSV Travel",
            },
        );
    }

    public function content(): Content
    {
        $web = rtrim((string) config('app.frontend_url'), '/');

        return new Content(
            view: match ($this->loai) {
                self::XAC_NHAN => 'emails.booking-confirmed',
                self::BAO_ADMIN => 'emails.booking-admin',
                default => 'emails.booking-received',
            },
            with: [
                'booking' => $this->booking,
                'hotline' => $this->cauHinh('hotline', '1900 1177'),
                'tenCongTy' => $this->cauHinh('legal_name', null)
                    ?? $this->cauHinh('company_name', 'PSV Travel'),
                'linkTraCuu' => $web.'/tra-cuu-booking',
                'linkAdmin' => rtrim((string) config('app.url'), '/').'/admin/bookings',
                'luuY' => [
                    'Mang theo CCCD/hộ chiếu còn hạn (hộ chiếu cần còn hạn trên 6 tháng nếu đi nước ngoài).',
                    'Có mặt tại điểm tập trung trước giờ khởi hành 30 phút.',
                    'Giữ lại mã đơn để đối chiếu khi làm thủ tục.',
                ],
            ],
        );
    }

    // Đọc cấu hình từ bảng settings, thiếu thì dùng giá trị mặc định
    private function cauHinh(string $key, ?string $macDinh): ?string
    {
        $giaTri = Setting::query()->where('key', $key)->value('value');

        return filled($giaTri) ? $giaTri : $macDinh;
    }
}
