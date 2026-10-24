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
        'destination', 'certificate', 'amount', 'transaction_amount', 'mail_by',
    ];

    protected $fillable = ['client_id', 'invoice_id', ...self::FIELDS];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'amount' => 'decimal:2',
            'transaction_amount' => 'decimal:2',
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

    /** Which column decides who the month-end bill goes to: party, mail_by or manual. */
    public static function billToMode(): string
    {
        return in_array($mode = Setting::get('bill_to'), ['party', 'mail_by', 'manual']) ? $mode : 'party';
    }

    /**
     * Find (or create) the client to bill for a row, following the "bill to" setting.
     * In manual mode the row must already carry client_id.
     */
    public static function resolveClientId(array $row): ?int
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
            ?? Client::create(['name' => $name]);

        return $client->id;
    }
}
