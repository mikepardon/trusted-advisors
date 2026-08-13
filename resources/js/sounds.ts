type SoundName =
  | 'click'
  | 'clickNav'
  | 'clickToggle'
  | 'clickCard'
  | 'clickButton'
  | 'clickMenu'
  | 'dice'
  | 'fail'
  | 'win'
  | 'totalLoss';

interface SoundSettings {
  music: boolean;
  ui: boolean;
  actions: boolean;
}

// Mutable module state grouped on an object so functions mutate its
// properties rather than reassigning top-level bindings.
const state: {
  context: AudioContext | undefined;
  soundUrls: Partial<Record<string, string>>;
  isUrlsFetched: boolean;
} = {
  context: undefined,
  soundUrls: {},
  isUrlsFetched: false,
};

const bufferCache = new Map<string, AudioBuffer>();

const defaultPaths: Record<SoundName, string> = {
  click: '/sounds/Click_01.wav',
  clickNav: '/sounds/Click_01.wav',
  clickToggle: '/sounds/Click_03.wav',
  clickCard: '/sounds/Click_04.wav',
  clickButton: '/sounds/Click_05.wav',
  clickMenu: '/sounds/Click_06.wav',
  dice: '/sounds/Dice_02.wav',
  fail: '/sounds/Fail_01.wav',
  win: '/sounds/Win_01.wav',
  totalLoss: '/sounds/Total_Loss_02.wav',
};

const soundCategories: Partial<Record<SoundName, 'ui' | 'actions'>> = {
  click: 'ui',
  clickNav: 'ui',
  clickToggle: 'ui',
  clickCard: 'ui',
  clickButton: 'ui',
  clickMenu: 'ui',
  dice: 'ui',
  win: 'actions',
  fail: 'actions',
  totalLoss: 'actions',
};

export function getSoundSettings(): SoundSettings {
  const defaults: SoundSettings = { music: true, ui: true, actions: true };
  try {
    const saved: unknown = JSON.parse(localStorage.getItem('soundSettings') ?? '{}');
    if (saved !== undefined && saved !== null && typeof saved === 'object') {
      return { ...defaults, ...(saved as Partial<SoundSettings>) };
    }
    return defaults;
  } catch {
    return defaults;
  }
}

export function saveSoundSettings(settings: SoundSettings): void {
  localStorage.setItem('soundSettings', JSON.stringify(settings));
}

function getContext(): AudioContext {
  const AudioContextConstructor =
    globalThis.AudioContext ??
    (globalThis as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
  state.context ??= new AudioContextConstructor();
  if (state.context.state === 'suspended') {
    void state.context.resume();
  }
  return state.context;
}

export async function fetchSoundUrls(): Promise<void> {
  if (state.isUrlsFetched) {
    return;
  }
  state.isUrlsFetched = true;
  try {
    const response = await fetch('/api/sound-assets');
    const data: unknown = await response.json();
    if (data !== undefined && data !== null && typeof data === 'object') {
      state.soundUrls = data as Record<string, string>;
    }
  } catch {
    // ignore fetch errors — fall back to default paths
  }
}

async function playBuffer(context: AudioContext, url: string): Promise<void> {
  const cached = bufferCache.get(url);
  if (cached) {
    const source = context.createBufferSource();
    source.buffer = cached;
    source.connect(context.destination);
    source.start(0);
    return;
  }

  const response = await fetch(url);
  const rawBuffer = await response.arrayBuffer();
  const audioBuffer = await context.decodeAudioData(rawBuffer);
  bufferCache.set(url, audioBuffer);
  const source = context.createBufferSource();
  source.buffer = audioBuffer;
  source.connect(context.destination);
  source.start(0);
}

export function playSound(name: SoundName): void {
  const category = soundCategories[name];
  if (category) {
    const settings = getSoundSettings();
    if (category === 'ui' && !settings.ui) {
      return;
    }
    if (category === 'actions' && !settings.actions) {
      return;
    }
  }

  const url = state.soundUrls[name] ?? defaultPaths[name];
  if (!url) {
    return;
  }

  try {
    const context = getContext();
    void playBuffer(context, url).catch(() => {
      // ignore audio errors
    });
  } catch {
    // ignore audio errors
  }
}
