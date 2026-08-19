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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác thực tài khoản PSV Travel: '.$this->ma,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
