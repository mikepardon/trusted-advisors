<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCharacter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpCurveTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_xp_thresholds_follow_the_cubic_curve(): void
    {
        $this->assertSame(0, User::xpForLevel(1));
        $this->assertSame(50, User::xpForLevel(2));
        $this->assertSame(250, User::xpForLevel(3));
        $this->assertSame(700, User::xpForLevel(4));
        $this->assertSame(7000, User::xpForLevel(8));
    }

    public function test_account_level_is_derived_from_total_xp(): void
    {
        $this->assertSame(1, User::calculateLevel(0));
        $this->assertSame(1, User::calculateLevel(49));
        $this->assertSame(2, User::calculateLevel(50));
        $this->assertSame(3, User::calculateLevel(699));
        $this->assertSame(4, User::calculateLevel(700));
    }

    public function test_advisor_xp_uses_the_configured_threshold_ladder(): void
    {
        $this->assertSame(0, UserCharacter::xpForLevel(1));
        $this->assertSame(400, UserCharacter::xpForLevel(2));
        $this->assertSame(2100, UserCharacter::xpForLevel(4));
        $this->assertSame(18000, UserCharacter::xpForLevel(8));
    }

    public function test_advisor_level_is_derived_and_capped_at_max_level(): void
    {
        $this->assertSame(1, UserCharacter::calculateLevel(399));
        $this->assertSame(2, UserCharacter::calculateLevel(400));
        $this->assertSame(7, UserCharacter::calculateLevel(11500));
        $this->assertSame(8, UserCharacter::calculateLevel(18000));
        // Beyond the level-8 threshold the level clamps to the configured max.
        $this->assertSame(8, UserCharacter::calculateLevel(999999));
    }
}
