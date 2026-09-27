// src/stores/gameRun.ts
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { runApi } from '../api/runApi';
import { buildParticipantResolver } from '../composables/buildParticipantResolver';
import { startCombatPlayback, type CombatPlaybackHandle } from '../composables/combatPlayback';
import { playCombatSfx } from '../composables/combatSfxPlayer';
import type {
  RunStateDTO,
  CombatEventDTO,
  HeroDTO,
  OpponentInventoryDTO,
  Side,
} from '../api/types';

export const useGameRunStore = defineStore('gameRun', () => {
  const runId = ref<string | null>(null);
  const state = ref<RunStateDTO | null>(null);
  const lastCombatLog = ref<CombatEventDTO[]>([]);
  const visibleCombatLog = ref<CombatEventDTO[]>([]);
  const isPlayingBack = ref(false);
  const opponentRoster = ref<HeroDTO[]>([]);
  const opponentInventory = ref<OpponentInventoryDTO>({ items: [] });

  /**
   * Côté occupé par le joueur dans le dernier combat résolu (D-19).
   *
   * `null` tant qu'aucune manche n'a été jouée : avant un combat, aucun côté
   * n'a été attribué et il n'y a rien à supposer. La valeur vient de la
   * réponse du serveur, jamais d'une convention écrite ici.
   */
  const viewerSide = ref<Side | null>(null);

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
    viewerSide.value = null;
  }

  async function chooseHero(heroId: string): Promise<void> {
    const id = requireRunId();
    const res = await runApi.chooseHero(id, heroId);
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

    lastCombatLog.value = res.combatLog;
    visibleCombatLog.value = [];
    opponentRoster.value = res.opponentRoster;
    opponentInventory.value = res.opponentInventory;
    viewerSide.value = res.viewerSide;
    isPlayingBack.value = true;

    playbackHandle = startCombatPlayback(res.combatLog, {
      onReveal: (events) => {
        const newEvents = events.slice(visibleCombatLog.value.length);
        newEvents.forEach((event) => playCombatSfx(event));
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
    // Sans côté attribué, aucun inventaire ne peut être indexé : c'est le cas
    // avant le premier combat, où le journal est vide de toute façon.
    if (state.value === null || viewerSide.value === null) {
      return () => null;
    }
    return buildParticipantResolver(
      viewerSide.value,
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
    viewerSide,
    participantResolver,
    startNewRun,
    chooseHero,
    buyItem,
    swapItem,
    resolveRound,
  };
});
