<script setup lang="ts">
import { useGameRunStore } from './stores/gameRun';
import CombatLogView from './components/combat/CombatLogView.vue';
import ShopView from './components/shop/ShopView.vue';

const store = useGameRunStore();
</script>

<template>
  <div id="app-root">
    <button
      v-if="store.runId === null || store.state?.isOver"
      @click="store.startNewRun()"
    >
      {{ store.runId === null ? 'Démarrer un run' : 'Rejouer' }}
    </button>

    <template v-if="store.runId !== null && !store.state?.isOver">
      <p>
        Round {{ store.state?.round }} — Victoires: {{ store.state?.victories }} — Défaites:
        {{ store.state?.defeats }} — Or: {{ store.state?.wallet.balance }}
      </p>

      <ShopView />

      <button @click="store.resolveRound()">Résoudre le round</button>
    </template>

    <template v-if="store.runId !== null">
      <CombatLogView />
    </template>
  </div>
</template>