<template>
  <GameReplay :shareToken="token" />
</template>

<script>
import GameReplay from './GameReplay.vue';

export default {
  name: 'PublicReplay',
  components: { GameReplay },
  props: {
    token: { type: String, required: true },
  },
};
</script>
