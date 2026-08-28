import { ThreeDDice, ThreeDDiceAPI, ThreeDDiceRollEvent } from 'dddice-js';
import axios from 'axios';

const DEFAULT_THEME = 'dddice-standard';

/**
 * Predetermined die specification for a roll.
 */
export interface DiceSpec {
  theme?: string;
  value: number;
  // Optional custom face label (e.g. "W" for a wild face). Overrides the numeric display.
  label?: string;
}

/**
 * Optional per-instance render configuration.
 */
export interface DddiceInstanceConfig {
  diceSize?: number;
}

// Shared token manager — fetches guest token once, reused by all instances.
// State is held on a single object so it can be mutated from within
// getSharedToken without reassigning module-level bindings.
const sharedTokenState: {
  token: string | undefined;
  promise: Promise<string> | undefined;
  failedUntil: number;
} = {
  token: undefined,
  promise: undefined,
  failedUntil: 0,
};

async function fetchGuestToken(): Promise<string> {
  try {
    const response = await axios.get('/api/dddice/guest-token');
    sharedTokenState.token = response.data.token;
    sharedTokenState.promise = undefined;
    sharedTokenState.failedUntil = 0;
    return response.data.token;
  } catch (error) {
    sharedTokenState.promise = undefined;
    sharedTokenState.failedUntil = Date.now() + 5 * 60 * 1000;
    throw error;
  }
}

function getSharedToken(): Promise<string> {
  if (sharedTokenState.token) return Promise.resolve(sharedTokenState.token);
  // Don't retry for 5 minutes after a failure (e.g. 502 from dddice API)
  if (Date.now() < sharedTokenState.failedUntil) {
    return Promise.reject(new Error('dddice token fetch on cooldown'));
  }
  if (sharedTokenState.promise) return sharedTokenState.promise;
  // Store the in-flight promise so concurrent callers share one fetch.
  sharedTokenState.promise = fetchGuestToken();
  return sharedTokenState.promise;
}

function isAvailable(): boolean {
  if (localStorage.getItem('dddice_enabled') === 'false') return false;
  try {
    return ThreeDDice.isWebGLAvailable();
  } catch {
    return false;
  }
}

const generateUUID = (): string =>
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replaceAll(/[xy]/g, (c) => {
    const r = Math.trunc(Math.random() * 16);
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });

/**
 * Independent dice instance — owns one ThreeDDice renderer, one canvas.
 * Multiple instances can exist simultaneously (e.g., one per player column).
 */
class DddiceInstance {
  #ready = false;
  protected loadedThemes = new Set<string>();
  protected initPromise: Promise<boolean> | undefined = undefined;
  instance: ThreeDDice | undefined = undefined;
  api: ThreeDDiceAPI | undefined = undefined;

  async #loadTheme(themeId: string): Promise<boolean> {
    if (this.loadedThemes.has(themeId)) return true;
    try {
      const themeResponse = await this.api.theme.get(themeId);
      this.instance.loadTheme(themeResponse.data, false);
      this.loadedThemes.add(themeId);
      // console.log('[dddice] Loaded theme:', themeId);
      return true;
    } catch (error) {
      console.warn('[dddice] Failed to load theme:', themeId, error);
      return false;
    }
  }

  async init(canvas: HTMLCanvasElement, config: DddiceInstanceConfig = {}): Promise<boolean> {
    if (this.initPromise) return this.initPromise;
    if (!isAvailable()) {
      // console.log('[dddice] Not available (WebGL or disabled)');
      return false;
    }
    this.initPromise = this._doInit(canvas, config);
    return this.initPromise;
  }

  protected async _doInit(canvas: HTMLCanvasElement, config: DddiceInstanceConfig): Promise<boolean> {
    try {
      const token = await getSharedToken();

      this.api = new ThreeDDiceAPI(token);

      const diceSize = config.diceSize ?? 1;

      this.instance = new ThreeDDice(canvas, token, {
        bgColor: 0x00_00_00,
        bgOpacity: 0,
        autoClear: 3,
        dice: {
          drawOutlines: false,
          size: diceSize,
        },
      });

      this.instance.start();
      this.instance.controlsEnabled = false;

      await this.#loadTheme(DEFAULT_THEME);

      this.#ready = true;
      // console.log('[dddice] Instance initialized successfully');
      return true;
    } catch (error) {
      console.warn('[dddice] Instance init failed:', error);
      this.#ready = false;
      this.initPromise = undefined;
      return false;
    }
  }

  isReady(): boolean {
    return this.#ready && this.instance != undefined;
  }

  /**
   * Roll 3D dice with predetermined values.
   * Resolves when the animation finishes.
   */
  async roll(diceSpecs: DiceSpec[]): Promise<void> {
    if (!this.isReady()) {
      // console.log('[dddice] Not ready, skipping roll');
      return;
    }

    // Ensure all needed themes are loaded, fall back to default if loading fails
    const themes = [...new Set(diceSpecs.map((s) => s.theme || DEFAULT_THEME))];
    for (const themeId of themes) {
      if (this.loadedThemes.has(themeId)) {
      	continue;
      }

      const ok = await this.#loadTheme(themeId);
      if (!ok && themeId !== DEFAULT_THEME) {
        // Theme failed to load — specs using it will fall back to default
      }
    }

    // Build the roll object manually with predetermined values
    const values = diceSpecs.map((spec) => {
      let theme = spec.theme || DEFAULT_THEME;
      if (!this.loadedThemes.has(theme)) theme = DEFAULT_THEME;

      let valueToDisplay: number | string = spec.label ?? spec.value;
      if (spec.label === undefined) {
        try {
          const options = this.instance.getThemeOptions(theme);
          if (options?.values?.d6) {
            valueToDisplay = options.values.d6[spec.value - 1] ?? spec.value;
          }
        } catch {
          // use raw value
        }
      }

      return {
        uuid: generateUUID(),
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

    const totalValue = values.reduce((sum, v) => sum + (typeof v.value_to_display === 'number' ? v.value_to_display : Number(v.value_to_display) || 0), 0);

    const rollObject = {
      uuid: generateUUID(),
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

    // console.log('[dddice] Rolling with predetermined values:', values.map(v => v.value));

    return new Promise((resolve) => {
      const timeout = setTimeout(() => {
        // console.log('[dddice] Roll timed out after 8s');
        this.instance?.off(ThreeDDiceRollEvent.RollFinished);
        resolve();
      }, 8000);

      this.instance.on(ThreeDDiceRollEvent.RollFinished, () => {
        clearTimeout(timeout);
        this.instance?.off(ThreeDDiceRollEvent.RollFinished);
        // console.log('[dddice] Roll finished');
        resolve();
      });

      try {
        this.instance.executeRoll(rollObject);
      } catch (error) {
        console.warn('[dddice] executeRoll failed:', error);
        clearTimeout(timeout);
        this.instance?.off(ThreeDDiceRollEvent.RollFinished);
        resolve();
      }
    });
  }

  resize(w: number, h: number): void {
    if (this.instance) {
      try {
        this.instance.resize(w, h);
      } catch {
        // ignore resize errors
      }
    }
  }

  clear(): void {
    if (this.instance) {
      this.instance.clear();
    }
  }

  destroy(): void {
    if (this.instance) {
      try {
        this.instance.stop();
      } catch {
        // ignore teardown errors
      }
      this.instance = undefined;
    }
    this.api = undefined;
    this.#ready = false;
    this.initPromise = undefined;
    this.loadedThemes.clear();
  }
}

/**
 * Factory to create a new independent DddiceInstance.
 * Each instance manages its own ThreeDDice renderer on its own canvas.
 */
export function createDddiceInstance(): DddiceInstance {
  return new DddiceInstance();
}

// Singleton for cooperative mode (backwards-compatible)
class DddiceService extends DddiceInstance {
  token: string | undefined = undefined;

  isAvailable(): boolean {
    return isAvailable();
  }

  override async init(canvas: HTMLCanvasElement): Promise<boolean> {
    if (this.initPromise) return this.initPromise;
    if (!this.isAvailable()) {
      // console.log('[dddice] Not available (WebGL or disabled)');
      return false;
    }
    this.initPromise = this._doInit(canvas, {});
    return this.initPromise;
  }

  override destroy(): void {
    super.destroy();
    this.token = undefined;
  }
}

export default new DddiceService();
export { isAvailable as isDddiceAvailable };
