<x-layouts.app title="Bank statement">
    <x-page-header title="Bank statement" subtitle="Upload your statement and payments are matched to clients" />
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('bank.upload') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Statement file (Excel or CSV)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
            </div>
            <button class="btn btn-primary" data-busy="Reading..."><x-icon name="upload" class="size-4" />Upload and match</button>
        </form>
        <div class="card card-pad text-sm text-slate-600">
            <h2 class="font-semibold text-slate-900">How matching works</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Only money received (credit) is looked at.</li>
                <li>If the client's name, or their "name in bank statement", is in the description, it is matched.</li>
                <li>Otherwise, if exactly one client owes that exact amount, it is matched by amount.</li>
                <li>You check and fix the matches, then press Save. Fixed names are remembered for next month.</li>
                <li>Uploading the same statement again will not double count payments.</li>
            </ul>
            <p class="mt-2">Works with PNB net banking downloads (XLS/XLSX/CSV) and most other banks.</p>
            <a href="{{ route('bank.sample') }}" class="btn btn-secondary mt-3"><x-icon name="download" class="size-4" />Download sample statement</a>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Uploaded statements</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($imports as $imp)
                <li><a href="{{ route('bank.show', $imp) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">{{ $imp->filename }}</div>
                        <div class="text-xs text-slate-500">{{ $imp->created_at->format('d M Y, H:i') }} · {{ $imp->rows_credit }} credits</div>
                    </div>
                    @if ($imp->posted_count)<span class="badge-paid badge">{{ $imp->posted_count }} saved</span>@endif
                    @if ($imp->matched_count)<span class="badge-partial badge">{{ $imp->matched_count }} to save</span>@endif
                </a></li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No statements uploaded yet.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $imports->links() }}</div>
</x-layouts.app>
