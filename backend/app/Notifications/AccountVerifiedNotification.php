<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountVerifiedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            (string) env(
                'FRONTEND_URL',
                config('app.url')
            ),
            '/'
        );

        return (new MailMessage())
            ->subject(
                'Access Granted — Your CrypticX Lab Account Is Approved'
            )
            ->view(
                'emails.account-verified',
                [
                    'userName' =>
                        $notifiable->name ?? 'Security Researcher',

                    'loginUrl' =>
                        $frontendUrl.'/login',

                    'logoUrl' =>
                        $frontendUrl.'/brand/crypticx2.png',
                ]
            );
    }
}
