<?php

namespace Database\Seeders;

use App\Models\KingdomStyle;
use Illuminate\Database\Seeder;

class KingdomStyleSeeder extends Seeder
{
    public function run(): void
    {
        $styles = [
            [
                'slug' => 'classic',
                'name' => 'Classic',
                'description' => 'The timeless kingdom style.',
                'is_default' => true,
                'is_default_unlocked' => true,
                'css_vars' => [
                    'border_color' => 'transparent',
                    'border_glow' => 'none',
                    'bg_tint' => 'transparent',
                    'bg_color' => 'transparent',
                    'name_accent' => 'var(--accent-gold)',
                    'total_accent' => 'var(--accent-gold)',
                    'bar_safe' => '#27ae60',
                    'bar_caution' => '#d4a843',
                ],
            ],
            [
                'slug' => 'royal-gold',
                'name' => 'Royal Gold',
                'description' => 'A regal golden aura befitting true royalty.',
                'css_vars' => [
                    'border_color' => '#c9a227',
                    'border_glow' => '0 0 12px rgba(201, 162, 39, 0.4)',
                    'bg_tint' => 'rgba(201, 162, 39, 0.06)',
                    'bg_color' => 'rgba(201, 162, 39, 0.12)',
                    'name_accent' => '#e8c468',
                    'total_accent' => '#e8c468',
                    'bar_safe' => '#c9a227',
                    'bar_caution' => '#b08a20',
                ],
            ],
            [
                'slug' => 'shadow-knight',
                'name' => 'Shadow Knight',
                'description' => 'Dark violet emanations of arcane power.',
                'css_vars' => [
                    'border_color' => '#7b42c9',
                    'border_glow' => '0 0 12px rgba(123, 66, 201, 0.4)',
                    'bg_tint' => 'rgba(123, 66, 201, 0.06)',
                    'bg_color' => 'rgba(123, 66, 201, 0.12)',
                    'name_accent' => '#c49bff',
                    'total_accent' => '#c49bff',
                    'bar_safe' => '#9055d4',
                    'bar_caution' => '#7b42c9',
                ],
            ],
            [
                'slug' => 'natures-keeper',
                'name' => "Nature's Keeper",
                'description' => 'The verdant glow of the ancient forest.',
                'css_vars' => [
                    'border_color' => '#2d8a4e',
                    'border_glow' => '0 0 12px rgba(45, 138, 78, 0.4)',
                    'bg_tint' => 'rgba(45, 138, 78, 0.06)',
                    'bg_color' => 'rgba(45, 138, 78, 0.12)',
                    'name_accent' => '#5ec87a',
                    'total_accent' => '#5ec87a',
                    'bar_safe' => '#2d8a4e',
                    'bar_caution' => '#4a9e60',
                ],
            ],
            [
                'slug' => 'fire-lord',
                'name' => 'Fire Lord',
                'description' => 'Ember-forged power radiating intense heat.',
                'css_vars' => [
                    'border_color' => '#c44a20',
                    'border_glow' => '0 0 12px rgba(196, 74, 32, 0.4)',
                    'bg_tint' => 'rgba(196, 74, 32, 0.06)',
                    'bg_color' => 'rgba(196, 74, 32, 0.12)',
                    'name_accent' => '#f09040',
                    'total_accent' => '#f09040',
                    'bar_safe' => 'linear-gradient(90deg, #c44a20, #e67e22)',
                    'bar_caution' => '#b84020',
                ],
            ],
            [
                'slug' => 'ice-crown',
                'name' => 'Ice Crown',
                'description' => 'Frost-kissed majesty of the frozen north.',
                'css_vars' => [
                    'border_color' => '#5ba4c9',
                    'border_glow' => '0 0 12px rgba(91, 164, 201, 0.4)',
                    'bg_tint' => 'rgba(91, 164, 201, 0.06)',
                    'bg_color' => 'rgba(91, 164, 201, 0.12)',
                    'name_accent' => '#8cc8e8',
                    'total_accent' => '#8cc8e8',
                    'bar_safe' => '#5ba4c9',
                    'bar_caution' => '#4a90b8',
                ],
            ],
        ];

        foreach ($styles as $style) {
            KingdomStyle::updateOrCreate(
                ['slug' => $style['slug']],
                $style,
            );
        }
    }
}
