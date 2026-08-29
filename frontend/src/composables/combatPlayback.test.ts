// src/composables/combatPlayback.test.ts
import { describe, it, expect, vi, afterEach } from 'vitest';
import { startCombatPlayback } from './combatPlayback';
import type { CombatEventDTO } from '../api/types';

describe('startCombatPlayback', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('completes immediately without posing a timer when the log is empty', () => {
    vi.useFakeTimers();
    const setIntervalSpy = vi.spyOn(global, 'setInterval');

    const onReveal = vi.fn();
    const onComplete = vi.fn();

    startCombatPlayback([] as CombatEventDTO[], { onReveal, onComplete });

    expect(onReveal).toHaveBeenCalledTimes(1);
    expect(onReveal).toHaveBeenCalledWith([]);
    expect(onComplete).toHaveBeenCalledTimes(1);
    expect(setIntervalSpy).not.toHaveBeenCalled();
  });
});
