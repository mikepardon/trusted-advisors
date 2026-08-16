<template>
    <TaSubPage title="Shop" subtitle="Advisors, gold and cosmetics">
        <template #stat>
            <div class="shop-head-actions">
                <button
                    v-if="freeChest.state.loaded"
                    class="shop-head-btn"
                    :class="{ ready: freeChest.state.available }"
                    :title="
                        freeChest.state.available
                            ? 'Free chest ready to claim'
                            : `Next chest in ${freeChestCountdown}`
                    "
                    @click="claimFreeChest"
                >
                    &#127873;
                    <span
                        v-if="freeChest.state.available"
                        class="ta-dot shop-head-dot"
                    ></span>
                    <span v-else class="shop-head-timer">{{
                        freeChestCountdown
                    }}</span>
                </button>
                <button
                    v-if="!isPremium && paymentsEnabled"
                    class="shop-head-btn shop-head-premium"
                    title="Go Premium"
                    @click="$router.push('/premium')"
                >
                    &#9733;
                </button>
            </div>
        </template>

        <div class="shop-page">
            <HintBubble hint-id="shop-coins">
                Earn coins by completing games and claiming achievements. Spend
                them here on cosmetics and unlocks!
            </HintBubble>

            <div class="ta-divider">
                <span class="ta-divider-label">{{ activeSectionLabel }}</span>
                <span class="ta-divider-line"></span>
            </div>

            <div v-if="loading" class="shop-loading">Loading...</div>

            <div v-else-if="items.length === 0" class="shop-empty">
                No items available in the shop right now.
            </div>

            <div v-else-if="filteredItems.length === 0" class="shop-empty">
                No items in this category.
            </div>

            <!-- Advisors for hire: 2-col portrait cards -->
            <div v-else-if="activeTab === 'character'" class="advisor-grid">
                <div
                    v-for="item in filteredItems"
                    :key="item.id"
                    class="advisor-card"
                    :class="{ owned: item.owned }"
                >
                    <div class="advisor-portrait-wrap">
                        <div
                            class="advisor-portrait"
                            :style="{
                                backgroundImage: `url(${item.image_url || '/images/character.png'})`,
                            }"
                        ></div>
                    </div>
                    <div class="advisor-name">{{ item.name }}</div>
                    <div v-if="item.wild_ability" class="advisor-tag">
                        Wild {{ item.wild_value }} &middot;
                        {{ item.wild_ability }}
                    </div>
                    <div v-else-if="item.description" class="advisor-tag">
                        {{ item.description }}
                    </div>

                    <button class="advisor-view" @click="viewCharacter(item)">
                        View
                    </button>

                    <button
                        v-if="item.owned"
                        class="ta-pill ta-pill--claimed advisor-buy"
                        :disabled="!item.price || coins < item.price"
                        @click="openGiftPicker(item)"
                    >
                        OWNED
                    </button>
                    <template v-else>
                        <button
                            v-if="item.price"
                            class="ta-pill ta-pill--gold advisor-buy"
                            :disabled="
                                coins < item.price || purchasing === item.id
                            "
                            @click="confirmPurchase(item)"
                        >
                            <span v-if="purchasing === item.id">...</span>
                            <span v-else>&#9673; {{ item.price }}</span>
                        </button>
                        <button
                            v-if="item.cash_price_cents"
                            class="ta-pill ta-pill--blue advisor-buy advisor-buy--cash"
                            :disabled="purchasing === item.id"
                            @click="purchaseCash(item)"
                        >
                            ${{ (item.cash_price_cents / 100).toFixed(2) }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Dice / kingdom styles / other: collection-style card grid -->
            <div v-else class="shop-card-grid">
                <div
                    v-for="item in filteredItems"
                    :key="item.id"
                    class="shop-card"
                    :class="{ owned: item.owned }"
                >
                    <div class="shop-card-preview">
                        <img
                            v-if="
                                item.type === 'dice_theme' && item.preview_image
                            "
                            :src="item.preview_image"
                            :alt="item.name"
                            class="shop-card-img"
                        />
                        <div
                            v-else-if="item.type === 'kingdom_style'"
                            class="shop-card-ks"
                            :data-kingdom-style="item.slug"
                            :data-ks-anim="item.css_vars?.border_anim || 'none'"
                            :style="ksPreviewStyle(item)"
                        >
                            <div class="ks-mini-bar ks-mini-safe"></div>
                            <div class="ks-mini-bar ks-mini-caution"></div>
                        </div>
                        <span v-else class="shop-card-glyph">{{
                            typeIcon(item.type)
                        }}</span>
                    </div>

                    <div class="shop-card-name">{{ item.name }}</div>
                    <div class="shop-card-meta">
                        {{ item.description || typeLabel(item.type) }}
                    </div>

                    <div class="shop-card-actions">
                        <button
                            v-if="item.type === 'dice_theme'"
                            class="row-action"
                            :disabled="tryingDice === item.id"
                            @click="tryDice(item)"
                        >
                            {{ tryingDice === item.id ? "..." : "Try" }}
                        </button>
                        <button
                            v-else-if="item.type === 'kingdom_style'"
                            class="row-action"
                            @click="viewKingdomStyle(item)"
                        >
                            View
                        </button>

                        <button
                            v-if="item.owned"
                            class="ta-pill ta-pill--claimed"
                            :disabled="!item.price || coins < item.price"
                            @click="openGiftPicker(item)"
                        >
                            OWNED
                        </button>
                        <template v-else>
                            <button
                                v-if="item.price"
                                class="ta-pill ta-pill--gold"
                                :disabled="
                                    coins < item.price || purchasing === item.id
                                "
                                @click="confirmPurchase(item)"
                            >
                                <span v-if="purchasing === item.id">...</span>
                                <span v-else>&#9673; {{ item.price }}</span>
                            </button>
                            <button
                                v-if="item.cash_price_cents"
                                class="ta-pill ta-pill--blue"
                                :disabled="purchasing === item.id"
                                @click="purchaseCash(item)"
                            >
                                ${{ (item.cash_price_cents / 100).toFixed(2) }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Restore Purchases (mobile only) -->
            <div v-if="!loading && isNativeApp" class="restore-section">
                <button
                    class="restore-btn"
                    :disabled="restoring"
                    @click="doRestorePurchases"
                >
                    {{ restoring ? "Restoring..." : "Restore Purchases" }}
                </button>
            </div>

            <!-- Confirmation Modal -->
            <div
                v-if="confirmItem"
                class="modal-overlay"
                @click.self="confirmItem = null"
            >
                <div class="modal-box">
                    <h3>Purchase</h3>
                    <p>
                        <strong>{{ confirmItem.name }}</strong> &mdash;
                        <strong>&#129689; {{ confirmItem.price }}</strong>
                    </p>
                    <div class="modal-actions modal-actions-col">
                        <button
                            class="modal-confirm"
                            @click="doPurchase(confirmItem)"
                        >
                            Buy for Myself
                        </button>
                        <button
                            class="modal-confirm gift-action-btn"
                            @click="
                                openGiftPicker(confirmItem);
                                confirmItem = null;
                            "
                        >
                            Gift a Friend
                        </button>
                        <button
                            class="modal-cancel"
                            @click="confirmItem = null"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Friend Picker Modal -->
            <div
                v-if="giftItem"
                class="modal-overlay"
                @click.self="giftItem = null"
            >
                <div class="modal-box friend-picker-box">
                    <h3>Gift to Friend</h3>
                    <p>
                        Send <strong>{{ giftItem.name }}</strong> (&#129689;
                        {{ giftItem.price }})
                    </p>
                    <div v-if="friendsLoading" class="shop-loading">
                        Loading friends...
                    </div>
                    <div
                        v-else-if="friends.length === 0"
                        class="shop-empty"
                        style="padding: 10px"
                    >
                        No friends yet. Add friends first!
                    </div>
                    <div v-else class="friend-list">
                        <div
                            v-for="f in friends"
                            :key="f.id"
                            class="friend-row"
                            @click="confirmGift(f)"
                        >
                            <div class="friend-info">
                                <span class="friend-name">{{ f.name }}</span>
                                <span class="friend-level"
                                    >Lv. {{ f.level || 1 }}</span
                                >
                            </div>
                            <span class="friend-select-icon">&#127873;</span>
                        </div>
                    </div>
                    <button
                        class="modal-cancel"
                        style="margin-top: 10px; width: 100%"
                        @click="giftItem = null"
                    >
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Gift Confirm Modal -->
            <div
                v-if="giftingTo"
                class="modal-overlay"
                @click.self="giftingTo = null"
            >
                <div class="modal-box">
                    <h3>Confirm Gift</h3>
                    <p>
                        Gift <strong>{{ giftingTo.item.name }}</strong> to
                        <strong>{{ giftingTo.friend.name }}</strong> for
                        <strong>&#129689; {{ giftingTo.item.price }}</strong
                        >?
                    </p>
                    <div class="modal-actions">
                        <button class="modal-cancel" @click="giftingTo = null">
                            Cancel
                        </button>
                        <button
                            class="modal-confirm"
                            :disabled="gifting"
                            @click="doGift"
                        >
                            {{ gifting ? "..." : "Send Gift" }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Character Detail Modal -->
            <div
                v-if="selectedChar"
                class="modal-overlay"
                @click.self="selectedChar = null"
            >
                <div class="char-modal-card">
                    <img
                        :src="selectedChar.image_url || '/images/character.png'"
                        :alt="selectedChar.name"
                        class="char-modal-portrait"
                    />
                    <h3 class="char-modal-name">{{ selectedChar.name }}</h3>
                    <p class="char-modal-desc">
                        {{ selectedChar.description }}
                    </p>
                    <div v-if="selectedChar.dice" class="char-modal-dice">
                        <div
                            v-for="(die, di) in selectedChar.dice"
                            :key="di"
                            class="dice-row"
                        >
                            <span class="dice-label">Die {{ di + 1 }}:</span>
                            <span
                                v-for="(face, fi) in die"
                                :key="fi"
                                class="dice-face"
                                >{{ face }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="selectedChar.wild_ability"
                        class="char-modal-wild"
                    >
                        <span class="wild-badge"
                            >W = {{ selectedChar.wild_value }}</span
                        >
                        <span class="wild-desc"
                            >{{ selectedChar.wild_ability }}:
                            {{ selectedChar.wild_ability_description }}</span
                        >
                    </div>
                    <button
                        class="btn-primary char-modal-close"
                        @click="selectedChar = null"
                    >
                        Close
                    </button>
                </div>
            </div>

            <!-- Kingdom Style Preview Modal -->
            <div
                v-if="previewStyle"
                class="modal-overlay"
                @click.self="previewStyle = null"
            >
                <div class="ks-modal-card">
                    <h3 class="ks-modal-title">{{ previewStyle.name }}</h3>
                    <div
                        class="ks-modal-preview"
                        :data-kingdom-style="previewStyle.slug"
                        :data-ks-anim="
                            previewStyle.css_vars?.border_anim || 'none'
                        "
                        :style="ksModalStyle(previewStyle)"
                    >
                        <div
                            class="ks-modal-name"
                            :style="{
                                color:
                                    previewStyle.css_vars?.name_accent ||
                                    'var(--accent-gold)',
                            }"
                        >
                            Kingdom Name
                        </div>
                        <div class="ks-modal-bars">
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_safe ||
                                        '#27ae60',
                                    width: '85%',
                                }"
                            ></div>
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_caution ||
                                        '#d4a843',
                                    width: '45%',
                                }"
                            ></div>
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_safe ||
                                        '#27ae60',
                                    width: '65%',
                                }"
                            ></div>
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_safe ||
                                        '#27ae60',
                                    width: '75%',
                                }"
                            ></div>
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_caution ||
                                        '#d4a843',
                                    width: '35%',
                                }"
                            ></div>
                            <div
                                class="ks-modal-bar"
                                :style="{
                                    background:
                                        previewStyle.css_vars?.bar_safe ||
                                        '#27ae60',
                                    width: '55%',
                                }"
                            ></div>
                        </div>
                        <div
                            class="ks-modal-total"
                            :style="{
                                color:
                                    previewStyle.css_vars?.total_accent ||
                                    'var(--accent-gold)',
                            }"
                        >
                            72
                        </div>
                    </div>
                    <p v-if="previewStyle.description" class="ks-modal-desc">
                        {{ previewStyle.description }}
                    </p>
                    <button
                        class="btn-primary char-modal-close"
                        @click="previewStyle = null"
                    >
                        Close
                    </button>
                </div>
            </div>

            <!-- Persistent dice canvas (always mounted, pointer-events: none) -->
            <canvas ref="diceCanvas" class="dice-canvas-persistent"></canvas>
        </div>

        <template v-if="!loading && items.length > 0" #footer>
            <nav class="ta-footer-nav">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="ta-footer-tab"
                    :class="{ active: activeTab === tab.key }"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </template>
    </TaSubPage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, useTemplateRef } from "vue";
import type { CSSProperties } from "vue";
import axios, { isAxiosError } from "axios";
import HintBubble from "./HintBubble.vue";
import TaSubPage from "./TaSubPage.vue";
import { DateTime } from "luxon";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { useFreeChest } from "../stores/free-chest";
import { useReward } from "../stores/reward";
import { createDddiceInstance, isDddiceAvailable } from "../dddice-service";
import {
    isWebToNative,
    getPaymentPlatform,
    stripeCheckout,
    completePurchaseIAP,
    restorePurchases,
    restorePurchasesBackend,
} from "../services/payment-service";
import "../styles/kingdom-styles.css";

type ShopItemType = "character" | "dice_theme" | "kingdom_style" | "item";

interface KingdomStyleCssVariables {
    border_color?: string;
    border_glow?: string;
    border_color_rgb?: string;
    border_anim?: string;
    bg_tint?: string;
    bg_color?: string;
    name_accent?: string;
    total_accent?: string;
    bar_safe?: string;
    bar_caution?: string;
}

interface ShopItem {
    id: number;
    type: ShopItemType;
    name: string;
    description?: string;
    slug?: string;
    image_url?: string;
    preview_image?: string;
    background_image_url?: string;
    css_vars?: KingdomStyleCssVariables;
    price?: number;
    cash_price_cents?: number;
    owned?: boolean;
    apple_product_id?: string;
    google_product_id?: string;
    dice?: number[][];
    wild_ability?: string;
    wild_value?: number;
    wild_ability_description?: string;
}

interface PremiumProduct {
    apple_product_id?: string;
    google_product_id?: string;
}

interface ShopFriend {
    id: number;
    name: string;
    level?: number;
}

interface GiftTarget {
    item: ShopItem;
    friend: ShopFriend;
}

interface ShopTab {
    key: ShopItemType;
    icon: string;
    label: string;
    count: number;
}

interface DddiceInstance {
    init(canvas: HTMLCanvasElement): Promise<boolean>;
    isReady(): boolean;
    roll(diceSpecs: { theme?: string; value: number }[]): Promise<void>;
    resize(width: number, height: number): void;
    destroy(): void;
}

const { updateUserStats, state: authState } = useAuth();
const toast = useToast();
const freeChest = useFreeChest();
const reward = useReward();

const items = ref<ShopItem[]>([]);
const coins = ref(0);
const chestNowMs = ref(0);
const chestTimer = ref<ReturnType<typeof setInterval>>();
const claimingChest = ref(false);

// Remaining time on the free chest, formatted "1h 32m" / "12m" / "45s".
const freeChestCountdown = computed(() => {
    const iso = freeChest.state.nextAvailableAtIso;
    if (iso === undefined) {
        return "";
    }
    const seconds = Math.max(
        0,
        Math.floor(
            (DateTime.fromISO(iso).toMillis() - chestNowMs.value) / 1000,
        ),
    );
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    if (minutes > 0) {
        return `${minutes}m`;
    }
    return `${seconds}s`;
});

function tickChest(): void {
    chestNowMs.value = DateTime.now().toMillis();
    if (
        !freeChest.state.available &&
        freeChest.state.nextAvailableAtIso !== undefined &&
        DateTime.fromISO(freeChest.state.nextAvailableAtIso).toMillis() <=
            chestNowMs.value
    ) {
        freeChest.markAvailable();
    }
}

async function claimFreeChest(): Promise<void> {
    if (!freeChest.state.available || claimingChest.value) {
        return;
    }
    claimingChest.value = true;
    const claimed = await freeChest.claim();
    claimingChest.value = false;
    if (claimed !== undefined) {
        coins.value += claimed;
        // The reveal overlay handles its own sound + haptics.
        reward.reveal({ amount: claimed, title: "Free Chest", icon: "🎁" });
    }
}
const isPremium = ref(false);
const premiumProduct = ref<PremiumProduct>();
const loading = ref(true);
const purchasing = ref<number>();
const restoring = ref(false);
const confirmItem = ref<ShopItem>();
const selectedChar = ref<ShopItem>();
const previewStyle = ref<ShopItem>();
const diceInstance = ref<DddiceInstance>();
const activeTab = ref<ShopItemType>("dice_theme");
const giftItem = ref<ShopItem>();
const friends = ref<ShopFriend[]>([]);
const friendsLoading = ref(false);
const giftingTo = ref<GiftTarget>();
const gifting = ref(false);
const tryingDice = ref<number>();
const isNativeApp = isWebToNative();

const diceCanvas = useTemplateRef<HTMLCanvasElement>("diceCanvas");
const onResize = ref<() => void>();

const paymentsEnabled = computed(
    () => authState.user?.payments_enabled ?? true,
);

const tabs = computed<ShopTab[]>(() => {
    const typeCounts: Record<string, number> = {};
    for (const item of items.value) {
        typeCounts[item.type] = (typeCounts[item.type] ?? 0) + 1;
    }
    const allTabs: ShopTab[] = [
        {
            key: "dice_theme",
            icon: "\u{1F3B2}",
            label: "Dice",
            count: typeCounts.dice_theme ?? 0,
        },
        {
            key: "character",
            icon: "\u{1F9D9}",
            label: "Advisors",
            count: typeCounts.character ?? 0,
        },
        {
            key: "kingdom_style",
            icon: "\u{1F3F0}",
            label: "Styles",
            count: typeCounts.kingdom_style ?? 0,
        },
    ];
    return allTabs.filter((tab) => tab.count > 0);
});

const filteredItems = computed(() =>
    items.value.filter((item) => item.type === activeTab.value),
);

const activeSectionLabel = computed(() => {
    const labels: Record<ShopItemType, string> = {
        character: "ADVISORS FOR HIRE",
        dice_theme: "DICE THEMES",
        kingdom_style: "KINGDOM STYLES",
        item: "TREASURY",
    };
    return labels[activeTab.value];
});

onMounted(async () => {
    void freeChest.fetchStatus();
    tickChest();
    chestTimer.value = setInterval(tickChest, 1000);
    await fetchShop();
    await initDiceCanvas();
});

onBeforeUnmount(() => {
    if (chestTimer.value !== undefined) {
        clearInterval(chestTimer.value);
    }
    if (diceInstance.value) {
        diceInstance.value.destroy();
        diceInstance.value = undefined;
    }
    if (onResize.value) {
        window.removeEventListener("resize", onResize.value);
    }
});

function typeLabel(type: string): string {
    const labels: Record<string, string> = {
        character: "Character",
        dice_theme: "Dice",
        kingdom_style: "Kingdom Style",
        item: "Item",
    };
    return labels[type] ?? type;
}

function typeIcon(type: string): string {
    const icons: Record<string, string> = {
        character: "\u{1F9D9}",
        dice_theme: "\u{1F3B2}",
        kingdom_style: "\u{1F3F0}",
        item: "\u{1F4E6}",
    };
    return icons[type] ?? "\u{1F4E6}";
}

interface ShopResponse {
    items: ShopItem[];
    coins: number;
    is_premium: boolean;
    premium_product?: PremiumProduct;
}

async function fetchShop(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get<ShopResponse>("/api/shop");
        items.value = response.data.items;
        coins.value = response.data.coins;
        isPremium.value = response.data.is_premium;
        premiumProduct.value = response.data.premium_product;
    } catch {
        // Shop may be unavailable
    }
    loading.value = false;
}

function confirmPurchase(item: ShopItem): void {
    confirmItem.value = item;
}

function purchaseErrorMessage(error: unknown, fallback: string): string {
    if (isAxiosError<{ error?: string }>(error)) {
        return error.response?.data?.error ?? error.message ?? fallback;
    }
    if (error instanceof Error) {
        return error.message || fallback;
    }
    return fallback;
}

async function doPurchase(item: ShopItem): Promise<void> {
    confirmItem.value = undefined;
    purchasing.value = item.id;
    try {
        const response = await axios.post<{ new_coins: number }>(
            `/api/shop/${item.id}/purchase`,
        );
        item.owned = true;
        coins.value = response.data.new_coins;
        updateUserStats({ coins: coins.value });
    } catch (error) {
        toast.error(purchaseErrorMessage(error, "Purchase failed."));
    }
    purchasing.value = undefined;
}

// --- Character view ---
function viewCharacter(item: ShopItem): void {
    selectedChar.value = item;
}

// --- Dice canvas (persistent) ---
async function initDiceCanvas(): Promise<void> {
    if (!isDddiceAvailable()) {
        return;
    }
    const canvas = diceCanvas.value;
    if (!canvas) {
        return;
    }
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const instance: DddiceInstance = createDddiceInstance();
    diceInstance.value = instance;
    await instance.init(canvas);
    onResize.value = () => {
        if (!(diceCanvas.value && diceInstance.value)) {
            return;
        }

        diceCanvas.value.width = window.innerWidth;
        diceCanvas.value.height = window.innerHeight;
        diceInstance.value.resize(window.innerWidth, window.innerHeight);
    };
    window.addEventListener("resize", onResize.value);
}

async function tryDice(item: ShopItem): Promise<void> {
    if (!diceInstance.value?.isReady() || tryingDice.value !== undefined) {
        return;
    }
    tryingDice.value = item.id;
    const slug = item.slug;
    try {
        await diceInstance.value.roll([
            { theme: slug, value: Math.ceil(Math.random() * 6) },
            { theme: slug, value: Math.ceil(Math.random() * 6) },
            { theme: slug, value: Math.ceil(Math.random() * 6) },
        ]);
    } catch {
        // Roll failed
    }
    setTimeout(() => {
        tryingDice.value = undefined;
    }, 2000);
}

// --- Gifting ---
interface FriendResponse {
    friends?: { user: { id: number; name: string; level?: number } }[];
}

async function openGiftPicker(item: ShopItem): Promise<void> {
    giftItem.value = item;
    friendsLoading.value = true;
    try {
        const response = await axios.get<FriendResponse>("/api/friends");
        friends.value = (response.data.friends ?? []).map((friend) => ({
            id: friend.user.id,
            name: friend.user.name,
            level: friend.user.level,
        }));
    } catch {
        friends.value = [];
    }
    friendsLoading.value = false;
}

function confirmGift(friend: ShopFriend): void {
    if (!giftItem.value) {
        return;
    }
    giftingTo.value = { item: giftItem.value, friend };
}

async function doGift(): Promise<void> {
    if (!giftingTo.value) {
        return;
    }
    gifting.value = true;
    const { item, friend } = giftingTo.value;
    try {
        const response = await axios.post<{
            new_coins: number;
            message: string;
        }>(`/api/shop/${item.id}/gift`, { friend_id: friend.id });
        coins.value = response.data.new_coins;
        updateUserStats({ coins: coins.value });
        toast.success(response.data.message);
        giftingTo.value = undefined;
        giftItem.value = undefined;
    } catch (error) {
        toast.error(purchaseErrorMessage(error, "Gift failed."));
    }
    gifting.value = false;
}

// --- Cash purchases ---
async function purchaseCash(item: ShopItem): Promise<void> {
    purchasing.value = item.id;
    try {
        const platform = getPaymentPlatform();
        if (platform === "stripe") {
            await stripeCheckout("one_time", item.id);
        } else {
            const productId =
                platform === "apple"
                    ? item.apple_product_id
                    : item.google_product_id;
            await completePurchaseIAP(productId, false);
            item.owned = true;
            await fetchShop();
        }
    } catch (error) {
        toast.error(purchaseErrorMessage(error, "Purchase failed."));
    }
    purchasing.value = undefined;
}

interface RestoreReceipt {
    productId?: string;
    product_id?: string;
    transactionId?: string;
    transaction_id?: string;
    receiptData?: string;
    receipt_data?: string;
}

async function doRestorePurchases(): Promise<void> {
    restoring.value = true;
    try {
        const platform = getPaymentPlatform();
        const purchases: RestoreReceipt[] = await restorePurchases();
        const receipts = purchases.map((purchase) => ({
            product_id: purchase.productId ?? purchase.product_id,
            transaction_id: purchase.transactionId ?? purchase.transaction_id,
            receipt_data: purchase.receiptData ?? purchase.receipt_data,
        }));
        if (receipts.length > 0) {
            const response = await restorePurchasesBackend(platform, receipts);
            isPremium.value = response.data.is_premium;
            toast.success(response.data.message);
        } else {
            toast.show("No purchases to restore.");
        }
        await fetchShop();
    } catch (error) {
        toast.error(
            purchaseErrorMessage(error, "Failed to restore purchases."),
        );
    }
    restoring.value = false;
}

// --- Kingdom style view ---
function viewKingdomStyle(item: ShopItem): void {
    previewStyle.value = item;
}

function ksPreviewStyle(item: ShopItem): CSSProperties {
    const style: CSSProperties = {};
    if (item.css_vars) {
        const cv = item.css_vars;
        if (cv.border_color) style["--ks-border-color"] = cv.border_color;
        if (cv.border_glow) style["--ks-border-glow"] = cv.border_glow;
        if (cv.border_color_rgb)
            style["--ks-border-color-rgb"] = cv.border_color_rgb;
        if (cv.bg_tint) style["--ks-bg-tint"] = cv.bg_tint;
        if (cv.bg_color) style["--ks-bg-color"] = cv.bg_color;
        if (cv.bar_safe) style["--ks-bar-safe"] = cv.bar_safe;
        if (cv.bar_caution) style["--ks-bar-caution"] = cv.bar_caution;
    }
    if (item.background_image_url) {
        style.backgroundImage = `linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(${item.background_image_url})`;
        style.backgroundSize = "cover";
        style.backgroundPosition = "center";
    }
    return style;
}

function ksModalStyle(item: ShopItem): CSSProperties {
    const style: CSSProperties = {};
    if (item.css_vars) {
        const cv = item.css_vars;
        if (cv.border_color) style["--ks-border-color"] = cv.border_color;
        if (cv.border_glow) style["--ks-border-glow"] = cv.border_glow;
        if (cv.border_color_rgb)
            style["--ks-border-color-rgb"] = cv.border_color_rgb;
        if (cv.bg_tint) style["--ks-bg-tint"] = cv.bg_tint;
        if (cv.bg_color) style["--ks-bg-color"] = cv.bg_color;
        if (cv.name_accent) style["--ks-name-accent"] = cv.name_accent;
        if (cv.total_accent) style["--ks-total-accent"] = cv.total_accent;
        if (cv.bar_safe) style["--ks-bar-safe"] = cv.bar_safe;
        if (cv.bar_caution) style["--ks-bar-caution"] = cv.bar_caution;
    }
    const borderColor = item.css_vars?.border_color ?? "transparent";
    style.border = `2px solid ${borderColor}`;
    if (item.background_image_url) {
        style.backgroundImage = `linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(${item.background_image_url})`;
        style.backgroundSize = "cover";
        style.backgroundPosition = "center";
    } else {
        style.backgroundColor =
            item.css_vars?.bg_color ??
            item.css_vars?.bg_tint ??
            "var(--bg-primary)";
    }
    return style;
}
</script>

<style scoped>
.shop-page {
    max-width: 700px;
    margin: 0 auto;
}

/* Free chest row sits at the top of the shop */
.free-chest-row {
    margin-bottom: 12px;
    cursor: pointer;
}

/* Header icon buttons (free chest / premium) in the title bar */
.shop-head-actions {
    display: flex;
    gap: 6px;
}

.shop-head-btn {
    position: relative;
    width: 34px;
    height: 34px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(240, 192, 80, 0.4);
    font-size: 16px;
    color: #f0dcb0;
    cursor: pointer;
    padding: 0;
    box-shadow: none;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
}

.shop-head-btn.ready {
    border-color: var(--accent-gold);
    box-shadow: 0 0 12px rgba(240, 192, 80, 0.45);
}

.shop-head-premium {
    color: var(--accent-gold);
    border-color: rgba(240, 192, 80, 0.6);
}

.shop-head-dot {
    position: absolute;
    top: -3px;
    right: -3px;
}

.shop-head-timer {
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    font-family: "Cinzel", serif;
    font-size: 0.5rem;
    font-weight: 700;
    white-space: nowrap;
    color: var(--text-secondary);
    background: rgba(13, 9, 5, 0.96);
    border: 1px solid rgba(240, 192, 80, 0.35);
    border-radius: 6px;
    padding: 0 4px;
    line-height: 12px;
}

/* Collection-style card grid for dice / kingdom styles */
.shop-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.shop-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 11px 10px;
    border-radius: 14px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.55),
        rgba(14, 10, 6, 0.85)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.28);
}

.shop-card.owned {
    border-color: rgba(142, 240, 200, 0.5);
}

.shop-card-preview {
    width: 62px;
    height: 62px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(240, 192, 80, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
}

.shop-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.shop-card-ks {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px;
    box-sizing: border-box;
}

.shop-card-glyph {
    font-size: 1.6rem;
}

.shop-card-name {
    font-family: "Cinzel", serif;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-primary);
    text-align: center;
    line-height: 1.15;
}

.shop-card-meta {
    font-size: 0.68rem;
    color: var(--text-secondary);
    text-align: center;
    line-height: 1.2;
}

.shop-card-actions {
    margin-top: 2px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
}

/* Keep the premium call-to-action compact rather than a full-width slab */
.premium-card .ta-cta {
    display: block;
    width: fit-content;
    margin: 4px auto 0;
    padding: 10px 26px;
    font-size: 0.9rem;
}

/* Category Tabs — restyled as gold war-table pills */
.shop-tabs-wrap {
    margin-bottom: 4px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.shop-tabs-wrap::-webkit-scrollbar {
    display: none;
}

.shop-tabs {
    display: flex;
    gap: 6px;
    justify-content: center;
    min-width: min-content;
}

.shop-tab {
    font-family: "Cinzel", serif;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-radius: 999px;
    border: 1px solid rgba(240, 192, 80, 0.3);
    background: rgba(0, 0, 0, 0.45);
    color: var(--ta-mute);
    cursor: pointer;
    transition:
        background 0.2s,
        color 0.2s;
}

.shop-tab.active {
    background: linear-gradient(180deg, #ffe897, #c8952e);
    border-color: #fff0b0;
    color: #241703;
    box-shadow:
        0 3px 0 #7a5410,
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.shop-loading,
.shop-empty {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 30px;
}

/* ---------------------------------------------------------------- Advisors for hire */
.advisor-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.advisor-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 11px;
    border-radius: 15px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.9),
        rgba(14, 10, 6, 0.95)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.45);
    box-shadow: 0 4px 0 rgba(0, 0, 0, 0.5);
}

.advisor-card.owned {
    border-color: rgba(142, 240, 200, 0.5);
}

.advisor-portrait-wrap {
    display: flex;
    justify-content: center;
}

.advisor-portrait {
    width: 62px;
    height: 62px;
    border-radius: 50%;
    border: 2px solid var(--ta-gold);
    background-size: cover;
    background-position: center 18%;
}

.advisor-card.owned .advisor-portrait {
    border-color: var(--ta-good);
}

.advisor-name {
    margin-top: 8px;
    text-align: center;
    font-family: "Cinzel", serif;
    font-size: 13px;
    font-weight: 800;
    color: var(--ta-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.advisor-tag {
    text-align: center;
    font-size: 11px;
    font-style: italic;
    color: var(--ta-mute);
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.advisor-view {
    margin-top: 8px;
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 8px;
    border: 1px solid rgba(67, 160, 212, 0.3);
    background: rgba(67, 160, 212, 0.1);
    color: #60b8e0;
    cursor: pointer;
    transition: background 0.2s;
}

.advisor-view:hover {
    background: rgba(67, 160, 212, 0.25);
}

.advisor-buy {
    margin-top: 8px;
    text-align: center;
    cursor: pointer;
}

.advisor-buy--cash {
    margin-top: 6px;
}

/* ---------------------------------------------------------------- Treasury / cosmetics rows */
.treasury-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.shop-row-face {
    overflow: hidden;
}

.shop-row-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}

.shop-row-ks {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 4px;
    padding: 7px;
    border-radius: 10px;
    border: 1px solid var(--ks-border-color, transparent);
    box-shadow: var(--ks-border-glow, none);
    background-color: var(
        --ks-bg-color,
        var(--ks-bg-tint, rgba(0, 0, 0, 0.35))
    );
}

.ks-mini-bar {
    height: 4px;
    border-radius: 2px;
    width: 80%;
}

.ks-mini-safe {
    background: var(--ks-bar-safe, #27ae60);
}

.ks-mini-caution {
    background: var(--ks-bar-caution, #d4a843);
    width: 50%;
}

.row-action {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    padding: 5px 10px;
    border-radius: 8px;
    border: 1px solid rgba(67, 160, 212, 0.3);
    background: rgba(67, 160, 212, 0.1);
    color: #60b8e0;
    cursor: pointer;
    transition: background 0.2s;
}

.row-action:hover:not(:disabled) {
    background: rgba(67, 160, 212, 0.25);
}

.row-buy {
    cursor: pointer;
}

.row-buy + .row-buy {
    margin-left: 6px;
}

.ta-pill:disabled {
    opacity: 0.4;
    cursor: default;
}

/* Transaction History */
.history-section {
    margin-top: 24px;
    border-top: 1px solid rgba(138, 106, 46, 0.15);
    padding-top: 16px;
}

.history-toggle {
    display: block;
    margin: 0 auto 12px;
    font-family: "Cinzel", serif;
    font-size: 0.75rem;
    padding: 5px 16px;
    border-radius: 4px;
    border: 1px solid rgba(138, 106, 46, 0.3);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background 0.2s;
}

.history-toggle:hover {
    background: rgba(138, 106, 46, 0.1);
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.tx-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.12);
}

.tx-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.tx-desc {
    font-size: 0.8rem;
    color: var(--text-primary);
}

.tx-date {
    font-size: 0.65rem;
    color: var(--text-secondary);
    opacity: 0.7;
}

.tx-amount {
    font-weight: 700;
    font-size: 0.85rem;
    white-space: nowrap;
    margin-left: 12px;
}

.tx-row.earn .tx-amount {
    color: #6abf50;
}

.tx-row.spend .tx-amount {
    color: #cf6679;
}

/* Confirmation Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-box {
    background: var(--bg-secondary);
    border: 1px solid var(--accent-gold);
    border-radius: 12px;
    padding: 20px 24px;
    max-width: 320px;
    width: 90%;
    text-align: center;
}

.modal-box h3 {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    margin: 0 0 10px;
    font-size: 1.1rem;
}

.modal-box p {
    color: var(--text-primary);
    font-size: 0.9rem;
    margin: 0 0 16px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-cancel,
.modal-confirm {
    font-family: "Cinzel", serif;
    font-size: 0.8rem;
    padding: 6px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}

.modal-cancel {
    border: 1px solid rgba(138, 106, 46, 0.3);
    background: transparent;
    color: var(--text-secondary);
}

.modal-cancel:hover {
    background: rgba(138, 106, 46, 0.1);
}

.modal-confirm {
    border: 1px solid var(--accent-gold);
    background: rgba(212, 168, 67, 0.2);
    color: var(--accent-gold);
}

.modal-confirm:hover {
    background: rgba(212, 168, 67, 0.35);
}

.modal-actions-col {
    flex-direction: column;
    gap: 8px;
}

.gift-action-btn {
    border-color: rgba(67, 160, 212, 0.4);
    background: rgba(67, 160, 212, 0.15);
    color: #60b8e0;
}

.gift-action-btn:hover {
    background: rgba(67, 160, 212, 0.3);
}

/* Friend Picker */
.friend-picker-box {
    max-width: 340px;
    max-height: 80vh;
    overflow-y: auto;
}

.friend-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 250px;
    overflow-y: auto;
}

.friend-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: background 0.2s;
}

.friend-row:hover {
    background: rgba(212, 168, 67, 0.15);
}

.friend-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.friend-name {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 0.85rem;
    font-weight: 600;
}

.friend-level {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.friend-select-icon {
    font-size: 1.2rem;
}

/* Character Detail Modal */
.char-modal-card {
    background: linear-gradient(180deg, #3a2a1a, #2a1f14);
    border: 2px solid var(--accent-gold);
    border-radius: 12px;
    padding: 24px;
    max-width: 340px;
    width: 90%;
    text-align: center;
}

.char-modal-portrait {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--accent-gold);
    margin-bottom: 12px;
}

.char-modal-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.2rem;
    margin-bottom: 8px;
}

.char-modal-desc {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 0.85rem;
    margin-bottom: 12px;
}

.char-modal-dice {
    margin-bottom: 12px;
}

.char-modal-dice .dice-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    justify-content: center;
}

.char-modal-dice .dice-label {
    color: var(--text-secondary);
    font-size: 0.8rem;
    min-width: 42px;
}

.char-modal-dice .dice-face {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    background: rgba(212, 168, 67, 0.12);
    border: 1px solid rgba(212, 168, 67, 0.3);
    border-radius: 4px;
    color: var(--text-bright);
    font-size: 0.8rem;
    font-weight: 600;
}

.char-modal-wild {
    background: rgba(212, 168, 67, 0.08);
    border-top: 1px solid rgba(212, 168, 67, 0.2);
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 16px;
}

.char-modal-wild .wild-badge {
    display: inline-block;
    background: rgba(212, 168, 67, 0.2);
    color: var(--accent-gold);
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.char-modal-wild .wild-desc {
    display: block;
    color: var(--text-secondary);
    font-size: 0.78rem;
    font-style: italic;
}

.char-modal-close {
    padding: 8px 28px;
}

/* Kingdom Style Preview Modal */
.ks-modal-card {
    background: linear-gradient(180deg, #3a2a1a, #2a1f14);
    border: 2px solid var(--accent-gold);
    border-radius: 12px;
    padding: 24px;
    max-width: 360px;
    width: 90%;
    text-align: center;
}

.ks-modal-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    margin-bottom: 14px;
}

.ks-modal-preview {
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
    position: relative;
    overflow: hidden;
}

.ks-modal-name {
    font-family: "Cinzel", serif;
    font-size: 0.95rem;
    text-align: center;
    margin-bottom: 10px;
}

.ks-modal-bars {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ks-modal-bar {
    height: 8px;
    border-radius: 4px;
}

.ks-modal-total {
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
    margin-top: 8px;
}

.ks-modal-desc {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 0.82rem;
    margin-bottom: 14px;
}

/* Persistent dice canvas */
.dice-canvas-persistent {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 70px;
    width: 100%;
    height: calc(100% - 70px);
    pointer-events: none;
    z-index: 200;
}

/* Premium card */
.premium-card {
    background: linear-gradient(
        135deg,
        rgba(212, 168, 67, 0.15),
        rgba(180, 120, 30, 0.08)
    );
    border: 2px solid var(--accent-gold);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    text-align: center;
}

.premium-card-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 8px;
}

.premium-crown {
    font-size: 1.3rem;
    color: var(--accent-gold);
}

.premium-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.2rem;
    margin: 0;
}

.premium-desc {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin: 0 0 12px;
}

.premium-active-badge {
    text-align: center;
    padding: 10px;
    margin-bottom: 16px;
    background: rgba(106, 191, 80, 0.1);
    border: 1px solid rgba(106, 191, 80, 0.3);
    border-radius: 8px;
    color: #6abf50;
    font-family: "Cinzel", serif;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Restore purchases */
.restore-section {
    text-align: center;
    margin-bottom: 16px;
}

.restore-btn {
    font-family: "Cinzel", serif;
    font-size: 0.8rem;
    padding: 8px 20px;
    border-radius: 6px;
    border: 1px solid rgba(138, 106, 46, 0.3);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
}

.restore-btn:hover:not(:disabled) {
    background: rgba(138, 106, 46, 0.1);
}

.restore-btn:disabled {
    opacity: 0.5;
}
</style>
