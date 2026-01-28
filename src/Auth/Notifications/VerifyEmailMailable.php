<?php

namespace Maharlika\Auth\Notifications;

use Maharlika\Mail\Mailable;
use Maharlika\Mail\Mailable\Content;
use Maharlika\Mail\Mailable\Envelope;

class VerifyEmailMailable extends Mailable
{
    public string $verificationUrl;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(string $verificationUrl, $user)
    {
        $this->verificationUrl = $verificationUrl;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail::templates.verify-email',
            with: [
                'verificationUrl' => $this->verificationUrl,
                'user'            => $this->user,
                'app_name'        => config('app.name', 'MyApp'),
                'app_url'         => config('app.url'),
                'userName'        => $this->user->name ?? null,
                'expirationMinutes' => config('auth.verification.expire', 60),
            ]
        );
    }


    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
