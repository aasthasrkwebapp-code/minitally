@php use App\Support\IndianNumber as N;
$pendingMatched = $credits->where('status', 'pending')->whereNotNull('client_id');
@endphp
<x-layouts.app title="Match payments">
    <x-page-header title="Match payments" :subtitle="$import->filename.' · '.$credits->count().' credits · '.$debitCount.' debits ignored'">
        <a href="{{ route('bank.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('bank.rows', $import) }}">
        @csrf
        <div class="card overflow-hidden">
            <ul class="divide-y divide-slate-100">
                @forelse ($credits as $t)
                    @php $locked = in_array($t->status, ['posted', 'duplicate']); @endphp
                    <li class="grid gap-2 px-4 py-3 md:grid-cols-[1fr_auto_16rem] md:items-center md:gap-4 {{ $locked ? 'bg-slate-50' : '' }}">
                        <div class="min-w-0">
                            <div class="break-words text-sm font-medium text-slate-900">{{ $t->narration }}</div>
                            <div class="text-xs text-slate-500">{{ $t->txn_date->format('d-m-Y') }} {{ $t->reference ? '· '.$t->reference : '' }}
                                @if ($t->match_reason === 'name')· <span class="text-emerald-700">matched by name</span>
                                @elseif ($t->match_reason === 'amount')· <span class="text-amber-700">matched by amount, please check</span>
                                @elseif ($t->match_reason === 'manual')· <span class="text-brand-600">chosen by you</span>@endif
                            </div>
                        </div>
                        <div class="text-base font-semibold text-emerald-700 md:text-right">₹{{ N::money($t->credit) }}</div>
                        <div>
                            @if ($t->status === 'posted')
                                <span class="badge-paid badge">Saved · {{ $t->client?->name }}</span>
                            @elseif ($t->status === 'duplicate')
                                <span class="badge-muted badge">Already saved earlier</span>
                            @else
                                <select name="rows[{{ $t->id }}]" class="input {{ $t->client_id ? 'border-emerald-300 bg-emerald-50' : ($t->status === 'ignored' ? 'text-slate-400' : 'border-amber-300 bg-amber-50') }}">
                                    <option value="">Not matched</option>
                                    <option value="ignore" @selected($t->status === 'ignored')>Not an exporter payment (ignore)</option>
                                    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($t->client_id === $c->id && $t->status === 'pending')>{{ $c->name }}</option>@endforeach
                                </select>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-slate-500">No money received in this statement.</li>
                @endforelse
            </ul>
        </div>
        @if ($credits->whereIn('status', ['pending', 'ignored'])->isNotEmpty())
            <div class="sticky bottom-16 z-10 mt-4 flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg sm:flex-row sm:items-center sm:justify-between md:bottom-4">
                <span class="text-sm text-slate-600">{{ $pendingMatched->count() }} matched · ₹{{ N::money($pendingMatched->sum('credit')) }}</span>
                <div class="flex flex-wrap gap-2">
                    <button name="then" value="" class="btn btn-secondary">Save matches only</button>
                    <button name="then" value="post" class="btn btn-primary" data-busy="Saving...">Save payments</button>
                </div>
            </div>
        @endif
    </form>

    @unless ($credits->where('status', 'posted')->count())
        <form method="POST" action="{{ route('bank.destroy', $import) }}" class="mt-4" data-confirm="Remove this uploaded statement?">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm"><x-icon name="trash" class="size-4" />Remove statement</button>
        </form>
    @endunless
</x-layouts.app>
