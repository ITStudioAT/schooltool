<?php

namespace App\Mail;

use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingEntryDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TeachingCourseStudentEntryNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $confirmationUrl;

    public string $trackingUrl;

    public function __construct(
        public TeachingCourseStudentEntryNotification $notification,
        public TeachingCourseStudentEntry $entry,
        public TeachingEntryDefinition $definition,
    ) {
        $this->confirmationUrl = URL::signedRoute('teaching-entry-notifications.confirm.show', [
            'notification' => $notification,
        ]);
        $this->trackingUrl = URL::signedRoute('teaching-entry-notifications.open', [
            'notification' => $notification,
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verständigung: '.$this->definition->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.teachingCourseStudentEntryNotification',
            with: [
                'confirmationUrl' => $this->confirmationUrl,
                'trackingUrl' => $this->trackingUrl,
                'course' => $this->entry->teachingCourse,
                'student' => $this->entry->user,
            ],
        );
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
}
