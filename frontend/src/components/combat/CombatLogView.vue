<!-- src/components/combat/CombatLogView.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { formatCombatEvent } from '../../composables/formatCombatEvent';

const store = useGameRunStore();

const lines = computed(() =>
  store.lastCombatLog.map((event) => formatCombatEvent(event, store.participantResolver)),
);
</script>

<template>
  <ul class="combat-log">
    <li v-for="(line, index) in lines" :key="index">{{ line }}</li>
  </ul>
</template>
