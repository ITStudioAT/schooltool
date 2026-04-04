<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StandardEmailWithAttachment extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $data,
        public array|string|null $attachments = null // can be a single path or an array
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from($this->data['from_address'], $this->data['from_name'])
            ->subject($this->data['subject'])
            ->markdown($this->data['markdown'], [
                'notifiable' => $notifiable,
                'data' => $this->data,
                'logo' => $this->data['logo'] ?? null,
            ]);

        // ✅ Add attachments if provided
        if ($this->attachments) {
            $attachments = is_array($this->attachments)
                ? $this->attachments
                : [$this->attachments];

            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    $mail->attach($file);
                }
            }
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
