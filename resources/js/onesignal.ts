import axios from 'axios';

// Minimal shape of the OneSignal web SDK (loaded at runtime from the CDN).
interface OneSignalSdk {
  init(options: { appId: string; allowLocalhostAsSecureOrigin?: boolean }): Promise<void>;
  Notifications: {
    permission: boolean | Promise<boolean>;
    requestPermission(): Promise<void>;
    addEventListener(
      event: 'permissionChange',
      listener: (isGranted: boolean) => void,
    ): void;
  };
  User: {
    PushSubscription: {
      id: string | undefined | Promise<string | undefined>;
    };
  };
}

type OneSignalDeferredCallback = (oneSignal: OneSignalSdk) => void | Promise<void>;

const ONESIGNAL_APP_ID: string | undefined = import.meta.env.VITE_ONESIGNAL_APP_ID;

const state = {
  isInitialized: false,
};

function getDeferredQueue(): OneSignalDeferredCallback[] {
  const existing = Reflect.get(globalThis, 'OneSignalDeferred') as
    | OneSignalDeferredCallback[]
    | undefined;
  if (existing) {
    return existing;
  }
  const queue: OneSignalDeferredCallback[] = [];
  Reflect.set(globalThis, 'OneSignalDeferred', queue);
  return queue;
}

export async function initOneSignal(): Promise<void> {
  if (!ONESIGNAL_APP_ID || state.isInitialized) {
    return;
  }
  state.isInitialized = true;

  try {
    // Load OneSignal SDK
    await loadScript('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js');

    getDeferredQueue().push(async (oneSignal: OneSignalSdk) => {
      await oneSignal.init({
        appId: ONESIGNAL_APP_ID,
        allowLocalhostAsSecureOrigin: true,
      });

      oneSignal.Notifications.addEventListener('permissionChange', (isGranted: boolean) => {
        if (isGranted) {
          void registerPlayerId();
        }
      });

      // If already subscribed, register
      const permission = await oneSignal.Notifications.permission;
      if (permission) {
        await registerPlayerId();
      }
    });
  } catch (error) {
    console.warn('OneSignal init failed:', error);
  }
}

export function promptPushPermission(): void {
  if (!ONESIGNAL_APP_ID) {
    return;
  }

  getDeferredQueue().push(async (oneSignal: OneSignalSdk) => {
    try {
      await oneSignal.Notifications.requestPermission();
      await registerPlayerId();
    } catch {
      // Permission blocked by browser — nothing to do
    }
  });
}

async function registerPlayerId(): Promise<void> {
  getDeferredQueue().push(async (oneSignal: OneSignalSdk) => {
    const id = await oneSignal.User.PushSubscription.id;
    if (id) {
      try {
        await axios.post('/api/auth/push-subscribe', { player_id: id });
      } catch {
        // ignore — user may not be logged in yet
      }
    }
  });
}

function loadScript(source: string): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${CSS.escape(source)}"]`)) {
      resolve();
      return;
    }
    const script = document.createElement('script');
    script.src = source;
    script.defer = true;
    script.addEventListener('load', () => {
      resolve();
    });
    script.addEventListener('error', () => {
      reject(new Error(`Failed to load script: ${source}`));
    });
    document.head.append(script);
  });
}
