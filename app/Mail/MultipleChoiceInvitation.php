<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MultipleChoiceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $testTitle,
        public string $studentName,
        public string $accessUrl,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SchoolTool: Einladung zu '.$this->testTitle,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $name = e($this->studentName);
        $title = e($this->testTitle);
        $url = e($this->accessUrl);

        return new Content(htmlString: <<<HTML
            <!doctype html><html lang="de"><body>
            <p>Guten Tag {$name},</p>
            <p>für Sie wurde der Multiple-Choice-Test <strong>{$title}</strong> freigegeben.</p>
            <p><a href="{$url}">Persönlichen Test öffnen</a></p>
            <p>Sie können den Test auch nach Ihrer gewohnten Schüleranmeldung unter Multiple Choice öffnen. Beide Zugänge führen zu demselben Versuch.</p>
            <p>Vor dem gemeinsamen Start warten Sie im Warteraum. Den Countdown startet Ihre Lehrperson.</p>
            <p>Dieser Link ist persönlich. Bitte geben Sie ihn nicht weiter.</p>
            </body></html>
            HTML);
    }
}
