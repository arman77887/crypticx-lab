<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdminMail extends Mailable
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
            'userEmail' => $this->registeredUser->email,
            'registeredAt' =>
                $this->registeredUser->created_at?->format(
                    'Y-m-d H:i:s T'
                ),
            'adminUrl' =>
                $frontendUrl.'/admin/users',
            'logoUrl' =>
                $frontendUrl.'/brand/crypticx2.png',
        ];

        return $this
            ->subject(
                'New Account Awaiting Approval — CrypticX Lab'
            )
            ->view(
                'emails.new-registration-admin',
                $data
            )
            ->text(
                'emails.new-registration-admin-text',
                $data
            );
    }
}
