<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReminderMail extends Mailable
{
    /** @param array<string, string> $pdfs file name => PDF content */
    public function __construct(public Client $client, public string $body, private array $pdfs) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment reminder - '.Setting::get('business_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.message', with: ['body' => $this->body]);
    }

    public function attachments(): array
    {
        return collect($this->pdfs)
            ->map(fn ($pdf, $name) => Attachment::fromData(fn () => $pdf, $name)->withMime('application/pdf'))
            ->values()->all();
    }
}
