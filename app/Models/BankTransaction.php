<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_import_id', 'txn_date', 'narration', 'reference', 'credit', 'debit',
        'client_id', 'match_reason', 'status', 'payment_id', 'hash',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'credit' => 'decimal:2',
            'debit' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankImport::class, 'bank_import_id');
    }
}
