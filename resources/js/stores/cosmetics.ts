import { reactive } from "vue";
import axios from "axios";
import { useAuth } from "./auth";

export interface Cosmetic {
  id: number;
  type: string;
  slug: string;
  name: string;
  rarity: string;
  value: string | null;
  meta: Record<string, unknown> | null;
  owned: boolean;
  active: boolean;
}

// Slot values come back as null from the server when nothing is equipped.
export interface CosmeticActiveMap {
  title: string | null;
  frame: string | null;
  card_back: string | null;
  victory_fx: string | null;
  crest: Record<string, string>;
}

interface CosmeticsIndexResponse {
  cosmetics: Cosmetic[];
  active: CosmeticActiveMap;
}

interface CosmeticsState {
  cosmetics: Cosmetic[];
  active: CosmeticActiveMap | undefined;
  loaded: boolean;
  loading: boolean;
}

const state = reactive<CosmeticsState>({
  cosmetics: [],
  active: undefined,
  loaded: false,
  loading: false,
});

// Recompute each item's `active` flag from the current active map so the grid
// updates immediately after an equip/unequip without a refetch.
function reconcileActive(): void {
  const active = state.active;
  if (active === undefined) {
    return;
  }
  for (const cosmetic of state.cosmetics) {
    const slotSlug = active[cosmetic.type as keyof CosmeticActiveMap];
    if (typeof slotSlug === "string") {
      cosmetic.active = slotSlug === cosmetic.slug;
      continue;
    }
    cosmetic.active = active.crest[cosmetic.type] === cosmetic.slug;
  }
}

async function fetchCatalogue(): Promise<void> {
  state.loading = true;
  try {
    const response = await axios.get<CosmeticsIndexResponse>("/api/cosmetics");
    state.cosmetics = response.data.cosmetics;
    state.active = response.data.active;
  } finally {
    state.loading = false;
    state.loaded = true;
  }
}

// Mirror the equipped title/frame onto the live auth user so anything rendering
// them (profile, header) updates immediately, without a /me refetch.
function syncAuthUser(type: string, value: string | undefined): void {
  const user = useAuth().state.user;
  if (user === undefined) {
    return;
  }
  if (type === "title") {
    user.active_title = value;
  } else if (type === "frame") {
    user.avatar_frame = value;
  }
}

async function equip(cosmetic: Cosmetic): Promise<void> {
  const response = await axios.post<{ active: CosmeticActiveMap }>(`/api/cosmetics/${cosmetic.id}/equip`);
  state.active = response.data.active;
  reconcileActive();
  syncAuthUser(cosmetic.type, cosmetic.value ?? undefined);
}

async function unequip(type: string): Promise<void> {
  const response = await axios.post<{ active: CosmeticActiveMap }>("/api/cosmetics/unequip", { type });
  state.active = response.data.active;
  reconcileActive();
  syncAuthUser(type, undefined);
}

export function useCosmetics() {
  return { state, fetchCatalogue, equip, unequip };
}
