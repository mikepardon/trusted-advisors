<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'product_id',
        'type',
        'amount_cents',
        'currency',
        'transaction_id',
        'receipt_data',
        'status',
        'unlockable_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unlockable(): BelongsTo
    {
        return $this->belongsTo(Unlockable::class);
    }
}
