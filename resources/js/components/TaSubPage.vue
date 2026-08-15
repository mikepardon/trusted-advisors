<template>
    <div class="ta-page">
        <div class="ta-drawer">
            <div class="ta-page-header">
                <button class="ta-back" aria-label="Back" @click="onBack">
                    <svg
                        viewBox="0 0 24 24"
                        width="18"
                        height="18"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <polyline points="15 6 9 12 15 18" />
                    </svg>
                </button>
                <div class="ta-page-heading">
                    <div class="ta-page-title">{{ title }}</div>
                    <div v-if="subtitle" class="ta-page-sub">
                        {{ subtitle }}
                    </div>
                </div>
                <span v-if="stat" class="ta-stat-pill">{{ stat }}</span>
                <slot name="stat" />
            </div>
            <div class="ta-page-body">
                <slot />
            </div>
            <div v-if="$slots.footer" class="ta-page-footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from "vue-router";
import { playSound } from "../sounds";

const {
    title,
    subtitle = "",
    stat = "",
    back = "",
} = defineProps<{
    title: string;
    subtitle?: string;
    stat?: string;
    /**
  Route to navigate to on back; defaults to browser history back.
  */
    back?: string;
}>();

const emit = defineEmits<{ back: [] }>();
const router = useRouter();

function onBack(): void {
    playSound("clickNav");
    emit("back");
    if (back) {
        void router.push(back);
        return;
    }
    router.back();
}
</script>
