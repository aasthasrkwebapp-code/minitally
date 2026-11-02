<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A rate quote to an exporter. Once accepted, its rates fill the Amount on daily entries. */
class Quotation extends Model
{
    /** Values stored in ir_na, shown with these labels. Same values as the IR/NA column on entries. */
    public const TYPES = ['IR' => 'IR (Retrospective)', 'NA' => 'Normal'];

    /** Number of sets. Test option: the exact meaning (copies of a certificate?) is still to be confirmed. */
    public const SETS = [1, 2, 3];

    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];

    protected $fillable = ['client_id', 'number', 'quote_date', 'valid_until', 'status', 'notes', 'total'];

    protected function casts(): array
    {
        return ['quote_date' => 'date', 'valid_until' => 'date', 'total' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class)->orderBy('issued_date')->orderBy('id');
    }

    public static function typeLabel(?string $irNa): string
    {
        return self::TYPES[self::normaliseType($irNa)] ?? (string) $irNa;
    }

    /** IR stays IR; NA, Normal or blank count as NA (Normal). */
    public static function normaliseType(?string $irNa): string
    {
        return strtoupper(trim((string) $irNa)) === 'IR' ? 'IR' : 'NA';
    }

    /**
     * The accepted rate for an exporter, or null. Matches certificate (ignoring case and spaces),
     * type and sets, using the newest accepted quotation that is still valid on $date.
     *
     * @return array{rate: float, quotation_id: int, number: string}|null
     */
    public static function rateFor(?int $clientId, ?string $certificate, ?string $irNa, ?int $sets, $date = null): ?array
    {
        $certificate = self::normaliseCertificate($certificate);
        if (! $clientId || $certificate === '') {
            return null;
        }
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $item = QuotationItem::query()
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->where('quotations.client_id', $clientId)
            ->where('quotations.status', 'accepted')
            ->where('quotations.quote_date', '<=', $date.' 23:59:59')
            ->where(fn ($q) => $q->whereNull('quotations.valid_until')->orWhere('quotations.valid_until', '>=', $date))
            ->where('quotation_items.ir_na', self::normaliseType($irNa))
            ->where('quotation_items.sets', max(1, (int) $sets))
            ->orderByDesc('quotations.quote_date')->orderByDesc('quotations.id')
            ->get(['quotation_items.*', 'quotations.number'])
            ->first(fn ($i) => self::normaliseCertificate($i->certificate) === $certificate);

        return $item ? ['rate' => (float) $item->rate, 'quotation_id' => (int) $item->quotation_id, 'number' => $item->number] : null;
    }

    public static function normaliseCertificate(?string $certificate): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $certificate)));
    }

    /** e.g. QT/26-27/0001, numbering restarts each financial year (April). */
    public static function nextNumber(Carbon $date): string
    {
        $fyStart = $date->month >= 4 ? $date->year : $date->year - 1;
        $base = sprintf('QT/%02d-%02d/', $fyStart % 100, ($fyStart + 1) % 100);
        $last = self::where('number', 'like', $base.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($base)))->max() ?? 0;

        return $base.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function fileName(): string
    {
        return 'Quotation-'.str_replace(['/', '\\'], '-', $this->number).'.pdf';
    }
}
