<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Season;
use App\Models\SeasonPassTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_opens_a_season_with_pass_tiers_when_none_is_active(): void
    {
        $this->assertSame(0, Season::query()->count());

        $this->artisan('app:generate-monthly-season')->assertSuccessful();

        $season = Season::query()->first();
        $this->assertNotNull($season);
        $this->assertTrue($season->is_active);
        $this->assertGreaterThan(0, SeasonPassTier::query()->where('season_id', $season->id)->count());
    }

    public function test_generator_is_idempotent_while_a_season_is_active(): void
    {
        $this->artisan('app:generate-monthly-season')->assertSuccessful();
        $this->artisan('app:generate-monthly-season')->assertSuccessful();

        $this->assertSame(1, Season::query()->count());
    }
}
