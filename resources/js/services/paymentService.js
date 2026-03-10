import axios from 'axios';
import { isIosApp, isAndroidApp, isNativeApp } from 'webtonative';
import { inAppPurchase, getReceiptData, getAllPurchases } from 'webtonative/InAppPurchase';

/**
 * Detect if running inside a WebToNative wrapper app.
 */
export function isWebToNative() {
    return isNativeApp;
}

export function isIOS() {
    return isIosApp;
}

export function isAndroid() {
    return isAndroidApp;
}

/**
 * Get the appropriate payment platform for the current environment.
 */
export function getPaymentPlatform() {
    if (isIosApp) return 'apple';
    if (isAndroidApp) return 'google';
    return 'stripe';
}

/**
 * Purchase an item via IAP (WebToNative SDK).
 * Returns a promise that resolves with { receiptData, transactionId }.
 */
export function purchaseIAP(productId, isSubscription = false) {
    return new Promise((resolve, reject) => {
        let settled = false;

        // Timeout after 60s — if native never responds, don't hang forever
        const timeout = setTimeout(() => {
            if (!settled) {
                settled = true;
                reject(new Error('Purchase timed out. Please check that In-App Purchases are enabled and the product is configured in App Store Connect.'));
            }
        }, 60000);

        const params = {
            productId,
            callback: (data) => {
                if (settled) return;
                settled = true;
                clearTimeout(timeout);
                if (data.isSuccess) {
                    resolve({
                        receiptData: data.receiptData,
                        // WTN doesn't provide a separate transaction ID;
                        // generate one for backend duplicate detection
                        transactionId: 'wtn_' + Date.now() + '_' + Math.random().toString(36).slice(2, 11),
                    });
                } else {
                    reject(new Error(data.error || 'Purchase failed'));
                }
            },
        };

        // Android requires additional params
        if (isAndroidApp) {
            params.productType = isSubscription ? 'SUBS' : 'INAPP';
            params.isConsumable = false;
        }

        console.log('[IAP] Starting purchase', { productId, platform: getPaymentPlatform(), isSubscription });
        inAppPurchase(params);
    });
}

/**
 * Subscribe to premium via IAP.
 */
export function subscribePremiumIAP(productId) {
    return purchaseIAP(productId, true);
}

/**
 * Restore purchases via IAP.
 * iOS: getReceiptData()
 * Android: getAllPurchases()
 */
export function restorePurchases() {
    return new Promise((resolve, reject) => {
        if (isAndroidApp) {
            getAllPurchases({
                callback: (data) => {
                    if (data.isSuccess) {
                        const purchases = (data.purchaseData || []).map(p => ({
                            product_id: p.productId || p.product_id,
                            transaction_id: p.orderId || p.transactionId || ('wtn_restore_' + Date.now()),
                            receipt_data: p.purchaseToken || p.receiptData || data.receiptData,
                        }));
                        resolve(purchases);
                    } else {
                        reject(new Error(data.error || 'Restore failed'));
                    }
                },
            });
        } else if (isIosApp) {
            getReceiptData({
                callback: (data) => {
                    if (data.isSuccess && data.receiptData) {
                        resolve([{
                            product_id: 'restore',
                            transaction_id: 'wtn_restore_' + Date.now(),
                            receipt_data: data.receiptData,
                        }]);
                    } else {
                        resolve([]);
                    }
                },
            });
        } else {
            reject(new Error('Restore not available'));
        }
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
export async function completePurchaseIAP(productId, isSubscription = false) {
    const platform = getPaymentPlatform();

    // Do the IAP purchase via WTN SDK
    const purchaseResult = await purchaseIAP(productId, isSubscription);

    // Verify with backend
    const res = await verifyIAPPurchase(
        platform,
        productId,
        purchaseResult.transactionId,
        purchaseResult.receiptData
    );

    return res.data;
}