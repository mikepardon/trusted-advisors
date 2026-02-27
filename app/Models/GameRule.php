<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRule extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $rule = static::where('key', $key)->first();

        return $rule ? $rule->value : $default;
    }
}
