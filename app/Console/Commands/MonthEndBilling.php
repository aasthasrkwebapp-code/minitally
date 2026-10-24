<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\BillSender;
use App\Services\InvoiceService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class MonthEndBilling extends Command
{
    protected $signature = 'billing:month-end {month? : YYYY-MM, default last month} {--force : run even if automatic month-end is off or today is not the set day}';

    protected $description = 'Make bills for a month and send them by email (and WhatsApp when the Business API is set up)';

    public function handle(InvoiceService $invoices, BillSender $sender, WhatsAppService $whatsapp): int
    {
        if (! $this->option('force')) {
            if (Setting::get('auto_month_end') !== '1') {
                $this->line('Automatic month-end is off in Settings.');

                return self::SUCCESS;
            }
            if ((int) now()->day !== (int) Setting::get('auto_month_end_day', '1')) {
                $this->line('Not the month-end day today.');

                return self::SUCCESS;
            }
        }

        $month = $this->argument('month') ?? now()->subMonthNoOverflow()->format('Y-m');
        $made = $invoices->generateForMonth($month);
        $this->info("{$made->count()} bill(s) made for $month.");

        foreach (Invoice::with('client')->where('period', $month)->get() as $invoice) {
            if (! $invoice->emailed_at && $invoice->client->email) {
                try {
                    $sender->emailBill($invoice);
                    $this->line("Emailed {$invoice->number} to {$invoice->client->name}");
                } catch (\Throwable $e) {
                    $this->error("Email {$invoice->number}: {$e->getMessage()}");
                }
            }
            if ($whatsapp->mode() === 'cloud' && ! $invoice->whatsapp_at && $invoice->client->whatsapp) {
                try {
                    $sender->whatsappBill($invoice);
                    $this->line("WhatsApp {$invoice->number} to {$invoice->client->name}");
                } catch (\Throwable $e) {
                    $this->error("WhatsApp {$invoice->number}: {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
