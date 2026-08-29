<!-- src/components/stash/StashPanel.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { useItemSwapSelection } from '../../composables/useItemSwapSelection';
import { stashImageUrl, itemImageUrl, itemFrameUrl } from '../../composables/assetPaths';

const store = useGameRunStore();
const { selection, clear } = useItemSwapSelection();

const stashItems = computed(() => store.state?.stash.items ?? []);
const capacity = computed(() => store.state?.stash.capacity ?? 0);
const isEmpty = computed(() => stashItems.value.length === 0);

const errorMessage = ref<string | null>(null);

async function swapWithStashItem(stashIndex: number): Promise<void> {
  if (selection.value === null) {
    return;
  }
  errorMessage.value = null;

  try {
    await store.swapItem(selection.value.inventoryIndex, stashIndex, selection.value.heroId);
    clear();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Échange impossible.';
  }
}
</script>

<template>
  <div class="stash-panel">
    <img class="stash-panel__art" :src="stashImageUrl(isEmpty)" alt="" aria-hidden="true" />

    <h4 class="stash-panel__title">Coffre ({{ stashItems.length }}/{{ capacity }})</h4>

    <p v-if="selection" class="stash-panel__hint">
      Échanger <strong>{{ selection.itemName }}</strong> contre :
    </p>

    <p v-if="errorMessage" class="stash-panel__error">{{ errorMessage }}</p>

    <p v-if="isEmpty" class="stash-panel__empty">Aucun objet dans le coffre</p>
    <ul v-else class="stash-panel__items">
      <li v-for="entry in stashItems" :key="entry.stashIndex" class="stash-panel__item">
        <span class="stash-panel__item-info">
          <span
            class="stash-panel__item-art"
            :class="`stash-panel__item-art--${entry.item.rarity.toLowerCase()}`"
          >
            <img
              class="stash-panel__item-illustration"
              :src="itemImageUrl(entry.item.id)"
              :alt="entry.item.name"
            />
            <img class="stash-panel__item-frame" :src="itemFrameUrl()" alt="" aria-hidden="true" />
          </span>
          {{ entry.item.name }}
        </span>
        <button
          v-if="selection"
          type="button"
          class="stash-panel__swap-button"
          @click="swapWithStashItem(entry.stashIndex)"
        >
          Échanger
        </button>
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
.stash-panel__art {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 4px;
  margin-bottom: 8px;
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
.stash-panel__hint {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--rare);
}
.stash-panel__error {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--color-damage);
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
  color: var(--bone);
}
.stash-panel__item-info {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.stash-panel__swap-button {
  font-size: 11px;
  padding: 2px 8px;
  flex-shrink: 0;
}
.stash-panel__item-art {
  position: relative;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 3px;
}
.stash-panel__item-illustration {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 3px;
}
.stash-panel__item-frame {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
.stash-panel__item-art--common {
  box-shadow: 0 0 0 1px var(--common);
}
.stash-panel__item-art--rare {
  box-shadow: 0 0 4px 1px var(--rare);
}
.stash-panel__item-art--legendary {
  box-shadow: 0 0 6px 2px var(--legendary);
}
</style>
