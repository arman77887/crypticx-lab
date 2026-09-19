<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $registeredUser
    ) {
    }

    public function build(): self
    {
        $frontendUrl = rtrim(
            (string) env(
                'FRONTEND_URL',
                config('app.url')
            ),
            '/'
        );

        $data = [
            'userName' => $this->registeredUser->name,
            'loginUrl' => $frontendUrl.'/login',
            'logoUrl' =>
                $frontendUrl.'/brand/crypticx2.png',
        ];

        return $this
            ->subject(
                'Welcome to CrypticX Lab — Registration Received'
            )
            ->view(
                'emails.registration-pending',
                $data
            )
            ->text(
                'emails.registration-pending-text',
                $data
            );
    }
}
