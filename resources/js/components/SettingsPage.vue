<template>
  <div class="settings-screen">
    <div class="card-panel">
      <h2 class="section-title">Settings</h2>
      <p class="flavor-text">Adjust your experience in the realm.</p>

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
</template>

<script>
import axios from 'axios';
import { getSoundSettings, saveSoundSettings, playSound } from '../sounds';
import { getHintsSetting, setHintsSetting } from '../hints';
import { useAuth } from '../stores/auth';

export default {
  name: 'SettingsPage',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      settings: getSoundSettings(),
      hintsEnabled: getHintsSetting(),
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
  async mounted() {
    // Load notification preferences from user data
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
    async toggleNotif(key) {
      this.notifPrefs[key] = !this.notifPrefs[key];
      if (this.notifPrefs[key]) {
        playSound('clickToggle');
      }
      try {
        await axios.put('/api/notifications/preferences', { preferences: { ...this.notifPrefs } });
        // Update local user data
        if (this.auth.state.user) {
          this.auth.state.user.notification_preferences = { ...this.notifPrefs };
        }
      } catch {
        // Revert on failure
        this.notifPrefs[key] = !this.notifPrefs[key];
      }
    },
  },
};
</script>

<style scoped>
.settings-screen {
  max-width: 600px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  margin-bottom: 8px;
  text-align: center;
}

.flavor-text {
  text-align: center;
  font-style: italic;
  color: var(--text-secondary);
  margin-bottom: 25px;
  font-size: 1rem;
}

.settings-group {
  margin-bottom: 20px;
}

.group-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
  margin-bottom: 14px;
  padding-bottom: 6px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
}

.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid rgba(138, 106, 46, 0.12);
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
  color: var(--text-bright);
  font-size: 1rem;
}

.setting-desc {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-style: italic;
}

/* Toggle switch */
.toggle {
  position: relative;
  width: 52px;
  height: 28px;
  border-radius: 14px;
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
  border-color: var(--accent-gold);
}

.toggle-knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--text-secondary);
  transition: all 0.25s ease;
}

.toggle.active .toggle-knob {
  left: 26px;
  background: var(--accent-gold);
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
  font-size: 0.7rem;
  color: var(--text-secondary);
  font-style: italic;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .section-title {
    font-size: 1.3rem;
  }

  .setting-label {
    font-size: 0.95rem;
  }
}
</style>
