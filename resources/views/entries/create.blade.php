<x-layouts.app title="Add entries">
    <x-page-header title="Add entries" :subtitle="['party' => 'The bill goes to the party name.', 'mail_by' => 'The bill goes to the Mail by name.', 'manual' => 'Choose who to bill on each row.'][$mode].' Add as many rows as you need, then save once.'" />

    <form method="POST" action="{{ route('entries.store') }}">
        @csrf
        <div class="card card-pad mb-4 max-w-xs">
            <label class="label">Issued date *</label>
            <input type="date" name="issued_date" value="{{ old('issued_date', now()->toDateString()) }}" required class="input">
        </div>

        <div id="rows" class="space-y-3">
            @foreach (old('rows', [[]]) as $i => $row)
                @include('entries._row', ['i' => $i, 'row' => $row])
            @endforeach
        </div>

        <template id="row-template">@include('entries._row', ['i' => '__i__', 'row' => []])</template>
        @include('entries._datalists')

        <div class="sticky bottom-16 z-10 mt-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur md:bottom-4">
            <button type="button" class="btn btn-secondary" data-add-row="rows" data-template="row-template"><x-icon name="plus" class="size-4" />Add another row</button>
            <button class="btn btn-primary">Save entries</button>
        </div>
    </form>
</x-layouts.app>
