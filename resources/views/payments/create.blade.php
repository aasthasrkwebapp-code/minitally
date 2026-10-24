@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Add payment">
    <x-page-header title="Add payment" subtitle="For cash, cheque or anything not in the bank statement upload" />
    <form method="POST" action="{{ route('payments.store') }}" class="card card-pad max-w-2xl space-y-4">
        @csrf
        <div><label class="label">Client *</label>
            <select name="client_id" class="input" required>
                <option value="">Select client</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $clientId) == $c->id)>{{ $c->name }} (due ₹{{ N::money($c->outstanding(), false) }})</option>@endforeach
            </select></div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label">Date *</label><input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" class="input" required></div>
            <div><label class="label">Amount (₹) *</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="input" required inputmode="decimal"></div>
            <div><label class="label">Mode *</label>
                <select name="mode" class="input">@foreach (['bank' => 'Bank transfer', 'upi' => 'UPI', 'cash' => 'Cash', 'cheque' => 'Cheque'] as $k => $v)<option value="{{ $k }}" @selected(old('mode') === $k)>{{ $v }}</option>@endforeach</select></div>
        </div>
        <div><label class="label">Reference / cheque no.</label><input name="reference" value="{{ old('reference') }}" class="input"></div>
        <div><label class="label">Note</label><textarea name="narration" rows="2" class="input">{{ old('narration') }}</textarea></div>
        <p class="text-xs text-slate-500">The payment is applied to the oldest unpaid bills first.</p>
        <div class="flex gap-2"><button class="btn btn-primary">Save payment</button><a href="{{ url()->previous() }}" class="btn btn-secondary">Cancel</a></div>
    </form>
</x-layouts.app>
