<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with('client')
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->month, fn ($q, $m) => $q->where('paid_on', 'like', $m.'%'))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return view('payments.create', [
            'clients' => Client::orderBy('name')->get(),
            'clientId' => $request->client_id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', 'in:bank,upi,cash,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = Payment::create($data + ['source' => 'manual']);
        $payment->client->reallocatePayments();

        return redirect()->route('clients.show', $payment->client_id)->with('ok', 'Payment recorded.');
    }

    public function destroy(Payment $payment)
    {
        $client = $payment->client;
        BankTransaction::where('payment_id', $payment->id)->update(['status' => 'pending', 'payment_id' => null]);
        $payment->delete();
        $client->reallocatePayments();

        return back()->with('ok', 'Payment deleted.');
    }
}
