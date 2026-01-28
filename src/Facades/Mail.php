<?php

namespace Maharlika\Facades;

use Maharlika\Mail\EmailBuilder;
use Symfony\Component\Mime\Email;
use Maharlika\Mail\Mailable;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;

/**
 * Mail Facade
 *
 * @method static void send(Email|Mailable $message) Send an email message
 * @method static Email message() Create a new email message
 * @method static EmailBuilder to(string|array $recipients) Quick send method with fluent interface
 * @method static SymfonyMailer getSymfonyMailer() Get the underlying Symfony mailer instance *
 * @method static EmailBuilder from(string $address, string $name = '') Set sender
 * @method static EmailBuilder cc(string|array $address) Add CC recipients
 * @method static EmailBuilder bcc(string|array $address) Add BCC recipients
 * @method static EmailBuilder replyTo(string $address) Set reply-to address
 * @method static EmailBuilder subject(string $subject) Set email subject
 * @method static EmailBuilder text(string $text) Set plain text body
 * @method static EmailBuilder html(string $html) Set HTML body
 * @method static EmailBuilder attach(string $filePath, string|null $name = null, string|null $contentType = null) Attach a file
 * @method static EmailBuilder attachData(string $data, string $name, string|null $contentType = null) Attach data as file
 * @method static EmailBuilder priority(int $priority) Set email priority (1-5)
 * @method static bool hasAttachments() Check if email has attachments
 * @method static int attachmentCount() Get number of attachments
 * @method static array getAttachments() Get all attachments
 * @method static Email getEmail() Get the underlying Email object
 * @method static void send() Send the email
 *
 * @see \Maharlika\Mail\Mailer
 * 
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mailer';
    }
}
