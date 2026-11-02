<x-layouts.app title="Bills">
    <x-page-header title="Bills" subtitle="All bills made so far" />
    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <input type="month" name="month" value="{{ request('month') }}" class="input sm:w-auto" data-autosubmit>
        <select name="status" class="input sm:w-40" data-autosubmit>
            <option value="">Any status</option>
            @foreach (['due' => 'Not fully paid', 'unpaid' => 'Unpaid', 'partial' => 'Part paid', 'paid' => 'Paid'] as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="client_id" class="input col-span-2 sm:w-56" data-autosubmit>
            <option value="">All exporters</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>
    <div class="card overflow-hidden">@include('invoices._list', ['invoices' => $invoices])</div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
