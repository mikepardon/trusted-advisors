<template>
    <TaSubPage title="Premium" back="/">
        <div class="premium-page">
            <!-- Hero -->
            <div class="premium-hero">
                <span class="hero-star">&#9733;</span>
                <h1 class="hero-title">Trusted Advisors Premium</h1>
                <p class="hero-sub">
                    Unlock the full potential of your kingdom
                </p>
            </div>

            <!-- Already premium -->
            <div v-if="isPremium" class="premium-active-card">
                <span class="active-icon">&#9733;</span>
                <h2>Premium Active</h2>
                <p>
                    You have access to all premium features. Thank you for your
                    support!
                </p>
                <button class="manage-btn" @click="manageSub">
                    Manage Subscription
                </button>
            </div>

            <!-- Feature cards -->
            <div class="features-grid">
                <div class="feature-card">
                    <span class="feature-icon">&#128202;</span>
                    <h3>Detailed Stats & Analytics</h3>
                    <p>
                        Deep dive into your game history with win rates, stat
                        trends, character performance, and more.
                    </p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">&#9881;</span>
                    <h3>Custom Game Creation</h3>
                    <p>
                        Choose starting stats, card and event pools, and house
                        rules like no negative effects or hardcore mode.
                    </p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">&#128274;</span>
                    <h3>Private Lobbies</h3>
                    <p>
                        Create password-protected games for your friends.
                        Control who joins your kingdom.
                    </p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">&#127942;</span>
                    <h3>Tournament Mode</h3>
                    <p>
                        Organize bracket-style tournaments for 4, 8, or 16
                        players. Compete for ultimate glory.
                    </p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon">&#128142;</span>
                    <h3>Exclusive Cosmetics</h3>
                    <p>
                        Access premium-only dice themes, kingdom styles, and
                        advisor skins in the shop.
                    </p>
                </div>
            </div>

            <!-- Pricing -->
            <div
                v-if="!isPremium && auth.state.user?.payments_enabled"
                class="pricing-section"
            >
                <div class="price-card">
                    <h3 class="price-title">Trusted Advisors Premium</h3>
                    <div v-if="priceLoading" class="price-loading">
                        Loading price...
                    </div>
                    <template v-else-if="price">
                        <div class="price-amount">
                            {{
                                formatPrice(price.amount_cents, price.currency)
                            }}
                        </div>
                        <div v-if="price.interval" class="price-interval">
                            per
                            {{
                                price.interval_count > 1
                                    ? price.interval_count + " "
                                    : ""
                            }}{{ price.interval
                            }}{{ price.interval_count > 1 ? "s" : "" }}
                        </div>
                        <p class="price-description">
                            Auto-renewable subscription. Includes all premium
                            features: detailed stats &amp; analytics, custom
                            game creation, private lobbies, tournament mode, and
                            exclusive cosmetics.
                        </p>
                    </template>
                    <button
                        class="subscribe-btn"
                        :disabled="subscribing"
                        @click="subscribe"
                    >
                        {{ subscribing ? "Processing..." : "Subscribe Now" }}
                    </button>
                    <p class="subscription-terms">
                        Payment will be charged to your Apple ID account at
                        confirmation of purchase. Subscription automatically
                        renews unless auto-renew is turned off at least 24 hours
                        before the end of the current period.
                    </p>
                </div>
            </div>

            <!-- Legal links -->
            <div class="legal-links">
                <router-link to="/terms">Terms of Use (EULA)</router-link>
                <span class="legal-divider">|</span>
                <router-link to="/privacy">Privacy Policy</router-link>
            </div>
        </div>
    </TaSubPage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import axios, { isAxiosError } from "axios";
import TaSubPage from "./TaSubPage.vue";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import {
    stripeCheckout,
    getPaymentPlatform,
    completePurchaseIAP,
} from "../services/payment-service";

interface PremiumPrice {
    amount_cents: number;
    currency: string;
    interval?: string;
    interval_count: number;
    apple_product_id?: string;
    google_product_id?: string;
}

const auth = useAuth();
const toast = useToast();

const price = ref<PremiumPrice>();
const priceLoading = ref(true);
const subscribing = ref(false);

const isPremium = computed(() => auth.state.user?.is_premium);

onMounted(async () => {
    await fetchPrice();
});

async function fetchPrice(): Promise<void> {
    priceLoading.value = true;
    try {
        const response = await axios.get<PremiumPrice>("/api/premium/price");
        price.value = response.data;
    } catch {
        // Price may not be configured
    }
    priceLoading.value = false;
}

function formatPrice(cents: number, currency: string): string {
    const amount = (cents / 100).toFixed(2);
    const symbols: Record<string, string> = {
        USD: "$",
        EUR: "\u{20AC}",
        GBP: "\u{00A3}",
    };
    const symbol = symbols[currency] ?? `${currency} `;
    return `${symbol}${amount}`;
}

async function subscribe(): Promise<void> {
    subscribing.value = true;
    const platform = getPaymentPlatform();
    console.log("[Premium] subscribe clicked", {
        platform,
        price: price.value,
    });
    try {
        if (platform === "stripe") {
            await stripeCheckout("subscription");
        } else {
            const productId =
                platform === "apple"
                    ? price.value?.apple_product_id
                    : price.value?.google_product_id;
            console.log("[Premium] IAP productId:", productId);
            if (!productId) {
                toast.error("IAP product not configured.");
                subscribing.value = false;
                return;
            }
            const result = await completePurchaseIAP(productId, true);
            console.log("[Premium] IAP result:", result);
            if (auth.state.user) {
                auth.state.user.is_premium = true;
            }
            toast.success("Premium activated!");
        }
    } catch (error) {
        console.error("[Premium] subscribe error:", error);
        toast.error(subscribeErrorMessage(error));
    }
    subscribing.value = false;
}

function subscribeErrorMessage(error: unknown): string {
    if (isAxiosError<{ error?: string }>(error)) {
        return (
            error.response?.data?.error ?? error.message ?? "Purchase failed."
        );
    }
    return "Purchase failed.";
}

async function manageSub(): Promise<void> {
    const platform = getPaymentPlatform();
    if (platform === "apple") {
        window.location.assign("https://apps.apple.com/account/subscriptions");
        return;
    }
    if (platform === "google") {
        window.location.assign(
            "https://play.google.com/store/account/subscriptions",
        );
        return;
    }
    try {
        const response = await axios.get<{ url?: string }>(
            "/api/premium/manage",
        );
        if (response.data.url) {
            window.location.assign(response.data.url);
        }
    } catch {
        toast.error("Could not open subscription management.");
    }
}
</script>

<style scoped>
.premium-page {
    max-width: 600px;
    margin: 0 auto;
    padding: 0 16px 100px;
}

.back-btn {
    background: none;
    border: 1px solid rgba(138, 106, 46, 0.4);
    color: var(--text-secondary);
    font-size: 0.85rem;
    padding: 6px 14px;
    cursor: pointer;
    margin-bottom: 8px;
    border-radius: 6px;
    letter-spacing: 0;
}

.back-btn:hover {
    color: var(--text-bright);
    border-color: var(--border-gold);
}

.premium-hero {
    text-align: center;
    padding: 16px 0 16px;
}

.hero-star {
    font-size: 2.4rem;
    color: var(--accent-gold);
    display: block;
    margin-bottom: 4px;
    filter: drop-shadow(0 0 12px rgba(212, 168, 67, 0.5));
}

.hero-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.3rem;
    margin: 0 0 4px;
}

.hero-sub {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 0.9rem;
    margin: 0;
}

.premium-active-card {
    text-align: center;
    padding: 24px;
    margin-bottom: 24px;
    background: rgba(106, 191, 80, 0.08);
    border: 2px solid rgba(106, 191, 80, 0.3);
    border-radius: 12px;
}

.premium-active-card .active-icon {
    font-size: 2rem;
    color: #6abf50;
}

.premium-active-card h2 {
    font-family: "Cinzel", serif;
    color: #6abf50;
    font-size: 1.2rem;
    margin: 8px 0;
}

.premium-active-card p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 16px;
}

.manage-btn {
    font-family: "Cinzel", serif;
    font-size: 0.85rem;
    padding: 8px 20px;
    border-radius: 6px;
    border: 1px solid rgba(106, 191, 80, 0.4);
    background: rgba(106, 191, 80, 0.12);
    color: #6abf50;
    cursor: pointer;
}

.manage-btn:hover {
    background: rgba(106, 191, 80, 0.25);
}

.features-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 16px;
}

.feature-card {
    background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
    border: 1px solid rgba(138, 106, 46, 0.25);
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.feature-icon {
    font-size: 1.3rem;
    flex-shrink: 0;
    margin-top: 1px;
}

.feature-card h3 {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.85rem;
    margin: 0 0 2px;
}

.feature-card p {
    color: var(--text-secondary);
    font-size: 0.78rem;
    margin: 0;
    line-height: 1.35;
}

@media (max-width: 480px) {
    .feature-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
}

.pricing-section {
    text-align: center;
}

.price-card {
    background: linear-gradient(
        135deg,
        rgba(212, 168, 67, 0.12),
        rgba(180, 120, 30, 0.06)
    );
    border: 2px solid var(--accent-gold);
    border-radius: 14px;
    padding: 20px 20px;
}

.price-loading {
    color: var(--text-secondary);
    font-style: italic;
    padding: 12px 0;
}

.price-amount {
    font-family: "Cinzel", serif;
    font-size: 1.8rem;
    color: var(--accent-gold);
    font-weight: 700;
}

.price-interval {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-bottom: 14px;
}

.subscribe-btn {
    font-family: "Cinzel", serif;
    font-size: 1rem;
    padding: 12px 36px;
    border-radius: 10px;
    border: 2px solid var(--accent-gold);
    background: linear-gradient(
        180deg,
        rgba(212, 168, 67, 0.25),
        rgba(180, 120, 30, 0.15)
    );
    color: var(--accent-gold);
    cursor: pointer;
    font-weight: 700;
    transition: all 0.2s;
    margin-top: 8px;
}

.subscribe-btn:hover:not(:disabled) {
    background: linear-gradient(
        180deg,
        rgba(212, 168, 67, 0.4),
        rgba(180, 120, 30, 0.25)
    );
    box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
}

.subscribe-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.price-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    margin: 0 0 8px;
}

.price-description {
    color: var(--text-secondary);
    font-size: 0.8rem;
    line-height: 1.4;
    margin: 8px 0 4px;
}

.subscription-terms {
    color: var(--text-secondary);
    font-size: 0.7rem;
    line-height: 1.4;
    margin: 12px 0 0;
    opacity: 0.7;
}

.legal-links {
    text-align: center;
    margin-top: 20px;
    padding-bottom: 20px;
}

.legal-links a {
    color: var(--text-secondary);
    font-size: 0.8rem;
    text-decoration: underline;
    transition: color 0.2s;
}

.legal-links a:hover {
    color: var(--accent-gold);
}

.legal-divider {
    color: var(--text-secondary);
    margin: 0 8px;
    opacity: 0.4;
}
</style>
