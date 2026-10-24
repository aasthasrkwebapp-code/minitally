<x-layouts.app :title="$client->exists ? 'Edit client' : 'Add client'">
    <x-page-header :title="$client->exists ? 'Edit '.$client->name : 'Add client'" />

    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="card card-pad max-w-3xl space-y-4">
        @csrf
        @if ($client->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">Client / firm name *</label>
                <input name="name" value="{{ old('name', $client->name) }}" required class="input">
            </div>
            <div>
                <label class="label">Contact person</label>
                <input name="contact_person" value="{{ old('contact_person', $client->contact_person) }}" class="input" placeholder="Used in 'Dear ...'">
            </div>
            <div>
                <label class="label">WhatsApp number</label>
                <input name="whatsapp" value="{{ old('whatsapp', $client->whatsapp) }}" class="input" inputmode="tel" placeholder="+91 98xxxxxxxx">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Email (for more than one, separate with commas)</label>
                <input name="email" value="{{ old('email', $client->email) }}" class="input" inputmode="email">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Address (printed under BILL TO)</label>
                <textarea name="address" rows="3" class="input">{{ old('address', $client->address) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Name in bank statement</label>
                <input name="bank_keywords" value="{{ old('bank_keywords', $client->bank_keywords) }}" class="input" placeholder="e.g. BALAJI EXP, RAMESH KUMAR">
                <p class="mt-1 text-xs text-slate-500">How this client's name shows in your bank statement, if different. Separate with commas. Learned automatically when you match a payment by hand.</p>
            </div>
            <div>
                <label class="label">Opening balance due (₹)</label>
                <input name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance', $client->opening_balance ?? 0) }}" class="input">
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active)) class="size-4 rounded border-slate-300">
                Active client
            </label>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button class="btn btn-primary">Save</button>
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @if ($client->exists)
        <form method="POST" action="{{ route('clients.destroy', $client) }}" class="mt-4" data-confirm="Delete this client?">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Delete client</button>
        </form>
    @endif
</x-layouts.app>
