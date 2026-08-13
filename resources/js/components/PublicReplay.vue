<template>
  <GameReplay :share-token="token" />
</template>

<script setup lang="ts">
import GameReplay from "./GameReplay.vue";

defineProps<{
  token: string;
}>();
</script>
