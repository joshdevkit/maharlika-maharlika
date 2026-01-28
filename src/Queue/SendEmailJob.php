<?php

declare(strict_types=1);

namespace Maharlika\Queue;

class SendEmailJob extends Job
{
    protected string $serializedMailable;

    public function __construct($mailable)
    {
        // The mailable should already have recipients set via to()
        // Serialize the entire mailable (triggers __serialize)
        $this->serializedMailable = base64_encode(serialize($mailable));

        // Set defaults
        $this->queue = $mailable->queue ?? config('queue.default_queue', 'default');
        $this->connection = $mailable->connection ?? config('queue.default', 'database');
        $this->delay = $mailable->delay;
    }

    public function handle(): void
    {
        // Unserialize the mailable (triggers __unserialize which restores models)
        $mailable = unserialize(base64_decode($this->serializedMailable));

        mailer()->send($mailable);
    }
}
