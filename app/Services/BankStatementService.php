<?php

namespace App\Services;

use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Support\Sheet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reads a bank statement (PNB or most Indian bank Excel/CSV formats) and matches credits to clients. */
class BankStatementService
{
    public const PATTERNS = [
        'date' => '/^(txn\.?\s*date|tran(saction)?\.?\s*date|date|post(ing)?\s*date|value\s*date)/',
        'narration' => '/description|narration|particulars|remarks|details/',
        'reference' => '/cheque|chq|ref/',
        'credit' => '/^(credit|cr\.?\s*amount|cr\.?\s*amt|deposits?|deposit\s*amt|cr\.?|amount\s*\(cr\)|credit\s*amount)/',
        'debit' => '/^(debit|dr\.?\s*amount|dr\.?\s*amt|withdrawals?|withdrawal\s*amt|dr\.?|amount\s*\(dr\)|debit\s*amount)/',
        'amount' => '/^(amount|txn\s*amount|transaction\s*amount)$/',
        'drcr' => '/^(dr\s*\/\s*cr|cr\s*\/\s*dr|type|dr\/cr\s*indicator)/',
    ];

    public function import(string $path, string $filename): BankImport
    {
        $rows = Sheet::rows($path);
        $header = Sheet::findHeader($rows, self::PATTERNS, ['date', 'narration', 'credit'])
            ?? Sheet::findHeader($rows, self::PATTERNS, ['date', 'narration', 'amount']);
        if (! $header) {
            throw new RuntimeException('Could not read this statement. It needs columns for Date, Description/Narration and Credit (or Amount with Dr/Cr).');
        }
        [$headerRow, $map] = $header;

        return DB::transaction(function () use ($rows, $headerRow, $map, $filename) {
            $import = BankImport::create(['filename' => $filename]);
            $total = 0;
            $credits = 0;

            foreach (array_slice($rows, $headerRow + 1) as $row) {
                $date = Sheet::date($row[$map['date']] ?? null);
                if (! $date) {
                    continue;
                }
                [$credit, $debit] = $this->amounts($row, $map);
                if ($credit <= 0 && $debit <= 0) {
                    continue;
                }
                $narration = trim(preg_replace('/\s+/', ' ', (string) ($row[$map['narration']] ?? '')));
                $reference = isset($map['reference']) ? trim((string) ($row[$map['reference']] ?? '')) : null;
                $hash = hash('sha256', implode('|', [$date->toDateString(), $narration, $reference, $credit, $debit]));

                $duplicate = BankTransaction::where('hash', $hash)->where('status', 'posted')->exists();

                $txn = new BankTransaction([
                    'txn_date' => $date->toDateString(),
                    'narration' => $narration,
                    'reference' => $reference ?: null,
                    'credit' => $credit,
                    'debit' => $debit,
                    'hash' => $hash,
                    'status' => $duplicate ? 'duplicate' : ($credit > 0 ? 'pending' : 'ignored'),
                ]);

                if ($credit > 0 && ! $duplicate) {
                    [$clientId, $reason] = $this->match($narration, $credit);
                    $txn->client_id = $clientId;
                    $txn->match_reason = $reason;
                    $credits++;
                }

                $import->transactions()->save($txn);
                $total++;
            }

            $import->update(['rows_total' => $total, 'rows_credit' => $credits]);

            return $import;
        });
    }

    private function amounts(array $row, array $map): array
    {
        if (isset($map['credit'])) {
            return [
                Sheet::money($row[$map['credit']] ?? null),
                isset($map['debit']) ? Sheet::money($row[$map['debit']] ?? null) : 0.0,
            ];
        }

        $amount = abs(Sheet::money($row[$map['amount']] ?? null));
        $flag = strtoupper(trim((string) ($row[$map['drcr'] ?? -1] ?? '')));

        return str_starts_with($flag, 'C') ? [$amount, 0.0] : [0.0, $amount];
    }

    /**
     * 1. A client's name or bank keyword appears in the narration (longest wins).
     * 2. Otherwise, exactly one client has an unpaid bill or total due equal to the amount.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public function match(string $narration, float $amount): array
    {
        $text = ' '.Client::normalise($narration).' ';
        $best = null;
        $bestLength = 0;

        foreach (Client::where('is_active', true)->get() as $client) {
            foreach ($client->matchKeywords() as $keyword) {
                if (str_contains($text, ' '.$keyword.' ') && strlen($keyword) > $bestLength) {
                    $best = $client;
                    $bestLength = strlen($keyword);
                }
            }
        }
        if ($best) {
            return [$best->id, 'name'];
        }

        $byAmount = Client::where('is_active', true)->get()->filter(function (Client $client) use ($amount) {
            if (abs($client->outstanding() - $amount) < 0.01) {
                return true;
            }

            return $client->invoices()->where('status', '!=', 'paid')->get()
                ->contains(fn ($i) => abs($i->balance() - $amount) < 0.01);
        });

        return $byAmount->count() === 1 ? [$byAmount->first()->id, 'amount'] : [null, null];
    }

    /** Turn every matched, pending credit into a payment and update bill statuses. */
    public function post(BankImport $import): int
    {
        return DB::transaction(function () use ($import) {
            $posted = 0;
            $clients = [];

            foreach ($import->transactions()->where('status', 'pending')->whereNotNull('client_id')->where('credit', '>', 0)->get() as $txn) {
                $payment = Payment::create([
                    'client_id' => $txn->client_id,
                    'paid_on' => $txn->txn_date,
                    'amount' => $txn->credit,
                    'mode' => 'bank',
                    'reference' => $txn->reference,
                    'narration' => $txn->narration,
                    'source' => 'bank',
                ]);
                $txn->update(['status' => 'posted', 'payment_id' => $payment->id]);
                $clients[$txn->client_id] = true;
                $posted++;
            }

            foreach (array_keys($clients) as $id) {
                Client::find($id)?->reallocatePayments();
            }

            $import->update(['posted_at' => now()]);

            return $posted;
        });
    }
}
