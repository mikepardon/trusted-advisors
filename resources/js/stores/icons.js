import { reactive } from 'vue';
import axios from 'axios';

const DEFAULTS = {
  nav_shop: { type: 'emoji', value: '\u{1F6D2}', label: 'Shop', category: 'navigation' },
  nav_collection: { type: 'emoji', value: '\u{1F0CF}', label: 'Collection', category: 'navigation' },
  nav_campaigns: { type: 'emoji', value: '\u{2694}', label: 'Campaigns', category: 'navigation' },
  nav_friends: { type: 'emoji', value: '\u{1F465}', label: 'Friends', category: 'navigation' },
  nav_profile: { type: 'emoji', value: '\u{1F464}', label: 'Profile', category: 'navigation' },
  stat_wealth: { type: 'emoji', value: '\u{1FA99}', label: 'Wealth', category: 'stats' },
  stat_influence: { type: 'emoji', value: '\u{1F3DB}', label: 'Influence', category: 'stats' },
  stat_security: { type: 'emoji', value: '\u{1F6E1}', label: 'Security', category: 'stats' },
  stat_religion: { type: 'emoji', value: '\u{1F54C}', label: 'Religion', category: 'stats' },
  stat_food: { type: 'emoji', value: '\u{1F33E}', label: 'Food', category: 'stats' },
  stat_happiness: { type: 'emoji', value: '\u{1F3AD}', label: 'Happiness', category: 'stats' },
  ui_coins: { type: 'emoji', value: '\u{1F9E9}', label: 'Coins', category: 'ui' },
  ui_elo_trophy: { type: 'emoji', value: '\u{1F3C6}', label: 'ELO Trophy', category: 'ui' },
};

const state = reactive({ icons: { ...DEFAULTS }, loaded: false });

export function useIcons() {
  function getIcon(key) {
    return state.icons[key] || DEFAULTS[key] || { type: 'emoji', value: '?' };
  }

  function getStatIcons() {
    const make = (key, label, short, iconKey) => {
      const icon = getIcon(iconKey);
      return { key, label, short, type: icon.type, value: icon.value, icon: icon.type === 'emoji' ? icon.value : '' };
    };
    return [
      make('wealth', 'Wealth', 'W', 'stat_wealth'),
      make('influence', 'Influence', 'INF', 'stat_influence'),
      make('security', 'Security', 'SEC', 'stat_security'),
      make('religion', 'Religion', 'REL', 'stat_religion'),
      make('food', 'Food', 'FD', 'stat_food'),
      make('happiness', 'Happiness', 'HAP', 'stat_happiness'),
    ];
  }

  async function fetchIcons() {
    try {
      const { data } = await axios.get('/api/app-icons');
      Object.assign(state.icons, data);
      state.loaded = true;
    } catch {
      // Keep defaults
    }
  }

  return { state, getIcon, getStatIcons, fetchIcons };
}
