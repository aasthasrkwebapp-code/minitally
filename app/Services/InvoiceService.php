<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Entry;
use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Per-client totals of entries in the month that are not yet billed.
     *
     * @return Collection<int, object{client: Client, count: int, total: float}>
     */
    public function pendingForMonth(string $period): Collection
    {
        [$start, $end] = $this->monthRange($period);

        return Entry::query()
            ->whereNull('invoice_id')
            ->whereBetween('issued_date', [$start, $end])
            ->selectRaw('client_id, COUNT(*) as entry_count, SUM(amount) as entry_total')
            ->groupBy('client_id')
            ->get()
            ->map(fn ($row) => (object) [
                'client' => Client::find($row->client_id),
                'count' => (int) $row->entry_count,
                'total' => (float) $row->entry_total,
            ])
            ->filter(fn ($row) => $row->client)
            ->sortBy(fn ($row) => $row->client->name)
            ->values();
    }

    /**
     * Make one bill per client for every unbilled entry in the month.
     * If the client already has a bill for that month, new entries are added to it.
     *
     * @return Collection<int, Invoice>
     */
    public function generateForMonth(string $period, ?array $clientIds = null): Collection
    {
        [$start, $end] = $this->monthRange($period);
        $made = collect();

        foreach ($this->pendingForMonth($period) as $row) {
            if ($clientIds !== null && ! in_array($row->client->id, $clientIds)) {
                continue;
            }

            $made->push(DB::transaction(function () use ($row, $period, $start, $end) {
                $invoice = Invoice::where('client_id', $row->client->id)->where('period', $period)->first()
                    ?? Invoice::create([
                        'client_id' => $row->client->id,
                        'number' => $this->nextNumber(Carbon::parse($end)),
                        'invoice_date' => $this->invoiceDate($end),
                        'period' => $period,
                        'public_token' => Str::random(40),
                    ]);

                Entry::where('client_id', $row->client->id)
                    ->whereNull('invoice_id')
                    ->whereBetween('issued_date', [$start, $end])
                    ->update(['invoice_id' => $invoice->id]);

                $this->refreshTotal($invoice);

                return $invoice->fresh();
            }));
        }

        return $made;
    }

    public function refreshTotal(Invoice $invoice): void
    {
        $invoice->forceFill(['total' => $invoice->entries()->sum('amount')])->save();
        $invoice->client->reallocatePayments();
    }

    /** Bills are dated the last day of the month, or today if the month is not over yet. */
    private function invoiceDate(string $monthEnd): string
    {
        $end = Carbon::parse($monthEnd);

        return $end->isFuture() ? now()->toDateString() : $end->toDateString();
    }

    /** e.g. RC/26-27/0001, numbering restarts each financial year (April). */
    public function nextNumber(Carbon $date): string
    {
        $prefix = Setting::get('invoice_prefix') ?: 'INV';
        $fyStart = $date->month >= 4 ? $date->year : $date->year - 1;
        $fy = sprintf('%02d-%02d', $fyStart % 100, ($fyStart + 1) % 100);
        $base = "{$prefix}/{$fy}/";

        $last = Invoice::where('number', 'like', $base.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($base)))
            ->max() ?? 0;

        $configured = (int) Setting::get('invoice_next_number', '1');
        $seq = max($last + 1, $last === 0 ? $configured : 1);

        return $base.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function monthRange(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    public function pdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing('client', 'entries');

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            's' => Setting::allWithDefaults(),
        ])->setPaper('a4');
    }

    public function pdfContent(Invoice $invoice): string
    {
        return $this->pdf($invoice)->output();
    }
}
