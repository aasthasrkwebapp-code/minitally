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

    <div class="card mt-6 overflow-hidden">
        <h2 class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 font-semibold">Exporter details <x-dsc-badge :client="$client" /></h2>
        <dl class="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-slate-500">Email ID</dt><dd class="break-all">{{ $client->email ?: '—' }}</dd></div>
            <div><dt class="text-xs text-slate-500">Mobile number</dt><dd>{{ $client->whatsapp ?: '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Address</dt><dd>{!! $client->address ? nl2br(e($client->address)) : '—' !!}</dd></div>
            @foreach ([['DGFT user ID', 'dgft_user_id', 'DGFT password', 'dgft_password'], ['ICEGATE ID', 'icegate_id', 'ICEGATE password', 'icegate_password']] as [$idLabel, $idField, $pwLabel, $pwField])
                <div><dt class="text-xs text-slate-500">{{ $idLabel }}</dt><dd class="break-all">{{ $client->$idField ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $pwLabel }}</dt>
                    <dd>
                        @if ($client->$pwField)
                            <div class="flex items-center gap-2">
                                <input id="s-{{ $pwField }}" type="password" value="{{ $client->$pwField }}" readonly class="input py-1">
                                <button type="button" data-reveal="s-{{ $pwField }}" class="btn btn-secondary btn-sm shrink-0">Show</button>
                                <button type="button" data-copy="s-{{ $pwField }}" class="btn btn-secondary btn-sm shrink-0">Copy</button>
                            </div>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            @endforeach
            <div><dt class="text-xs text-slate-500">DSC validity date</dt><dd>{{ $client->dsc_valid_till?->format('d-m-Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs text-slate-500">DSC status</dt><dd>{{ App\Models\Client::DSC_STATUSES[$client->dsc_status] ?? '—' }}</dd></div>
        </dl>
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
