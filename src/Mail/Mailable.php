<?php

namespace Maharlika\Mail;

use Maharlika\Mail\Mailable\Content;
use Maharlika\Mail\Mailable\Envelope;
use Maharlika\Support\Traits\Queueable;
use Symfony\Component\Mime\Email;

abstract class Mailable
{
    use Queueable;

    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected array $replyTo = [];
    protected array $from = [];

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope();
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content();
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Set the recipients of the message.
     */
    public function to(string|array $address): self
    {
        $this->to = is_array($address) ? $address : [$address];
        return $this;
    }

    /**
     * Set the "cc" address of the message.
     */
    public function cc(string|array $address): self
    {
        $this->cc = is_array($address) ? $address : [$address];
        return $this;
    }

    /**
     * Set the "bcc" address of the message.
     */
    public function bcc(string|array $address): self
    {
        $this->bcc = is_array($address) ? $address : [$address];
        return $this;
    }

    /**
     * Build the message into a Symfony Email instance.
     */
    public function build(): Email
    {
        $email = new Email();

        // Apply envelope
        $envelope = $this->envelope();

        if ($envelope->subject) {
            $email->subject($envelope->subject);
        }

        if ($envelope->from) {
            $from = is_array($envelope->from) ? $envelope->from : [$envelope->from];
            $email->from(...$from);
        }

        // Apply recipients - prioritize $this->to over envelope
        $to = !empty($this->to) ? $this->to : (!empty($envelope->to) ? (is_array($envelope->to) ? $envelope->to : [$envelope->to]) : []);
        if (!empty($to)) {
            $email->to(...$to);
        }

        // Also check cc and bcc from instance properties
        $cc = !empty($this->cc) ? $this->cc : (!empty($envelope->cc) ? (is_array($envelope->cc) ? $envelope->cc : [$envelope->cc]) : []);
        $bcc = !empty($this->bcc) ? $this->bcc : (!empty($envelope->bcc) ? (is_array($envelope->bcc) ? $envelope->bcc : [$envelope->bcc]) : []);

        if (!empty($cc)) {
            $email->cc(...$cc);
        }

        if (!empty($bcc)) {
            $email->bcc(...$bcc);
        }

        if ($envelope->replyTo) {
            $replyTo = is_array($envelope->replyTo) ? $envelope->replyTo : [$envelope->replyTo];
            $email->replyTo(...$replyTo);
        }

        // Apply content
        $content = $this->content();

        if ($content->view) {
            $html = $this->renderView($content->view, $content->with);
            $email->html($html);
        } elseif ($content->html) {
            $email->html($content->html);
        }

        if ($content->text) {
            $email->text($content->text);
        }

        // Apply attachments
        foreach ($this->attachments() as $attachment) {
            if ($attachment->isFromPath()) {
                $email->attachFromPath(
                    $attachment->getFile(),
                    $attachment->getName(),
                    $attachment->getMime()
                );
            } else {
                $email->attach(
                    $attachment->getData(),
                    $attachment->getName(),
                    $attachment->getMime()
                );
            }
        }

        return $email;
    }

    /**
     * access recepient variable
     */
    public function ToMail()
    {
        $this->to;
    }

    /**
     * Render the view template.
     */
    protected function renderView(string $view, array $data = []): string
    {
        // Merge public properties into data
        $publicProperties = $this->getPublicProperties();
        $data = array_merge($publicProperties, $data);

        return view()->render($view, $data);
    }

    /**
     * Get all public properties of the mailable.
     */
    protected function getPublicProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property->getValue($this);
            }
        }

        return $properties;
    }
}