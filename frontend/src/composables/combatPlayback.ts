// src/composables/combatPlayback.ts
import type { CombatEventDTO } from '../api/types';

export interface CombatPlaybackHandle {
  stop(): void;
}

export interface CombatPlaybackOptions {
  tickDurationMs?: number;
  onReveal: (visibleEvents: CombatEventDTO[]) => void;
  onComplete: () => void;
}

function maxTickOf(log: CombatEventDTO[]): number {
  return log.reduce((max, event) => Math.max(max, event.tick), 0);
}

function eventsUpToTick(log: CombatEventDTO[], tick: number): CombatEventDTO[] {
  return log.filter((event) => event.tick <= tick);
}

const DEFAULT_TICK_DURATION_MS = 100;

export function startCombatPlayback(
  log: CombatEventDTO[],
  options: CombatPlaybackOptions,
): CombatPlaybackHandle {
  const { onReveal, onComplete, tickDurationMs = DEFAULT_TICK_DURATION_MS } = options;

  const maxTick = maxTickOf(log);
  let currentTick = 0;

  onReveal(eventsUpToTick(log, currentTick));

  if (currentTick >= maxTick) {
    onComplete();
    return { stop(): void {} };
  }

  const intervalId = setInterval(() => {
    currentTick += 1;
    onReveal(eventsUpToTick(log, currentTick));

    if (currentTick >= maxTick) {
      clearInterval(intervalId);
      onComplete();
    }
  }, tickDurationMs);

  return {
    stop(): void {
      clearInterval(intervalId);
    },
  };
}
