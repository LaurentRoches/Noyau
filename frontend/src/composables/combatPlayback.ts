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

export function startCombatPlayback(
  log: CombatEventDTO[],
  options: CombatPlaybackOptions,
): CombatPlaybackHandle {
  const { onReveal, onComplete } = options;

  const maxTick = maxTickOf(log);
  const currentTick = 0;

  onReveal(eventsUpToTick(log, currentTick));

  if (currentTick >= maxTick) {
    onComplete();
  }

  return {
    stop(): void {},
  };
}
