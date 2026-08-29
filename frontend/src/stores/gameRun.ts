// src/stores/gameRun.ts
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { runApi } from '../api/runApi';
import { buildParticipantResolver } from '../composables/buildParticipantResolver';
import { startCombatPlayback, type CombatPlaybackHandle } from '../composables/combatPlayback';
import type { RunStateDTO, CombatEventDTO, HeroDTO, OpponentInventoryDTO } from '../api/types';

export const useGameRunStore = defineStore('gameRun', () => {
  const runId = ref<string | null>(null);
  const state = ref<RunStateDTO | null>(null);
  const lastCombatLog = ref<CombatEventDTO[]>([]);
  const visibleCombatLog = ref<CombatEventDTO[]>([]);
  const isPlayingBack = ref(false);
  const opponentRoster = ref<HeroDTO[]>([]);
  const opponentInventory = ref<OpponentInventoryDTO>({ items: [] });

  let playbackHandle: CombatPlaybackHandle | null = null;

  function requireRunId(): string {
    if (runId.value === null) {
      throw new Error('No active run.');
    }
    return runId.value;
  }

  async function startNewRun(): Promise<void> {
    playbackHandle?.stop();
    playbackHandle = null;

    const res = await runApi.create();
    runId.value = res.run_id;
    state.value = res.state;
    lastCombatLog.value = [];
    visibleCombatLog.value = [];
    isPlayingBack.value = false;
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

    lastCombatLog.value = res.combatLog;
    visibleCombatLog.value = [];
    opponentRoster.value = res.opponentRoster;
    opponentInventory.value = res.opponentInventory;
    isPlayingBack.value = true;

    playbackHandle = startCombatPlayback(res.combatLog, {
      onReveal: (events) => {
        visibleCombatLog.value = events;
      },
      onComplete: () => {
        state.value = res.state;
        isPlayingBack.value = false;
        playbackHandle = null;
      },
    });
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
    visibleCombatLog,
    isPlayingBack,
    opponentRoster,
    opponentInventory,
    participantResolver,
    startNewRun,
    buyItem,
    swapItem,
    resolveRound,
  };
});
