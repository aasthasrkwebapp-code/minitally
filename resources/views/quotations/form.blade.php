<x-layouts.app :title="$quotation->exists ? 'Edit quotation' : 'New quotation'">
    <x-page-header :title="$quotation->exists ? 'Edit '.$quotation->number : 'New quotation'" subtitle="One row per certificate, type and sets. Sets (1, 2, 3) is a test option for now." />

    @php
        $items = old('items', $quotation->exists ? $quotation->items->map->only(['certificate', 'ir_na', 'sets', 'rate'])->all() : [[]]);
    @endphp

    <form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}" class="max-w-5xl space-y-4" autocomplete="off">
        @csrf
        @if ($quotation->exists) @method('PUT') @endif

        <div class="card card-pad grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label">Exporter *</label>
                <select name="client_id" required class="input">
                    <option value="">Select exporter</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $quotation->client_id) == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">New party? <a href="{{ route('clients.create') }}" class="text-brand-600 underline">Add the exporter</a> first.</p>
            </div>
            <div>
                <label class="label">Quotation date *</label>
                <input type="date" name="quote_date" value="{{ old('quote_date', $quotation->quote_date?->toDateString()) }}" required class="input">
            </div>
            <div>
                <label class="label">Valid until</label>
                <input type="date" name="valid_until" value="{{ old('valid_until', $quotation->valid_until?->toDateString()) }}" class="input">
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="mb-1 font-semibold">Rates</h2>
            <p class="text-xs text-slate-500">IR (Retrospective) is saved as IR and Normal as NA, the same as the IR/NA column on daily entries.</p>
            <div id="q-items">
                @foreach ($items as $i => $item)
                    @include('quotations._item', ['i' => $i, 'item' => $item])
                @endforeach
            </div>
            <template id="q-item-template">@include('quotations._item', ['i' => '__i__', 'item' => []])</template>
            <datalist id="dl-q-certificate">@foreach ($certificates as $c)<option value="{{ $c }}">@endforeach</datalist>
            <button type="button" class="btn btn-secondary btn-sm mt-3" data-add-row="q-items" data-template="q-item-template"><x-icon name="plus" class="size-4" />Add rate</button>
        </div>

        <div class="card card-pad">
            <label class="label">Notes (printed on the quotation)</label>
            <textarea name="notes" rows="3" class="input">{{ old('notes', $quotation->notes) }}</textarea>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-primary">Save quotation</button>
            <a href="{{ $quotation->exists ? route('quotations.show', $quotation) : route('quotations.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
