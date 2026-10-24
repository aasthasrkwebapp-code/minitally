@php use App\Support\IndianNumber as N; @endphp
@if ($invoices->isEmpty())
    <p class="px-4 py-6 text-center text-sm text-slate-500">{{ $empty ?? 'No bills.' }}</p>
@else
<ul class="divide-y divide-slate-100">
    @foreach ($invoices as $inv)
        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
            <a href="{{ route('invoices.show', $inv) }}" class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-slate-900">{{ $inv->client->name }}</span>
                <span class="block text-xs text-slate-500">{{ $inv->number }} · {{ $inv->invoice_date->format('d-m-Y') }}</span>
            </a>
            <div class="flex items-center justify-between gap-3 sm:justify-end">
                <div class="text-right">
                    <div class="text-sm font-semibold">₹{{ N::money($inv->total) }}</div>
                    <x-status :status="$inv->status" />
                </div>
                <div class="flex flex-wrap justify-end gap-1.5">
                    @if (! $inv->emailed_at || ! $inv->whatsapp_at)
                        <form method="POST" action="{{ route('invoices.send', $inv) }}" target="_blank">@csrf<button class="btn btn-primary btn-sm" title="Email the PDF and open WhatsApp"><x-icon name="send" class="size-4" />Send both</button></form>
                    @endif
                    <a href="{{ route('invoices.pdf', $inv) }}" target="_blank" class="btn btn-secondary btn-sm" title="View PDF"><x-icon name="eye" class="size-4" /><span class="hidden xl:inline">PDF</span></a>
                    <form method="POST" action="{{ route('invoices.email', $inv) }}">@csrf
                        <button class="btn btn-sm btn-secondary" title="{{ $inv->emailed_at ? 'Emailed '.$inv->emailed_at->format('d M H:i') : 'Send email' }}" data-busy="...">
                            @if ($inv->emailed_at)<x-icon name="check" class="size-4" />@else<x-icon name="mail" class="size-4" />@endif<span class="hidden sm:inline">Email</span>
                        </button>
                    </form>
                    <a href="{{ route('invoices.whatsapp', $inv) }}" target="_blank" class="btn btn-sm {{ $inv->whatsapp_at ? 'btn-secondary' : 'btn-whatsapp' }}" title="{{ $inv->whatsapp_at ? 'Sent '.$inv->whatsapp_at->format('d M H:i') : 'Send on WhatsApp' }}">
                        @if ($inv->whatsapp_at)<x-icon name="check" class="size-4" />@else<x-icon name="chat" class="size-4" />@endif<span class="hidden sm:inline">WhatsApp</span>
                    </a>
                </div>
            </div>
        </li>
    @endforeach
</ul>
@endif
