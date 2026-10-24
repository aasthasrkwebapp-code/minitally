<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Entry;
use App\Models\Invoice;
use App\Services\BillSender;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('client')
            ->when($request->status, fn ($q, $s) => $s === 'due' ? $q->where('status', '!=', 'paid') : $q->where('status', $s))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->month, fn ($q, $m) => $q->where('period', $m))
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('client', 'entries');

        return view('invoices.show', compact('invoice'));
    }

    public function pdf(Request $request, Invoice $invoice, InvoiceService $service)
    {
        $pdf = $service->pdf($invoice);

        return $request->boolean('download') ? $pdf->download($invoice->fileName()) : $pdf->stream($invoice->fileName());
    }

    public function email(Invoice $invoice, BillSender $sender)
    {
        try {
            $sender->emailBill($invoice);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'Email not sent: '.$e->getMessage()]);
        }

        return back()->with('ok', "Bill {$invoice->number} emailed to {$invoice->client->email}.");
    }

    public function whatsapp(Invoice $invoice, BillSender $sender)
    {
        try {
            $url = $sender->whatsappBill($invoice);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'WhatsApp not sent: '.$e->getMessage()]);
        }

        return $url ? redirect()->away($url) : back()->with('ok', "Bill {$invoice->number} sent on WhatsApp.");
    }

    /** Deleting a bill releases its entries so they can be billed again. */
    public function destroy(Invoice $invoice)
    {
        $client = $invoice->client;
        $month = $invoice->period;
        Entry::where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
        $invoice->delete();
        $client->reallocatePayments();

        return redirect()->route('billing.index', ['month' => $month])->with('ok', 'Bill deleted. Its entries are unbilled again.');
    }
}
