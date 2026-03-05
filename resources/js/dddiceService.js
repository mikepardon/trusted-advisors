import { ThreeDDice, ThreeDDiceAPI, ThreeDDiceRollEvent } from 'dddice-js';
import axios from 'axios';

const DEFAULT_THEME = 'dddice-standard';

// Shared token manager — fetches guest token once, reused by all instances
let _sharedToken = null;
let _sharedTokenPromise = null;

async function getSharedToken() {
  if (_sharedToken) return _sharedToken;
  if (_sharedTokenPromise) return _sharedTokenPromise;
  _sharedTokenPromise = axios.get('/api/dddice/guest-token').then(res => {
    _sharedToken = res.data.token;
    _sharedTokenPromise = null;
    return _sharedToken;
  }).catch(e => {
    _sharedTokenPromise = null;
    throw e;
  });
  return _sharedTokenPromise;
}

function isAvailable() {
  if (localStorage.getItem('dddice_enabled') === 'false') return false;
  try {
    return ThreeDDice.isWebGLAvailable();
  } catch {
    return false;
  }
}

const _genUUID = () => 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
  const r = Math.random() * 16 | 0;
  return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
});

/**
 * Independent dice instance — owns one ThreeDDice renderer, one canvas.
 * Multiple instances can exist simultaneously (e.g., one per player column).
 */
class DddiceInstance {
  constructor() {
    this.instance = null;
    this.api = null;
    this._ready = false;
    this._loadedThemes = new Set();
    this._initPromise = null;
  }

  async init(canvas, config = {}) {
    if (this._initPromise) return this._initPromise;
    if (!isAvailable()) {
      console.log('[dddice] Not available (WebGL or disabled)');
      return false;
    }
    this._initPromise = this._doInit(canvas, config);
    return this._initPromise;
  }

  async _doInit(canvas, config) {
    try {
      const token = await getSharedToken();

      this.api = new ThreeDDiceAPI(token);

      const diceSize = config.diceSize ?? 1;

      this.instance = new ThreeDDice(canvas, token, {
        bgColor: 0x000000,
        bgOpacity: 0,
        autoClear: 3,
        dice: {
          drawOutlines: false,
          size: diceSize,
        },
      });

      this.instance.start();
      this.instance.controlsEnabled = false;

      await this._loadTheme(DEFAULT_THEME);

      this._ready = true;
      console.log('[dddice] Instance initialized successfully');
      return true;
    } catch (e) {
      console.warn('[dddice] Instance init failed:', e);
      this._ready = false;
      this._initPromise = null;
      return false;
    }
  }

  async _loadTheme(themeId) {
    if (this._loadedThemes.has(themeId)) return true;
    try {
      const themeRes = await this.api.theme.get(themeId);
      this.instance.loadTheme(themeRes.data, false);
      this._loadedThemes.add(themeId);
      console.log('[dddice] Loaded theme:', themeId);
      return true;
    } catch (e) {
      console.warn('[dddice] Failed to load theme:', themeId, e);
      return false;
    }
  }

  isReady() {
    return this._ready && this.instance != null;
  }

  /**
   * Roll 3D dice with predetermined values.
   * @param {Array<{theme: string, value: number}>} diceSpecs
   * @returns {Promise<void>} resolves when animation finishes
   */
  async roll(diceSpecs) {
    if (!this.isReady()) {
      console.log('[dddice] Not ready, skipping roll');
      return;
    }

    // Ensure all needed themes are loaded, fall back to default if loading fails
    const themes = [...new Set(diceSpecs.map(s => s.theme || DEFAULT_THEME))];
    for (const themeId of themes) {
      if (!this._loadedThemes.has(themeId)) {
        const ok = await this._loadTheme(themeId);
        if (!ok && themeId !== DEFAULT_THEME) {
          // Theme failed to load — specs using it will fall back to default
        }
      }
    }

    // Build the roll object manually with predetermined values
    const values = diceSpecs.map(spec => {
      let theme = spec.theme || DEFAULT_THEME;
      if (!this._loadedThemes.has(theme)) theme = DEFAULT_THEME;

      let valueToDisplay = spec.value;
      try {
        const opts = this.instance.getThemeOptions(theme);
        if (opts?.values?.d6) {
          valueToDisplay = opts.values.d6[spec.value - 1] ?? spec.value;
        }
      } catch {
        // use raw value
      }

      return {
        uuid: _genUUID(),
        is_hidden: false,
        is_user_value: false,
        is_visible: true,
        is_cleared: false,
        is_dropped: false,
        type: 'd6',
        theme,
        value: spec.value,
        value_to_display: valueToDisplay,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };
    });

    const totalValue = values.reduce((sum, v) => sum + (typeof v.value_to_display === 'number' ? v.value_to_display : parseFloat(v.value_to_display) || 0), 0);

    const rollObj = {
      uuid: _genUUID(),
      user: { uuid: 'local-player' },
      room: { participants: [{ username: 'Player', user: { uuid: 'local-player' } }] },
      equation: `${values.length}d6`,
      direction: 0,
      total_value: totalValue,
      values,
      velocity: 10,
      operator: {},
      whisper: [],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    console.log('[dddice] Rolling with predetermined values:', values.map(v => v.value));

    return new Promise((resolve) => {
      const timeout = setTimeout(() => {
        console.log('[dddice] Roll timed out after 8s');
        this.instance.off(ThreeDDiceRollEvent.RollFinished);
        resolve();
      }, 8000);

      this.instance.on(ThreeDDiceRollEvent.RollFinished, () => {
        clearTimeout(timeout);
        this.instance.off(ThreeDDiceRollEvent.RollFinished);
        console.log('[dddice] Roll finished');
        resolve();
      });

      try {
        this.instance.executeRoll(rollObj);
      } catch (err) {
        console.warn('[dddice] executeRoll failed:', err);
        clearTimeout(timeout);
        this.instance.off(ThreeDDiceRollEvent.RollFinished);
        resolve();
      }
    });
  }

  resize(w, h) {
    if (this.instance) {
      try {
        this.instance.resize(w, h);
      } catch {
        // ignore resize errors
      }
    }
  }

  clear() {
    if (this.instance) {
      this.instance.clear();
    }
  }

  destroy() {
    if (this.instance) {
      try {
        this.instance.stop();
      } catch {
        // ignore teardown errors
      }
      this.instance = null;
    }
    this.api = null;
    this._ready = false;
    this._initPromise = null;
    this._loadedThemes.clear();
  }
}

/**
 * Factory to create a new independent DddiceInstance.
 * Each instance manages its own ThreeDDice renderer on its own canvas.
 */
export function createDddiceInstance() {
  return new DddiceInstance();
}

// Singleton for cooperative mode (backwards-compatible)
class DddiceService extends DddiceInstance {
  constructor() {
    super();
    this.token = null;
  }

  isAvailable() {
    return isAvailable();
  }

  async init(canvas) {
    if (this._initPromise) return this._initPromise;
    if (!this.isAvailable()) {
      console.log('[dddice] Not available (WebGL or disabled)');
      return false;
    }
    this._initPromise = this._doInit(canvas, {});
    return this._initPromise;
  }

  destroy() {
    super.destroy();
    this.token = null;
  }
}

export default new DddiceService();
export { isAvailable as isDddiceAvailable };
