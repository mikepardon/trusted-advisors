import axios from 'axios';

const ONESIGNAL_APP_ID = import.meta.env.VITE_ONESIGNAL_APP_ID;

let initialized = false;

export async function initOneSignal() {
    if (!ONESIGNAL_APP_ID || initialized) return;
    initialized = true;

    try {
        // Load OneSignal SDK
        await loadScript('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js');

        window.OneSignalDeferred = window.OneSignalDeferred || [];
        window.OneSignalDeferred.push(async function (OneSignal) {
            await OneSignal.init({
                appId: ONESIGNAL_APP_ID,
                allowLocalhostAsSecureOrigin: true,
            });

            OneSignal.Notifications.addEventListener('permissionChange', async (granted) => {
                if (granted) {
                    await registerPlayerId();
                }
            });

            // If already subscribed, register
            const permission = await OneSignal.Notifications.permission;
            if (permission) {
                await registerPlayerId();
            }
        });
    } catch (e) {
        console.warn('OneSignal init failed:', e);
    }
}

export async function promptPushPermission() {
    if (!ONESIGNAL_APP_ID) return;

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async function (OneSignal) {
        await OneSignal.Notifications.requestPermission();
        await registerPlayerId();
    });
}

async function registerPlayerId() {
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async function (OneSignal) {
        const id = await OneSignal.User.PushSubscription.id;
        if (id) {
            try {
                await axios.post('/api/auth/push-subscribe', { player_id: id });
            } catch {
                // ignore - user may not be logged in yet
            }
        }
    });
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.defer = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}
