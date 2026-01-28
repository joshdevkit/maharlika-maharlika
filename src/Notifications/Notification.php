<?php

namespace Maharlika\Notifications;

use Maharlika\Mail\Mailable;
use Maharlika\Support\Traits\Queueable;

abstract class Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return Mailable
     */
    public function toMail($notifiable): Mailable
    {
        throw new \BadMethodCallException('Notification is missing toMail method.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}