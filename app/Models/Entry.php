<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of the daily sheet: S.NO, ISSUED DATE, PARTY NAME, REFERENCE NO., INVOICE NUMBER, IR/NA, DESTINATION, CERTIFICATE, AMOUNT, TRANSACTION AMOUNT, MAIL BY */
class Entry extends Model
{
    use HasFactory;

    public const FIELDS = [
        'issued_date', 'party_name', 'reference_no', 'invoice_number', 'ir_na',
        'destination', 'certificate', 'sets', 'amount', 'transaction_amount', 'mail_by',
    ];

    protected $fillable = ['client_id', 'invoice_id', 'quotation_id', ...self::FIELDS];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'amount' => 'decimal:2',
            'transaction_amount' => 'decimal:2',
            'sets' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Link a row to the exporter's accepted quotation. A blank amount is filled with the quoted
     * rate; a typed amount that differs from the quote keeps the typed amount and no link.
     */
    public static function applyQuotedRate(array $row, int $clientId): array
    {
        $sets = (int) ($row['sets'] ?? 1);
        $row['sets'] = in_array($sets, Quotation::SETS, true) ? $sets : 1;
        $quote = Quotation::rateFor($clientId, $row['certificate'] ?? null, $row['ir_na'] ?? null, $row['sets'], $row['issued_date'] ?? null);
        $row['quotation_id'] = null;
        if ($quote && blank($row['amount'] ?? null)) {
            $row['amount'] = $quote['rate'];
        }
        if ($quote && round((float) ($row['amount'] ?? 0), 2) === round($quote['rate'], 2)) {
            $row['quotation_id'] = $quote['quotation_id'];
        }

        return $row;
    }

    /** Which column decides who the month-end bill goes to: party, mail_by or manual. */
    public static function billToMode(): string
    {
        return in_array($mode = Setting::get('bill_to'), ['party', 'mail_by', 'manual']) ? $mode : 'party';
    }

    /**
     * Find (or create) the client to bill for a row, following the "bill to" setting.
     * In manual mode the row must already carry client_id.
     */
    public static function resolveClientId(array $row, bool $create = true): ?int
    {
        $mode = self::billToMode();
        if ($mode === 'manual') {
            return isset($row['client_id']) && $row['client_id'] !== '' ? (int) $row['client_id'] : null;
        }

        $name = trim((string) ($mode === 'mail_by' ? ($row['mail_by'] ?? '') : ($row['party_name'] ?? '')));
        if ($mode === 'mail_by' && $name === '') {
            $name = trim((string) ($row['party_name'] ?? ''));
        }
        if ($name === '') {
            return null;
        }

        $key = Client::normalise($name);
        $client = Client::all(['id', 'name'])->first(fn ($c) => Client::normalise($c->name) === $key)
            ?? ($create ? Client::create(['name' => $name]) : null);

        return $client?->id;
    }
}
