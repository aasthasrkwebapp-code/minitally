<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\BillSender;
use App\Services\InvoiceService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request, InvoiceService $service)
    {
        $month = $request->input('month', now()->format('Y-m'));

        return view('billing.index', [
            'month' => $month,
            'pending' => $service->pendingForMonth($month),
            'invoices' => Invoice::with('client')->where('period', $month)->orderBy('number')->get(),
        ]);
    }

    public function generate(Request $request, InvoiceService $service)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'clients' => ['nullable', 'array'],
            'clients.*' => ['integer'],
        ]);

        $made = $service->generateForMonth($data['month'], $data['clients'] ?? null);

        return redirect()->route('billing.index', ['month' => $data['month']])
            ->with('ok', $made->count().' bill(s) ready. Now send them by email and WhatsApp.');
    }

    public function emailAll(Request $request, BillSender $sender)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'resend' => ['nullable', 'boolean']]);
        @set_time_limit(0);

        $invoices = Invoice::with('client')->where('period', $data['month'])
            ->when(! $request->boolean('resend'), fn ($q) => $q->whereNull('emailed_at'))->get();

        [$sent, $failed] = [0, []];
        foreach ($invoices as $invoice) {
            try {
                $sender->emailBill($invoice);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $invoice->client->name.': '.$e->getMessage();
            }
        }

        return redirect()->route('billing.index', ['month' => $data['month']])
            ->with('ok', "$sent bill(s) emailed.")
            ->with('errors_list', $failed);
    }

    /**
     * One button for month end: email every unsent bill, send WhatsApp automatically when the
     * Business API is set up, otherwise open the WhatsApp list to send each one with a tap.
     */
    public function sendAll(Request $request, BillSender $sender, WhatsAppService $whatsapp)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        @set_time_limit(0);

        [$emailed, $whatsapped, $failed] = [0, 0, []];
        foreach (Invoice::with('client')->where('period', $data['month'])->get() as $invoice) {
            if (! $invoice->emailed_at && $invoice->client->email) {
                try {
                    $sender->emailBill($invoice);
                    $emailed++;
                } catch (\Throwable $e) {
                    $failed[] = $invoice->client->name.' (email): '.$e->getMessage();
                }
            }
            if ($whatsapp->mode() === 'cloud' && ! $invoice->whatsapp_at && $invoice->client->whatsapp) {
                try {
                    $sender->whatsappBill($invoice);
                    $whatsapped++;
                } catch (\Throwable $e) {
                    $failed[] = $invoice->client->name.' (WhatsApp): '.$e->getMessage();
                }
            }
        }

        if ($whatsapp->mode() === 'cloud') {
            return redirect()->route('billing.index', ['month' => $data['month']])
                ->with('ok', "$emailed bill(s) emailed and $whatsapped sent on WhatsApp.")->with('errors_list', $failed);
        }

        return redirect()->route('billing.whatsapp', ['month' => $data['month']])
            ->with('ok', "$emailed bill(s) emailed. Now tap Send for each exporter below to send it on WhatsApp.")
            ->with('errors_list', $failed);
    }

    public function whatsappQueue(Request $request, WhatsAppService $whatsapp)
    {
        $month = $request->input('month', now()->format('Y-m'));

        return view('billing.whatsapp', [
            'month' => $month,
            'mode' => $whatsapp->mode(),
            'invoices' => Invoice::with('client')->where('period', $month)->orderBy('number')->get(),
        ]);
    }

    /** Cloud API mode only: sends every unsent bill automatically. */
    public function whatsappAll(Request $request, BillSender $sender, WhatsAppService $whatsapp)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        if ($whatsapp->mode() !== 'cloud') {
            return back()->with('ok', 'Automatic WhatsApp needs the WhatsApp Cloud API set up. Use the buttons below to send one by one.');
        }
        @set_time_limit(0);

        [$sent, $failed] = [0, []];
        foreach (Invoice::with('client')->where('period', $data['month'])->whereNull('whatsapp_at')->get() as $invoice) {
            try {
                $sender->whatsappBill($invoice);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $invoice->client->name.': '.$e->getMessage();
            }
        }

        return back()->with('ok', "$sent bill(s) sent on WhatsApp.")->with('errors_list', $failed);
    }
}
