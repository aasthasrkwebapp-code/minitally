<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Entry;
use App\Models\Quotation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Quotations: rates per certificate, type (IR / Normal) and sets. Accepted ones fill entry amounts. */
class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $quotations = Quotation::with('client')->withCount('items', 'entries')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->orderByDesc('quote_date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $quotation = new Quotation([
            'client_id' => $request->integer('client_id') ?: null,
            'quote_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'draft',
        ]);

        return view('quotations.form', $this->formData($quotation));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($data) {
            $quotation = Quotation::create($data['head'] + ['number' => Quotation::nextNumber(Carbon::parse($data['head']['quote_date']))]);
            $this->saveItems($quotation, $data['items']);

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)->with('ok', "Quotation {$quotation->number} saved.");
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('client', 'items');

        return view('quotations.show', [
            'quotation' => $quotation,
            'entries' => $quotation->entries()->latest('issued_date')->limit(100)->get(),
            'entryCount' => $quotation->entries()->count(),
        ]);
    }

    public function edit(Quotation $quotation)
    {
        $quotation->load('items');

        return view('quotations.form', $this->formData($quotation));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($quotation, $data) {
            $quotation->update($data['head']);
            $quotation->items()->delete();
            $this->saveItems($quotation, $data['items']);
        });

        return redirect()->route('quotations.show', $quotation)->with('ok', "Quotation {$quotation->number} updated.");
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete(); // linked entries keep their amount; only the link is cleared

        return redirect()->route('quotations.index')->with('ok', "Quotation {$quotation->number} deleted.");
    }

    public function status(Request $request, Quotation $quotation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Quotation::STATUSES))]]);
        $quotation->update($data);

        $msg = $data['status'] === 'accepted'
            ? "Quotation {$quotation->number} accepted. New daily entries for {$quotation->client->name} will take these rates."
            : "Quotation {$quotation->number} marked ".strtolower(Quotation::STATUSES[$data['status']]).'.';

        return back()->with('ok', $msg);
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $quotation->load('client', 'items');
        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation, 's' => Setting::allWithDefaults()])
            ->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

        return $request->boolean('download') ? $pdf->download($quotation->fileName()) : $pdf->stream($quotation->fileName());
    }

    /** Used by the daily entry form to fill Amount from the exporter's accepted quotation. */
    public function rate(Request $request)
    {
        $row = $request->only(['party_name', 'mail_by', 'client_id', 'certificate', 'ir_na', 'sets', 'issued_date']);
        $clientId = Entry::resolveClientId($row, create: false);
        $quote = Quotation::rateFor($clientId, $row['certificate'] ?? null, $row['ir_na'] ?? null, (int) ($row['sets'] ?? 1), $row['issued_date'] ?? null);

        return response()->json($quote);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.certificate' => ['nullable', 'string', 'max:255'],
            'items.*.ir_na' => ['nullable', Rule::in(array_keys(Quotation::TYPES))],
            'items.*.sets' => ['nullable', Rule::in(Quotation::SETS)],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = [];
        foreach ($data['items'] as $i => $item) {
            if (blank($item['certificate'] ?? null) && blank($item['rate'] ?? null)) {
                continue;
            }
            if (blank($item['certificate'] ?? null) || blank($item['rate'] ?? null)) {
                throw ValidationException::withMessages(["items.$i.certificate" => 'Row '.($i + 1).': certificate and rate are needed.']);
            }
            $items[] = [
                'certificate' => trim($item['certificate']),
                'ir_na' => $item['ir_na'] ?? 'NA',
                'sets' => (int) ($item['sets'] ?? 1),
                'rate' => $item['rate'],
            ];
        }
        if (! $items) {
            throw ValidationException::withMessages(['items.0.certificate' => 'Add at least one certificate with a rate.']);
        }

        return ['head' => collect($data)->only(['client_id', 'quote_date', 'valid_until', 'notes'])->all(), 'items' => $items];
    }

    private function saveItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->createMany($items);
        $quotation->update(['total' => collect($items)->sum(fn ($i) => (float) $i['rate'])]);
    }

    private function formData(Quotation $quotation): array
    {
        return [
            'quotation' => $quotation,
            'clients' => Client::where('is_active', true)->orWhere('id', $quotation->client_id)->orderBy('name')->get(['id', 'name']),
            'certificates' => Entry::query()->whereNotNull('certificate')->distinct()->orderBy('certificate')->limit(200)->pluck('certificate'),
        ];
    }
}
