<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An exporter (shown as "Exporter" everywhere; the table is still `clients`). */
class Client extends Model
{
    use HasFactory;

    public const DSC_STATUSES = ['available' => 'Available', 'delivered' => 'Delivered', 'expired' => 'Expired'];

    /** Days before DSC validity ends that a warning is shown. */
    public const DSC_WARN_DAYS = 30;

    protected $fillable = [
        'name', 'contact_person', 'email', 'whatsapp', 'address',
        'bank_keywords', 'opening_balance', 'is_active',
        'dgft_user_id', 'dgft_password', 'icegate_id', 'icegate_password',
        'dsc_valid_till', 'dsc_status',
    ];

    protected $hidden = ['dgft_password', 'icegate_password'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'dgft_password' => 'encrypted',
            'icegate_password' => 'encrypted',
            'dsc_valid_till' => 'date',
        ];
    }

    /** Days left on the DSC (negative once past), or null when no date is set. */
    public function dscDaysLeft(): ?int
    {
        return $this->dsc_valid_till ? (int) now()->startOfDay()->diffInDays($this->dsc_valid_till, false) : null;
    }

    /** expired, expiring (within DSC_WARN_DAYS), ok, or null when nothing is filled in. */
    public function dscAlert(): ?string
    {
        $days = $this->dscDaysLeft();
        if ($this->dsc_status === 'expired' || ($days !== null && $days < 0)) {
            return 'expired';
        }
        if ($days !== null && $days <= self::DSC_WARN_DAYS) {
            return 'expiring';
        }

        return $this->dsc_status || $days !== null ? 'ok' : null;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Opening balance + all bills - all payments. */
    public function outstanding(): float
    {
        return round((float) $this->opening_balance
            + (float) $this->invoices()->sum('total')
            - (float) $this->payments()->sum('amount'), 2);
    }

    /** Keywords used to recognise this client in bank narrations. */
    public function matchKeywords(): array
    {
        $words = array_filter(array_map('trim', explode(',', (string) $this->bank_keywords)));
        $words[] = $this->name;

        return array_values(array_unique(array_filter(array_map(
            fn ($w) => self::normalise($w),
            $words
        ), fn ($w) => strlen($w) >= 4)));
    }

    public static function normalise(string $text): string
    {
        $text = strtoupper($text);
        $text = preg_replace('/\b(M\/S|MS|PVT|PRIVATE|LTD|LIMITED|LLP|AND|THE|CO|COMPANY)\b/', ' ', $text);
        $text = preg_replace('/[^A-Z0-9]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** International WhatsApp number without + or spaces, India assumed for 10 digits. */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Spread all of this client's payments over its bills, oldest first,
     * after first using them against the opening balance.
     */
    public function reallocatePayments(): void
    {
        $available = (float) $this->payments()->sum('amount') - max(0, (float) $this->opening_balance);

        foreach ($this->invoices()->orderBy('invoice_date')->orderBy('id')->get() as $invoice) {
            $paid = max(0, min((float) $invoice->total, $available));
            $available -= $paid;
            $status = $paid <= 0 ? 'unpaid' : ($paid + 0.009 >= (float) $invoice->total ? 'paid' : 'partial');
            if ((float) $invoice->total <= 0) {
                $status = 'paid';
            }
            $invoice->forceFill(['amount_paid' => round($paid, 2), 'status' => $status])->save();
        }
    }
}
