@php use App\Support\IndianNumber as N; $label = \Carbon\Carbon::parse($month.'-01')->format('F Y'); @endphp
<x-layouts.app title="WhatsApp bills">
    <x-page-header title="Send bills on WhatsApp" :subtitle="$label">
        <a href="{{ route('billing.index', ['month' => $month]) }}" class="btn btn-secondary">Back to billing</a>
    </x-page-header>

    @if ($mode === 'cloud')
        <form method="POST" action="{{ route('billing.whatsapp-all') }}" class="card card-pad mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-confirm="Send every unsent bill on WhatsApp now?">
            @csrf <input type="hidden" name="month" value="{{ $month }}">
            <p class="text-sm text-slate-600">WhatsApp Business API is connected. Bills are sent automatically with the PDF attached.</p>
            <button class="btn btn-whatsapp" data-busy="Sending..."><x-icon name="chat" class="size-4" />Send all unsent now</button>
        </form>
    @else
        <div class="card card-pad mb-4 text-sm text-slate-600">
            Tap <strong>Send</strong> on each exporter. WhatsApp opens with the message and bill link already typed; press send there and come back for the next one.
            For fully automatic sending, connect the WhatsApp Business API (see Settings).
        </div>
    @endif

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @forelse ($invoices as $inv)
                <li class="flex items-center gap-3 px-4 py-3 {{ $inv->whatsapp_at ? 'opacity-60' : '' }}" data-wa-row>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $inv->client->name }}</div>
                        <div class="text-xs text-slate-500">{{ $inv->number }} · ₹{{ N::money($inv->total) }} · {{ $inv->client->whatsapp ?: 'no number' }}</div>
                        <div class="text-xs text-emerald-700" data-wa-status>{{ $inv->whatsapp_at ? 'Sent '.$inv->whatsapp_at->format('d M, H:i') : '' }}</div>
                    </div>
                    @if ($inv->client->whatsapp)
                        <a href="{{ route('invoices.whatsapp', $inv) }}" target="_blank" class="btn btn-whatsapp btn-sm" data-wa-sent><x-icon name="chat" class="size-4" />{{ $inv->whatsapp_at ? 'Resend' : 'Send' }}</a>
                    @else
                        <a href="{{ route('clients.edit', $inv->client) }}" class="btn btn-secondary btn-sm">Add number</a>
                    @endif
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No bills for {{ $label }}.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
