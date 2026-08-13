/**
 * Hint system — shows each hint once per user.
 * Stores seen hints in localStorage.
 * Respects the "show_hints" setting (default: on).
 */

const STORAGE_KEY = 'ta_seen_hints';
const SETTING_KEY = 'ta_show_hints';

export function areHintsEnabled(): boolean {
  const value = localStorage.getItem(SETTING_KEY) ?? undefined;
  return value === undefined ? true : value === '1';
}

export function setHintsEnabled(isEnabled: boolean): void {
  localStorage.setItem(SETTING_KEY, isEnabled ? '1' : '0');
}

export function getSeenHints(): string[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter((value): value is string => typeof value === 'string');
  } catch {
    return [];
  }
}

export function hasSeenHint(id: string): boolean {
  return getSeenHints().includes(id);
}

export function markHintSeen(id: string): void {
  const seen = getSeenHints();
  if (!seen.includes(id)) {
    seen.push(id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(seen));
  }
}

export function shouldShowHint(id: string): boolean {
  return areHintsEnabled() && !hasSeenHint(id);
}
