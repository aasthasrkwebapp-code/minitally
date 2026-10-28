@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app :title="'Bill '.$invoice->number">
    <x-page-header :title="'Bill '.$invoice->number" :subtitle="$invoice->client->name.' · '.$invoice->periodLabel()">
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-secondary"><x-icon name="eye" class="size-4" />View PDF</a>
        <a href="{{ route('invoices.pdf', [$invoice, 'download' => 1]) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Download</a>
        <form method="POST" action="{{ route('invoices.send', $invoice) }}" target="_blank">@csrf<button class="btn btn-primary"><x-icon name="send" class="size-4" />Send email + WhatsApp</button></form>
        <form method="POST" action="{{ route('invoices.email', $invoice) }}">@csrf<button class="btn btn-secondary" data-busy="Sending..."><x-icon name="mail" class="size-4" />{{ $invoice->emailed_at ? 'Email again' : 'Email' }}</button></form>
        <a href="{{ route('invoices.whatsapp', $invoice) }}" target="_blank" class="btn btn-whatsapp"><x-icon name="chat" class="size-4" />WhatsApp</a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Bill amount" :value="'₹'.N::money($invoice->total)" />
        <x-stat label="Paid" :value="'₹'.N::money($invoice->amount_paid)" tone="green" />
        <x-stat label="Balance" :value="'₹'.N::money($invoice->balance())" :tone="$invoice->balance() > 0 ? 'red' : 'slate'" />
        <div class="card card-pad text-xs text-slate-600">
            <div class="mb-1"><x-status :status="$invoice->status" /></div>
            <div>Emailed: {{ $invoice->emailed_at?->format('d M Y, H:i') ?? 'not yet' }}</div>
            <div>WhatsApp: {{ $invoice->whatsapp_at?->format('d M Y, H:i') ?? 'not yet' }}</div>
            <div>Reminders: {{ $invoice->reminder_count }}</div>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>S.No</th><th>Issued date</th><th>Party name</th><th>IR/NA</th><th>Certificate</th><th>Destination</th><th>Invoice no.</th><th>Reference no.</th><th class="text-right">Amount</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($invoice->entries as $n => $e)
                    <tr>
                        <td>{{ $n + 1 }}</td><td class="whitespace-nowrap">{{ $e->issued_date->format('d-m-Y') }}</td>
                        <td class="font-medium">{{ $e->party_name }}</td><td>{{ $e->ir_na }}</td><td>{{ $e->certificate }}</td>
                        <td>{{ $e->destination }}</td><td>{{ $e->invoice_number }}</td><td>{{ $e->reference_no }}</td><td class="text-right">{{ N::money($e->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="bg-slate-50 font-semibold"><td colspan="8" class="px-3 py-2.5">{{ N::words((float) $invoice->total) }}</td><td class="px-3 py-2.5 text-right">{{ N::money($invoice->total) }}</td></tr></tfoot>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-slate-500">
        <span>Exporter link: <a href="{{ $invoice->publicUrl() }}" target="_blank" class="break-all text-brand-600 underline">{{ $invoice->publicUrl() }}</a></span>
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="Delete this bill? Its entries go back to 'not billed' so you can bill them again.">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Delete bill</button>
        </form>
    </div>
</x-layouts.app>
