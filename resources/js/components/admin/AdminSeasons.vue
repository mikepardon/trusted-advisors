<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Seasons</h2>
      <button class="btn-primary" @click="openCreate">+ New Season</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="s in seasons" :key="s.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ s.name }}</strong>
            <span v-if="s.is_active" class="active-badge">Active</span>
          </div>
          <div class="list-dates">{{ formatDate(s.starts_at) }} &mdash; {{ formatDate(s.ends_at) }}</div>
        </div>
        <div class="list-actions">
          <button v-if="s.is_active" class="btn-sm btn-end" :disabled="endingSeasonId === s.id" @click="endSeason(s)">
            {{ endingSeasonId === s.id ? 'Ending...' : 'End Season' }}
          </button>
          <button class="btn-sm" @click="openRewards(s)">Rewards</button>
          <button class="btn-sm" @click="openEdit(s)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteSeason(s)">Del</button>
        </div>
      </div>
      <div v-if="seasons.length === 0" class="empty">No seasons yet.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Season' : 'New Season' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Starts At</label>
            <input v-model="form.starts_at" type="datetime-local" required />
          </div>
          <div class="form-group">
            <label>Ends At</label>
            <input v-model="form.ends_at" type="datetime-local" required />
          </div>
          <div class="form-group">
            <label>
              <input v-model="form.is_active" type="checkbox" /> Active
            </label>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editing ? 'Update' : 'Create' }}</button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Rewards Modal -->
    <div v-if="showRewardsModal" class="modal-overlay" @click.self="showRewardsModal = false">
      <div class="modal-content rewards-modal">
        <h3>Rewards: {{ rewardsSeason?.name }}</h3>

        <template v-if="rewards.length > 0">
          <div v-for="(group, metric) in groupedRewards" :key="metric" class="metric-group">
            <h4 class="metric-label">{{ metricLabel(metric) }}</h4>
            <table class="rewards-table">
              <thead>
                <tr>
                  <th>Place</th>
                  <th>XP</th>
                  <th>Coins</th>
                  <th>Character</th>
                  <th>Dice</th>
                  <th>Kingdom</th>
                  <th>Title</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in group" :key="r.id">
                  <td>{{ ordinal(r.placement) }}</td>
                  <td>{{ r.reward_xp }}</td>
                  <td>{{ r.reward_coins }}</td>
                  <td>{{ r.reward_character?.name || '-' }}</td>
                  <td>{{ r.reward_dice_theme?.name || '-' }}</td>
                  <td>{{ r.reward_kingdom_style?.name || '-' }}</td>
                  <td>{{ r.reward_title || '-' }}</td>
                  <td>
                    <button class="btn-sm" @click="editReward(r)">Edit</button>
                    <button class="btn-sm btn-danger" @click="deleteReward(r)">Del</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <p v-else class="empty">No rewards defined yet.</p>

        <h4 class="reward-form-title">{{ editingReward ? 'Edit Reward' : 'Add Reward' }}</h4>
        <form class="reward-form" @submit.prevent="saveReward">
          <div class="reward-form-row">
            <div class="form-group">
              <label>Metric</label>
              <select v-model="rewardForm.metric">
                <option value="elo">ELO Rating</option>
                <option value="score">Highest Score</option>
                <option value="wins">Most Wins</option>
              </select>
            </div>
            <div class="form-group">
              <label>From</label>
              <input v-model.number="rewardForm.placement_from" type="number" min="1" required />
            </div>
            <div class="form-group">
              <label>To</label>
              <input v-model.number="rewardForm.placement_to" type="number" min="1" required />
            </div>
            <div class="form-group">
              <label>XP</label>
              <input v-model.number="rewardForm.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Coins</label>
              <input v-model.number="rewardForm.reward_coins" type="number" min="0" />
            </div>
          </div>
          <div class="reward-form-row">
            <div class="form-group">
              <label>Character</label>
              <select v-model="rewardForm.reward_character_id">
                <option :value="null">None</option>
                <option v-for="c in allCharacters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Dice Theme</label>
              <select v-model="rewardForm.reward_dice_theme_id">
                <option :value="null">None</option>
                <option v-for="d in allDiceThemes" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Kingdom Style</label>
              <select v-model="rewardForm.reward_kingdom_style_id">
                <option :value="null">None</option>
                <option v-for="ks in allKingdomStyles" :key="ks.id" :value="ks.id">{{ ks.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Title</label>
              <input v-model="rewardForm.reward_title" type="text" placeholder="e.g. Season Champion" />
            </div>
          </div>
          <div v-if="rewardFormError" class="form-error">{{ rewardFormError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editingReward ? 'Update' : 'Add' }}</button>
            <button v-if="editingReward" type="button" @click="resetRewardForm">Cancel Edit</button>
            <button type="button" @click="showRewardsModal = false">Close</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";

interface Season {
  id: number;
  name: string;
  starts_at: string | undefined;
  ends_at: string | undefined;
  is_active: boolean;
}

interface EntityOption {
  id: number;
  name: string;
}

interface SeasonReward {
  id: number;
  metric: string | undefined;
  placement: number;
  reward_xp: number;
  reward_coins: number;
  reward_character_id: number | undefined;
  reward_dice_theme_id: number | undefined;
  reward_kingdom_style_id: number | undefined;
  reward_title: string | undefined;
  reward_character: EntityOption | undefined;
  reward_dice_theme: EntityOption | undefined;
  reward_kingdom_style: EntityOption | undefined;
}

interface SeasonForm {
  name: string;
  starts_at: string;
  ends_at: string;
  is_active: boolean;
}

interface RewardForm {
  metric: string;
  placement_from: number;
  placement_to: number;
  reward_xp: number;
  reward_coins: number;
  reward_character_id: number | undefined;
  reward_dice_theme_id: number | undefined;
  reward_kingdom_style_id: number | undefined;
  reward_title: string;
}

function emptySeasonForm(): SeasonForm {
  return { name: "", starts_at: "", ends_at: "", is_active: false };
}

function emptyRewardForm(): RewardForm {
  return {
    metric: "elo",
    placement_from: 1,
    placement_to: 1,
    reward_xp: 0,
    reward_coins: 0,
    reward_character_id: undefined,
    reward_dice_theme_id: undefined,
    reward_kingdom_style_id: undefined,
    reward_title: "",
  };
}

const toast = useToast();

const seasons = ref<Season[]>([]);
const showModal = ref(false);
const editing = ref<number | undefined>(undefined);
const formError = ref("");
const form = reactive<SeasonForm>(emptySeasonForm());
// Rewards
const showRewardsModal = ref(false);
const rewardsSeason = ref<Season | undefined>(undefined);
const rewards = ref<SeasonReward[]>([]);
const allCharacters = ref<EntityOption[]>([]);
const allDiceThemes = ref<EntityOption[]>([]);
const allKingdomStyles = ref<EntityOption[]>([]);
const editingReward = ref<number | undefined>(undefined);
const rewardFormError = ref("");
const rewardForm = reactive<RewardForm>(emptyRewardForm());
const endingSeasonId = ref<number | undefined>(undefined);

const groupedRewards = computed<Record<string, SeasonReward[]>>(() => {
  const groups: Record<string, SeasonReward[]> = {};
  for (const reward of rewards.value) {
    const metric = reward.metric || "elo";
    (groups[metric] ??= []).push(reward);
  }
  return groups;
});

async function load(): Promise<void> {
  const response = await axios.get<Season[]>("/api/admin/seasons");
  seasons.value = response.data;
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, emptySeasonForm());
  formError.value = "";
  showModal.value = true;
}

function openEdit(season: Season): void {
  editing.value = season.id;
  Object.assign(form, {
    name: season.name,
    starts_at: season.starts_at?.slice(0, 16) || "",
    ends_at: season.ends_at?.slice(0, 16) || "",
    is_active: season.is_active,
  });
  formError.value = "";
  showModal.value = true;
}

async function save(): Promise<void> {
  formError.value = "";
  try {
    const data = { ...form };
    if (editing.value) {
      await axios.put(`/api/admin/seasons/${editing.value}`, data);
    } else {
      await axios.post("/api/admin/seasons", data);
    }
    showModal.value = false;
    await load();
  } catch (error) {
    formError.value = isAxiosError<{ error?: string; message?: string }>(error)
      ? (error.response?.data?.error ?? error.response?.data?.message ?? "Error")
      : "Error";
  }
}

async function endSeason(season: Season): Promise<void> {
  if (!confirm(`End season "${season.name}" and distribute rewards? This cannot be undone.`)) {
    return;
  }
  endingSeasonId.value = season.id;
  try {
    const response = await axios.post<{ rewards_distributed: number }>(`/api/admin/seasons/${season.id}/end`);
    toast.success(`Season ended! ${response.data.rewards_distributed} rewards distributed.`);
    await load();
  } catch (error) {
    const message = isAxiosError<{ error?: string }>(error)
      ? (error.response?.data?.error ?? "Failed to end season")
      : "Failed to end season";
    toast.error(message);
  }
  endingSeasonId.value = undefined;
}

async function deleteSeason(season: Season): Promise<void> {
  if (!confirm(`Delete "${season.name}"?`)) {
    return;
  }
  await axios.delete(`/api/admin/seasons/${season.id}`);
  await load();
}

async function openRewards(season: Season): Promise<void> {
  rewardsSeason.value = season;
  resetRewardForm();
  showRewardsModal.value = true;
  const [rewardsResponse, charactersResponse, diceResponse, kingdomStylesResponse] = await Promise.all([
    axios.get<SeasonReward[]>(`/api/admin/seasons/${season.id}/rewards`),
    axios.get<EntityOption[]>("/api/characters"),
    axios.get<EntityOption[]>("/api/admin/dice-themes"),
    axios.get<EntityOption[]>("/api/admin/kingdom-styles"),
  ]);
  rewards.value = rewardsResponse.data;
  allCharacters.value = charactersResponse.data;
  allDiceThemes.value = diceResponse.data;
  allKingdomStyles.value = kingdomStylesResponse.data;
}

function resetRewardForm(): void {
  editingReward.value = undefined;
  rewardFormError.value = "";
  Object.assign(rewardForm, emptyRewardForm());
}

function editReward(reward: SeasonReward): void {
  editingReward.value = reward.id;
  Object.assign(rewardForm, {
    metric: reward.metric || "elo",
    placement_from: reward.placement,
    placement_to: reward.placement,
    reward_xp: reward.reward_xp,
    reward_coins: reward.reward_coins,
    reward_character_id: reward.reward_character_id,
    reward_dice_theme_id: reward.reward_dice_theme_id ?? undefined,
    reward_kingdom_style_id: reward.reward_kingdom_style_id ?? undefined,
    reward_title: reward.reward_title || "",
  });
}

function metricLabel(metric: string): string {
  const labels: Record<string, string> = { elo: "ELO Rating", score: "Highest Score", wins: "Most Wins" };
  return labels[metric] || metric;
}

async function saveReward(): Promise<void> {
  rewardFormError.value = "";
  const from = rewardForm.placement_from;
  const to = rewardForm.placement_to;
  const season = rewardsSeason.value;
  if (!season) {
    return;
  }
  if (to < from) {
    rewardFormError.value = '"To" must be >= "From"';
    return;
  }
  try {
    if (editingReward.value) {
      // Editing a single reward — use placement_from as the placement
      const payload = { ...rewardForm, placement: from };
      await axios.put(`/api/admin/seasons/${season.id}/rewards/${editingReward.value}`, payload);
    } else {
      // Create one reward per placement in the range
      for (let placement = from; placement <= to; placement++) {
        const payload = { ...rewardForm, placement };
        await axios.post(`/api/admin/seasons/${season.id}/rewards`, payload);
      }
    }
    const response = await axios.get<SeasonReward[]>(`/api/admin/seasons/${season.id}/rewards`);
    rewards.value = response.data;
    resetRewardForm();
  } catch (error) {
    rewardFormError.value = isAxiosError<{ error?: string; message?: string }>(error)
      ? (error.response?.data?.error ?? error.response?.data?.message ?? "Error")
      : "Error";
  }
}

async function deleteReward(reward: SeasonReward): Promise<void> {
  const season = rewardsSeason.value;
  if (!season) {
    return;
  }
  if (!confirm(`Delete reward for ${ordinal(reward.placement)} place?`)) {
    return;
  }
  await axios.delete(`/api/admin/seasons/${season.id}/rewards/${reward.id}`);
  rewards.value = rewards.value.filter((rewardEntry) => rewardEntry.id !== reward.id);
}

function ordinal(value: number): string {
  const suffixes = ["th", "st", "nd", "rd"];
  const remainder = value % 100;
  return value + (suffixes[(remainder - 20) % 10] || suffixes[remainder] || suffixes[0]);
}

function formatDate(date: string | undefined): string {
  if (!date) {
    return "";
  }
  return new Date(date).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
}

onMounted(() => {
  load();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 8px; }
.list-dates { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.active-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; background: rgba(74, 138, 58, 0.2); color: #6abf50; font-weight: 600; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.btn-end { background: rgba(74, 138, 58, 0.15); color: #6abf50; border-color: rgba(74, 138, 58, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 450px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input:not([type="checkbox"]), .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }

/* Rewards modal */
.rewards-modal { max-width: 650px; }
.rewards-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.85rem; }
.rewards-table th { text-align: left; color: var(--accent-gold); font-family: 'Cinzel', serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
.rewards-table td { padding: 6px 8px; color: var(--text-bright); border-bottom: 1px solid rgba(138, 106, 46, 0.1); }
.reward-form-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1rem; margin-bottom: 12px; }
.reward-form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.reward-form-row .form-group { flex: 1; }
.metric-group { margin-bottom: 16px; }
.metric-label { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 0.9rem; margin-bottom: 6px; opacity: 0.85; }
</style>
