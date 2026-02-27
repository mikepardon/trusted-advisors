let ctx = null;
const bufferCache = {};
let soundUrls = {};
let urlsFetched = false;

const defaultPaths = {
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

const soundCategories = {
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

export function getSoundSettings() {
  try {
    const saved = JSON.parse(localStorage.getItem('soundSettings'));
    return { music: true, ui: true, actions: true, ...saved };
  } catch {
    return { music: true, ui: true, actions: true };
  }
}

export function saveSoundSettings(settings) {
  localStorage.setItem('soundSettings', JSON.stringify(settings));
}

function getContext() {
  if (!ctx) {
    ctx = new (window.AudioContext || window.webkitAudioContext)();
  }
  if (ctx.state === 'suspended') {
    ctx.resume();
  }
  return ctx;
}

export function fetchSoundUrls() {
  if (urlsFetched) return;
  urlsFetched = true;
  fetch('/api/sound-assets')
    .then(r => r.json())
    .then(data => { soundUrls = data; })
    .catch(() => {});
}

export function playSound(name) {
  const category = soundCategories[name];
  if (category) {
    const settings = getSoundSettings();
    if (category === 'ui' && !settings.ui) return;
    if (category === 'actions' && !settings.actions) return;
  }

  const url = soundUrls[name] || defaultPaths[name];
  if (!url) return;

  try {
    const context = getContext();

    if (bufferCache[url]) {
      const source = context.createBufferSource();
      source.buffer = bufferCache[url];
      source.connect(context.destination);
      source.start(0);
    } else {
      fetch(url)
        .then(r => r.arrayBuffer())
        .then(buf => context.decodeAudioData(buf))
        .then(audioBuf => {
          bufferCache[url] = audioBuf;
          const source = context.createBufferSource();
          source.buffer = audioBuf;
          source.connect(context.destination);
          source.start(0);
        })
        .catch(() => {});
    }
  } catch {
    // ignore audio errors
  }
}
