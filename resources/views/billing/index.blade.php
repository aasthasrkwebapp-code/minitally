@php use App\Support\IndianNumber as N; $label = \Carbon\Carbon::parse($month.'-01')->format('F Y'); @endphp
<x-layouts.app title="Month-end billing">
    <x-page-header title="Month-end billing" :subtitle="$label">
        <x-month-picker :month="$month" :action="route('billing.index')" />
    </x-page-header>

    {{-- Step 1 --}}
    <section class="card mb-6 overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold"><span class="mr-1 text-brand-600">Step 1.</span> Make bills</h2>
                <p class="text-xs text-slate-500">Entries of {{ $label }} not billed yet, grouped per exporter.</p>
            </div>
        </div>
        @if ($pending->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-slate-500">All entries of {{ $label }} are billed.</p>
        @else
            <form method="POST" action="{{ route('billing.generate') }}" data-confirm="Make bills for the selected exporters?">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <ul class="divide-y divide-slate-100">
                    <li class="flex items-center gap-3 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">
                        <input type="checkbox" checked data-check-all=".pick-client" class="size-4 rounded border-slate-300"> Select all
                    </li>
                    @foreach ($pending as $row)
                        <li>
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                <input type="checkbox" name="clients[]" value="{{ $row->client->id }}" checked class="pick-client size-4 rounded border-slate-300">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $row->client->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $row->count }} entries @if(!$row->client->email) · <span class="text-amber-600">no email</span>@endif @if(!$row->client->whatsapp) · <span class="text-amber-600">no WhatsApp</span>@endif</span>
                                </span>
                                <span class="text-sm font-semibold">₹{{ N::money($row->total) }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm text-slate-600">Total: <strong>₹{{ N::money($pending->sum('total')) }}</strong></span>
                    <button class="btn btn-primary" data-busy="Making bills..."><x-icon name="bill" class="size-4" />Make bills</button>
                </div>
            </form>
        @endif
    </section>

    {{-- Step 2 --}}
    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold"><span class="mr-1 text-brand-600">Step 2.</span> Send bills</h2>
                <p class="text-xs text-slate-500">{{ $invoices->count() }} bills · ₹{{ N::money($invoices->sum('total')) }} · emailed {{ $invoices->whereNotNull('emailed_at')->count() }} · WhatsApp {{ $invoices->whereNotNull('whatsapp_at')->count() }}</p>
            </div>
            @if ($invoices->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('billing.send-all') }}" data-confirm="Send every unsent bill of {{ $label }} by email and WhatsApp?">
                    @csrf <input type="hidden" name="month" value="{{ $month }}">
                    <button class="btn btn-primary" data-busy="Sending..."><x-icon name="send" class="size-4" />Send all (Email + WhatsApp)</button>
                </form>
                <form method="POST" action="{{ route('billing.email-all') }}" data-confirm="Email every bill of {{ $label }} that has not been emailed yet?">
                    @csrf <input type="hidden" name="month" value="{{ $month }}">
                    <button class="btn btn-secondary" data-busy="Sending emails..."><x-icon name="mail" class="size-4" />Email only</button>
                </form>
                <a href="{{ route('billing.whatsapp', ['month' => $month]) }}" class="btn btn-secondary"><x-icon name="chat" class="size-4" />WhatsApp only</a>
            </div>
            @endif
        </div>
        @include('invoices._list', ['invoices' => $invoices, 'empty' => 'No bills for '.$label.' yet.'])
    </section>

    <p class="mt-6 text-sm text-slate-500">Next: <a href="{{ route('bank.index') }}" class="text-brand-600 underline">upload the bank statement</a> to mark payments, then <a href="{{ route('outstanding.index') }}" class="text-brand-600 underline">send reminders</a>.</p>
</x-layouts.app>
