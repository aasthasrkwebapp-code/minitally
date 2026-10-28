@php use App\Models\Client; @endphp
<x-layouts.app :title="$client->exists ? 'Edit exporter' : 'Add exporter'">
    <x-page-header :title="$client->exists ? 'Edit '.$client->name : 'Add exporter'" />

    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="card card-pad max-w-3xl space-y-6" autocomplete="off">
        @csrf
        @if ($client->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">Exporter name *</label>
                <input name="name" value="{{ old('name', $client->name) }}" required class="input">
            </div>
            <div>
                <label class="label">Contact person</label>
                <input name="contact_person" value="{{ old('contact_person', $client->contact_person) }}" class="input" placeholder="Used in 'Dear ...'">
            </div>
            <div>
                <label class="label">Mobile number (WhatsApp)</label>
                <input name="whatsapp" value="{{ old('whatsapp', $client->whatsapp) }}" class="input" inputmode="tel" placeholder="+91 98xxxxxxxx">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Email ID (for more than one, separate with commas)</label>
                <input name="email" value="{{ old('email', $client->email) }}" class="input" inputmode="email">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Address (printed under BILL TO)</label>
                <textarea name="address" rows="3" class="input">{{ old('address', $client->address) }}</textarea>
            </div>
        </div>

        @php
            $logins = [
                ['DGFT', 'dgft_user_id', 'DGFT user ID', 'dgft_password', 'DGFT password'],
                ['ICEGATE', 'icegate_id', 'ICEGATE ID', 'icegate_password', 'ICEGATE password'],
            ];
        @endphp
        @foreach ($logins as [$head, $idField, $idLabel, $pwField, $pwLabel])
            <fieldset class="border-t border-slate-200 pt-4">
                <legend class="pr-2 text-sm font-semibold text-slate-700">{{ $head }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ $idLabel }}</label>
                        <input name="{{ $idField }}" value="{{ old($idField, $client->$idField) }}" class="input" autocomplete="off">
                    </div>
                    <div>
                        <label class="label">{{ $pwLabel }}</label>
                        <div class="flex gap-2">
                            <input id="f-{{ $pwField }}" type="password" name="{{ $pwField }}" value="{{ old($pwField, $client->$pwField) }}" class="input" autocomplete="new-password">
                            <button type="button" data-reveal="f-{{ $pwField }}" class="btn btn-secondary btn-sm shrink-0">Show</button>
                        </div>
                    </div>
                </div>
            </fieldset>
        @endforeach

        <fieldset class="border-t border-slate-200 pt-4">
            <legend class="pr-2 text-sm font-semibold text-slate-700">DSC (digital signature)</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">DSC validity date</label>
                    <input type="date" name="dsc_valid_till" value="{{ old('dsc_valid_till', $client->dsc_valid_till?->format('Y-m-d')) }}" class="input">
                </div>
                <div>
                    <label class="label">DSC status</label>
                    <select name="dsc_status" class="input">
                        <option value="">Select</option>
                        @foreach (Client::DSC_STATUSES as $k => $t)
                            <option value="{{ $k }}" @selected(old('dsc_status', $client->dsc_status) === $k)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500">The Exporters list warns you {{ Client::DSC_WARN_DAYS }} days before the DSC validity date.</p>
        </fieldset>

        <fieldset class="border-t border-slate-200 pt-4">
            <legend class="pr-2 text-sm font-semibold text-slate-700">Billing</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Name in bank statement</label>
                    <input name="bank_keywords" value="{{ old('bank_keywords', $client->bank_keywords) }}" class="input" placeholder="e.g. BALAJI EXP, RAMESH KUMAR">
                    <p class="mt-1 text-xs text-slate-500">How this exporter's name shows in your bank statement, if different. Separate with commas. Learned automatically when you match a payment by hand.</p>
                </div>
                <div>
                    <label class="label">Opening balance due (₹)</label>
                    <input name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance', $client->opening_balance ?? 0) }}" class="input">
                </div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active)) class="size-4 rounded border-slate-300">
                    Active exporter
                </label>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-2">
            <button class="btn btn-primary">Save</button>
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @if ($client->exists)
        <form method="POST" action="{{ route('clients.destroy', $client) }}" class="mt-4" data-confirm="Delete this exporter?">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Delete exporter</button>
        </form>
    @endif
</x-layouts.app>
