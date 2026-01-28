<?php

namespace Maharlika\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Fluent email builder
 */
class EmailBuilder
{
    protected Mailer $mailer;
    protected Email $email;

    public function __construct(Mailer $mailer, string|array $recipients)
    {
        $this->mailer = $mailer;
        $this->email = $mailer->message();

        if (is_array($recipients)) {
            $this->email->to(...$recipients);
        } else {
            $this->email->to($recipients);
        }
    }

    public function from(string $address, string $name = ''): self
    {
        $this->email->from(new Address($address, $name));
        return $this;
    }

    public function cc(string|array $address): self
    {
        if (is_array($address)) {
            $this->email->cc(...$address);
        } else {
            $this->email->cc($address);
        }
        return $this;
    }

    public function bcc(string|array $address): self
    {
        if (is_array($address)) {
            $this->email->bcc(...$address);
        } else {
            $this->email->bcc($address);
        }
        return $this;
    }

    public function replyTo(string $address): self
    {
        $this->email->replyTo($address);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->email->subject($subject);
        return $this;
    }

    public function text(string $text): self
    {
        $this->email->text($text);
        return $this;
    }

    public function html(string $html): self
    {
        $this->email->html($html);
        return $this;
    }

    public function attach(string $filePath, ?string $name = null, ?string $contentType = null): self
    {
        $this->email->attachFromPath($filePath, $name, $contentType);
        return $this;
    }

    public function attachData(string $data, string $name, ?string $contentType = null): self
    {
        $this->email->attach($data, $name, $contentType);
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->email->priority($priority);
        return $this;
    }

    public function send(): void
    {
        $this->mailer->send($this->email);
    }

    public function getEmail(): Email
    {
        return $this->email;
    }
}
