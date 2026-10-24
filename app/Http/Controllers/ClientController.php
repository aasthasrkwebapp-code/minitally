<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")->orWhere('whatsapp', 'like', "%$s%")))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('clients.show', $client)->with('ok', 'Client added.');
    }

    public function show(Client $client)
    {
        $client->load(['invoices' => fn ($q) => $q->latest('invoice_date'), 'payments' => fn ($q) => $q->latest('paid_on')]);

        $ledger = collect()
            ->when((float) $client->opening_balance != 0, fn ($l) => $l->push(['date' => null, 'text' => 'Opening balance', 'debit' => (float) $client->opening_balance, 'credit' => 0]))
            ->merge($client->invoices->map(fn ($i) => ['date' => $i->invoice_date, 'text' => 'Bill '.$i->number.' ('.$i->periodLabel().')', 'debit' => (float) $i->total, 'credit' => 0]))
            ->merge($client->payments->map(fn ($p) => ['date' => $p->paid_on, 'text' => 'Payment '.($p->reference ?: ucfirst($p->mode)), 'debit' => 0, 'credit' => (float) $p->amount]))
            ->sortBy(fn ($r) => $r['date']?->timestamp ?? 0)->values();

        $running = 0;
        $ledger = $ledger->map(function ($r) use (&$running) {
            $running += $r['debit'] - $r['credit'];

            return $r + ['balance' => $running];
        });

        return view('clients.show', compact('client', 'ledger'));
    }

    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request));
        $client->reallocatePayments();

        return redirect()->route('clients.show', $client)->with('ok', 'Client updated.');
    }

    public function destroy(Client $client)
    {
        if ($client->invoices()->exists() || $client->payments()->exists()) {
            $client->update(['is_active' => false]);

            return redirect()->route('clients.index')->with('ok', 'Client has bills or payments, so it was marked inactive instead of deleted.');
        }
        $client->delete();

        return redirect()->route('clients.index')->with('ok', 'Client deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'bank_keywords' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
