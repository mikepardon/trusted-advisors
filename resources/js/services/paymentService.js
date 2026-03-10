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
 * Uses the actual WTN.inAppPurchase({ productId, callback }) API.
 * Returns a promise that resolves with { receiptData, transactionId }.
 */
export function purchaseIAP(productId, isSubscription = false) {
    return new Promise((resolve, reject) => {
        if (!window.WTN?.inAppPurchase) {
            reject(new Error('IAP not available'));
            return;
        }

        const params = {
            productId,
            callback: (data) => {
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
        if (isAndroid()) {
            params.productType = isSubscription ? 'SUBS' : 'INAPP';
            params.isConsumable = false;
        }

        window.WTN.inAppPurchase(params);
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
 * iOS: WTN.getReceiptData({ callback })
 * Android: WTN.getAllPurchases({ callback })
 */
export function restorePurchases() {
    return new Promise((resolve, reject) => {
        if (!window.WTN) {
            reject(new Error('IAP not available'));
            return;
        }

        if (isAndroid() && window.WTN.getAllPurchases) {
            window.WTN.getAllPurchases({
                callback: (data) => {
                    if (data.isSuccess) {
                        // Android returns purchaseData array
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
        } else if (window.WTN.getReceiptData) {
            // iOS: returns a single receipt covering all transactions
            window.WTN.getReceiptData({
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
 * Get receipt data for verification (iOS).
 */
export function getReceiptData() {
    return new Promise((resolve, reject) => {
        if (!window.WTN?.getReceiptData) {
            reject(new Error('Receipt data not available'));
            return;
        }

        window.WTN.getReceiptData({
            callback: (data) => {
                if (data.isSuccess) {
                    resolve(data.receiptData);
                } else {
                    reject(new Error(data.error || 'Failed to get receipt'));
                }
            },
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
export async function completePurchaseIAP(productId, isSubscription = false) {
    const platform = getPaymentPlatform();

    // Do the IAP purchase via WTN
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