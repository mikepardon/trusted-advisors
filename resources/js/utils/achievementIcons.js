export const ACHIEVEMENT_ICON_MAP = {
  trophy: '\u{1F3C6}',
  shield: '\u{1F6E1}',
  crown: '\u{1F451}',
  flame: '\u{1F525}',
  lightning: '\u{26A1}',
  star: '\u{2B50}',
  swords: '\u{2694}',
  crossed_swords: '\u{2694}',
  scroll: '\u{1F4DC}',
  arrow_up: '\u{2B06}',
  diamond: '\u{1F48E}',
  book: '\u{1F4D6}',
  wizard: '\u{1F9D9}',
  castle: '\u{1F3F0}',
  people: '\u{1F465}',
  calendar: '\u{1F4C5}',
  handshake: '\u{1F91D}',
  globe: '\u{1F30D}',
  muscle: '\u{1F4AA}',
  sparkles: '\u{2728}',
};

export function resolveAchievementIcon(iconField) {
  if (!iconField) return { type: 'emoji', value: '\u{1F3C6}' };
  if (iconField.startsWith('image:')) {
    return { type: 'image', value: iconField.slice(6) };
  }
  const emoji = ACHIEVEMENT_ICON_MAP[iconField];
  return { type: 'emoji', value: emoji || '\u{1F3C6}' };
}
