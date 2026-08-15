/**
 * Cumulative account XP required to reach a given level.
 *
 * Cubic (sum-of-squares) curve — kept in one place because several components render
 * the XP bar and must agree with the server (App\Models\User::xpForLevel). If you change
 * this, change the PHP side too.
 */
export function xpForLevel(level: number): number {
    if (level <= 1) {
        return 0;
    }

    return Math.floor((50 * (level - 1) * level * (2 * level - 1)) / 6);
}
