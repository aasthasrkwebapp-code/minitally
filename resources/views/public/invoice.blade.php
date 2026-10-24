@php use App\Support\IndianNumber as N; @endphp
<x-layouts.guest :title="'Invoice '.$invoice->number">
    <div class="card card-pad w-full max-w-md text-center">
        <div class="text-sm font-semibold text-brand-700">{{ $s['business_name'] }}</div>
        <h1 class="mt-2 text-lg font-semibold">Invoice {{ $invoice->number }}</h1>
        <p class="text-sm text-slate-500">{{ $invoice->client->name }} · {{ $invoice->periodLabel() }}</p>
        <div class="mt-4 text-3xl font-bold">₹{{ N::money($invoice->total) }}</div>
        <div class="mt-1"><x-status :status="$invoice->status" /></div>
        <a href="{{ route('public.invoice.pdf', $invoice->public_token) }}" class="btn btn-primary mt-5 w-full"><x-icon name="download" class="size-4" />View / download PDF</a>
        <div class="mt-5 rounded-lg bg-slate-50 p-3 text-left text-xs text-slate-600">
            <div class="font-semibold text-slate-800">Pay to</div>
            <div>{{ $s['bank_account_name'] }} · {{ $s['bank_name'] }}</div>
            <div>A/c {{ $s['bank_account_no'] }} · IFSC {{ $s['bank_ifsc'] }}</div>
        </div>
    </div>
</x-layouts.guest>
