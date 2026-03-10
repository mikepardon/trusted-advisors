<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppIcon extends Model
{
    protected $fillable = ['key', 'label', 'category', 'icon_type', 'icon_value'];

    public static function allKeyed(): array
    {
        return static::all()->keyBy('key')->map(fn ($icon) => [
            'type' => $icon->icon_type,
            'value' => $icon->icon_value,
            'label' => $icon->label,
            'category' => $icon->category,
        ])->toArray();
    }
}
