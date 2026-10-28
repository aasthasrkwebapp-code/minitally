{{-- Shared entry fields. $name(field) gives the input name, $val(field) the current value. --}}
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="sm:col-span-2">
        <label class="label">Party name *</label>
        <input name="{{ $name('party_name') }}" value="{{ $val('party_name') }}" list="dl-party_name" class="input" autocomplete="off">
    </div>
    <div><label class="label">Reference no.</label><input name="{{ $name('reference_no') }}" value="{{ $val('reference_no') }}" class="input"></div>
    <div><label class="label">Invoice number</label><input name="{{ $name('invoice_number') }}" value="{{ $val('invoice_number') }}" class="input"></div>
    <div><label class="label">IR/NA</label><input name="{{ $name('ir_na') }}" value="{{ $val('ir_na') }}" list="dl-ir_na" class="input"></div>
    <div><label class="label">Destination</label><input name="{{ $name('destination') }}" value="{{ $val('destination') }}" list="dl-destination" class="input"></div>
    <div class="sm:col-span-2"><label class="label">Certificate</label><input name="{{ $name('certificate') }}" value="{{ $val('certificate') }}" list="dl-certificate" class="input"></div>
    <div><label class="label">Amount (₹) *</label><input name="{{ $name('amount') }}" value="{{ $val('amount') }}" type="number" step="0.01" min="0" inputmode="decimal" class="input"></div>
    <div><label class="label">Transaction amount (₹)</label><input name="{{ $name('transaction_amount') }}" value="{{ $val('transaction_amount') }}" type="number" step="0.01" min="0" inputmode="decimal" class="input"></div>
    <div class="{{ $mode === 'manual' ? '' : 'sm:col-span-2' }}"><label class="label">Mail by{{ $mode === 'mail_by' ? ' (bill goes to this exporter) *' : '' }}</label><input name="{{ $name('mail_by') }}" value="{{ $val('mail_by') }}" list="dl-mail_by" class="input"></div>
    @if ($mode === 'manual')
        <div>
            <label class="label">Bill to *</label>
            <select name="{{ $name('client_id') }}" class="input">
                <option value="">Select exporter</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($val('client_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
    @endif
</div>
