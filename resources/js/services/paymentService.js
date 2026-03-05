import axios from 'axios';

/**
 * Detect if running inside a WebToNative (WTN) wrapper app.
 */
export function isWebToNative() {
    return !!(window.WTN);
}

export function isIOS() {
    return isWebToNative() && /iPhone|iPad|iPod/i.test(navigator.userAgent);
}

export function isAndroid() {
    return isWebToNative() && /Android/i.test(navigator.userAgent);
}

/**
 * Get the appropriate payment platform for the current environment.
 */
export function getPaymentPlatform() {
    if (isIOS()) return 'apple';
    if (isAndroid()) return 'google';
    return 'stripe';
}

/**
 * Purchase an item via IAP (WebToNative wrapper).
 * Returns a promise that resolves with purchase data.
 */
export function purchaseIAP(productId) {
    return new Promise((resolve, reject) => {
        if (!window.WTN?.inAppPurchase) {
            reject(new Error('IAP not available'));
            return;
        }

        window.WTN.inAppPurchase.purchase(productId, (result) => {
            if (result.success) {
                resolve(result);
            } else {
                reject(new Error(result.error || 'Purchase failed'));
            }
        });
    });
}

/**
 * Subscribe to premium via IAP.
 */
export function subscribePremiumIAP(productId) {
    return purchaseIAP(productId);
}

/**
 * Restore purchases via IAP.
 */
export function restorePurchases() {
    return new Promise((resolve, reject) => {
        if (!window.WTN?.inAppPurchase) {
            reject(new Error('IAP not available'));
            return;
        }

        window.WTN.inAppPurchase.restorePurchases((result) => {
            if (result.success) {
                resolve(result.purchases || []);
            } else {
                reject(new Error(result.error || 'Restore failed'));
            }
        });
    });
}

/**
 * Get receipt data for verification.
 */
export function getReceiptData() {
    return new Promise((resolve, reject) => {
        if (!window.WTN?.inAppPurchase) {
            reject(new Error('IAP not available'));
            return;
        }

        window.WTN.inAppPurchase.getReceiptData((result) => {
            if (result.success) {
                resolve(result.receiptData);
            } else {
                reject(new Error(result.error || 'Failed to get receipt'));
            }
        });
    });
}

/**
 * Start a Stripe Checkout session (web users).
 * Redirects the browser to Stripe's hosted checkout page.
 */
export async function stripeCheckout(mode, unlockableId = null) {
    const payload = { mode };
    if (unlockableId) {
        payload.unlockable_id = unlockableId;
    }

    const res = await axios.post('/api/purchase/stripe/checkout', payload);
    if (res.data.url) {
        window.location.href = res.data.url;
    }
    return res.data;
}

/**
 * Verify an IAP purchase with the backend.
 */
export async function verifyIAPPurchase(platform, productId, transactionId, receiptData) {
    return axios.post('/api/purchase/iap/verify', {
        platform,
        product_id: productId,
        transaction_id: transactionId,
        receipt_data: receiptData,
    });
}

/**
 * Restore purchases via the backend.
 */
export async function restorePurchasesBackend(platform, receipts) {
    return axios.post('/api/purchase/restore', { platform, receipts });
}

/**
 * Get the Stripe customer portal URL for managing subscriptions.
 */
export async function getManageSubscriptionUrl() {
    const res = await axios.get('/api/premium/manage');
    return res.data.url;
}

/**
 * Complete IAP flow: purchase + verify with backend.
 */
export async function completePurchaseIAP(productId) {
    const platform = getPaymentPlatform();

    // Do the IAP purchase
    const purchaseResult = await purchaseIAP(productId);

    // Verify with backend
    const res = await verifyIAPPurchase(
        platform,
        productId,
        purchaseResult.transactionId || purchaseResult.transaction_id,
        purchaseResult.receiptData || purchaseResult.receipt_data
    );

    return res.data;
}
