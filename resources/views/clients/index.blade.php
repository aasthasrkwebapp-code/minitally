@php use App\Support\IndianNumber as N; @endphp
<x-layouts.app title="Clients">
    <x-page-header title="Clients" subtitle="Who you bill, and where bills are sent">
        <a href="{{ route('clients.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add client</a>
    </x-page-header>

    <form class="mb-4"><input name="q" value="{{ request('q') }}" placeholder="Search name, email or phone" class="input sm:max-w-sm"></form>

    <div class="card overflow-hidden">
        {{-- Phones: cards --}}
        <ul class="divide-y divide-slate-100 md:hidden">
            @forelse ($clients as $c)
                <li><a href="{{ route('clients.show', $c) }}" class="block px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $c->name }}</span>
                        @php $due = $c->outstanding(); @endphp
                        <span class="text-sm font-semibold {{ $due > 0 ? 'text-red-600' : 'text-slate-500' }}">₹{{ N::money($due) }}</span>
                    </div>
                    <div class="mt-0.5 truncate text-xs text-slate-500">{{ $c->email ?: 'No email' }} · {{ $c->whatsapp ?: 'No WhatsApp' }}</div>
                </a></li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">No clients yet.</li>
            @endforelse
        </ul>
        {{-- Tablet and up: table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>WhatsApp</th><th class="text-right">Outstanding</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($clients as $c)
                    <tr>
                        <td><a href="{{ route('clients.show', $c) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $c->name }}</a>
                            @unless ($c->is_active)<span class="badge-muted badge ml-1">Inactive</span>@endunless</td>
                        <td>{{ $c->email ?: '—' }}</td>
                        <td>{{ $c->whatsapp ?: '—' }}</td>
                        @php $due = $c->outstanding(); @endphp
                        <td class="text-right font-semibold {{ $due > 0 ? 'text-red-600' : 'text-slate-500' }}">₹{{ N::money($due) }}</td>
                        <td class="text-right"><a href="{{ route('clients.edit', $c) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No clients yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>
</x-layouts.app>
