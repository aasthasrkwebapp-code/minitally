@php use App\Models\Quotation; use App\Support\IndianNumber as N; @endphp
<x-layouts.app :title="'Quotation '.$quotation->number">
    <x-page-header :title="'Quotation '.$quotation->number" :subtitle="$quotation->client->name.' · '.$quotation->quote_date->format('d-m-Y').($quotation->valid_until ? ' · valid until '.$quotation->valid_until->format('d-m-Y') : '')">
        <a href="{{ route('quotations.pdf', $quotation) }}" target="_blank" class="btn btn-secondary"><x-icon name="eye" class="size-4" />View PDF</a>
        <a href="{{ route('quotations.pdf', [$quotation, 'download' => 1]) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Download</a>
        <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" />Edit</a>
    </x-page-header>

    <div class="card card-pad flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-600">Status</span> <x-status :status="$quotation->status" />
        <div class="ml-auto flex flex-wrap gap-2">
            @foreach (['sent' => 'Mark sent', 'accepted' => 'Mark accepted', 'rejected' => 'Mark rejected'] as $st => $label)
                @if ($quotation->status !== $st)
                    <form method="POST" action="{{ route('quotations.status', $quotation) }}">@csrf
                        <input type="hidden" name="status" value="{{ $st }}">
                        <button class="btn {{ $st === 'accepted' ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $label }}</button>
                    </form>
                @endif
            @endforeach
        </div>
        @if ($quotation->status === 'accepted')
            <p class="w-full text-xs text-slate-500">These rates now fill the Amount on new daily entries for {{ $quotation->client->name }} when certificate, IR/NA and sets match.</p>
        @endif
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>S.No</th><th>Certificate</th><th>Type</th><th>Sets</th><th class="text-right">Rate (₹)</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($quotation->items as $n => $item)
                    <tr><td>{{ $n + 1 }}</td><td class="font-medium">{{ $item->certificate }}</td><td>{{ Quotation::typeLabel($item->ir_na) }}</td><td>{{ $item->sets }}</td><td class="text-right">{{ N::money($item->rate) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($quotation->notes)<div class="border-t border-slate-200 px-4 py-3 text-sm text-slate-600">{!! nl2br(e($quotation->notes)) !!}</div>@endif
    </div>

    <div class="card mt-6 overflow-hidden">
        <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Daily entries at these rates ({{ $entryCount }})</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Issued date</th><th>Party name</th><th>IR/NA</th><th>Certificate</th><th>Sets</th><th class="text-right">Amount</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $e)
                    <tr><td class="whitespace-nowrap">{{ $e->issued_date->format('d-m-Y') }}</td><td><a href="{{ route('entries.edit', $e) }}" class="hover:text-brand-600">{{ $e->party_name }}</a></td><td>{{ $e->ir_na }}</td><td>{{ $e->certificate }}</td><td>{{ $e->sets }}</td><td class="text-right">{{ N::money($e->amount) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">No entries use this quotation yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" class="mt-4" data-confirm="Delete this quotation? Entries keep their amounts.">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Delete quotation</button>
    </form>
</x-layouts.app>
