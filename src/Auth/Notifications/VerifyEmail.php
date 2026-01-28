<?php

namespace Maharlika\Auth\Notifications;

use Maharlika\Contracts\Auth\MustVerifyEmail;
use Maharlika\Notifications\Notification;
use Maharlika\Mail\Mailable;
use Maharlika\Facades\Url;
use Maharlika\Facades\Hash;

class VerifyEmail extends Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        if (!$notifiable instanceof MustVerifyEmail) {
            throw new \RuntimeException(
                sprintf(
                    'The model [%s] must implement [%s] to send email verification notifications.',
                    get_class($notifiable),
                    MustVerifyEmail::class
                )
            );
        }
        
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return Mailable
     */
    public function toMail($notifiable): Mailable
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new VerifyEmailMailable($verificationUrl, $notifiable))
            ->to($notifiable->getEmailForVerification());
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param mixed $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable): string
    {
        $id = $notifiable->getAuthIdentifier();
        $hash = Hash::sha256($notifiable->getEmailForVerification());
        
        return Url::temporarySignedRoute(
            "/email/verify/{$id}/{$hash}",
            3600,
            []
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'verification_url' => $this->verificationUrl($notifiable),
        ];
    }
}