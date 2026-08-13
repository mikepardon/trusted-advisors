<template>
  <div class="admin-media-page">
    <h2 class="page-title">Media Library</h2>
    <MediaLibraryModal :visible="true" :select-mode="false" :inline="true" />
  </div>
</template>

<script setup lang="ts">
import MediaLibraryModal from "./MediaLibraryModal.vue";
</script>

<style scoped>
.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  margin-bottom: 24px;
  font-size: 1.8rem;
}
</style>
