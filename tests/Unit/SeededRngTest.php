<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SeededRng;
use PHPUnit\Framework\TestCase;

class SeededRngTest extends TestCase
{
    public function test_same_seed_and_context_always_produce_the_same_value(): void
    {
        $a = new SeededRng('daily:2026-08-28:1');
        $b = new SeededRng('daily:2026-08-28:1');

        $this->assertSame($a->int(0, 100, 'x'), $b->int(0, 100, 'x'));
        $this->assertSame($a->dieFace(3, 1, 2), $b->dieFace(3, 1, 2));
    }

    public function test_different_seeds_diverge(): void
    {
        $a = new SeededRng('daily:2026-08-28:1');
        $b = new SeededRng('daily:2026-08-29:1');

        // A full die sequence should differ across seeds (not a coincidental single match).
        $seqA = array_map(fn (int $i): int => $a->dieFace(1, 1, $i), range(0, 20));
        $seqB = array_map(fn (int $i): int => $b->dieFace(1, 1, $i), range(0, 20));

        $this->assertNotSame($seqA, $seqB);
    }

    public function test_int_stays_within_inclusive_bounds(): void
    {
        $rng = new SeededRng('seed');

        for ($i = 0; $i < 500; $i++) {
            $value = $rng->int(3, 9, 'n', (string) $i);
            $this->assertGreaterThanOrEqual(3, $value);
            $this->assertLessThanOrEqual(9, $value);
        }
    }

    public function test_die_face_is_always_zero_to_five(): void
    {
        $rng = new SeededRng('seed');

        for ($i = 0; $i < 200; $i++) {
            $face = $rng->dieFace(1, 1, $i);
            $this->assertGreaterThanOrEqual(0, $face);
            $this->assertLessThanOrEqual(5, $face);
        }
    }

    public function test_reroll_salt_changes_the_face(): void
    {
        $rng = new SeededRng('seed');

        // The salted reroll must not simply reproduce the original face for every die,
        // otherwise a reroll would be a no-op. Compare across a run of dice.
        $original = array_map(fn (int $i): int => $rng->dieFace(1, 1, $i), range(0, 20));
        $rerolled = array_map(fn (int $i): int => $rng->dieFace(1, 1, $i, 'reroll'), range(0, 20));

        $this->assertNotSame($original, $rerolled);
    }

    public function test_shuffle_is_deterministic_and_a_permutation(): void
    {
        $input = range(1, 10);
        $a = (new SeededRng('seed'))->shuffle($input, 'cards');
        $b = (new SeededRng('seed'))->shuffle($input, 'cards');

        $this->assertSame($a, $b);
        // Same elements, reordered.
        $sorted = $a;
        sort($sorted);
        $this->assertSame($input, $sorted);
    }

    public function test_shuffle_domains_do_not_correlate(): void
    {
        $input = range(1, 20);
        $rng = new SeededRng('seed');

        $this->assertNotSame(
            $rng->shuffle($input, 'cards'),
            $rng->shuffle($input, 'events'),
        );
    }
}
