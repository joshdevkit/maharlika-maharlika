<?php

namespace Maharlika\Auth\Notifications;

use Maharlika\Mail\Mailable;
use Maharlika\Mail\Mailable\Envelope;
use Maharlika\Mail\Mailable\Content;

class ResetPasswordMailable extends Mailable
{
    public string $resetUrl;
    public $user;

    public function __construct(string $resetUrl, $user)
    {
        $this->resetUrl = $resetUrl;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail::templates.forgot-password',
            with: [
                'resetUrl' => $this->resetUrl,
                'user' => $this->user,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments(): array
    {
        return [];
    }
}
