<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Card;
use App\Models\Character;
use App\Models\Cosmetic;
use App\Models\Curse;
use App\Models\Event;
use App\Models\Item;
use App\Models\SeasonPassTier;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SeederController extends Controller
{
    /**
     * Registry of admin-runnable seeders. Laravel doesn't track seeder runs (unlike
     * migrations), so each entry carries a `pending` check that inspects data state to
     * decide whether the seeder still needs running after a code update.
     *
     * @return list<array{class: string, label: string, description: string, pending: callable(): bool}>
     */
    private function seeders(): array
    {
        return [
            [
                'class' => 'ItemSeeder',
                'label' => 'Items',
                'description' => 'Item catalogue — effects, cadence, type, rarity and starter flags.',
                // Pending until the latest catalogue (single starter = Shield of Valor) is present.
                'pending' => fn (): bool => Item::query()->count() === 0
                    || Item::query()->where('name', 'Shield of Valor')->where('is_starter', true)->doesntExist(),
            ],
            [
                'class' => 'ItemShopSeeder',
                'label' => 'Item Shop',
                'description' => 'Makes non-starter items purchasable with coins in the shop.',
                'pending' => fn (): bool => Item::query()->where('is_starter', false)->exists()
                    && Unlockable::query()->where('type', 'item')->doesntExist(),
            ],
            [
                'class' => 'SeasonPassSeeder',
                'label' => 'Season Pass Tiers',
                'description' => 'Season pass reward ladder, including the tier-10 item reward.',
                'pending' => fn (): bool => SeasonPassTier::query()->where('tier', 10)->whereNotNull('reward_item_id')->doesntExist(),
            ],
            [
                'class' => 'CharacterSeeder',
                'label' => 'Advisors',
                'description' => 'Advisor (character) catalogue.',
                'pending' => fn (): bool => Character::query()->count() === 0,
            ],
            [
                'class' => 'CardSeeder',
                'label' => 'Cards',
                'description' => 'Decision card catalogue.',
                'pending' => fn (): bool => Card::query()->count() === 0,
            ],
            [
                'class' => 'EventSeeder',
                'label' => 'Events',
                'description' => 'Round event catalogue.',
                'pending' => fn (): bool => Event::query()->count() === 0,
            ],
            [
                'class' => 'CurseSeeder',
                'label' => 'Curses',
                'description' => 'Curse catalogue.',
                'pending' => fn (): bool => Curse::query()->count() === 0,
            ],
            [
                'class' => 'AchievementSeeder',
                'label' => 'Achievements',
                'description' => 'Achievement definitions.',
                'pending' => fn (): bool => Achievement::query()->count() === 0,
            ],
            [
                'class' => 'CosmeticSeeder',
                'label' => 'Cosmetics',
                'description' => 'Cosmetic catalogue.',
                'pending' => fn (): bool => Cosmetic::query()->count() === 0,
            ],
        ];
    }

    public function index(): JsonResponse
    {
        $seeders = collect($this->seeders())
            ->map(fn (array $seeder): array => [
                'class' => $seeder['class'],
                'label' => $seeder['label'],
                'description' => $seeder['description'],
                'pending' => (bool) $seeder['pending'](),
            ])
            ->values();

        return response()->json([
            'seeders' => $seeders,
            'pending_count' => $seeders->where('pending', true)->count(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate(['class' => ['required', 'string']]);

        $allowed = collect($this->seeders())->pluck('class')->all();
        if (! in_array($validated['class'], $allowed, true)) {
            return response()->json(['error' => 'Unknown seeder.'], 422);
        }

        Artisan::call('db:seed', [
            '--class' => $validated['class'],
            '--force' => true,
        ]);

        return response()->json([
            'message' => "{$validated['class']} ran successfully.",
            'output' => trim(Artisan::output()),
        ]);
    }
}
