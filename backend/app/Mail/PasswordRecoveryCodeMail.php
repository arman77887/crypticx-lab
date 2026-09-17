<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordRecoveryCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('CrypticX Lab password reset code')
            ->view('emails.password-recovery-code');
    }
}
