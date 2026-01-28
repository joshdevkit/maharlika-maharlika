<?php

declare(strict_types=1);

namespace Maharlika\Mail;

use Maharlika\Mail\Transport\FileLoggerTransport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class Mailer
{
    protected SymfonyMailer $mailer;
    protected array $config;
    protected ?string $defaultFrom = null;
    protected ?string $defaultFromName = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->defaultFrom = $config['from']['address'] ?? null;
        $this->defaultFromName = $config['from']['name'] ?? null;

        // Create transport based on config
        $transport = $this->createTransport();
        $this->mailer = new SymfonyMailer($transport);
    }

    /**
     * Create transport from config
     */
    protected function createTransport(): Transport\TransportInterface
    {
        $driver = $this->config['driver'] ?? 'smtp';

        return match ($driver) {
            'smtp' => $this->createSmtpTransport(),
            'sendmail' => Transport::fromDsn('sendmail://default'),
            'mailgun' => $this->createMailgunTransport(),
            'ses' => $this->createSesTransport(),
            'postmark' => $this->createPostmarkTransport(),
            'log' => $this->createLogTransport(),
            'array' => $this->createArrayTransport(),
            default => throw new \InvalidArgumentException("Unsupported mail driver: {$driver}"),
        };
    }

    /**
     * Create SMTP transport
     */
    protected function createSmtpTransport(): Transport\TransportInterface
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 587;
        $encryption = $this->config['encryption'] ?? 'tls';
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';

        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            urlencode($username),
            urlencode($password),
            $host,
            $port
        );

        if ($encryption) {
            $dsn .= "?encryption={$encryption}";
        }

        return Transport::fromDsn($dsn);
    }

    /**
     * Create Mailgun transport
     */
    protected function createMailgunTransport(): Transport\TransportInterface
    {
        $key = $this->config['mailgun']['key'] ?? '';
        $domain = $this->config['mailgun']['domain'] ?? '';
        $endpoint = $this->config['mailgun']['endpoint'] ?? 'api.mailgun.net';

        return Transport::fromDsn("mailgun+https://{$key}@{$endpoint}?domain={$domain}");
    }

    /**
     * Create AWS SES transport
     */
    protected function createSesTransport(): Transport\TransportInterface
    {
        $key = $this->config['ses']['key'] ?? '';
        $secret = $this->config['ses']['secret'] ?? '';
        $region = $this->config['ses']['region'] ?? 'us-east-1';

        return Transport::fromDsn("ses+https://{$key}:{$secret}@default?region={$region}");
    }

    /**
     * Create Postmark transport
     */
    protected function createPostmarkTransport(): Transport\TransportInterface
    {
        $token = $this->config['postmark']['token'] ?? '';
        return Transport::fromDsn("postmark+https://{$token}@default");
    }

    /**
     * Create log transport (for development)
     */
    protected function createLogTransport(): Transport\TransportInterface
    {
        $logFile = $this->config['log'] ?? storage_path('logs/mail.log');
        return new FileLoggerTransport($logFile);
    }


    /**
     * Create array transport (for testing)
     */
    protected function createArrayTransport(): Transport\TransportInterface
    {
        return Transport::fromDsn("null://null");
    }

    /**
     * Send an email
     */
    public function send(Email|Mailable $message): void
    {
        if ($message instanceof Mailable) {
            $message = $message->build();
        }

        // Set default from if not set
        if (empty($message->getFrom()) && $this->defaultFrom) {
            $message->from(new Address($this->defaultFrom, $this->defaultFromName ?? ''));
        }

        // Validate that at least one recipient exists
        if (empty($message->getTo()) && empty($message->getCc()) && empty($message->getBcc())) {
            throw new \RuntimeException('An email must have a "To", "Cc", or "Bcc" header.');
        }

        $this->mailer->send($message);
    }

    /**
     * Create a new email message
     */
    public function message(): Email
    {
        $email = new Email();

        if ($this->defaultFrom) {
            $email->from(new Address($this->defaultFrom, $this->defaultFromName ?? ''));
        }

        return $email;
    }

    /**
     * Quick send method
     */
    public function to(string|array $recipients): EmailBuilder
    {
        return new EmailBuilder($this, $recipients);
    }

    /**
     * Get the Symfony mailer instance
     */
    public function getSymfonyMailer(): SymfonyMailer
    {
        return $this->mailer;
    }
}
