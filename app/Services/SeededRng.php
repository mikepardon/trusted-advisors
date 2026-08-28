<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic, stateless pseudo-random generator for daily-challenge runs.
 *
 * Every draw is a pure function of (seed, ...context), so the same seed always
 * produces the same result regardless of when or in which HTTP request it is
 * computed. This is what lets dice rolls — which happen across many separate
 * requests over a game's life — reproduce identically for every player on a
 * given day, and lets a retried round resolve to the same faces.
 *
 * Inputs to shuffle()/pick() MUST already be in a stable order (e.g. ordered by
 * id); never feed in the result of a database `inRandomOrder()`.
 */
final class SeededRng
{
    public function __construct(private readonly string $seed) {}

    /**
     * A 31-bit non-negative integer derived from the seed and context tokens.
     */
    public function hash(string ...$context): int
    {
        $digest = hash('sha256', $this->seed.':'.implode(':', $context));

        // Take the first 8 hex chars (32 bits) and mask off the sign bit for a
        // portable non-negative integer on both 32- and 64-bit platforms.
        return (int) (hexdec(substr($digest, 0, 8)) & 0x7FFFFFFF);
    }

    /**
     * An integer in [$min, $max] (inclusive) derived deterministically from context.
     */
    public function int(int $min, int $max, string ...$context): int
    {
        if ($max <= $min) {
            return $min;
        }

        $span = $max - $min + 1;

        return $min + ($this->hash(...$context) % $span);
    }

    /**
     * A die face index in [0, 5] for a specific round/player/die. Use a distinct
     * $salt (e.g. 'reroll') when the same die is rolled again in the same round,
     * or the reroll reproduces the identical face and becomes a no-op.
     */
    public function dieFace(int $round, int $player, int $dieIndex, string $salt = ''): int
    {
        return $this->int(0, 5, 'die', $salt, (string) $round, (string) $player, (string) $dieIndex);
    }

    /**
     * A deterministic Fisher-Yates shuffle. $domain namespaces the randomness so
     * separate shuffles (and separate recycle passes) do not correlate.
     *
     * @template T
     *
     * @param  list<T>  $values
     * @return list<T>
     */
    public function shuffle(array $values, string $domain): array
    {
        $values = array_values($values);

        for ($i = count($values) - 1; $i > 0; $i--) {
            $j = $this->hash($domain, 'shuffle', (string) $i) % ($i + 1);
            [$values[$i], $values[$j]] = [$values[$j], $values[$i]];
        }

        return $values;
    }

    /**
     * Deterministically pick one element from a stable-ordered list.
     *
     * @template T
     *
     * @param  list<T>  $values
     * @return T|null
     */
    public function pick(array $values, string $domain, string ...$context)
    {
        $values = array_values($values);

        if ($values === []) {
            return null;
        }

        return $values[$this->int(0, count($values) - 1, $domain, ...$context)];
    }
}
