@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="now()->format('l, d F Y')">
        <a href="{{ route('entries.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add entries</a>
        <a href="{{ route('billing.index') }}" class="btn btn-secondary"><x-icon name="send" class="size-4" />Month-end billing</a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat label="Work this month" :value="'₹'.N::money($workThisMonth, false)" :hint="$entriesThisMonth.' entries'" tone="brand" :href="route('entries.index')" />
        <x-stat label="Not billed yet" :value="'₹'.N::money($unbilled, false)" hint="Bill at month end" tone="amber" :href="route('billing.index')" />
        <x-stat label="Collected this month" :value="'₹'.N::money($collectedThisMonth, false)" tone="green" :href="route('payments.index')" />
        <x-stat label="Total outstanding" :value="'₹'.N::money($totalDue, false)" :hint="$topDues->count().' exporters'" tone="red" :href="route('outstanding.index')" />
    </div>

    <div class="mt-6 card card-pad">
        <h2 class="font-semibold text-slate-900">Month-end in 4 steps</h2>
        <ol class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Make bills', 'One bill per exporter from the month\'s entries', route('billing.index'), 'bill'],
                ['Send bills', 'Email with PDF and WhatsApp with link', route('billing.index'), 'send'],
                ['Upload bank statement', 'Payments are matched to exporters', route('bank.index'), 'bank'],
                ['Remind unpaid', 'Email and WhatsApp reminders', route('outstanding.index'), 'bell'],
            ] as $i => [$t, $d, $href, $icon])
                <li><a href="{{ $href }}" class="flex h-full gap-3 rounded-lg border border-slate-200 p-3 hover:border-brand-200 hover:bg-brand-50">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ $i + 1 }}</span>
                    <span><span class="block text-sm font-medium text-slate-900">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                </a></li>
            @endforeach
        </ol>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="font-semibold">Highest outstanding</h2>
                <a href="{{ route('outstanding.index') }}" class="text-sm text-brand-600 hover:underline">All</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($topDues as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <a href="{{ route('clients.show', $row['client']) }}" class="min-w-0 truncate text-sm font-medium hover:text-brand-600">{{ $row['client']->name }}</a>
                        <span class="shrink-0 text-sm font-semibold text-red-600">₹{{ N::money($row['due']) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Nothing outstanding.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="font-semibold">Latest entries</h2>
                <a href="{{ route('entries.index') }}" class="text-sm text-brand-600 hover:underline">All</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentEntries as $e)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium">{{ $e->party_name }}</div>
                            <div class="truncate text-xs text-slate-500">{{ $e->issued_date->format('d M') }} · {{ $e->client->name }} · {{ $e->certificate }}</div>
                        </div>
                        <span class="shrink-0 text-sm font-semibold">₹{{ N::money($e->amount, false) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">No entries yet. <a class="text-brand-600 underline" href="{{ route('entries.import') }}">Import from Excel</a></li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
