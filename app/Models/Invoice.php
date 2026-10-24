<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Invoice extends Model
{
    protected $fillable = [
        'client_id', 'number', 'invoice_date', 'period', 'total', 'amount_paid',
        'status', 'public_token', 'emailed_at', 'whatsapp_at', 'last_reminded_at', 'reminder_count',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'emailed_at' => 'datetime',
            'whatsapp_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class)->orderBy('issued_date')->orderBy('id');
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function periodLabel(): string
    {
        return Carbon::createFromFormat('Y-m-d', $this->period.'-01')->format('F Y');
    }

    /** Link the client can open without logging in (used in WhatsApp messages). */
    public function publicUrl(): string
    {
        return URL::route('public.invoice', ['token' => $this->public_token]);
    }

    public function fileName(): string
    {
        return 'Invoice-'.str_replace(['/', '\\'], '-', $this->number).'.pdf';
    }
}
