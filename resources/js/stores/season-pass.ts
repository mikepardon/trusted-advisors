import { reactive } from "vue";
import axios from "axios";
import { useAuth } from "./auth";

export interface PassCosmeticReward {
  name: string;
  type: string;
  rarity: string;
  value: string | null;
}

export interface PassTier {
  tier: number;
  points_required: number;
  reward_coins: number;
  name: string | null;
  reward_cosmetic: PassCosmeticReward | null;
  reached: boolean;
  claimed: boolean;
  claimable: boolean;
}

export interface PassData {
  season: { id: number; name: string; ends_at: string | null } | null;
  points: number;
  current_tier: number;
  tiers: PassTier[];
}

interface PassStoreState {
  data: PassData | undefined;
  loaded: boolean;
  loading: boolean;
  claiming: number | undefined;
}

interface ClaimResponse {
  granted: { coins: number; cosmetic: string | null };
  state: PassData;
}

const state = reactive<PassStoreState>({
  data: undefined,
  loaded: false,
  loading: false,
  claiming: undefined,
});

async function fetchState(): Promise<void> {
  state.loading = true;
  try {
    const response = await axios.get<PassData>("/api/season-pass");
    state.data = response.data;
  } finally {
    state.loading = false;
    state.loaded = true;
  }
}

// Returns the granted reward on success, or undefined if the claim failed.
async function claim(tier: number): Promise<{ coins: number; cosmetic: string | null } | undefined> {
  state.claiming = tier;
  try {
    const response = await axios.post<ClaimResponse>("/api/season-pass/claim", { tier });
    state.data = response.data.state;
    if (response.data.granted.coins > 0) {
      const user = useAuth().state.user;
      if (user !== undefined) {
        user.coins += response.data.granted.coins;
      }
    }
    return response.data.granted;
  } catch {
    return undefined;
  } finally {
    state.claiming = undefined;
  }
}

export function useSeasonPass() {
  return { state, fetchState, claim };
}
