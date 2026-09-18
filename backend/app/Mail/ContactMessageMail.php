<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $contactData
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(
                '[CrypticX Contact] '.$this->contactData['subject']
            )
            ->replyTo(
                $this->contactData['email'],
                $this->contactData['name']
            )
            ->view('emails.contact-message');
    }
}
