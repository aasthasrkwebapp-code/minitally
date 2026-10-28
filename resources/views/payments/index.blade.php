@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Payments">
    <x-page-header title="Payments received">
        <a href="{{ route('bank.index') }}" class="btn btn-secondary"><x-icon name="bank" class="size-4" />From bank statement</a>
        <a href="{{ route('payments.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add payment</a>
    </x-page-header>
    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex">
        <input type="month" name="month" value="{{ request('month') }}" class="input sm:w-auto" data-autosubmit>
        <select name="client_id" class="input sm:w-56" data-autosubmit>
            <option value="">All exporters</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>
    <div class="card overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @forelse ($payments as $p)
                <li class="flex items-center gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('clients.show', $p->client) }}" class="block truncate text-sm font-medium hover:text-brand-600">{{ $p->client->name }}</a>
                        <div class="truncate text-xs text-slate-500">{{ $p->paid_on->format('d-m-Y') }} · {{ strtoupper($p->mode) }} @if($p->source === 'bank')· from statement @endif {{ $p->reference ? '· '.$p->reference : '' }}</div>
                        @if ($p->narration)<div class="truncate text-xs text-slate-400">{{ $p->narration }}</div>@endif
                    </div>
                    <span class="text-sm font-semibold text-emerald-700">₹{{ N::money($p->amount) }}</span>
                    <form method="POST" action="{{ route('payments.destroy', $p) }}" data-confirm="Delete this payment? Bills will show as unpaid again.">
                        @csrf @method('DELETE')
                        <button class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Delete"><x-icon name="trash" class="size-4" /></button>
                    </form>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">No payments yet.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
