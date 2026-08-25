<!-- src/components/combat/CombatLogView.vue -->
<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { formatCombatEvent } from '../../composables/formatCombatEvent';

const store = useGameRunStore();

const lines = computed(() =>
  store.lastCombatLog.map((event) => formatCombatEvent(event, store.participantResolver)),
);

const logContainer = ref<HTMLElement | null>(null);

watch(lines, async () => {
  await nextTick();
  if (logContainer.value) {
    logContainer.value.scrollTop = logContainer.value.scrollHeight;
  }
});
</script>

<template>
  <div ref="logContainer" class="combat-log">
    <ul class="combat-log__list">
      <li
        v-for="(line, index) in lines"
        :key="index"
        class="combat-log__entry"
        :class="{
          'combat-log__entry--player': line.sourceSide === 'PLAYER',
          'combat-log__entry--opponent': line.sourceSide === 'OPPONENT',
        }"
      >
        <span
          v-for="(segment, segIndex) in line.segments"
          :key="segIndex"
          :class="segment.colorClass ? `combat-log__value combat-log__value--${segment.colorClass}` : undefined"
          >{{ segment.text }}</span
        >
      </li>
    </ul>
  </div>
</template>

<style scoped>
.combat-log {
  height: 420px;
  overflow-y: auto;
  background: var(--shadow);
  border: 1px solid var(--shadow-border);
  border-radius: 4px;
  padding: 16px 20px;
}
.combat-log__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.combat-log__entry {
  font-size: 13px;
  line-height: 1.6;
  color: var(--mist);
  padding: 2px 8px;
  border-radius: 3px;
}
.combat-log__entry--player {
  background: var(--log-player-bg);
}
.combat-log__entry--opponent {
  background: var(--log-opponent-bg);
}
.combat-log__value {
  font-family: var(--font-mono);
  font-weight: 600;
}
.combat-log__value--damage {
  color: var(--color-damage);
}
.combat-log__value--poison {
  color: var(--color-poison);
}
.combat-log__value--burn {
  color: var(--color-burn);
}
.combat-log__value--shield {
  color: var(--color-shield);
}
.combat-log__value--heal {
  color: var(--color-heal);
}
</style>