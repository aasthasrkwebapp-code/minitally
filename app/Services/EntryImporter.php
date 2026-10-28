<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Entry;
use App\Support\Sheet;
use RuntimeException;

/** Imports the office's daily sheet (same columns as the Entries screen). */
class EntryImporter
{
    public const PATTERNS = [
        'issued_date' => '/^(issued\s*date|issue\s*date|date)/',
        'party_name' => '/^party/',
        'reference_no' => '/^ref(erence)?\.?\s*(no|number)?/',
        'invoice_number' => '/^inv(oice)?\.?\s*(no|number)/',
        'ir_na' => '/^(ir\s*\/\s*na|ir\/na|irna)/',
        'destination' => '/^(destination|country)/',
        'certificate' => '/^cert/',
        'amount' => '/^(amount|amt|fees?)\.?$/',
        'transaction_amount' => '/^(transaction|txn)\s*(amount|amt)/',
        'mail_by' => '/^mail\s*by/',
        'client' => '/^(exporter|client|bill\s*to)/',
    ];

    /** @return array{imported:int, skipped:int, duplicates:int, skipped_rows:array<int,int>, new_clients:array<int,string>} */
    public function import(string $path): array
    {
        $rows = Sheet::rows($path);
        $header = Sheet::findHeader($rows, self::PATTERNS, ['issued_date', 'party_name', 'amount']);
        if (! $header) {
            throw new RuntimeException('Could not find the heading row. The sheet needs at least: ISSUED DATE, PARTY NAME, AMOUNT.');
        }
        [$headerRow, $map] = $header;

        $before = Client::pluck('id')->all();
        $imported = 0;
        $skipped = 0;
        $skippedRows = [];
        $duplicates = 0;
        $seen = [];

        foreach (array_slice($rows, $headerRow + 1, null, true) as $index => $row) {
            $get = fn ($field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
            $date = Sheet::date($row[$map['issued_date']] ?? null);
            $amount = Sheet::money($row[$map['amount']] ?? null);

            if (! $date || $amount <= 0 || $get('party_name') === '') {
                $filled = array_filter($row, fn ($c) => $c !== null && trim((string) $c) !== '');
                $isTotalRow = (bool) array_filter($filled, fn ($c) => preg_match('/^\s*(grand\s*)?total\b/i', (string) $c));
                if ($filled && ! $isTotalRow) {
                    $skipped++;
                    $skippedRows[] = $index + 1; // Excel row number
                }

                continue;
            }

            $data = [
                'issued_date' => $date->toDateString(),
                'party_name' => $get('party_name'),
                'reference_no' => $get('reference_no') ?: null,
                'invoice_number' => $get('invoice_number') ?: null,
                'ir_na' => $get('ir_na') ?: null,
                'destination' => $get('destination') ?: null,
                'certificate' => $get('certificate') ?: null,
                'amount' => $amount,
                'transaction_amount' => isset($map['transaction_amount']) && $get('transaction_amount') !== '' ? Sheet::money($get('transaction_amount')) : null,
                'mail_by' => $get('mail_by') ?: null,
            ];

            // Importing the same sheet again must not double the entries. A row counts as already
            // imported when the same date, party, reference no., invoice number and amount exist
            // (as many times as it appears in this sheet).
            $key = implode('|', [$data['issued_date'], Client::normalise($data['party_name']), $data['reference_no'], $data['invoice_number'], number_format($amount, 2, '.', '')]);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $existing = Entry::whereDate('issued_date', $data['issued_date'])->where('party_name', $data['party_name'])
                ->where('reference_no', $data['reference_no'])->where('invoice_number', $data['invoice_number'])
                ->where('amount', $amount)->count();
            if ($existing >= $seen[$key]) {
                $duplicates++;

                continue;
            }

            // An "Exporter"/"Client"/"Bill to" column, if present, always wins
            $clientId = match (true) {
                $get('client') !== '' => $this->clientByName($get('client')),
                Entry::billToMode() === 'manual' => $this->clientByName($data['party_name']),
                default => Entry::resolveClientId($data),
            };
            if (! $clientId) {
                $skipped++;

                continue;
            }

            Entry::create($data + ['client_id' => $clientId]);
            $imported++;
        }

        $newClients = Client::whereNotIn('id', $before)->orderBy('name')->pluck('name')->all();

        return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates, 'skipped_rows' => $skippedRows, 'new_clients' => $newClients];
    }

    private function clientByName(string $name): int
    {
        $key = Client::normalise($name);

        return (Client::all(['id', 'name'])->first(fn ($c) => Client::normalise($c->name) === $key)
            ?? Client::create(['name' => $name]))->id;
    }
}
