<x-layouts.app title="Edit entry">
    <x-page-header title="Edit entry" :subtitle="$entry->invoice ? 'Already billed in '.$entry->invoice->number.'. Changing the amount updates that bill.' : 'Bill to: '.$entry->client->name" />
    <form method="POST" action="{{ route('entries.update', $entry) }}" class="card card-pad max-w-4xl">
        @csrf @method('PUT')
        <div class="mb-3 max-w-xs">
            <label class="label">Issued date *</label>
            <input type="date" name="issued_date" value="{{ old('issued_date', $entry->issued_date->toDateString()) }}" class="input" required>
        </div>
        @include('entries._fields', [
            'name' => fn ($f) => $f,
            'val' => fn ($f) => old($f, $f === 'issued_date' ? $entry->issued_date->toDateString() : $entry->{$f}),
        ])
        @include('entries._datalists')
        <div class="mt-4 flex gap-2"><button class="btn btn-primary">Save</button><a href="{{ route('entries.index', ['month' => $entry->issued_date->format('Y-m')]) }}" class="btn btn-secondary">Cancel</a></div>
    </form>
    <form method="POST" action="{{ route('entries.destroy', $entry) }}" class="mt-4" data-confirm="Delete this entry?">
        @csrf @method('DELETE')
        <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Delete entry</button>
    </form>
</x-layouts.app>
