function generateRandomString(length) {
    const array = new Uint8Array(length);
    crypto.getRandomValues(array);
    return Array.from(array, (byte) => byte.toString(36).padStart(2, '0')).join('').slice(0, length);
}

export function generateCodeVerifier() {
    return generateRandomString(64);
}

export async function generateCodeChallenge(verifier) {
    const encoder = new TextEncoder();
    const data = encoder.encode(verifier);

    // crypto.subtle is only available in secure contexts (HTTPS).
    // Fall back to js-sha256 for local HTTP dev.
    if (crypto.subtle) {
        const digest = await crypto.subtle.digest('SHA-256', data);
        return base64UrlEncode(new Uint8Array(digest));
    }

    // Manual SHA-256: import dynamically to keep bundle small in prod
    const { sha256 } = await import('./sha256.js');
    return base64UrlEncode(sha256(data));
}

function base64UrlEncode(bytes) {
    return btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

export function generateState() {
    return generateRandomString(32);
}
