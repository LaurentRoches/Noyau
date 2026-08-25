<!-- src/components/stash/StashPanel.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';

const store = useGameRunStore();

const stashItems = computed(() => store.state?.stash.items ?? []);
const capacity = computed(() => store.state?.stash.capacity ?? 0);
</script>

<template>
  <div class="stash-panel">
    <h4 class="stash-panel__title">Coffre ({{ stashItems.length }}/{{ capacity }})</h4>

    <p v-if="stashItems.length === 0" class="stash-panel__empty">Aucun objet dans le coffre</p>
    <ul v-else class="stash-panel__items">
      <li v-for="entry in stashItems" :key="entry.stashIndex" class="stash-panel__item">
        {{ entry.item.name }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.stash-panel {
  padding: 12px;
  background: var(--shadow);
  border: 1px solid var(--shadow-border);
  border-radius: 4px;
}
.stash-panel__title {
  margin: 0 0 8px;
  font-size: 12px;
  font-family: var(--font-body);
  font-weight: 600;
  color: var(--mist);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.stash-panel__empty {
  margin: 0;
  font-size: 12px;
  color: var(--mist);
  font-style: italic;
}
.stash-panel__items {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.stash-panel__item {
  font-size: 12px;
  color: var(--bone);
}
</style>
