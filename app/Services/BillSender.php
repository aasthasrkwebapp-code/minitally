<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Mail\ReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Support\IndianNumber;
use App\Support\Template;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** Sends bills and payment reminders by email and WhatsApp. */
class BillSender
{
    public function __construct(
        private InvoiceService $invoices,
        private WhatsAppService $whatsapp,
    ) {}

    public function billValues(Invoice $invoice): array
    {
        return [
            'exporter' => $invoice->client->contact_person ?: $invoice->client->name,
            'client' => $invoice->client->contact_person ?: $invoice->client->name,
            'invoice' => $invoice->number,
            'month' => $invoice->periodLabel(),
            'amount' => IndianNumber::money($invoice->total),
            'link' => $invoice->publicUrl(),
            'business' => Setting::get('business_name'),
        ];
    }

    public function emailBill(Invoice $invoice): void
    {
        $email = $invoice->client->email;
        if (! $email) {
            throw new RuntimeException("{$invoice->client->name} has no email address.");
        }

        Mail::to(array_map('trim', explode(',', $email)))->send(new InvoiceMail(
            $invoice,
            Template::fill(Setting::get('email_bill_message'), $this->billValues($invoice)),
            $this->invoices->pdfContent($invoice),
        ));

        $invoice->forceFill(['emailed_at' => now()])->save();
    }

    /**
     * Link mode: returns the wa.me URL to open. Cloud mode: sends and returns null.
     */
    public function whatsappBill(Invoice $invoice): ?string
    {
        $phone = $invoice->client->whatsappNumber();
        if (! $phone) {
            throw new RuntimeException("{$invoice->client->name} has no WhatsApp number.");
        }

        $values = $this->billValues($invoice);

        if ($this->whatsapp->mode() === 'cloud') {
            $this->whatsapp->sendBill($phone, route('public.invoice.pdf', $invoice->public_token), $invoice->fileName(), [
                $values['client'], $values['invoice'], $values['month'], $values['amount'],
            ]);
            $invoice->forceFill(['whatsapp_at' => now()])->save();

            return null;
        }

        $invoice->forceFill(['whatsapp_at' => now()])->save();

        return $this->whatsapp->link($phone, Template::fill(Setting::get('whatsapp_bill_message'), $values));
    }

    public function reminderValues(Client $client): array
    {
        $unpaid = $client->invoices()->where('status', '!=', 'paid')->orderBy('invoice_date')->get();

        return [
            'exporter' => $client->contact_person ?: $client->name,
            'client' => $client->contact_person ?: $client->name,
            'amount' => IndianNumber::money($client->outstanding()),
            'invoices' => $unpaid->map(fn ($i) => $i->number.' (Rs. '.IndianNumber::money($i->balance()).')')->implode(', ') ?: '-',
            'business' => Setting::get('business_name'),
            'link' => $unpaid->first()?->publicUrl() ?? '',
        ];
    }

    public function emailReminder(Client $client): void
    {
        if (! $client->email) {
            throw new RuntimeException("{$client->name} has no email address.");
        }

        $unpaid = $client->invoices()->where('status', '!=', 'paid')->orderBy('invoice_date')->get();
        $attachments = $unpaid->mapWithKeys(fn ($i) => [$i->fileName() => $this->invoices->pdfContent($i)])->all();

        Mail::to(array_map('trim', explode(',', $client->email)))->send(new ReminderMail(
            $client,
            Template::fill(Setting::get('reminder_message'), $this->reminderValues($client)),
            $attachments,
        ));

        $this->markReminded($client);
    }

    public function whatsappReminder(Client $client): ?string
    {
        $phone = $client->whatsappNumber();
        if (! $phone) {
            throw new RuntimeException("{$client->name} has no WhatsApp number.");
        }
        $values = $this->reminderValues($client);
        $this->markReminded($client);

        if ($this->whatsapp->mode() === 'cloud') {
            $this->whatsapp->sendReminder($phone, [$values['client'], $values['amount'], $values['invoices']]);

            return null;
        }

        return $this->whatsapp->link($phone, Template::fill(Setting::get('reminder_message'), $values));
    }

    private function markReminded(Client $client): void
    {
        $client->invoices()->where('status', '!=', 'paid')->each(function (Invoice $i) {
            $i->forceFill(['last_reminded_at' => now(), 'reminder_count' => $i->reminder_count + 1])->save();
        });
    }
}
