<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One rate on a quotation: certificate + type (IR or Normal) + sets. */
class QuotationItem extends Model
{
    protected $fillable = ['quotation_id', 'certificate', 'ir_na', 'sets', 'rate'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'sets' => 'integer'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
