import axios from 'axios';
import type { AxiosResponse } from 'axios';
import { isIosApp, isAndroidApp, isNativeApp } from 'webtonative';
import { inAppPurchase, getReceiptData, getAllPurchases } from 'webtonative/InAppPurchase';
import type { InAppPurchaseOptions, InAppPurchaseResponse } from 'webtonative/InAppPurchase/types';

export type PaymentPlatform = 'apple' | 'google' | 'stripe';

export interface PurchaseResult {
    receiptData: unknown;
    transactionId: string;
}

export interface RestoredPurchase {
    product_id: string;
    transaction_id: string;
    receipt_data: unknown;
}

/**
 * Detect if running inside a WebToNative wrapper app.
 */
export function isWebToNative(): boolean {
    return isNativeApp;
}

export function isIOS(): boolean {
    return isIosApp;
}

export function isAndroid(): boolean {
    return isAndroidApp;
}

/**
 * Get the appropriate payment platform for the current environment.
 */
export function getPaymentPlatform(): PaymentPlatform {
    if (isIosApp) return 'apple';
    if (isAndroidApp) return 'google';
    return 'stripe';
}

/**
 * Purchase an item via IAP (WebToNative SDK).
 * Returns a promise that resolves with { receiptData, transactionId }.
 */
export function purchaseIAP(productId: string, isSubscription = false): Promise<PurchaseResult> {
    return new Promise((resolve, reject) => {
        let isSettled = false;

        // Timeout after 60s — if native never responds, don't hang forever
        const timeout = setTimeout(() => {
            if (isSettled) {
            	return;
            }

            isSettled = true;
            reject(new Error('Purchase timed out. Please check that In-App Purchases are enabled and the product is configured in App Store Connect.'));
        }, 60_000);

        const parameters: InAppPurchaseOptions = {
            productId,
            callback: (data: InAppPurchaseResponse) => {
                if (isSettled) return;
                isSettled = true;
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
            parameters.productType = isSubscription ? 'SUBS' : 'INAPP';
            parameters.isConsumable = false;
        }

        console.log('[IAP] Starting purchase', { productId, platform: getPaymentPlatform(), isSubscription });
        inAppPurchase(parameters);
    });
}

/**
 * Subscribe to premium via IAP.
 */
export function subscribePremiumIAP(productId: string): Promise<PurchaseResult> {
    return purchaseIAP(productId, true);
}

/**
 * Restore purchases via IAP.
 * iOS: getReceiptData()
 * Android: getAllPurchases()
 */
export function restorePurchases(): Promise<RestoredPurchase[]> {
    return new Promise((resolve, reject) => {
        if (isAndroidApp) {
            getAllPurchases({
                callback: (data: InAppPurchaseResponse) => {
                    if (data.isSuccess) {
                        const purchases: RestoredPurchase[] = (data.purchaseData || []).map((p: Record<string, unknown>) => ({
                            product_id: (p.productId || p.product_id) as string,
                            transaction_id: (p.orderId || p.transactionId || ('wtn_restore_' + Date.now())) as string,
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
                callback: (data: InAppPurchaseResponse) => {
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
export async function stripeCheckout(mode: string, unlockableId?: number): Promise<unknown> {
    const payload: { mode: string; unlockable_id?: number } = { mode };
    if (unlockableId) {
        payload.unlockable_id = unlockableId;
    }

    const response = await axios.post('/api/purchase/stripe/checkout', payload);
    if (response.data.url) {
        location.assign(response.data.url);
    }
    return response.data;
}

/**
 * Verify an IAP purchase with the backend.
 */
export async function verifyIAPPurchase(platform: PaymentPlatform, productId: string, transactionId: string, receiptData: unknown): Promise<AxiosResponse> {
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
export async function restorePurchasesBackend(platform: PaymentPlatform, receipts: RestoredPurchase[]): Promise<AxiosResponse> {
    return axios.post('/api/purchase/restore', { platform, receipts });
}

/**
 * Get the Stripe customer portal URL for managing subscriptions.
 */
export async function getManageSubscriptionUrl(): Promise<string> {
    const response = await axios.get('/api/premium/manage');
    return response.data.url;
}

/**
 * Complete IAP flow: purchase + verify with backend.
 */
export async function completePurchaseIAP(productId: string, isSubscription = false): Promise<unknown> {
    const platform = getPaymentPlatform();

    // Do the IAP purchase via WTN SDK
    const purchaseResult = await purchaseIAP(productId, isSubscription);

    // Verify with backend
    const response = await verifyIAPPurchase(
        platform,
        productId,
        purchaseResult.transactionId,
        purchaseResult.receiptData
    );

    return response.data;
}
