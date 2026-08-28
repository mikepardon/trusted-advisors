import { reactive } from "vue";
import axios from "axios";
import { useAuth } from "./auth";

export interface LeagueRow {
    rank: number;
    name: string | null;
    score: number;
    is_you: boolean;
    is_bot: boolean;
    crest_style: number;
    crest_colour: number;
}

export interface LeagueStandings {
    tier: number;
    tier_name: string;
    tier_color: string;
    week_start: string | null;
    total: number;
    promote_count: number;
    demote_count: number;
    is_top_tier: boolean;
    is_bottom_tier: boolean;
    your_rank: number | null;
    rows: LeagueRow[];
}

export interface LeagueTier {
    name: string;
    color: string;
}

interface LeagueResponse {
    standings: LeagueStandings;
    tiers: LeagueTier[];
}

interface LeagueState {
    standings: LeagueStandings | undefined;
    tiers: LeagueTier[];
    loaded: boolean;
    loading: boolean;
}

const state = reactive<LeagueState>({
    standings: undefined,
    tiers: [],
    loaded: false,
    loading: false,
});

async function fetchStandings(): Promise<void> {
    state.loading = true;
    try {
        const response = await axios.get<LeagueResponse>("/api/league");
        state.standings = response.data.standings;
        state.tiers = response.data.tiers;

        // Keep the header's league_points in sync with the live ladder — it's otherwise
        // cached from login and goes stale after a game changes your score.
        const auth = useAuth();
        const you = response.data.standings.rows.find((row) => row.is_you);
        if (you && auth.state.user) {
            auth.state.user.league_points = you.score;
        }
    } finally {
        state.loading = false;
        state.loaded = true;
    }
}

export function useLeague() {
    return { state, fetchStandings };
}
