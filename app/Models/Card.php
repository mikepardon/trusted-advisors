<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'title', 'description', 'sort_order',
        'difficulty', 'positive_effects', 'negative_effects',
        'positive_flavor', 'negative_flavor', 'category',
    ];

    protected $casts = [
        'difficulty' => 'integer',
        'positive_effects' => 'array',
        'negative_effects' => 'array',
    ];
}
