/**
 * Hint system — shows each hint once per user.
 * Stores seen hints in localStorage.
 * Respects the "show_hints" setting (default: on).
 */

const STORAGE_KEY = 'ta_seen_hints';
const SETTING_KEY = 'ta_show_hints';

export function getHintsSetting() {
  const val = localStorage.getItem(SETTING_KEY);
  return val === null ? true : val === '1';
}

export function setHintsSetting(enabled) {
  localStorage.setItem(SETTING_KEY, enabled ? '1' : '0');
}

export function getSeenHints() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  } catch {
    return [];
  }
}

export function hasSeenHint(id) {
  return getSeenHints().includes(id);
}

export function markHintSeen(id) {
  const seen = getSeenHints();
  if (!seen.includes(id)) {
    seen.push(id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(seen));
  }
}

export function shouldShowHint(id) {
  return getHintsSetting() && !hasSeenHint(id);
}
