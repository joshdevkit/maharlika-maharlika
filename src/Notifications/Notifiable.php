<?php

namespace Maharlika\Notifications;

use Maharlika\Database\Relations\MorphMany;
use Maharlika\Facades\Mail;
use Maharlika\Support\Str;

trait Notifiable
{
    /**
     * Send the given notification.
     *
     * @param Notification $notification
     * @return void
     */
    public function notify(Notification $notification): void
    {
        $channels = $notification->via($this);

        foreach ($channels as $channel) {
            $this->sendNotificationViaChannel($channel, $notification);
        }
    }

    /**
     * Send notification via a specific channel.
     *
     * @param string $channel
     * @param Notification $notification
     * @return void
     */
    protected function sendNotificationViaChannel(string $channel, Notification $notification): void
    {
        switch ($channel) {
            case 'mail':
                $this->sendMailNotification($notification);
                break;
            case 'database':
                $this->sendDatabaseNotification($notification);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send a mail notification.
     *
     * @param Notification $notification
     * @return void
     */
    protected function sendMailNotification(Notification $notification): void
    {
        $mailable = $notification->toMail($this);
        // Set recipient if not already set
        $mailable->to($this->getEmailForNotifications());

        Mail::send($mailable);
    }

    /**
     * Send a database notification.
     *
     * @param Notification $notification
     * @return void
     */
    protected function sendDatabaseNotification(Notification $notification): void
    {
        $data = $notification->toArray($this);
        $attributes = [
            'id' => Str::uuid(),
            'type' => get_class($notification),
            'data' => $data,
            'read_at' => null,
        ];

        $this->notifications()->create($attributes);
    }

    /**
     * Get the entity's notifications.
     *
     * @return \Maharlika\Database\Relations\MorphMany
     */
    public function notifications() : MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->latest();
    }

    /**
     * Get the entity's unread notifications.
     *
     * @return \Maharlika\Database\Collection
     */
    public function unreadNotifications()
    {
        return $this->notifications()
            ->whereNull('read_at')
            ->get();
    }

    /**
     * Get the entity's read notifications.
     *
     * @return \Maharlika\Database\Collection
     */
    public function readNotifications()
    {
        return $this->notifications()
            ->whereNotNull('read_at')
            ->get();
    }

    /**
     * Get the email address for notifications.
     *
     * @return string
     */
    public function getEmailForNotifications(): string
    {
        return $this->email;
    }
}
