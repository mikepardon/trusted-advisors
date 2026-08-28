<template>
    <div class="lvlup-overlay" @click.self="$emit('close')">
        <div class="lvlup-modal">
            <button class="lvlup-close" @click="$emit('close')">&times;</button>
            <h3 class="lvlup-title">Level Up!</h3>
            <p class="lvlup-subtitle">
                {{ advisor.display_name }} &mdash; Choose upgrade for Level
                {{ forLevel }}
            </p>

            <div v-if="loading" class="loading-text">Loading options...</div>
            <div v-else-if="options.length === 0" class="loading-text">
                No options available.
            </div>
            <div v-else class="lvlup-options">
                <div
                    v-for="opt in options"
                    :key="opt.id"
                    class="lvlup-option"
                    :class="{ selected: selectedOption?.id === opt.id }"
                    @click="selectOption(opt)"
                >
                    <div class="lvlup-opt-header">
                        <span class="lvlup-opt-name">{{ opt.name }}</span>
                    </div>
                    <p class="lvlup-opt-desc">{{ opt.description }}</p>

                    <!-- Dice picker for bump/wild types -->
                    <div
                        v-if="
                            selectedOption?.id === opt.id &&
                            isDicePickerNeeded(opt)
                        "
                        class="lvlup-dice-picker"
                        @click.stop
                    >
                        <p class="lvlup-picker-label">
                            {{
                                opt.type === "bump_two_dice_faces"
                                    ? "Select 2 die faces:"
                                    : "Select a die face:"
                            }}
                        </p>
                        <div
                            v-for="(die, di) in advisor.modified_dice"
                            :key="di"
                            class="lvlup-dice-row"
                        >
                            <span class="lvlup-dice-label"
                                >Die {{ di + 1 }}:</span
                            >
                            <span
                                v-for="(face, fi) in die"
                                :key="fi"
                                class="lvlup-dice-face"
                                :class="{
                                    'lvlup-face-selected': isFaceSelected(
                                        di,
                                        fi,
                                    ),
                                    'lvlup-face-disabled':
                                        face === 'WILD' &&
                                        opt.type !== 'add_wild',
                                    'lvlup-face-wild': face === 'WILD',
                                }"
                                @click="toggleFace(opt, di, fi, face)"
                                >{{ face === "WILD" ? "W" : face }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div v-if="selectedOption && previewText" class="lvlup-preview">
                <span class="lvlup-preview-label">Preview:</span>
                <span class="lvlup-preview-text">{{ previewText }}</span>
            </div>

            <div v-if="error" class="lvlup-error">{{ error }}</div>

            <div class="lvlup-actions">
                <p
                    v-if="cost > 0"
                    class="lvlup-cost"
                    :class="{ 'lvlup-cost-short': !canAfford }"
                >
                    Cost: &#129689; {{ cost
                    }}<span v-if="!canAfford"> — not enough coins</span>
                </p>
                <p class="lvlup-warning">This choice is permanent.</p>
                <button
                    class="btn-lvlup-confirm"
                    :disabled="!canConfirm || saving"
                    @click="confirm"
                >
                    {{
                        saving
                            ? "Choosing..."
                            : cost > 0
                              ? `Confirm · ${cost}`
                              : "Confirm"
                    }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../stores/toast";
import { useAuth } from "../stores/auth";

interface Advisor {
    id: number;
    display_name?: string;
    modified_dice: string[][];
    next_upgrade_cost?: number;
}

interface LevelOption {
    id: number;
    name: string;
    description?: string;
    type: string;
}

interface SelectedFace {
    die_index: number;
    face_index: number;
}

interface LevelOptionsResponse {
    options: LevelOption[];
    for_level: number;
}

const { advisor } = defineProps<{ advisor: Advisor }>();

const emit = defineEmits<{ close: []; chosen: [result: unknown] }>();

const toast = useToast();
const auth = useAuth();

const cost = computed(() => advisor.next_upgrade_cost ?? 0);
const canAfford = computed(
    () => cost.value === 0 || (auth.state.user?.coins ?? 0) >= cost.value,
);

const loading = ref(true);
const options = ref<LevelOption[]>([]);
const forLevel = ref<number>();
const selectedOption = ref<LevelOption>();
const selectedFaces = ref<SelectedFace[]>([]);
const saving = ref(false);
const error = ref("");

const canConfirm = computed(() => {
    if (!selectedOption.value || !canAfford.value) {
        return false;
    }
    const type = selectedOption.value.type;
    if (type === "bump_dice_face" || type === "add_wild") {
        return selectedFaces.value.length === 1;
    }
    if (type === "bump_two_dice_faces") {
        return selectedFaces.value.length === 2;
    }
    return true;
});

const previewText = computed(() => {
    if (!selectedOption.value) {
        return "";
    }
    const type = selectedOption.value.type;
    if (type === "bump_dice_face" && selectedFaces.value.length === 1) {
        const face = selectedFaces.value[0];
        const current =
            advisor.modified_dice[face.die_index]?.[face.face_index];
        return `Die ${face.die_index + 1}, face ${face.face_index + 1}: ${current} -> ${Number(current) + 1}`;
    }
    if (type === "bump_two_dice_faces" && selectedFaces.value.length === 2) {
        return selectedFaces.value
            .map((face) => {
                const current =
                    advisor.modified_dice[face.die_index]?.[face.face_index];
                return `Die ${face.die_index + 1} face ${face.face_index + 1}: ${current} -> ${Number(current) + 1}`;
            })
            .join(", ");
    }
    if (type === "add_wild" && selectedFaces.value.length === 1) {
        const face = selectedFaces.value[0];
        const current =
            advisor.modified_dice[face.die_index]?.[face.face_index];
        return `Die ${face.die_index + 1}, face ${face.face_index + 1}: ${current} -> WILD`;
    }
    return "";
});

async function loadOptions(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get<LevelOptionsResponse>(
            `/api/my-advisors/${advisor.id}/level-options`,
        );
        options.value = response.data.options;
        forLevel.value = response.data.for_level;
    } catch (error_) {
        const message = isAxiosError<{ error?: string }>(error_)
            ? error_.response?.data?.error
            : undefined;
        error.value = message ?? "Failed to load options";
    }
    loading.value = false;
}

function isDicePickerNeeded(option: LevelOption): boolean {
    return ["bump_dice_face", "bump_two_dice_faces", "add_wild"].includes(
        option.type,
    );
}

function selectOption(option: LevelOption): void {
    selectedOption.value = option;
    selectedFaces.value = [];
    error.value = "";
}

function isFaceSelected(dieIndex: number, faceIndex: number): boolean {
    return selectedFaces.value.some(
        (face) => face.die_index === dieIndex && face.face_index === faceIndex,
    );
}

function toggleFace(
    option: LevelOption,
    dieIndex: number,
    faceIndex: number,
    face: string,
): void {
    // Can't select WILD faces for bump types
    if (face === "WILD" && option.type !== "add_wild") {
        return;
    }
    // Can't select non-WILD for add_wild (it replaces a non-WILD face)
    if (face === "WILD" && option.type === "add_wild") {
        return;
    }

    const existing = selectedFaces.value.findIndex(
        (selected) =>
            selected.die_index === dieIndex &&
            selected.face_index === faceIndex,
    );
    if (existing !== -1) {
        selectedFaces.value.splice(existing, 1);
        return;
    }

    const maxFaces = option.type === "bump_two_dice_faces" ? 2 : 1;
    if (selectedFaces.value.length >= maxFaces) {
        selectedFaces.value = [];
    }
    selectedFaces.value.push({ die_index: dieIndex, face_index: faceIndex });
}

function buildUserChoice():
    SelectedFace | { faces: SelectedFace[] } | undefined {
    if (!selectedOption.value) {
        return undefined;
    }
    const type = selectedOption.value.type;
    if (type === "bump_dice_face" || type === "add_wild") {
        return selectedFaces.value[0] || undefined;
    }
    if (type === "bump_two_dice_faces") {
        return { faces: selectedFaces.value };
    }
    return undefined;
}

async function confirm(): Promise<void> {
    if (!canConfirm.value || !selectedOption.value) {
        return;
    }
    saving.value = true;
    error.value = "";
    try {
        const response = await axios.post<{ new_coins?: number }>(
            `/api/my-advisors/${advisor.id}/choose-upgrade`,
            {
                option_id: selectedOption.value.id,
                user_choice: buildUserChoice(),
            },
        );
        toast.success(`Chose: ${selectedOption.value.name}`);
        if (typeof response.data.new_coins === "number") {
            auth.updateUserStats({ coins: response.data.new_coins });
        }
        emit("chosen", response.data);
    } catch (error_) {
        const message = isAxiosError<{ error?: string }>(error_)
            ? error_.response?.data?.error
            : undefined;
        error.value = message ?? "Failed to choose upgrade";
    }
    saving.value = false;
}

onMounted(async () => {
    await loadOptions();
});
</script>

<style scoped>
.lvlup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.lvlup-modal {
    background: linear-gradient(180deg, #2a1f14, #1a1209);
    border: 2px solid #5ab87a;
    border-radius: 12px;
    padding: 24px;
    max-width: 440px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.lvlup-close {
    position: absolute;
    top: 8px;
    right: 12px;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.lvlup-close:hover {
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
}

.lvlup-title {
    font-family: "Cinzel", serif;
    color: #5ab87a;
    font-size: 1.4rem;
    text-align: center;
    margin: 0 0 4px;
}

.lvlup-subtitle {
    color: var(--text-secondary);
    font-size: 0.8rem;
    text-align: center;
    margin: 0 0 16px;
}

.loading-text {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 20px;
}

.lvlup-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.lvlup-option {
    padding: 12px;
    border: 2px solid rgba(212, 168, 67, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.02);
    cursor: pointer;
    transition: all 0.15s;
}

.lvlup-option:hover {
    border-color: rgba(212, 168, 67, 0.4);
}

.lvlup-option.selected {
    border-color: #5ab87a;
    background: rgba(90, 184, 122, 0.06);
}

.lvlup-opt-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.lvlup-opt-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.9rem;
    font-weight: 700;
}

.lvlup-opt-desc {
    color: var(--text-secondary);
    font-size: 0.78rem;
    margin: 0;
    line-height: 1.4;
}

.lvlup-dice-picker {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.lvlup-picker-label {
    color: var(--text-secondary);
    font-size: 0.72rem;
    margin: 0 0 6px;
}

.lvlup-dice-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    justify-content: center;
}

.lvlup-dice-label {
    color: var(--text-secondary);
    font-size: 0.75rem;
    min-width: 42px;
}

.lvlup-dice-face {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: rgba(212, 168, 67, 0.12);
    border: 2px solid rgba(212, 168, 67, 0.2);
    border-radius: 4px;
    color: var(--text-bright);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.lvlup-dice-face:hover:not(.lvlup-face-disabled) {
    border-color: var(--accent-gold);
    background: rgba(212, 168, 67, 0.2);
}

.lvlup-dice-face.lvlup-face-selected {
    border-color: #5ab87a;
    background: rgba(90, 184, 122, 0.25);
    color: #5ab87a;
}

.lvlup-dice-face.lvlup-face-disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.lvlup-dice-face.lvlup-face-wild {
    color: var(--accent-gold);
}

.lvlup-preview {
    padding: 8px 12px;
    background: rgba(90, 184, 122, 0.06);
    border: 1px solid rgba(90, 184, 122, 0.2);
    border-radius: 6px;
    margin-bottom: 12px;
    text-align: center;
}

.lvlup-preview-label {
    color: var(--text-secondary);
    font-size: 0.72rem;
    margin-right: 6px;
}

.lvlup-preview-text {
    color: #5ab87a;
    font-size: 0.78rem;
    font-weight: 600;
}

.lvlup-error {
    color: #e07070;
    font-size: 0.78rem;
    text-align: center;
    margin-bottom: 8px;
}

.lvlup-actions {
    text-align: center;
}

.lvlup-cost {
    color: var(--accent-gold);
    font-family: "Cinzel", serif;
    font-weight: 700;
    font-size: 0.85rem;
    margin: 0 0 6px;
}

.lvlup-cost.lvlup-cost-short {
    color: #e07070;
}

.lvlup-warning {
    color: var(--text-secondary);
    font-size: 0.68rem;
    font-style: italic;
    margin: 0 0 8px;
}

.btn-lvlup-confirm {
    padding: 10px 32px;
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    font-weight: 700;
    background: linear-gradient(180deg, #2a6e3a, #1a4a26);
    border: 2px solid #5ab87a;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-lvlup-confirm:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
