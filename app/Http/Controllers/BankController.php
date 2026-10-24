<?php

namespace App\Http\Controllers;

use App\Models\BankImport;
use App\Models\Client;
use App\Services\BankStatementService;
use App\Services\SampleFiles;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        return view('bank.index', [
            'imports' => BankImport::withCount([
                'transactions as matched_count' => fn ($q) => $q->where('status', 'pending')->whereNotNull('client_id'),
                'transactions as posted_count' => fn ($q) => $q->where('status', 'posted'),
            ])->latest()->paginate(20),
        ]);
    }

    public function upload(Request $request, BankStatementService $service)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);

        try {
            $import = $service->import($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('bank.show', $import)->with('ok', 'Statement read. Check the matches below, then press "Save payments".');
    }

    public function show(BankImport $import)
    {
        $rows = $import->transactions()->with('client')->get();

        return view('bank.show', [
            'import' => $import,
            'credits' => $rows->where('credit', '>', 0),
            'debitCount' => $rows->where('credit', '<=', 0)->count(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateRows(Request $request, BankImport $import)
    {
        $data = $request->validate(['rows' => ['array'], 'rows.*' => ['nullable', 'string']]);
        $learn = $request->boolean('learn', true);

        foreach ($data['rows'] ?? [] as $id => $value) {
            $txn = $import->transactions()->where('id', $id)->whereIn('status', ['pending', 'ignored'])->first();
            if (! $txn || $txn->credit <= 0) {
                continue;
            }
            if ($value === 'ignore') {
                $txn->update(['status' => 'ignored', 'client_id' => null, 'match_reason' => null]);
            } elseif ($value) {
                $changed = (int) $value !== $txn->client_id;
                $txn->update(['status' => 'pending', 'client_id' => (int) $value, 'match_reason' => $changed ? 'manual' : $txn->match_reason]);
                if ($changed && $learn) {
                    $this->learnKeyword(Client::find($value), $txn->narration);
                }
            } else {
                $txn->update(['status' => 'pending', 'client_id' => null, 'match_reason' => null]);
            }
        }

        if ($request->input('then') === 'post') {
            return $this->post($import, app(BankStatementService::class));
        }

        return back()->with('ok', 'Matches saved.');
    }

    public function post(BankImport $import, BankStatementService $service)
    {
        $count = $service->post($import);

        return redirect()->route('bank.show', $import)->with('ok', "$count payment(s) saved and bills updated.");
    }

    public function destroy(BankImport $import)
    {
        if ($import->transactions()->where('status', 'posted')->exists()) {
            return back()->withErrors(['file' => 'This statement already has saved payments. Delete those payments first.']);
        }
        $import->delete();

        return redirect()->route('bank.index')->with('ok', 'Statement removed.');
    }

    public function sample(SampleFiles $samples)
    {
        return response()->download($samples->bankStatement(), 'sample-bank-statement.xlsx')->deleteFileAfterSend();
    }

    /**
     * Remember the payer name from a manually matched narration, so next month it matches by itself.
     * UPI/NEFT narrations look like "UPI/123/SHARMA EXPORTS/..." or "NEFT-HDFC0001-SHARMA EXPORTS-...".
     */
    private function learnKeyword(?Client $client, ?string $narration): void
    {
        if (! $client || ! $narration) {
            return;
        }
        $stop = '/^(UPI|NEFT|IMPS|RTGS|CR|DR|TRF|TO|FROM|BY|INB|MOB|PAYMENT|TRANSFER|CLG|CHQ|CHEQUE|P2A|P2P|BIL|ONL|INF|INFT|ACH|ECS|XX)$/';
        $keyword = collect(preg_split('/[\/\-:|]+/', $narration))
            ->map(fn ($part) => collect(preg_split('/\s+/', trim($part)))
                ->reject(fn ($w) => $w === '' || preg_match('/[0-9@.]/', $w) || preg_match($stop, strtoupper($w)))
                ->implode(' '))
            ->filter(fn ($p) => strlen(Client::normalise($p)) >= 4)
            ->sortByDesc(fn ($p) => strlen($p))
            ->first();
        if (! $keyword) {
            return;
        }
        $existing = array_filter(array_map('trim', explode(',', (string) $client->bank_keywords)));
        if (! in_array(strtoupper($keyword), array_map('strtoupper', $existing))) {
            $existing[] = $keyword;
            $client->update(['bank_keywords' => implode(', ', $existing)]);
        }
    }
}
