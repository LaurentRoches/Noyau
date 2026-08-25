<!-- src/components/hero/HeroRosterPanel.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';

const store = useGameRunStore();

const roster = computed(() => store.state?.roster ?? []);
</script>

<template>
  <ul class="hero-roster">
    <li v-for="hero in roster" :key="hero.id" class="hero-roster__entry">
      <strong class="hero-roster__name">{{ hero.name }}</strong>
      <span class="hero-roster__affinity">{{ hero.affinity }}</span>
      <span class="hero-roster__skill">{{ hero.skill ?? 'Aucune compétence' }}</span>
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
</style>
