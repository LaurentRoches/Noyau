// src/stores/gameRun.ts
import { ref } from 'vue';
import { defineStore } from 'pinia';
import { runApi } from '../api/runApi';
import type { RunStateDTO, CombatEventDTO } from '../api/types';

export const useGameRunStore = defineStore('gameRun', () => {
  const runId = ref<string | null>(null);
  const state = ref<RunStateDTO | null>(null);
  const lastCombatLog = ref<CombatEventDTO[]>([]);

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
  }

  return { runId, state, lastCombatLog, startNewRun, buyItem, swapItem, resolveRound };
});
