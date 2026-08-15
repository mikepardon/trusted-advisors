<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueCohort extends Model
{
    protected $fillable = ['tier', 'week_start'];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'week_start' => 'immutable_date',
            'processed_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<LeagueMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class, 'cohort_id');
    }
}
