<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Page\Models\Setting;
use Modules\Visa\Models\VisaCase;

/**
 * Thư xác nhận gửi khách ngay khi nộp hồ sơ visa trên website: mã hồ sơ và
 * danh sách giấy tờ cần chuẩn bị. Vào hàng đợi như thư đặt tour — khách bấm
 * "Nộp" không phải chờ SMTP.
 */
class HoSoVisaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public VisaCase $hoSo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Đã nhận hồ sơ visa {$this->hoSo->country} — {$this->hoSo->code} | PSV Travel",
        );
    }

    public function content(): Content
    {
        $web = rtrim((string) config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.visa-received',
            with: [
                'hoSo' => $this->hoSo,
                'giayTo' => collect($this->hoSo->checklist ?? [])
                    ->map(fn ($g) => trim(($g['ten'] ?? '').(filled($g['ghi_chu'] ?? null) ? ' ('.$g['ghi_chu'].')' : '')))
                    ->filter()->values()->all(),
                'hotline' => $this->cauHinh('hotline', '1900 1177'),
                'tenCongTy' => $this->cauHinh('legal_name', null) ?? $this->cauHinh('company_name', 'PSV Travel'),
                'linkTaiKhoan' => $this->hoSo->user_id ? $web.'/tai-khoan?tab=visa' : null,
            ],
        );
    }

    private function cauHinh(string $key, ?string $macDinh): ?string
    {
        $giaTri = Setting::query()->where('key', $key)->value('value');

        return filled($giaTri) ? $giaTri : $macDinh;
    }
}
