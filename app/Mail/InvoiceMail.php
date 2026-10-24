<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(public Invoice $invoice, public string $body, private string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->number} - {$this->invoice->periodLabel()} - ".Setting::get('business_name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.message', with: ['body' => $this->body]);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->invoice->fileName())->withMime('application/pdf')];
    }
}
