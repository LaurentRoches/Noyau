// src/stores/gameRun.test.ts
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useGameRunStore } from './gameRun';
import { runApi } from '../api/runApi';
import type { RunStateDTO } from '../api/types';

class FakeAudio {
  volume = 0;
  play(): Promise<void> {
    return Promise.resolve();
  }
}

vi.mock('../api/runApi', () => ({
  runApi: {
    create: vi.fn(),
    show: vi.fn(),
    buyItem: vi.fn(),
    swapItem: vi.fn(),
    resolveRound: vi.fn(),
  },
}));

function makeState(overrides: Partial<RunStateDTO> = {}): RunStateDTO {
  return {
    round: 1,
    victories: 0,
    defeats: 0,
    isOver: false,
    hasWon: false,
    vestige: {
      id: 'shadow_vestige',
      name: 'Porteur de l’Ombre',
      affinity: 'shadow',
      baseHp: 100,
      baseShield: 0,
      startingGold: 20,
      startingIncome: 5,
    },
    wallet: { balance: 20 },
    shop: null,
    inventory: { items: [] },
    stash: { items: [], capacity: 3, isFull: false },
    roster: [],
    ...overrides,
  };
}

describe('useGameRunStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.stubGlobal('Audio', FakeAudio);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('startNewRun sets runId and state from the create response', async () => {
    vi.mocked(runApi.create).mockResolvedValueOnce({
      run_id: 'abc123',
      state: makeState(),
    });

    const store = useGameRunStore();
    await store.startNewRun();

    expect(store.runId).toBe('abc123');
    expect(store.state).toEqual(makeState());
  });

  it('buyItem calls runApi with the current runId and updates state', async () => {
    vi.mocked(runApi.create).mockResolvedValueOnce({
      run_id: 'abc123',
      state: makeState(),
    });
    vi.mocked(runApi.buyItem).mockResolvedValueOnce({
      state: makeState({ wallet: { balance: 10 } }),
    });

    const store = useGameRunStore();
    await store.startNewRun();
    await store.buyItem(0);

    expect(runApi.buyItem).toHaveBeenCalledWith('abc123', 0);
    expect(store.state?.wallet.balance).toBe(10);
  });

  it('buyItem throws when no run has been started', async () => {
    const store = useGameRunStore();

    await expect(store.buyItem(0)).rejects.toThrow('No active run.');
    expect(runApi.buyItem).not.toHaveBeenCalled();
  });

  it('does not commit state or reveal events until the playback progresses', async () => {
    vi.useFakeTimers();
    vi.mocked(runApi.create).mockResolvedValueOnce({
      run_id: 'abc123',
      state: makeState(),
    });
    vi.mocked(runApi.resolveRound).mockResolvedValueOnce({
      state: makeState({ round: 2 }),
      combatLog: [{ tick: 1, type: 'DAMAGE_DEALT', payload: { amount: 10 } }],
      opponentRoster: [],
      opponentInventory: { items: [] },
    });

    const store = useGameRunStore();
    await store.startNewRun();
    await store.resolveRound();

    expect(store.state?.round).toBe(1);
    expect(store.isPlayingBack).toBe(true);
    expect(store.visibleCombatLog).toHaveLength(0);

    vi.useRealTimers();
  });

  it('commits the pending state once the combat playback completes', async () => {
    vi.useFakeTimers();
    vi.mocked(runApi.create).mockResolvedValueOnce({
      run_id: 'abc123',
      state: makeState(),
    });
    vi.mocked(runApi.resolveRound).mockResolvedValueOnce({
      state: makeState({ round: 2 }),
      combatLog: [{ tick: 1, type: 'DAMAGE_DEALT', payload: { amount: 10 } }],
      opponentRoster: [],
      opponentInventory: { items: [] },
    });

    const store = useGameRunStore();
    await store.startNewRun();
    await store.resolveRound();

    vi.advanceTimersByTime(100);

    expect(store.state?.round).toBe(2);
    expect(store.isPlayingBack).toBe(false);
    expect(store.visibleCombatLog).toHaveLength(1);
    expect(store.visibleCombatLog[0].type).toBe('DAMAGE_DEALT');

    vi.useRealTimers();
  });

  // Couverture manquante, notée explicitement : appeler startNewRun() pendant qu'un
  // playback est en cours doit stopper l'ancien handle (playbackHandle?.stop()) pour
  // qu'un onComplete tardif n'écrase pas le state du nouveau run démarré entre-temps.
  // Pas encore testé — à ajouter si ce scénario devient un vrai risque observé en usage
  // (ex. double-clic rapide sur "Rejouer" pendant l'animation d'un ancien combat).
  it('resolveRound stores the opponent roster/inventory and exposes a participant resolver', async () => {
    vi.mocked(runApi.create).mockResolvedValueOnce({
      run_id: 'abc123',
      state: makeState(),
    });
    vi.mocked(runApi.resolveRound).mockResolvedValueOnce({
      state: makeState({ round: 2 }),
      combatLog: [],
      opponentRoster: [
        { id: 'shadow_hero_1', name: 'Ravageur', affinity: 'shadow', itemSlots: 6, skill: null },
      ],
      opponentInventory: {
        items: [
          {
            item: {
              id: 'venom_fang',
              name: 'Venom Fang',
              rarity: 'COMMON',
              affinity: 'shadow',
              size: 'ONE_HAND',
              cooldownTicks: 4,
              effects: [],
            },
            heroId: 'shadow_hero_1',
          },
        ],
      },
    });

    const store = useGameRunStore();
    await store.startNewRun();
    await store.resolveRound();

    expect(store.opponentRoster).toHaveLength(1);
    expect(store.opponentRoster[0].name).toBe('Ravageur');
    expect(store.opponentInventory.items).toHaveLength(1);

    const resolved = store.participantResolver('venom_fang', 'OPPONENT');
    expect(resolved).toEqual({ heroName: 'Ravageur', itemName: 'Venom Fang' });
  });
});
