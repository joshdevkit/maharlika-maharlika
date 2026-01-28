<?php

declare(strict_types=1);

namespace Maharlika\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class FileLoggerTransport implements TransportInterface
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function send(RawMessage $message, ?object $envelope = null): ?SentMessage
    {
        $timestamp = date('Y-m-d H:i:s');
        $separator = str_repeat('=', 80);
        
        $logEntry = "\n{$separator}\n";
        $logEntry .= "[{$timestamp}] Email Log\n";
        $logEntry .= "{$separator}\n";
        $logEntry .= $message->toString();
        $logEntry .= "\n{$separator}\n\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return new SentMessage($message, $envelope ?? new \Symfony\Component\Mailer\DelayedEnvelope($message));
    }

    public function __toString(): string
    {
        return sprintf('file://%s', $this->logFile);
    }
}