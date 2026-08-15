<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMember extends Model
{
    protected $fillable = ['cohort_id', 'user_id', 'bot_name', 'score'];

    protected function casts(): array
    {
        return [
            'cohort_id' => 'integer',
            'user_id' => 'integer',
            'score' => 'integer',
        ];
    }

    /** @return BelongsTo<LeagueCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(LeagueCohort::class, 'cohort_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
