<x-layouts.app title="Import entries">
    <x-page-header title="Import entries from Excel" />
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('entries.import.store') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Excel or CSV file</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
            </div>
            <button class="btn btn-primary" data-busy="Importing..."><x-icon name="upload" class="size-4" />Import</button>
        </form>
        <div class="card card-pad text-sm text-slate-600">
            <h2 class="font-semibold text-slate-900">Your sheet's columns</h2>
            <p class="mt-2 font-medium text-slate-800">S.NO, ISSUED DATE, PARTY NAME, REFERENCE NO., INVOICE NUMBER, IR/NA, DESTINATION, CERTIFICATE, AMOUNT, TRANSACTION AMOUNT, MAIL BY</p>
            <p class="mt-2">Issued date, Party name and Amount are required. Column order does not matter, and a few title rows above the heading are fine.</p>
            <p class="mt-2">Bills go to: <strong>{{ ['party' => 'Party name', 'mail_by' => 'Mail by', 'manual' => 'an "Exporter" column in the sheet (or Party name if missing)'][$mode] }}</strong> (change in Settings). New names become exporters automatically.</p>
            <p class="mt-2">Importing the same sheet again is safe: rows already in the software are skipped, only new rows are added. So you can keep adding to one Excel file and import it every day.</p>
            <a href="{{ route('entries.sample') }}" class="btn btn-secondary mt-4"><x-icon name="download" class="size-4" />Download sample (50 entries)</a>
        </div>
    </div>
</x-layouts.app>
