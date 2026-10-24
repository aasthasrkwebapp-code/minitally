<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Entry;
use App\Models\Invoice;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $clients = Client::with('invoices', 'payments')->get();
        $dues = $clients->map(fn ($c) => ['client' => $c, 'due' => $c->outstanding()])
            ->filter(fn ($r) => $r['due'] > 0)->sortByDesc('due')->values();

        return view('dashboard', [
            'workThisMonth' => (float) Entry::whereBetween('issued_date', [$monthStart, $monthEnd])->sum('amount'),
            'entriesThisMonth' => Entry::whereBetween('issued_date', [$monthStart, $monthEnd])->count(),
            'unbilled' => (float) Entry::whereNull('invoice_id')->sum('amount'),
            'collectedThisMonth' => (float) Payment::whereBetween('paid_on', [$monthStart, $monthEnd])->sum('amount'),
            'totalDue' => (float) $dues->sum('due'),
            'topDues' => $dues->take(6),
            'recentEntries' => Entry::with('client')->latest('issued_date')->latest('id')->take(6)->get(),
            'unsentInvoices' => Invoice::whereNull('emailed_at')->orWhereNull('whatsapp_at')->count(),
        ]);
    }
}
