@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Outstanding">
    <x-page-header title="Outstanding payments" :subtitle="$rows->count().' clients · ₹'.N::money($total).' due'">
        @if ($rows->isNotEmpty())
            <form method="POST" action="{{ route('outstanding.email-all') }}" data-confirm="Email a reminder with pending bills attached to all {{ $rows->count() }} clients?">
                @csrf <button class="btn btn-primary" data-busy="Sending..."><x-icon name="mail" class="size-4" />Email reminder to all</button>
            </form>
        @endif
    </x-page-header>

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @forelse ($rows as $r)
                <li class="flex flex-col gap-3 px-4 py-3 md:flex-row md:items-center" data-wa-row>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('clients.show', $r->client) }}" class="block truncate font-medium hover:text-brand-600">{{ $r->client->name }}</a>
                        <div class="text-xs text-slate-500">
                            {{ $r->unpaid->count() }} unpaid bill(s)
                            @if ($r->oldest) · oldest {{ $r->oldest->format('d M Y') }} ({{ (int) $r->oldest->diffInDays(now()) }} days) @endif
                            · last reminder: <span data-wa-status>{{ $r->last_reminded ? \Carbon\Carbon::parse($r->last_reminded)->format('d M') : 'never' }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3 md:justify-end">
                        <span class="text-base font-semibold text-red-600">₹{{ N::money($r->due) }}</span>
                        <div class="flex flex-wrap justify-end gap-1.5">
                            <form method="POST" action="{{ route('outstanding.send', $r->client) }}" target="_blank">@csrf<button class="btn btn-primary btn-sm" data-wa-sent><x-icon name="send" class="size-4" />Remind both</button></form>
                            <form method="POST" action="{{ route('outstanding.email', $r->client) }}">@csrf
                                <button class="btn btn-secondary btn-sm" @disabled(!$r->client->email) data-busy="..."><x-icon name="mail" class="size-4" />Email</button>
                            </form>
                            @if ($r->client->whatsapp)
                                <a href="{{ route('outstanding.whatsapp', $r->client) }}" target="_blank" class="btn btn-secondary btn-sm" data-wa-sent><x-icon name="chat" class="size-4" />WhatsApp</a>
                            @endif
                            <a href="{{ route('payments.create', ['client_id' => $r->client->id]) }}" class="btn btn-secondary btn-sm">Paid</a>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">Nobody owes you anything right now.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
