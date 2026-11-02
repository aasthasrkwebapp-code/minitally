@php use App\Models\Quotation; @endphp
<x-layouts.app title="Quotations">
    <x-page-header title="Quotations" subtitle="Rates you quote to exporters. Accepted rates fill the Amount on daily entries.">
        <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />New quotation</a>
    </x-page-header>

    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <select name="status" class="input sm:w-40" data-autosubmit>
            <option value="">Any status</option>
            @foreach (Quotation::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="client_id" class="input sm:w-56" data-autosubmit>
            <option value="">All exporters</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100 md:hidden">
            @forelse ($quotations as $q)
                <li><a href="{{ route('quotations.show', $q) }}" class="block px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $q->client->name }}</span>
                        <x-status :status="$q->status" />
                    </div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ $q->number }} · {{ $q->quote_date->format('d-m-Y') }} · {{ $q->items_count }} rates · {{ $q->entries_count }} entries</div>
                </a></li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">No quotations yet.</li>
            @endforelse
        </ul>
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <thead><tr><th>Number</th><th>Date</th><th>Exporter</th><th>Valid until</th><th class="text-right">Rates</th><th class="text-right">Entries</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($quotations as $q)
                    <tr>
                        <td><a href="{{ route('quotations.show', $q) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $q->number }}</a></td>
                        <td class="whitespace-nowrap">{{ $q->quote_date->format('d-m-Y') }}</td>
                        <td>{{ $q->client->name }}</td>
                        <td class="whitespace-nowrap">{{ $q->valid_until?->format('d-m-Y') ?? '—' }}</td>
                        <td class="text-right">{{ $q->items_count }}</td>
                        <td class="text-right">{{ $q->entries_count }}</td>
                        <td><x-status :status="$q->status" /></td>
                        <td class="text-right"><a href="{{ route('quotations.edit', $q) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">No quotations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $quotations->links() }}</div>
</x-layouts.app>
