<!-- src/components/hero/HeroRosterPanel.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { useItemSwapSelection } from '../../composables/useItemSwapSelection';
import type { AssignedItemDTO } from '../../api/types';

const store = useGameRunStore();
const { selection, select } = useItemSwapSelection();

const roster = computed(() => store.state?.roster ?? []);

function itemsForHero(heroId: string): AssignedItemDTO[] {
  const inventoryItems = store.state?.inventory.items ?? [];
  return inventoryItems.filter((assigned) => assigned.heroId === heroId);
}

function isSelected(assigned: AssignedItemDTO): boolean {
  return (
    selection.value?.inventoryIndex === assigned.inventoryIndex &&
    selection.value?.heroId === assigned.heroId
  );
}
</script>

<template>
  <ul class="hero-roster">
    <li v-for="hero in roster" :key="hero.id" class="hero-roster__entry">
      <strong class="hero-roster__name">{{ hero.name }}</strong>
      <span class="hero-roster__affinity">{{ hero.affinity }}</span>
      <span class="hero-roster__skill">{{ hero.skill ?? 'Aucune compétence' }}</span>

      <ul class="hero-roster__items">
        <li
          v-if="itemsForHero(hero.id).length === 0"
          class="hero-roster__item hero-roster__item--empty"
        >
          Aucun objet équipé
        </li>
        <li v-for="assigned in itemsForHero(hero.id)" :key="assigned.inventoryIndex">
          <button
            type="button"
            class="hero-roster__item-button"
            :class="{ 'hero-roster__item-button--selected': isSelected(assigned) }"
            @click="
              select({
                inventoryIndex: assigned.inventoryIndex,
                heroId: assigned.heroId,
                itemName: assigned.item.name,
              })
            "
          >
            {{ assigned.item.name }}
          </button>
        </li>
      </ul>
    </li>
  </ul>
</template>

<style scoped>
.hero-roster {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hero-roster__entry {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px;
  background: var(--shadow);
  border: 1px solid var(--shadow-border);
  border-radius: 4px;
}
.hero-roster__name {
  font-size: 14px;
  color: var(--bone);
}
.hero-roster__affinity {
  font-size: 11px;
  color: var(--mist);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.hero-roster__skill {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--mist);
}
.hero-roster__items {
  list-style: none;
  margin: 6px 0 0;
  padding: 6px 0 0;
  border-top: 1px solid var(--shadow-border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.hero-roster__item--empty {
  font-size: 12px;
  color: var(--mist);
  font-style: italic;
}
.hero-roster__item-button {
  width: 100%;
  text-align: left;
  font-size: 12px;
  padding: 4px 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--bone);
}
.hero-roster__item-button:hover:not(:disabled) {
  border-color: var(--mist);
}
.hero-roster__item-button--selected {
  border-color: var(--rare);
  color: var(--rare);
}
</style>
