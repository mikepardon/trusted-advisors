<template>
    <!-- Teleported to <body> so it escapes the admin light-theme token remap and
         renders the slotted game component in the real dark game skin. -->
    <Teleport to="body">
        <div v-if="visible" class="apv-overlay" @click.self="emit('close')">
            <div class="apv-modal">
                <div class="apv-head">
                    <div class="apv-heading">
                        <span class="apv-eyebrow">In-game preview</span>
                        <span v-if="title" class="apv-title">{{ title }}</span>
                    </div>
                    <button
                        class="apv-close"
                        aria-label="Close preview"
                        @click="emit('close')"
                    >
                        &times;
                    </button>
                </div>
                <p class="apv-note">This is how players see it in game.</p>
                <div class="apv-stage">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
const { visible = false, title = undefined } = defineProps<{
    visible?: boolean;
    title?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<style scoped>
.apv-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.72);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 300;
    padding: 20px;
}

.apv-modal {
    width: 100%;
    max-width: 440px;
    max-height: 88vh;
    overflow-y: auto;
    background: linear-gradient(
        180deg,
        rgba(20, 14, 8, 0.98),
        rgba(11, 8, 5, 0.99)
    );
    border: 1px solid rgba(240, 192, 80, 0.35);
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
    padding: 16px 16px 20px;
}

.apv-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.apv-heading {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.apv-eyebrow {
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c8952e;
}

.apv-title {
    font-family: "Cinzel", serif;
    font-size: 1rem;
    font-weight: 700;
    color: #f0e0c8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.apv-close {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(240, 192, 80, 0.3);
    border-radius: 9px;
    color: #f0c050;
    font-size: 1.3rem;
    line-height: 1;
    cursor: pointer;
}

.apv-close:hover {
    border-color: #f0c050;
}

.apv-note {
    font-family: "Crimson Text", Georgia, serif;
    font-size: 0.78rem;
    color: #9a8a68;
    margin: 8px 2px 14px;
}

.apv-stage {
    background: radial-gradient(
        ellipse at 50% 34%,
        #3a2a18 0%,
        #150f08 62%,
        #0b0805 100%
    );
    border-radius: 14px;
    padding: 16px;
}
</style>
