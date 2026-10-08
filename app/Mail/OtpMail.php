<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ma,
        public int $soPhut,
        public ?string $tenNguoiNhan = null,
        public string $mucDich = 'register',
    ) {}

    public function quenMatKhau(): bool
    {
        return $this->mucDich === 'reset_password';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->quenMatKhau() ? 'Mã đặt lại mật khẩu PSV Travel: ' : 'Mã xác thực tài khoản PSV Travel: ').$this->ma,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
