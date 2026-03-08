<template>
  <transition name="settings-fade">
    <div v-if="visible" class="settings-overlay" @click.self="$emit('close')">
      <div class="settings-modal">
        <div class="settings-modal-header">
          <h2 class="section-title">Settings</h2>
          <button class="settings-close-btn" @click="$emit('close')">&times;</button>
        </div>

        <div class="settings-modal-body">
          <div class="settings-group">
            <h3 class="group-title">Sound</h3>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Background Music</span>
                <span class="setting-desc">Ambient music during gameplay</span>
              </div>
              <div class="toggle-wrap coming-soon">
                <button class="toggle" disabled>
                  <span class="toggle-knob"></span>
                </button>
                <span class="coming-tag">Coming soon</span>
              </div>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">UI Sounds</span>
                <span class="setting-desc">Clicks, toggles, dice rolls</span>
              </div>
              <button
                class="toggle"
                :class="{ active: settings.ui }"
                @click="toggle('ui')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Action Sounds</span>
                <span class="setting-desc">Win, fail, and game over</span>
              </div>
              <button
                class="toggle"
                :class="{ active: settings.actions }"
                @click="toggle('actions')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>
          </div>

          <div class="settings-group">
            <h3 class="group-title">Gameplay</h3>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Show Hints</span>
                <span class="setting-desc">Helpful tips for new features as you explore</span>
              </div>
              <button
                class="toggle"
                :class="{ active: hintsEnabled }"
                @click="toggleHints"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Game Background</span>
                <span class="setting-desc">Show background image during games</span>
              </div>
              <button
                class="toggle"
                :class="{ active: gameBgEnabled }"
                @click="toggleGameBg"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>
          </div>

          <div class="settings-group">
            <h3 class="group-title">Notifications</h3>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Game Updates</span>
                <span class="setting-desc">Turn reminders, game invites</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_game }"
                @click="toggleNotif('push_game')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Social</span>
                <span class="setting-desc">Friend requests and accepts</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_social }"
                @click="toggleNotif('push_social')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Achievements</span>
                <span class="setting-desc">Unlocks and level ups</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_achievement }"
                @click="toggleNotif('push_achievement')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Seasons</span>
                <span class="setting-desc">Season end rewards</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_season }"
                @click="toggleNotif('push_season')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Challenges</span>
                <span class="setting-desc">Daily and weekly challenges</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_challenge }"
                @click="toggleNotif('push_challenge')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>

            <div class="setting-row">
              <div class="setting-info">
                <span class="setting-label">Admin Announcements</span>
                <span class="setting-desc">Gifts and announcements from admins</span>
              </div>
              <button
                class="toggle"
                :class="{ active: notifPrefs.push_admin }"
                @click="toggleNotif('push_admin')"
              >
                <span class="toggle-knob"></span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import axios from 'axios';
import { getSoundSettings, saveSoundSettings, playSound } from '../sounds';
import { getHintsSetting, setHintsSetting } from '../hints';
import { useAuth } from '../stores/auth';

export default {
  name: 'SettingsModal',
  props: {
    visible: { type: Boolean, default: false },
  },
  emits: ['close'],
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      settings: getSoundSettings(),
      hintsEnabled: getHintsSetting(),
      dddiceEnabled: localStorage.getItem('dddice_enabled') !== 'false',
      gameBgEnabled: localStorage.getItem('game_bg_enabled') !== 'false',
      notifPrefs: {
        push_game: true,
        push_social: true,
        push_achievement: true,
        push_season: true,
        push_admin: true,
        push_challenge: true,
      },
    };
  },
  watch: {
    visible(val) {
      if (val) {
        this.settings = getSoundSettings();
        this.hintsEnabled = getHintsSetting();
        this.dddiceEnabled = localStorage.getItem('dddice_enabled') !== 'false';
        this.gameBgEnabled = localStorage.getItem('game_bg_enabled') !== 'false';
        const prefs = this.auth.state.user?.notification_preferences;
        if (prefs) {
          Object.keys(this.notifPrefs).forEach(key => {
            if (key in prefs) {
              this.notifPrefs[key] = prefs[key];
            }
          });
        }
      }
    },
  },
  mounted() {
    const prefs = this.auth.state.user?.notification_preferences;
    if (prefs) {
      Object.keys(this.notifPrefs).forEach(key => {
        if (key in prefs) {
          this.notifPrefs[key] = prefs[key];
        }
      });
    }
  },
  methods: {
    toggle(key) {
      this.settings[key] = !this.settings[key];
      saveSoundSettings(this.settings);
      if (this.settings[key]) {
        playSound('clickToggle');
      }
    },
    toggleHints() {
      this.hintsEnabled = !this.hintsEnabled;
      setHintsSetting(this.hintsEnabled);
      if (this.hintsEnabled) {
        playSound('clickToggle');
      }
    },
    toggleDddice() {
      this.dddiceEnabled = !this.dddiceEnabled;
      localStorage.setItem('dddice_enabled', this.dddiceEnabled ? 'true' : 'false');
      if (this.dddiceEnabled) {
        playSound('clickToggle');
      }
    },
    toggleGameBg() {
      this.gameBgEnabled = !this.gameBgEnabled;
      localStorage.setItem('game_bg_enabled', this.gameBgEnabled ? 'true' : 'false');
      if (this.gameBgEnabled) {
        playSound('clickToggle');
      }
    },
    async toggleNotif(key) {
      this.notifPrefs[key] = !this.notifPrefs[key];
      if (this.notifPrefs[key]) {
        playSound('clickToggle');
      }
      try {
        await axios.put('/api/notifications/preferences', { preferences: { ...this.notifPrefs } });
        if (this.auth.state.user) {
          this.auth.state.user.notification_preferences = { ...this.notifPrefs };
        }
      } catch {
        this.notifPrefs[key] = !this.notifPrefs[key];
      }
    },
  },
};
</script>

<style scoped>
.settings-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.88);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
}

.settings-modal {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #8a6a2e);
  border-radius: 14px;
  max-width: 480px;
  width: 92%;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6), 0 0 20px rgba(212, 168, 67, 0.1);
}

.settings-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 10px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
  flex-shrink: 0;
}

.settings-modal-header .section-title {
  margin-bottom: 0;
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #d4a843);
  font-size: 1.3rem;
}

.settings-close-btn {
  background: none;
  border: none;
  color: var(--text-secondary, #a08a6a);
  font-size: 1.6rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color 0.2s;
  box-shadow: none;
}

.settings-close-btn:hover {
  color: var(--text-bright, #f5e6cc);
  transform: none;
  box-shadow: none;
}

.settings-modal-body {
  padding: 16px 20px 20px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.settings-group {
  margin-bottom: 18px;
}

.group-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #f5e6cc);
  font-size: 0.95rem;
  margin-bottom: 12px;
  padding-bottom: 6px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
}

.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid rgba(138, 106, 46, 0.1);
}

.setting-row:last-child {
  border-bottom: none;
}

.setting-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.setting-label {
  color: var(--text-bright, #f5e6cc);
  font-size: 0.95rem;
}

.setting-desc {
  color: var(--text-secondary, #a08a6a);
  font-size: 0.75rem;
  font-style: italic;
}

.toggle {
  position: relative;
  width: 48px;
  height: 26px;
  border-radius: 13px;
  background: rgba(100, 80, 60, 0.4);
  border: 2px solid rgba(138, 106, 46, 0.3);
  padding: 0;
  cursor: pointer;
  transition: all 0.25s ease;
  flex-shrink: 0;
}

.toggle:hover {
  transform: none;
  box-shadow: none;
}

.toggle.active {
  background: rgba(212, 168, 67, 0.3);
  border-color: var(--accent-gold, #d4a843);
}

.toggle-knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--text-secondary, #a08a6a);
  transition: all 0.25s ease;
}

.toggle.active .toggle-knob {
  left: 24px;
  background: var(--accent-gold, #d4a843);
}

.toggle:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.toggle-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.coming-tag {
  font-size: 0.65rem;
  color: var(--text-secondary, #a08a6a);
  font-style: italic;
  white-space: nowrap;
}

.settings-fade-enter-active {
  transition: opacity 0.25s ease;
}

.settings-fade-leave-active {
  transition: opacity 0.2s ease;
}

.settings-fade-enter-from,
.settings-fade-leave-to {
  opacity: 0;
}
</style>
