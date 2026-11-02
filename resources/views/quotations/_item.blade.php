@php use App\Models\Quotation; @endphp
{{-- One rate row. $i = index (or __i__ in the template), $item = values --}}
<div class="grid grid-cols-2 gap-3 border-b border-slate-100 py-3 last:border-0 sm:grid-cols-12 sm:items-end" data-row>
    <div class="col-span-2 sm:col-span-5">
        <label class="label">Certificate *</label>
        <input name="items[{{ $i }}][certificate]" value="{{ $item['certificate'] ?? '' }}" list="dl-q-certificate" class="input" autocomplete="off">
    </div>
    <div class="col-span-2 sm:col-span-3">
        <span class="label">Type</span>
        <div class="flex gap-1">
            @foreach (Quotation::TYPES as $value => $label)
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="items[{{ $i }}][ir_na]" value="{{ $value }}" class="peer sr-only" @checked(($item['ir_na'] ?? 'NA') === $value)>
                    <span class="block rounded-lg border border-slate-300 px-2 py-2 text-center text-sm peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="sm:col-span-2">
        <span class="label">Sets</span>
        <div class="flex gap-1">
            @foreach (Quotation::SETS as $n)
                <label class="flex-1 cursor-pointer">
                    <input type="radio" name="items[{{ $i }}][sets]" value="{{ $n }}" class="peer sr-only" @checked((int) ($item['sets'] ?? 1) === $n)>
                    <span class="block rounded-lg border border-slate-300 px-2 py-2 text-center text-sm peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">{{ $n }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Rate (₹) *</label>
        <div class="flex items-center gap-2">
            <input name="items[{{ $i }}][rate]" value="{{ $item['rate'] ?? '' }}" type="number" step="0.01" min="0" inputmode="decimal" class="input">
            <button type="button" class="text-slate-400 hover:text-red-600" data-remove-row title="Remove"><x-icon name="trash" class="size-4" /></button>
        </div>
    </div>
</div>
