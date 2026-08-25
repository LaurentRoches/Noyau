// src/stores/gameRun.ts
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { runApi } from '../api/runApi';
import { buildParticipantResolver } from '../composables/buildParticipantResolver';
import type { RunStateDTO, CombatEventDTO, HeroDTO, OpponentInventoryDTO } from '../api/types';

export const useGameRunStore = defineStore('gameRun', () => {
  const runId = ref<string | null>(null);
  const state = ref<RunStateDTO | null>(null);
  const lastCombatLog = ref<CombatEventDTO[]>([]);
  const opponentRoster = ref<HeroDTO[]>([]);
  const opponentInventory = ref<OpponentInventoryDTO>({ items: [] });

  function requireRunId(): string {
    if (runId.value === null) {
      throw new Error('No active run.');
    }
    return runId.value;
  }

  async function startNewRun(): Promise<void> {
    const res = await runApi.create();
    runId.value = res.run_id;
    state.value = res.state;
    lastCombatLog.value = [];
    opponentRoster.value = [];
    opponentInventory.value = { items: [] };
  }

  async function buyItem(slotIndex: number): Promise<void> {
    const id = requireRunId();
    const res = await runApi.buyItem(id, slotIndex);
    state.value = res.state;
  }

  async function swapItem(
    inventoryIndex: number,
    stashIndex: number,
    heroId: string,
  ): Promise<void> {
    const id = requireRunId();
    const res = await runApi.swapItem(id, inventoryIndex, stashIndex, heroId);
    state.value = res.state;
  }

  async function resolveRound(): Promise<void> {
    const id = requireRunId();
    const res = await runApi.resolveRound(id);
    state.value = res.state;
    lastCombatLog.value = res.combatLog;
    opponentRoster.value = res.opponentRoster;
    opponentInventory.value = res.opponentInventory;
  }

  const participantResolver = computed(() => {
    if (state.value === null) {
      return () => null;
    }
    return buildParticipantResolver(
      state.value.roster,
      state.value.inventory.items,
      opponentRoster.value,
      opponentInventory.value.items,
    );
  });

  return {
    runId,
    state,
    lastCombatLog,
    opponentRoster,
    opponentInventory,
    participantResolver,
    startNewRun,
    buyItem,
    swapItem,
    resolveRound,
  };
});
