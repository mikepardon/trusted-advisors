<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCharacterUpgrade extends Model
{
    protected $fillable = [
        'user_character_id', 'character_level_option_id',
        'chosen_at_level', 'incarnation', 'user_choice',
    ];

    protected $casts = [
        'chosen_at_level' => 'integer',
        'incarnation' => 'integer',
        'user_choice' => 'array',
    ];

    public function userCharacter(): BelongsTo
    {
        return $this->belongsTo(UserCharacter::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CharacterLevelOption::class, 'character_level_option_id');
    }
}
