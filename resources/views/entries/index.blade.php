@php use App\Support\IndianNumber as N; $sn = ($entries->currentPage() - 1) * $entries->perPage(); @endphp
<x-layouts.app title="Daily entries">
    <x-page-header title="Daily entries" :subtitle="\Carbon\Carbon::parse($month.'-01')->format('F Y').' · '.$entries->total().' entries · ₹'.N::money($total)">
        <a href="{{ route('entries.import') }}" class="btn btn-secondary"><x-icon name="upload" class="size-4" />Import Excel</a>
        <a href="{{ route('entries.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add entries</a>
    </x-page-header>

    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <input type="month" name="month" value="{{ $month }}" class="input sm:w-auto" data-autosubmit>
        <select name="client_id" class="input sm:w-56" data-autosubmit>
            <option value="">All clients</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <input name="q" value="{{ request('q') }}" placeholder="Search party, ref, invoice, certificate" class="input col-span-2 sm:w-80">
    </form>

    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100 lg:hidden">
            @forelse ($entries as $e)
                <li><a href="{{ route('entries.edit', $e) }}" class="block px-4 py-3 hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $e->party_name }}</div>
                            <div class="text-xs text-slate-500">{{ $e->issued_date->format('d M') }} · {{ collect([$e->certificate, $e->destination, $e->ir_na])->filter()->implode(' · ') }}</div>
                            <div class="text-xs text-slate-500">{{ collect(['Ref '.$e->reference_no => $e->reference_no, 'Inv '.$e->invoice_number => $e->invoice_number, 'Mail by '.$e->mail_by => $e->mail_by])->filter()->keys()->implode(' · ') }}</div>
                            @if ($mode !== 'party')<div class="text-xs text-brand-600">Bill to {{ $e->client->name }}</div>@endif
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-semibold">₹{{ N::money($e->amount, false) }}</div>
                            @if ($e->transaction_amount)<div class="text-xs text-slate-500">Txn ₹{{ N::money($e->transaction_amount, false) }}</div>@endif
                            @if ($e->invoice)<span class="badge-muted badge">Billed</span>@endif
                        </div>
                    </div>
                </a></li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">No entries for this month.</li>
            @endforelse
        </ul>
        <div class="hidden overflow-x-auto lg:block">
            <table class="table">
                <thead><tr>
                    <th>S.No</th><th>Issued date</th><th>Party name</th><th>Reference no.</th><th>Invoice number</th><th>IR/NA</th>
                    <th>Destination</th><th>Certificate</th><th class="text-right">Amount</th><th class="text-right">Transaction amount</th><th>Mail by</th>
                    @if ($mode !== 'party')<th>Bill to</th>@endif<th></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $n => $e)
                    <tr>
                        <td>{{ $sn + $n + 1 }}</td>
                        <td class="whitespace-nowrap">{{ $e->issued_date->format('d-m-Y') }}</td>
                        <td class="font-medium text-slate-900">{{ $e->party_name }}</td>
                        <td>{{ $e->reference_no }}</td>
                        <td>{{ $e->invoice_number }}</td>
                        <td>{{ $e->ir_na }}</td>
                        <td>{{ $e->destination }}</td>
                        <td>{{ $e->certificate }}</td>
                        <td class="text-right font-medium">{{ N::money($e->amount) }}</td>
                        <td class="text-right">{{ $e->transaction_amount !== null ? N::money($e->transaction_amount) : '' }}</td>
                        <td>{{ $e->mail_by }}</td>
                        @if ($mode !== 'party')<td>{{ $e->client->name }}</td>@endif
                        <td class="whitespace-nowrap text-right">
                            @if ($e->invoice)<a href="{{ route('invoices.show', $e->invoice) }}" class="badge-muted badge mr-1">{{ $e->invoice->number }}</a>@endif
                            <a href="{{ route('entries.edit', $e) }}" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-8 text-center text-slate-500">No entries for this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
