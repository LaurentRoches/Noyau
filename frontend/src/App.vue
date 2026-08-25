<script setup lang="ts">
import { useGameRunStore } from './stores/gameRun';
import CombatLogView from './components/combat/CombatLogView.vue';
import ShopView from './components/shop/ShopView.vue';
import VestigePanel from './components/vestige/VestigePanel.vue';
import HeroRosterPanel from './components/hero/HeroRosterPanel.vue';

const store = useGameRunStore();
</script>

<template>
  <div class="app-shell">
    <header v-if="store.runId !== null" class="status-bar">
      <span>Manche {{ store.state?.round }}</span>
      <span class="status-bar__sep">·</span>
      <span>{{ store.state?.victories }} victoires</span>
      <span class="status-bar__sep">·</span>
      <span>{{ store.state?.defeats }} défaites</span>
      <span class="status-bar__sep">·</span>
      <span class="status-bar__gold">{{ store.state?.wallet.balance }} or</span>
    </header>

    <div class="app-body">
      <aside v-if="store.runId !== null" class="app-rail app-rail--left">
        <VestigePanel />
      </aside>

      <main class="app-main">
        <button
          v-if="store.runId === null || store.state?.isOver"
          class="start-button"
          @click="store.startNewRun()"
        >
          {{ store.runId === null ? 'Démarrer un run' : 'Rejouer' }}
        </button>
        <template v-if="store.runId !== null && !store.state?.isOver">
          <ShopView />
          <button class="resolve-button" @click="store.resolveRound()">Résoudre le round</button>
        </template>
        <CombatLogView v-if="store.runId !== null" />
      </main>

      <aside v-if="store.runId !== null" class="app-rail app-rail--right">
        <HeroRosterPanel />
      </aside>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.status-bar {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
  padding: 16px 24px;
  background: var(--shadow);
  border-bottom: 1px solid var(--shadow-border);
  font-family: var(--font-mono);
  font-size: 17px;
  color: var(--mist);
}
.status-bar__sep {
  opacity: 0.4;
}
.status-bar__gold {
  color: var(--legendary);
}
.app-body {
  flex: 1;
  display: flex;
  justify-content: center;
  gap: 24px;
  padding: 24px;
}
.app-rail {
  width: 220px;
  flex-shrink: 0;
}
.app-main {
  flex: 1;
  max-width: 900px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.start-button {
  align-self: center;
  font-size: 16px;
  padding: 12px 32px;
}
.resolve-button {
  align-self: center;
}
</style>
