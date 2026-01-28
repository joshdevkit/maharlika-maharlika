<?php

namespace Maharlika\Auth\Notifications;

use Maharlika\Notifications\Notification;
use Maharlika\Mail\Mailable;
use Maharlika\Facades\Url;

class ResetPassword extends Notification
{
    protected string $token;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): Mailable
    {
        $resetUrl = $this->resetUrl($notifiable);

        return (new ResetPasswordMailable($resetUrl, $notifiable))
            ->to($notifiable->getEmailForPasswordReset());
    }

    /**
     * Get the password reset URL.
     */
    protected function resetUrl($notifiable): string
    {
        return Url::temporarySignedRoute(
            "/password/reset/{$this->token}",
            3600, // 1 hour
            ['email' => $notifiable->getEmailForPasswordReset()]
        );
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'reset_url' => $this->resetUrl($notifiable),
        ];
    }
}
