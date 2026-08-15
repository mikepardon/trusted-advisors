<template>
    <div>
        <div class="page-header">
            <h2 class="page-title">Cosmetics</h2>
            <div class="header-buttons">
                <button class="btn-primary" @click="openCreate">
                    + New Cosmetic
                </button>
            </div>
        </div>

        <div v-if="loading" class="loading">Loading...</div>

        <template v-else>
            <section
                v-for="group in groups"
                :key="group.type"
                class="type-group"
            >
                <h3 class="group-title">{{ group.label }}</h3>
                <div v-if="group.items.length === 0" class="group-empty">
                    No cosmetics of this type.
                </div>
                <table v-else class="cosmetics-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Rarity</th>
                            <th>{{ valueLabel(group.type) }}</th>
                            <th>Flags</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cosmetic in group.items" :key="cosmetic.id">
                            <td>{{ cosmetic.name }}</td>
                            <td class="mono">{{ cosmetic.slug }}</td>
                            <td>
                                <span
                                    class="rarity-tag"
                                    :class="`rarity-${cosmetic.rarity}`"
                                    >{{ cosmetic.rarity }}</span
                                >
                            </td>
                            <td class="value-cell">{{ cosmetic.value }}</td>
                            <td>
                                <span
                                    v-if="cosmetic.is_default"
                                    class="flag-tag flag-default"
                                    >Default</span
                                >
                                <span
                                    v-if="cosmetic.is_available"
                                    class="flag-tag flag-available"
                                    >Available</span
                                >
                                <span v-else class="flag-tag flag-disabled"
                                    >Hidden</span
                                >
                            </td>
                            <td class="actions-col">
                                <button @click="openEdit(cosmetic)">
                                    Edit
                                </button>
                                <button
                                    class="btn-danger"
                                    :disabled="cosmetic.is_default"
                                    :title="
                                        cosmetic.is_default
                                            ? 'Default cosmetics cannot be deleted'
                                            : ''
                                    "
                                    @click="confirmDelete(cosmetic)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>

        <!-- Modal -->
        <div
            v-if="showModal"
            class="modal-overlay"
            @click.self="showModal = false"
        >
            <div class="modal-content">
                <h3>{{ editing ? "Edit Cosmetic" : "New Cosmetic" }}</h3>
                <form @submit.prevent="save">
                    <div class="form-group">
                        <label>Type</label>
                        <select v-model="form.type" required>
                            <option
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input v-model="form.name" required />
                    </div>

                    <div class="form-group">
                        <label>Slug</label>
                        <input v-model="form.slug" required />
                    </div>

                    <div class="form-group">
                        <label>Rarity</label>
                        <select v-model="form.rarity" required>
                            <option
                                v-for="rarity in rarityOptions"
                                :key="rarity"
                                :value="rarity"
                            >
                                {{ rarity }}
                            </option>
                        </select>
                    </div>

                    <!-- Crest style / colour cosmetics use a visual composer with a
                         live SVG preview instead of a plain numeric input. -->
                    <div v-if="isCrestComposer" class="form-group">
                        <label>{{ valueLabel(form.type) }}</label>

                        <div class="crest-preview">
                            <span class="crest-preview-label">Preview</span>
                            <div class="crest-preview-box">
                                <LeagueCrest
                                    :variant="previewCrestShape"
                                    :tier="previewCrestColour"
                                />
                            </div>
                        </div>

                        <!-- crest_style: pick a shape; a colour toggle only affects the preview. -->
                        <template v-if="isCrestStyle">
                            <div class="crest-toggle-row">
                                <span class="crest-toggle-label"
                                    >Preview colour</span
                                >
                                <button
                                    v-for="colourId in crestColourIds"
                                    :key="`preview-colour-${colourId}`"
                                    type="button"
                                    class="crest-toggle"
                                    :class="{
                                        'crest-toggle-active':
                                            previewColour === colourId,
                                    }"
                                    @click="previewColour = colourId"
                                >
                                    {{ colourId }}
                                </button>
                            </div>
                            <div class="crest-swatches">
                                <button
                                    v-for="shapeId in crestStyleIds"
                                    :key="`shape-${shapeId}`"
                                    type="button"
                                    class="crest-swatch"
                                    :class="{
                                        'crest-swatch-active':
                                            selectedValueNumber === shapeId,
                                    }"
                                    @click="selectCrestValue(shapeId)"
                                >
                                    <div class="crest-swatch-box">
                                        <LeagueCrest
                                            :variant="shapeId"
                                            :tier="previewColour"
                                        />
                                    </div>
                                    <span class="crest-swatch-caption">{{
                                        shapeId
                                    }}</span>
                                </button>
                            </div>
                        </template>

                        <!-- crest_colour: pick a palette; a shape toggle only affects the preview. -->
                        <template v-else>
                            <div class="crest-toggle-row">
                                <span class="crest-toggle-label"
                                    >Preview shape</span
                                >
                                <button
                                    v-for="shapeId in crestStyleIds"
                                    :key="`preview-shape-${shapeId}`"
                                    type="button"
                                    class="crest-toggle"
                                    :class="{
                                        'crest-toggle-active':
                                            previewShape === shapeId,
                                    }"
                                    @click="previewShape = shapeId"
                                >
                                    {{ shapeId }}
                                </button>
                            </div>
                            <div class="crest-swatches">
                                <button
                                    v-for="colourId in crestColourIds"
                                    :key="`colour-${colourId}`"
                                    type="button"
                                    class="crest-swatch"
                                    :class="{
                                        'crest-swatch-active':
                                            selectedValueNumber === colourId,
                                    }"
                                    @click="selectCrestValue(colourId)"
                                >
                                    <div class="crest-swatch-box">
                                        <LeagueCrest
                                            :variant="previewShape"
                                            :tier="colourId"
                                        />
                                    </div>
                                    <span class="crest-swatch-caption">{{
                                        colourId
                                    }}</span>
                                </button>
                            </div>
                        </template>

                        <span class="field-hint">{{
                            valueHint(form.type)
                        }}</span>
                    </div>

                    <div v-else class="form-group">
                        <label>{{ valueLabel(form.type) }}</label>
                        <input v-model="form.value" required />
                        <span class="field-hint">{{
                            valueHint(form.type)
                        }}</span>
                    </div>

                    <div class="form-group">
                        <label>Sort</label>
                        <input v-model.number="form.sort" type="number" />
                    </div>

                    <div class="form-group">
                        <label>Image (optional)</label>
                        <div class="image-picker">
                            <img
                                v-if="form.meta_image_url"
                                :src="form.meta_image_url"
                                class="preview-img"
                                alt="Cosmetic image"
                            />
                            <div class="image-picker-buttons">
                                <button
                                    type="button"
                                    class="btn-secondary"
                                    @click="showMediaLibrary = true"
                                >
                                    {{
                                        form.meta_image
                                            ? "Change Image"
                                            : "Choose Image"
                                    }}
                                </button>
                                <button
                                    v-if="form.meta_image"
                                    type="button"
                                    class="btn-link"
                                    @click="clearImage"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input v-model="form.is_default" type="checkbox" />
                            Default (granted to new players)
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input
                                v-model="form.is_available"
                                type="checkbox"
                            />
                            Available
                        </label>
                    </div>

                    <div v-if="formError" class="form-error">
                        {{ formError }}
                    </div>

                    <div class="modal-actions">
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="saving"
                        >
                            {{ saving ? "Saving..." : "Save" }}
                        </button>
                        <button type="button" @click="showModal = false">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <MediaLibraryModal
            :visible="showMediaLibrary"
            :select-mode="true"
            @close="showMediaLibrary = false"
            @select="onMediaSelected"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";
import MediaLibraryModal from "./MediaLibraryModal.vue";
import LeagueCrest from "../LeagueCrest.vue";

type CosmeticType =
    | "title"
    | "frame"
    | "card_back"
    | "victory_fx"
    | "crest_style"
    | "crest_colour";

interface CosmeticMeta {
    image?: string;
    image_url?: string;
    [key: string]: unknown;
}

interface Cosmetic {
    id: number;
    type: CosmeticType;
    slug: string;
    name: string;
    rarity: string;
    value: string;
    meta: CosmeticMeta | undefined;
    is_default: boolean;
    is_available: boolean;
    sort: number;
}

interface CosmeticForm {
    type: CosmeticType;
    name: string;
    slug: string;
    rarity: string;
    value: string;
    sort: number;
    is_default: boolean;
    is_available: boolean;
    meta_image: string | undefined;
    meta_image_url: string | undefined;
}

interface MediaItem {
    id: number;
    path: string;
    url: string;
}

interface TypeGroup {
    type: CosmeticType;
    label: string;
    items: Cosmetic[];
}

const typeOptions: { value: CosmeticType; label: string }[] = [
    { value: "title", label: "Title" },
    { value: "frame", label: "Frame" },
    { value: "card_back", label: "Card Back" },
    { value: "victory_fx", label: "Victory Effect" },
    { value: "crest_style", label: "Crest Style" },
    { value: "crest_colour", label: "Crest Colour" },
];

const groupOrder: { type: CosmeticType; label: string }[] = [
    { type: "title", label: "Titles" },
    { type: "frame", label: "Frames" },
    { type: "card_back", label: "Card Backs" },
    { type: "victory_fx", label: "Victory Effects" },
    { type: "crest_style", label: "Crest Styles" },
    { type: "crest_colour", label: "Crest Colours" },
];

const rarityOptions = ["common", "rare", "epic", "legendary"];

function valueLabel(type: CosmeticType): string {
    if (type === "title") {
        return "Display Text";
    }
    if (type === "crest_style") {
        return "Crest Shape";
    }
    if (type === "crest_colour") {
        return "Crest Colour";
    }
    return "Render Key";
}

function valueHint(type: CosmeticType): string {
    if (type === "title") {
        return 'The text shown beside the player, e.g. "The Wise".';
    }
    if (type === "crest_style") {
        return "Pick a shield shape below; the saved value is its id (1–5).";
    }
    if (type === "crest_colour") {
        return "Pick a colour palette below; the saved value is its id (0–4).";
    }
    return "Render key used by the frontend, e.g. iron, parchment, gold_rain.";
}

// Parse a crest render value (a numeric string like "0".."5") to a number, returning
// undefined for blank or non-integer input so the picker shows nothing selected.
function parseCrestValue(value: string): number | undefined {
    if (value.trim() === "") {
        return undefined;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : undefined;
}

function defaultForm(): CosmeticForm {
    return {
        type: "title",
        name: "",
        slug: "",
        rarity: "common",
        value: "",
        sort: 0,
        is_default: false,
        is_available: true,
        meta_image: undefined,
        meta_image_url: undefined,
    };
}

function errorMessage(error: unknown, fallback: string): string {
    if (isAxiosError<{ message?: string }>(error)) {
        return error.response?.data?.message ?? error.message;
    }
    return fallback;
}

const toast = useToast();

const cosmetics = ref<Cosmetic[]>([]);
const loading = ref(true);
const showModal = ref(false);
const showMediaLibrary = ref(false);
const editing = ref<Cosmetic | undefined>(undefined);
const saving = ref(false);
const formError = ref("");
const form = reactive<CosmeticForm>(defaultForm());

// Crest preview toggles. These only affect the live preview, never the saved value:
// when editing a shape the admin can flip through colours, and vice versa.
const previewShape = ref(1);
const previewColour = ref(0);

// The approved shield shapes (1–15) and colour palettes (0–4).
const crestStyleIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
const crestColourIds = [0, 1, 2, 3, 4];

// Whether the current form type drives the crest composer instead of the plain input.
const isCrestStyle = computed(() => form.type === "crest_style");
const isCrestColour = computed(() => form.type === "crest_colour");
const isCrestComposer = computed(
    () => isCrestStyle.value || isCrestColour.value,
);

// The numeric render value currently held in the form, or undefined when blank/invalid.
const selectedValueNumber = computed(() => parseCrestValue(form.value));

// The shape/colour the large preview should compose. For a crest_style cosmetic the
// shape comes from the saved value and the colour from the preview toggle; for a
// crest_colour cosmetic it is the reverse.
const previewCrestShape = computed(() => {
    if (isCrestStyle.value && selectedValueNumber.value !== undefined) {
        return selectedValueNumber.value;
    }
    return previewShape.value;
});
const previewCrestColour = computed(() => {
    if (isCrestColour.value && selectedValueNumber.value !== undefined) {
        return selectedValueNumber.value;
    }
    return previewColour.value;
});

// Commit a chosen swatch to the form as the numeric string the backend expects.
function selectCrestValue(value: number): void {
    form.value = String(value);
}

const groups = computed<TypeGroup[]>(() =>
    groupOrder.map((group) => ({
        type: group.type,
        label: group.label,
        items: cosmetics.value.filter(
            (cosmetic) => cosmetic.type === group.type,
        ),
    })),
);

async function fetch(): Promise<void> {
    loading.value = true;
    const response = await axios.get<Cosmetic[]>("/api/admin/cosmetics");
    cosmetics.value = response.data;
    loading.value = false;
}

function openCreate(): void {
    editing.value = undefined;
    Object.assign(form, defaultForm());
    previewShape.value = 1;
    previewColour.value = 0;
    formError.value = "";
    showModal.value = true;
}

function openEdit(cosmetic: Cosmetic): void {
    editing.value = cosmetic;
    const image = cosmetic.meta?.image;
    Object.assign(form, {
        type: cosmetic.type,
        name: cosmetic.name,
        slug: cosmetic.slug,
        rarity: cosmetic.rarity,
        value: cosmetic.value,
        sort: cosmetic.sort,
        is_default: cosmetic.is_default,
        is_available: cosmetic.is_available,
        meta_image: image,
        meta_image_url:
            image === undefined ? undefined : `/api/storage/${image}`,
    });
    // Seed the preview toggles from the saved value so a colour cosmetic previews on
    // its own shape and vice versa, otherwise fall back to sensible defaults.
    const savedValue = parseCrestValue(cosmetic.value);
    previewShape.value =
        savedValue !== undefined && cosmetic.type === "crest_style"
            ? savedValue
            : 1;
    previewColour.value =
        savedValue !== undefined && cosmetic.type === "crest_colour"
            ? savedValue
            : 0;
    formError.value = "";
    showModal.value = true;
}

function onMediaSelected(mediaItem: MediaItem | undefined): void {
    showMediaLibrary.value = false;
    if (!mediaItem) {
        return;
    }
    form.meta_image = mediaItem.path;
    form.meta_image_url = mediaItem.url;
}

function clearImage(): void {
    form.meta_image = undefined;
    form.meta_image_url = undefined;
}

async function save(): Promise<void> {
    formError.value = "";

    // The crest composer sets the value via swatch clicks rather than an HTML input,
    // so the browser's required check does not cover it — validate it here instead.
    if (isCrestComposer.value && selectedValueNumber.value === undefined) {
        formError.value = isCrestStyle.value
            ? "Choose a crest shape."
            : "Choose a crest colour.";
        return;
    }

    const meta =
        form.meta_image === undefined ? undefined : { image: form.meta_image };
    const payload = {
        type: form.type,
        name: form.name,
        slug: form.slug,
        rarity: form.rarity,
        value: form.value,
        sort: form.sort,
        is_default: form.is_default,
        is_available: form.is_available,
        meta,
    };

    saving.value = true;
    try {
        const current = editing.value;
        if (current) {
            await axios.put(`/api/admin/cosmetics/${current.id}`, payload);
        } else {
            await axios.post("/api/admin/cosmetics", payload);
        }
        showModal.value = false;
        await fetch();
    } catch (error) {
        formError.value = errorMessage(error, "Save failed");
    }
    saving.value = false;
}

async function confirmDelete(cosmetic: Cosmetic): Promise<void> {
    if (cosmetic.is_default) {
        toast.error("Default cosmetics cannot be deleted");
        return;
    }
    if (!confirm(`Delete cosmetic "${cosmetic.name}"?`)) {
        return;
    }
    try {
        await axios.delete(`/api/admin/cosmetics/${cosmetic.id}`);
        await fetch();
    } catch (error) {
        toast.error(`Delete failed: ${errorMessage(error, "Delete failed")}`);
    }
}

onMounted(async () => {
    await fetch();
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.5rem;
}
.header-buttons {
    display: flex;
    gap: 8px;
}
.loading {
    text-align: center;
    color: var(--text-secondary);
    padding: 40px;
}

.type-group {
    margin-bottom: 28px;
}
.group-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(184, 148, 46, 0.25);
    padding-bottom: 6px;
}
.group-empty {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-style: italic;
}

.cosmetics-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-secondary);
    border: 1px solid rgba(184, 148, 46, 0.25);
    border-radius: 8px;
    overflow: hidden;
}
.cosmetics-table th,
.cosmetics-table td {
    text-align: left;
    padding: 10px 12px;
    font-size: 0.88rem;
    border-bottom: 1px solid rgba(184, 148, 46, 0.12);
}
.cosmetics-table th {
    font-family: "Cinzel", serif;
    color: var(--text-secondary);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.cosmetics-table td {
    color: var(--text-bright);
}
.cosmetics-table tr:last-child td {
    border-bottom: none;
}
.mono {
    font-family: monospace;
    color: var(--text-secondary);
}
.value-cell {
    font-size: 1rem;
}
.actions-col {
    white-space: nowrap;
    text-align: right;
}
.actions-col button {
    padding: 5px 12px;
    font-size: 0.8rem;
    margin-left: 6px;
}
.actions-col button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.rarity-tag {
    font-size: 0.72rem;
    padding: 2px 8px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.rarity-common {
    background: rgba(150, 150, 150, 0.2);
    color: #b0b0b0;
}
.rarity-rare {
    background: rgba(52, 152, 219, 0.2);
    color: #5dade2;
}
.rarity-epic {
    background: rgba(155, 89, 182, 0.2);
    color: #bb8fce;
}
.rarity-legendary {
    background: rgba(212, 168, 67, 0.2);
    color: #d4a843;
}

.flag-tag {
    font-size: 0.68rem;
    padding: 1px 6px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-right: 4px;
}
.flag-default {
    background: rgba(212, 168, 67, 0.2);
    color: #d4a843;
}
.flag-available {
    background: rgba(39, 174, 96, 0.2);
    color: #5ab87a;
}
.flag-disabled {
    background: rgba(192, 57, 43, 0.2);
    color: #c0392b;
}

.field-hint {
    display: block;
    color: var(--text-secondary);
    font-size: 0.78rem;
    margin-top: 4px;
}

.image-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}
.image-picker-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}
.preview-img {
    max-width: 80px;
    max-height: 80px;
    border-radius: 6px;
    border: 1px solid rgba(184, 148, 46, 0.3);
}
.btn-secondary {
    padding: 6px 14px;
    background: none;
    border: 1px solid var(--border-gold);
    border-radius: 6px;
    color: var(--text-secondary);
    font-size: 0.85rem;
    cursor: pointer;
}
.btn-secondary:hover {
    color: var(--text-bright);
}
.btn-link {
    background: none;
    border: none;
    color: var(--accent-red);
    font-size: 0.82rem;
    cursor: pointer;
    padding: 0;
}
.btn-link:hover {
    text-decoration: underline;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 200;
}
.modal-content {
    background: var(--bg-secondary);
    border: 2px solid var(--border-gold);
    border-radius: 10px;
    padding: 28px;
    width: 90%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
}
.modal-content h3 {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    margin-bottom: 18px;
    font-size: 1.3rem;
}
.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-bottom: 5px;
}
.form-group input:not([type="checkbox"]):not([type="file"]),
.form-group select {
    width: 100%;
    background: var(--bg-primary);
    border: 1px solid rgba(184, 148, 46, 0.3);
    color: var(--text-bright);
    padding: 8px 12px;
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.95rem;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--accent-gold);
}
.form-error {
    color: var(--accent-red);
    font-size: 0.9rem;
    margin-bottom: 10px;
}
.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

/* Crest composer */
.crest-preview {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 12px;
}
.crest-preview-label {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.crest-preview-box {
    width: 100px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.crest-toggle-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
}
.crest-toggle-label {
    color: var(--text-secondary);
    font-size: 0.78rem;
    margin-right: 4px;
}
.crest-toggle {
    padding: 4px 9px;
    background: var(--bg-primary);
    border: 1px solid rgba(184, 148, 46, 0.3);
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 0.8rem;
    cursor: pointer;
}
.crest-toggle:hover {
    color: var(--text-bright);
}
.crest-toggle-active {
    border-color: var(--accent-gold);
    color: var(--accent-gold);
}
.crest-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 8px;
}
.crest-swatch {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 6px;
    background: var(--bg-primary);
    border: 2px solid rgba(184, 148, 46, 0.25);
    border-radius: 8px;
    cursor: pointer;
}
.crest-swatch:hover {
    border-color: rgba(184, 148, 46, 0.6);
}
.crest-swatch-active {
    border-color: var(--accent-gold);
    box-shadow: 0 0 0 1px var(--accent-gold);
}
.crest-swatch-box {
    width: 60px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.crest-swatch-caption {
    color: var(--text-secondary);
    font-size: 0.75rem;
}
</style>
