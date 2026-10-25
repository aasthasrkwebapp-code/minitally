<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Entry;
use App\Services\EntryImporter;
use App\Services\InvoiceService;
use App\Services\SampleFiles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EntryController extends Controller
{
    private const ROW_RULES = [
        'party_name' => ['nullable', 'string', 'max:255'],
        'reference_no' => ['nullable', 'string', 'max:100'],
        'invoice_number' => ['nullable', 'string', 'max:100'],
        'ir_na' => ['nullable', 'string', 'max:20'],
        'destination' => ['nullable', 'string', 'max:100'],
        'certificate' => ['nullable', 'string', 'max:255'],
        'amount' => ['nullable', 'numeric', 'min:0'],
        'transaction_amount' => ['nullable', 'numeric', 'min:0'],
        'mail_by' => ['nullable', 'string', 'max:255'],
        'client_id' => ['nullable', 'exists:clients,id'],
    ];

    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$start, $end] = app(InvoiceService::class)->monthRange($month);

        $query = Entry::with('client', 'invoice')
            ->whereBetween('issued_date', [$start, $end])
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('party_name', 'like', "%$s%")
                ->orWhere('reference_no', 'like', "%$s%")->orWhere('invoice_number', 'like', "%$s%")
                ->orWhere('certificate', 'like', "%$s%")->orWhere('mail_by', 'like', "%$s%")));

        return view('entries.index', [
            'entries' => (clone $query)->orderBy('issued_date')->orderBy('id')->paginate(100)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'month' => $month,
            'mode' => Entry::billToMode(),
        ]);
    }

    public function create()
    {
        return view('entries.create', $this->formData());
    }

    public function store(Request $request)
    {
        $rules = ['issued_date' => ['required', 'date'], 'rows' => ['required', 'array', 'min:1']];
        foreach (self::ROW_RULES as $field => $rule) {
            $rules["rows.*.$field"] = $rule;
        }
        $data = $request->validate($rules);

        $saved = 0;
        foreach ($data['rows'] as $i => $row) {
            if (blank($row['party_name'] ?? null) && blank($row['amount'] ?? null)) {
                continue;
            }
            if (blank($row['party_name'] ?? null) || blank($row['amount'] ?? null)) {
                throw ValidationException::withMessages(["rows.$i.party_name" => 'Row '.($i + 1).': party name and amount are needed.']);
            }
            $row['issued_date'] = $data['issued_date'];
            $clientId = Entry::resolveClientId($row);
            if (! $clientId) {
                throw ValidationException::withMessages(["rows.$i.client_id" => 'Row '.($i + 1).': choose who to bill.']);
            }
            Entry::create(collect($row)->only(Entry::FIELDS)->all() + ['client_id' => $clientId]);
            $saved++;
        }

        return redirect()->route('entries.index', ['month' => substr($data['issued_date'], 0, 7)])
            ->with('ok', "$saved entr".($saved === 1 ? 'y' : 'ies').' saved.');
    }

    public function edit(Entry $entry)
    {
        return view('entries.edit', ['entry' => $entry] + $this->formData());
    }

    public function update(Request $request, Entry $entry)
    {
        $data = $request->validate(['issued_date' => ['required', 'date']] + self::ROW_RULES + [
            'party_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);
        $clientId = Entry::resolveClientId($data + ['client_id' => $entry->client_id]);

        $invoice = $entry->invoice;
        if ($invoice && ($clientId !== $entry->client_id || substr($data['issued_date'], 0, 7) !== $invoice->period)) {
            return back()->withInput()->withErrors(['party_name' => 'This entry is already billed in '.$invoice->number.'. Delete that bill first to move the entry to another client or month.']);
        }

        $entry->update(collect($data)->only(Entry::FIELDS)->all() + ['client_id' => $clientId]);
        if ($invoice) {
            app(InvoiceService::class)->refreshTotal($invoice);
        }

        return redirect()->route('entries.index', ['month' => $entry->issued_date->format('Y-m')])->with('ok', 'Entry updated.');
    }

    public function destroy(Entry $entry)
    {
        $invoice = $entry->invoice;
        $month = $entry->issued_date->format('Y-m');
        $entry->delete();
        if ($invoice) {
            app(InvoiceService::class)->refreshTotal($invoice);
        }

        return redirect()->route('entries.index', ['month' => $month])->with('ok', 'Entry deleted.');
    }

    public function importForm()
    {
        return view('entries.import', ['mode' => Entry::billToMode()]);
    }

    public function import(Request $request, EntryImporter $importer)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);

        try {
            $result = $importer->import($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $msg = "{$result['imported']} entries imported.";
        if ($result['duplicates']) {
            $msg .= " {$result['duplicates']} rows were already in the software, so they were not added again.";
        }
        if ($result['skipped']) {
            $msg .= " {$result['skipped']} rows skipped because the date, party name or amount was missing or unreadable (Excel row ".implode(', ', array_slice($result['skipped_rows'], 0, 15)).(count($result['skipped_rows']) > 15 ? ', ...' : '').').';
        }
        if ($result['new_clients']) {
            $msg .= ' '.count($result['new_clients']).' new client(s) added: '.implode(', ', $result['new_clients']).'. Add their email and WhatsApp on the Clients page.';
        }

        return redirect()->route('entries.index')->with('ok', $msg);
    }

    public function sample(SampleFiles $samples)
    {
        return response()->download($samples->entriesWorkbook(), 'daily-entries-sample.xlsx')->deleteFileAfterSend();
    }

    private function formData(): array
    {
        $distinct = fn ($col, $limit = 100) => Entry::query()->whereNotNull($col)->distinct()->orderBy($col)->limit($limit)->pluck($col);

        return [
            'clients' => Client::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'mode' => Entry::billToMode(),
            'suggest' => [
                'party_name' => Client::orderBy('name')->pluck('name')->merge($distinct('party_name', 300))->unique()->values(),
                'ir_na' => $distinct('ir_na')->merge(['IR', 'NA'])->unique()->values(),
                'destination' => $distinct('destination'),
                'certificate' => $distinct('certificate'),
                'mail_by' => $distinct('mail_by'),
            ],
        ];
    }
}
