@php use App\Support\IndianNumber as N; $due = $client->outstanding(); @endphp
<x-layouts.app :title="$client->name">
    <x-page-header :title="$client->name" :subtitle="collect([$client->contact_person, $client->email, $client->whatsapp])->filter()->implode(' · ')">
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" />Edit</a>
        <a href="{{ route('payments.create', ['client_id' => $client->id]) }}" class="btn btn-secondary"><x-icon name="rupee" class="size-4" />Add payment</a>
        @if ($due > 0)
            <form method="POST" action="{{ route('outstanding.email', $client) }}">@csrf<button class="btn btn-secondary" data-busy="Sending..."><x-icon name="mail" class="size-4" />Email reminder</button></form>
            <a href="{{ route('outstanding.whatsapp', $client) }}" target="_blank" class="btn btn-whatsapp"><x-icon name="chat" class="size-4" />WhatsApp reminder</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-stat label="Total billed" :value="'₹'.N::money($client->invoices->sum('total'), false)" />
        <x-stat label="Total paid" :value="'₹'.N::money($client->payments->sum('amount'), false)" tone="green" />
        <x-stat label="Outstanding" :value="'₹'.N::money($due)" :tone="$due > 0 ? 'red' : 'slate'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Bills</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($client->invoices as $i)
                    <li><a href="{{ route('invoices.show', $i) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                        <div><div class="text-sm font-medium">{{ $i->number }}</div><div class="text-xs text-slate-500">{{ $i->periodLabel() }}</div></div>
                        <div class="text-right"><div class="text-sm font-semibold">₹{{ N::money($i->total) }}</div><x-status :status="$i->status" /></div>
                    </a></li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">No bills yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="card overflow-hidden">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Account (ledger)</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Details</th><th class="text-right">Bill</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse ($ledger as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ $r['date']?->format('d-m-Y') ?? '—' }}</td>
                            <td>{{ $r['text'] }}</td>
                            <td class="text-right">{{ $r['debit'] ? N::money($r['debit']) : '' }}</td>
                            <td class="text-right text-emerald-700">{{ $r['credit'] ? N::money($r['credit']) : '' }}</td>
                            <td class="text-right font-medium">{{ N::money($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Nothing yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
