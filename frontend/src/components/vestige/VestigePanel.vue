<!-- src/components/vestige/VestigePanel.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';

const store = useGameRunStore();

const vestige = computed(() => store.state?.vestige ?? null);
</script>

<template>
  <div v-if="vestige" class="vestige-panel">
    <div class="vestige-panel__image" aria-hidden="true"></div>
    <h3 class="vestige-panel__name">{{ vestige.name }}</h3>
    <p class="vestige-panel__affinity">{{ vestige.affinity }}</p>

    <dl class="vestige-panel__stats">
      <div class="vestige-panel__stat">
        <dt>PV</dt>
        <dd class="vestige-panel__value vestige-panel__value--heal">{{ vestige.baseHp }}</dd>
      </div>
      <div class="vestige-panel__stat">
        <dt>Bouclier</dt>
        <dd class="vestige-panel__value vestige-panel__value--shield">
          {{ vestige.baseShield }}
        </dd>
      </div>
      <div class="vestige-panel__stat">
        <dt>Or de départ</dt>
        <dd class="vestige-panel__value">{{ vestige.startingGold }}</dd>
      </div>
      <div class="vestige-panel__stat">
        <dt>Revenu / manche</dt>
        <dd class="vestige-panel__value">{{ vestige.startingIncome }}</dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
.vestige-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: var(--shadow);
  border: 1px solid var(--shadow-border);
  border-radius: 4px;
}
.vestige-panel__image {
  width: 100%;
  aspect-ratio: 1;
  background: var(--shadow-border);
  border-radius: 4px;
}
.vestige-panel__name {
  font-size: 16px;
}
.vestige-panel__affinity {
  margin: 0;
  font-size: 12px;
  color: var(--mist);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.vestige-panel__stats {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 8px 0 0;
}
.vestige-panel__stat {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 12px;
  color: var(--mist);
}
.vestige-panel__stat dt {
  font-family: var(--font-body);
}
.vestige-panel__value {
  font-family: var(--font-mono);
  font-weight: 600;
  color: var(--bone);
}
.vestige-panel__value--heal {
  color: var(--color-heal);
}
.vestige-panel__value--shield {
  color: var(--color-shield);
}
</style>
