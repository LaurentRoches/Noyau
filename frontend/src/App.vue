<script setup lang="ts">
import { useGameRunStore } from './stores/gameRun';
import CombatLogView from './components/combat/CombatLogView.vue';
import ShopView from './components/shop/ShopView.vue';

const store = useGameRunStore();
</script>

<template>
  <div id="app-root">
    <button v-if="store.runId === null" @click="store.startNewRun()">Démarrer un run</button>

    <template v-else>
      <p>
        Round {{ store.state?.round }} — Victoires: {{ store.state?.victories }} — Défaites:
        {{ store.state?.defeats }} — Or: {{ store.state?.wallet.balance }}
      </p>

      <ShopView />

      <button :disabled="store.state?.isOver" @click="store.resolveRound()">
        Résoudre le round
      </button>

      <CombatLogView />
    </template>
  </div>
</template>