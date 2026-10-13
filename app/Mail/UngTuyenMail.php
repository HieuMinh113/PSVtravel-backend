<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Page\Models\Setting;

/**
 * Thư xác nhận gửi ứng viên ngay khi nộp hồ sơ trên website. Vào hàng đợi như
 * các thư khác — bấm "Gửi" không phải chờ SMTP.
 */
class UngTuyenMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public JobApplication $hoSo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Đã nhận hồ sơ ứng tuyển {$this->hoSo->job_title} | PSV Travel",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ung-tuyen-received',
            with: [
                'hoSo' => $this->hoSo,
                'hotline' => $this->cauHinh('hotline', '1900 1177'),
                'tenCongTy' => $this->cauHinh('legal_name', null) ?? $this->cauHinh('company_name', 'PSV Travel'),
            ],
        );
    }

    private function cauHinh(string $key, ?string $macDinh): ?string
    {
        $giaTri = Setting::query()->where('key', $key)->value('value');

        return filled($giaTri) ? $giaTri : $macDinh;
    }
}
