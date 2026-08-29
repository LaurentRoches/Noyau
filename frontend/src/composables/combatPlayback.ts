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

export function startCombatPlayback(
  log: CombatEventDTO[],
  options: CombatPlaybackOptions,
): CombatPlaybackHandle {
  const { onReveal, onComplete } = options;

  onReveal([]);
  onComplete();

  return {
    stop(): void {},
  };
}
