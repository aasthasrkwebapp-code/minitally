@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Exporters">
    <x-page-header title="Exporters" subtitle="Who you bill, their DGFT / ICEGATE logins and DSC">
        <a href="{{ route('clients.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add exporter</a>
    </x-page-header>

    @if ($dscAlerts && request('dsc') !== 'alert')
        <a href="{{ route('clients.index', ['dsc' => 'alert']) }}" class="mb-4 block rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>{{ $dscAlerts }} {{ $dscAlerts === 1 ? 'exporter has a DSC' : 'exporters have a DSC' }}</strong> expired or ending within {{ App\Models\Client::DSC_WARN_DAYS }} days. Tap to see them.
        </a>
    @endif

    <form class="mb-4 flex flex-wrap items-center gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search name, email, phone, DGFT or ICEGATE ID" class="input sm:max-w-sm">
        @if (request('dsc') === 'alert')
            <input type="hidden" name="dsc" value="alert">
            <a href="{{ route('clients.index', request()->except('dsc', 'page')) }}" class="btn btn-secondary btn-sm">Showing DSC alerts only · Show all</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        {{-- Phones: cards --}}
        <ul class="divide-y divide-slate-100 md:hidden">
            @forelse ($clients as $c)
                <li><a href="{{ route('clients.show', $c) }}" class="block px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $c->name }} <x-dsc-badge :client="$c" class="ml-1" /></span>
                        @php $due = $c->outstanding(); @endphp
                        <span class="text-sm font-semibold {{ $due > 0 ? 'text-red-600' : 'text-slate-500' }}">₹{{ N::money($due) }}</span>
                    </div>
                    <div class="mt-0.5 truncate text-xs text-slate-500">{{ $c->email ?: 'No email' }} · {{ $c->whatsapp ?: 'No mobile' }}@if ($c->dsc_valid_till) · DSC till {{ $c->dsc_valid_till->format('d-m-Y') }}@endif</div>
                </a></li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">No exporters yet.</li>
            @endforelse
        </ul>
        {{-- Tablet and up: table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <thead><tr><th>Exporter name</th><th>Email ID</th><th>Mobile</th><th>DSC validity</th><th class="text-right">Outstanding</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($clients as $c)
                    <tr>
                        <td><a href="{{ route('clients.show', $c) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $c->name }}</a>
                            @unless ($c->is_active)<span class="badge-muted badge ml-1">Inactive</span>@endunless</td>
                        <td>{{ $c->email ?: '—' }}</td>
                        <td>{{ $c->whatsapp ?: '—' }}</td>
                        <td class="whitespace-nowrap">{{ $c->dsc_valid_till?->format('d-m-Y') ?? '—' }} <x-dsc-badge :client="$c" class="ml-1" /></td>
                        @php $due = $c->outstanding(); @endphp
                        <td class="text-right font-semibold {{ $due > 0 ? 'text-red-600' : 'text-slate-500' }}">₹{{ N::money($due) }}</td>
                        <td class="text-right"><a href="{{ route('clients.edit', $c) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No exporters yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>
</x-layouts.app>
