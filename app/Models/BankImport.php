<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankImport extends Model
{
    protected $fillable = ['filename', 'rows_total', 'rows_credit', 'posted_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class)->orderBy('txn_date')->orderBy('id');
    }
}
