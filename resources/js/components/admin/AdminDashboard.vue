<template>
    <div class="dashboard">
        <h2 class="page-title">Dashboard</h2>

        <div v-if="loading" class="loading">Loading stats...</div>

        <template v-else>
            <!-- Player Stats -->
            <h3 class="section-title">Players</h3>
            <div class="stats-grid cols-2">
                <div class="stat-card">
                    <div class="stat-count">{{ stats.total_users }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">{{ stats.verified_users }}</div>
                    <div class="stat-label">Verified Users</div>
                </div>
            </div>

            <!-- Game Stats -->
            <h3 class="section-title">Games</h3>
            <div class="stats-grid cols-5">
                <div class="stat-card">
                    <div class="stat-count">{{ stats.completed_games }}</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">{{ stats.active_games }}</div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">{{ stats.setup_games }}</div>
                    <div class="stat-label">In Setup</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.cancelled_games || 0 }}
                    </div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">{{ winRate }}%</div>
                    <div class="stat-label">Win Rate</div>
                </div>
            </div>

            <!-- By Mode -->
            <h3 class="section-title">By Mode</h3>
            <div class="stats-grid cols-3">
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.games_by_mode?.single || 0 }}
                    </div>
                    <div class="stat-label">Single Player</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.games_by_mode?.pass_and_play || 0 }}
                    </div>
                    <div class="stat-label">Pass & Play</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.games_by_mode?.online || 0 }}
                    </div>
                    <div class="stat-label">Online</div>
                </div>
            </div>

            <!-- By Type -->
            <h3 class="section-title">By Type</h3>
            <div class="stats-grid cols-2">
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.games_by_type?.cooperative || 0 }}
                    </div>
                    <div class="stat-label">Cooperative</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count">
                        {{ stats.games_by_type?.duel || 0 }}
                    </div>
                    <div class="stat-label">Duel</div>
                </div>
            </div>

            <!-- Charts -->
            <div v-if="hasChartData" class="charts-grid">
                <div class="chart-card">
                    <h4 class="chart-title">Games by Mode</h4>
                    <Doughnut :data="modeChartData" :options="chartOptions" />
                </div>
                <div class="chart-card">
                    <h4 class="chart-title">Games by Type</h4>
                    <Doughnut :data="typeChartData" :options="chartOptions" />
                </div>
                <div class="chart-card">
                    <h4 class="chart-title">Win Rate</h4>
                    <Doughnut :data="winChartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Content -->
            <h3 class="section-title">Content</h3>
            <div class="stats-grid cols-4">
                <router-link to="/admin/characters" class="stat-card stat-link">
                    <div class="stat-count">
                        {{ stats.content_counts?.characters || 0 }}
                    </div>
                    <div class="stat-label">Characters</div>
                </router-link>
                <router-link to="/admin/cards" class="stat-card stat-link">
                    <div class="stat-count">
                        {{ stats.content_counts?.cards || 0 }}
                    </div>
                    <div class="stat-label">Cards</div>
                </router-link>
                <router-link to="/admin/events" class="stat-card stat-link">
                    <div class="stat-count">
                        {{ stats.content_counts?.events || 0 }}
                    </div>
                    <div class="stat-label">Events</div>
                </router-link>
                <router-link to="/admin/items" class="stat-card stat-link">
                    <div class="stat-count">
                        {{ stats.content_counts?.items || 0 }}
                    </div>
                    <div class="stat-label">Items</div>
                </router-link>
            </div>

            <!-- Progression & Competition -->
            <h3 class="section-title">Progression</h3>
            <div class="stats-grid cols-4">
                <router-link to="/admin/seasons" class="stat-card stat-link">
                    <div class="stat-count">&#128197;</div>
                    <div class="stat-label">Seasons</div>
                </router-link>
                <router-link
                    to="/admin/achievements"
                    class="stat-card stat-link"
                >
                    <div class="stat-count">&#127942;</div>
                    <div class="stat-label">Achievements</div>
                </router-link>
                <router-link
                    to="/admin/unlockables"
                    class="stat-card stat-link"
                >
                    <div class="stat-count">&#128274;</div>
                    <div class="stat-label">Unlockables</div>
                </router-link>
                <router-link to="/admin/challenges" class="stat-card stat-link">
                    <div class="stat-count">&#128203;</div>
                    <div class="stat-label">Challenges</div>
                </router-link>
            </div>

            <!-- Feature Toggles -->
            <h3 class="section-title">Feature Toggles</h3>
            <div class="toggles-panel">
                <label class="toggle-row">
                    <input
                        v-model="tournamentsEnabled"
                        type="checkbox"
                        @change="
                            saveToggle(
                                'tournaments_enabled',
                                tournamentsEnabled,
                            )
                        "
                    />
                    <span class="toggle-label">Tournaments Mode</span>
                    <span class="toggle-desc"
                        >Allow players to create and join tournaments</span
                    >
                </label>
            </div>

            <!-- Site Appearance -->
            <h3 class="section-title">Site Appearance</h3>
            <div class="toggles-panel">
                <div class="appearance-row">
                    <span class="toggle-label">Homepage Background Image</span>
                    <span class="toggle-desc" style="padding-left: 0"
                        >Displayed between header and bottom nav on the
                        homepage.</span
                    >
                    <div v-if="homepageBgUrl" class="bg-preview-wrap">
                        <img
                            :src="homepageBgUrl"
                            class="bg-preview"
                            alt="Homepage background"
                        />
                        <button class="btn-remove-bg" @click="removeHomepageBg">
                            Remove
                        </button>
                    </div>
                    <div class="bg-upload-wrap">
                        <button
                            class="btn-upload-bg"
                            @click="
                                mediaPickerTarget = 'homepage';
                                showMediaPicker = true;
                            "
                        >
                            {{
                                homepageBgUrl
                                    ? "Choose Different Image"
                                    : "Choose Image"
                            }}
                        </button>
                    </div>
                </div>
                <div class="appearance-row">
                    <span class="toggle-label">Classic Game Background</span>
                    <span class="toggle-desc" style="padding-left: 0"
                        >Background image during cooperative games.</span
                    >
                    <div v-if="classicBgUrl" class="bg-preview-wrap">
                        <img
                            :src="classicBgUrl"
                            class="bg-preview"
                            alt="Classic game background"
                        />
                        <button
                            class="btn-remove-bg"
                            @click="removeGameBg('classic')"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="bg-upload-wrap">
                        <button
                            class="btn-upload-bg"
                            @click="
                                mediaPickerTarget = 'classic';
                                showMediaPicker = true;
                            "
                        >
                            {{
                                classicBgUrl
                                    ? "Choose Different Image"
                                    : "Choose Image"
                            }}
                        </button>
                    </div>
                </div>
                <div class="appearance-row">
                    <span class="toggle-label">Duel Game Background</span>
                    <span class="toggle-desc" style="padding-left: 0"
                        >Background image during duel games.</span
                    >
                    <div v-if="duelBgUrl" class="bg-preview-wrap">
                        <img
                            :src="duelBgUrl"
                            class="bg-preview"
                            alt="Duel game background"
                        />
                        <button
                            class="btn-remove-bg"
                            @click="removeGameBg('duel')"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="bg-upload-wrap">
                        <button
                            class="btn-upload-bg"
                            @click="
                                mediaPickerTarget = 'duel';
                                showMediaPicker = true;
                            "
                        >
                            {{
                                duelBgUrl
                                    ? "Choose Different Image"
                                    : "Choose Image"
                            }}
                        </button>
                    </div>
                </div>
                <div class="appearance-row">
                    <span class="toggle-label">Hub Centre Image</span>
                    <span class="toggle-desc" style="padding-left: 0"
                        >The art shown on the war-table disc at the centre of
                        the home hub.</span
                    >
                    <div v-if="hubCenterUrl" class="bg-preview-wrap">
                        <img
                            :src="hubCenterUrl"
                            class="bg-preview"
                            alt="Hub centre image"
                        />
                        <button
                            class="btn-remove-bg"
                            @click="removeGameBg('hubCenter')"
                        >
                            Remove
                        </button>
                    </div>
                    <div class="bg-upload-wrap">
                        <button
                            class="btn-upload-bg"
                            @click="
                                mediaPickerTarget = 'hubCenter';
                                showMediaPicker = true;
                            "
                        >
                            {{
                                hubCenterUrl
                                    ? "Choose Different Image"
                                    : "Choose Image"
                            }}
                        </button>
                    </div>
                </div>
                <MediaLibraryModal
                    :visible="showMediaPicker"
                    :select-mode="true"
                    @close="showMediaPicker = false"
                    @select="onMediaSelected"
                />
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { useToast } from "../../stores/toast";
import { Doughnut } from "vue-chartjs";
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from "chart.js";
import MediaLibraryModal from "./MediaLibraryModal.vue";

ChartJS.register(ArcElement, Tooltip, Legend);

interface DashboardStats {
    total_users?: number;
    verified_users?: number;
    completed_games?: number;
    active_games?: number;
    setup_games?: number;
    cancelled_games?: number;
    wins?: number;
    games_by_mode?: {
        single?: number;
        pass_and_play?: number;
        online?: number;
    };
    games_by_type?: { cooperative?: number; duel?: number };
    content_counts?: {
        characters?: number;
        cards?: number;
        events?: number;
        items?: number;
    };
}

interface MediaItem {
    path: string;
    url: string;
}

type MediaTarget = "homepage" | "classic" | "duel" | "hubCenter";

const toast = useToast();

const loading = ref(true);
const stats = ref<DashboardStats>({});
const tournamentsEnabled = ref(false);
const homepageBgUrl = ref<string | undefined>(undefined);
const classicBgUrl = ref<string | undefined>(undefined);
const duelBgUrl = ref<string | undefined>(undefined);
const hubCenterUrl = ref<string | undefined>(undefined);
const showMediaPicker = ref(false);
const mediaPickerTarget = ref<MediaTarget>("homepage");

const winRate = computed<number>(() => {
    const completed = stats.value.completed_games || 0;
    if (completed === 0) {
        return 0;
    }
    return Math.round(((stats.value.wins ?? 0) / completed) * 100);
});

const hasChartData = computed<boolean>(
    () =>
        (stats.value.completed_games ?? 0) > 0 ||
        (stats.value.active_games ?? 0) > 0,
);

const modeChartData = computed(() => {
    const modes = stats.value.games_by_mode ?? {};
    return {
        labels: ["Single", "Pass & Play", "Online"],
        datasets: [
            {
                data: [
                    modes.single || 0,
                    modes.pass_and_play || 0,
                    modes.online || 0,
                ],
                backgroundColor: ["#d4a843", "#8a6a2e", "#e8c468"],
                borderColor: "rgba(30, 25, 18, 0.8)",
                borderWidth: 2,
            },
        ],
    };
});

const typeChartData = computed(() => {
    const types = stats.value.games_by_type ?? {};
    return {
        labels: ["Cooperative", "Duel"],
        datasets: [
            {
                data: [types.cooperative || 0, types.duel || 0],
                backgroundColor: ["#4a8a3a", "#a03020"],
                borderColor: "rgba(30, 25, 18, 0.8)",
                borderWidth: 2,
            },
        ],
    };
});

const winChartData = computed(() => {
    const wins = stats.value.wins || 0;
    const completed = stats.value.completed_games || 0;
    const losses = Math.max(0, completed - wins);
    return {
        labels: ["Wins", "Losses"],
        datasets: [
            {
                data: [wins, losses],
                backgroundColor: ["#4a8a3a", "#a03020"],
                borderColor: "rgba(30, 25, 18, 0.8)",
                borderWidth: 2,
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            position: "bottom" as const,
            labels: {
                color: "#b8a67a",
                padding: 14,
                font: { size: 12 },
            },
        },
        tooltip: {
            backgroundColor: "rgba(30, 25, 18, 0.95)",
            titleColor: "#d4a843",
            bodyColor: "#e0d6c2",
            borderColor: "#8a6a2e",
            borderWidth: 1,
        },
    },
}));

async function saveToggle(key: string, isEnabled: boolean): Promise<void> {
    try {
        await axios.put(`/api/admin/rules/${key}`, { value: isEnabled });
    } catch {
        toast.error("Failed to save setting");
    }
}

async function onMediaSelected(item: MediaItem): Promise<void> {
    showMediaPicker.value = false;
    const keyMap: Record<MediaTarget, string> = {
        homepage: "homepage_background_image",
        classic: "classic_game_background_image",
        duel: "duel_game_background_image",
        hubCenter: "hub_center_image",
    };
    const target = mediaPickerTarget.value;
    const key = keyMap[target];
    try {
        await axios.put(`/api/admin/rules/${key}`, { value: item.path });
        switch (target) {
            case "homepage": {
                homepageBgUrl.value = item.url;

                break;
            }
            case "classic": {
                classicBgUrl.value = item.url;

                break;
            }
            case "duel": {
                duelBgUrl.value = item.url;

                break;
            }
            default: {
                hubCenterUrl.value = item.url;
            }
        }
        toast.success(
            target === "hubCenter" ? "Hub image updated" : "Background updated",
        );
    } catch {
        toast.error("Failed to set image");
    }
}

async function removeHomepageBg(): Promise<void> {
    try {
        await axios.delete("/api/admin/homepage-background");
        homepageBgUrl.value = undefined;
        toast.success("Background removed");
    } catch {
        toast.error("Failed to remove background");
    }
}

async function removeGameBg(
    type: "classic" | "duel" | "hubCenter",
): Promise<void> {
    const keyMap: Record<"classic" | "duel" | "hubCenter", string> = {
        classic: "classic_game_background_image",
        duel: "duel_game_background_image",
        hubCenter: "hub_center_image",
    };
    const key = keyMap[type];
    try {
        const existing =
            await axios.get<Record<string, unknown>>("/api/admin/rules");
        const currentValue = existing.data ? existing.data[key] : undefined;
        if (currentValue) {
            await axios.put(`/api/admin/rules/${key}`, { value: "" });
        }
        if (type === "classic") {
            classicBgUrl.value = undefined;
        } else if (type === "duel") {
            duelBgUrl.value = undefined;
        } else {
            hubCenterUrl.value = undefined;
        }
        toast.success(
            type === "hubCenter" ? "Hub image removed" : "Background removed",
        );
    } catch {
        toast.error("Failed to remove image");
    }
}

onMounted(async () => {
    try {
        const [statsResponse, rulesResponse, siteResponse] = await Promise.all([
            axios.get<DashboardStats>("/api/admin/dashboard-stats"),
            axios.get<{ tournaments_enabled?: boolean }>("/api/admin/rules"),
            axios.get<{
                homepage_background_url?: string;
                classic_game_background_url?: string;
                duel_game_background_url?: string;
                hub_center_image_url?: string;
            }>("/api/site-settings"),
        ]);
        stats.value = statsResponse.data;
        tournamentsEnabled.value = Boolean(
            rulesResponse.data?.tournaments_enabled,
        );
        homepageBgUrl.value =
            siteResponse.data?.homepage_background_url || undefined;
        classicBgUrl.value =
            siteResponse.data?.classic_game_background_url || undefined;
        duelBgUrl.value =
            siteResponse.data?.duel_game_background_url || undefined;
        hubCenterUrl.value =
            siteResponse.data?.hub_center_image_url || undefined;
    } catch (error) {
        console.error("Failed to load dashboard stats", error);
    }
    loading.value = false;
});
</script>

<style scoped>
.page-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    text-align: center;
    margin-bottom: 30px;
    font-size: 1.8rem;
}

.section-title {
    font-family: "Cinzel", serif;
    color: var(--text-secondary);
    font-size: 1rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin: 24px 0 12px;
}

.stats-grid {
    display: grid;
    gap: 12px;
}

.cols-2 {
    grid-template-columns: repeat(2, 1fr);
}
.cols-3 {
    grid-template-columns: repeat(3, 1fr);
}
.cols-4 {
    grid-template-columns: repeat(4, 1fr);
}
.cols-5 {
    grid-template-columns: repeat(5, 1fr);
}

.stat-card {
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-link {
    text-decoration: none;
    transition: all 0.2s;
}

.stat-link:hover {
    box-shadow: 0 0 20px rgba(212, 168, 67, 0.2);
    transform: translateY(-2px);
}

.stat-count {
    font-family: "Cinzel", serif;
    font-size: 2.2rem;
    color: var(--accent-gold);
    font-weight: 900;
}

.stat-label {
    font-family: "Cinzel", serif;
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-top: 4px;
}

.loading {
    text-align: center;
    color: var(--text-secondary);
    padding: 40px;
}

/* Charts */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin: 24px 0;
}

.chart-card {
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 8px;
    padding: 18px;
    text-align: center;
}

.chart-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.9rem;
    margin-bottom: 12px;
}

/* Feature Toggles */
.toggles-panel {
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 8px;
    padding: 16px 20px;
}

.toggle-row {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    flex-wrap: wrap;
}

.toggle-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent-gold);
    cursor: pointer;
}

.toggle-label {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 0.95rem;
    font-weight: 600;
}

.toggle-desc {
    font-size: 0.8rem;
    color: var(--text-secondary);
    width: 100%;
    padding-left: 28px;
}

/* Site Appearance */
.appearance-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bg-preview-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
}

.bg-preview {
    width: 160px;
    height: 90px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid var(--border-gold);
}

.btn-upload-bg,
.btn-remove-bg {
    font-size: 0.85rem;
    padding: 6px 14px;
}

.btn-remove-bg {
    border-color: var(--accent-red);
    color: var(--accent-red);
}

.btn-remove-bg:hover {
    background: rgba(160, 48, 32, 0.2);
}

@media (max-width: 768px) {
    .cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
    .cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    .cols-3 {
        grid-template-columns: repeat(3, 1fr);
    }
    .stat-count {
        font-size: 1.6rem;
    }
    .stat-card {
        padding: 14px;
    }
    .charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>
