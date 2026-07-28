<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StandardEmail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $data,
        public array|string|null $attachments = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
        ];
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

        // ✅ Optional attachments support
        if ($this->attachments) {
            $attachments = is_array($this->attachments) && array_key_exists('data', $this->attachments)
                ? [$this->attachments]
                : (array) $this->attachments;

            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['data'], $attachment['name'])) {
                    $mail->attachData(
                        (string) $attachment['data'],
                        (string) $attachment['name'],
                        (array) ($attachment['options'] ?? []),
                    );

                    continue;
                }

                $file = (string) $attachment;

                if (file_exists($file)) {
                    $mail->attach($file, [
                        'as' => basename($file),
                        'mime' => mime_content_type($file) ?: null,
                    ]);
                }
            }
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
