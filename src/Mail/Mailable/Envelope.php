<?php

declare(strict_types=1);

namespace Maharlika\Mail\Mailable;


// ============================================
// Envelope Class
// ============================================
class Envelope
{
    public function __construct(
        public ?string $subject = null,
        public string|array|null $from = null,
        public string|array|null $to = null,
        public string|array|null $cc = null,
        public string|array|null $bcc = null,
        public string|array|null $replyTo = null,
    ) {}
}
