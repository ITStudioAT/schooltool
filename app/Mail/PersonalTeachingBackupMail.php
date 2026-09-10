<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PersonalTeachingBackupMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private string $backupContents,
        public string $backupFilename,
        public string $createdAtLabel,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre persönliche Unterrichts-Datensicherung',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.personalTeachingBackup',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->backupContents, $this->backupFilename)
                ->withMime('application/octet-stream'),
        ];
    }
}
