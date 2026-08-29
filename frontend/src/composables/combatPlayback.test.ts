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
    const setIntervalSpy = vi.spyOn(globalThis, 'setInterval');

    const onReveal = vi.fn();
    const onComplete = vi.fn();

    startCombatPlayback([] as CombatEventDTO[], { onReveal, onComplete });

    expect(onReveal).toHaveBeenCalledTimes(1);
    expect(onReveal).toHaveBeenCalledWith([]);
    expect(onComplete).toHaveBeenCalledTimes(1);
    expect(setIntervalSpy).not.toHaveBeenCalled();
  });

  it('reveals a single tick-0 event synchronously and completes without posing a timer', () => {
    vi.useFakeTimers();
    const setIntervalSpy = vi.spyOn(globalThis, 'setInterval');

    const event = { tick: 0, type: 'SHIELD_GAINED', payload: {} } as CombatEventDTO;
    const onReveal = vi.fn();
    const onComplete = vi.fn();

    startCombatPlayback([event], { onReveal, onComplete });

    expect(onReveal).toHaveBeenCalledTimes(1);
    expect(onReveal).toHaveBeenCalledWith([event]);
    expect(onComplete).toHaveBeenCalledTimes(1);
    expect(setIntervalSpy).not.toHaveBeenCalled();
  });

  it('reveals events progressively as simulated ticks advance, then completes', () => {
    vi.useFakeTimers();
    const setIntervalSpy = vi.spyOn(globalThis, 'setInterval');

    const eventAtTick0 = { tick: 0, type: 'SHIELD_GAINED', payload: {} } as CombatEventDTO;
    const eventAtTick2 = { tick: 2, type: 'DAMAGE_DEALT', payload: {} } as CombatEventDTO;

    const onReveal = vi.fn();
    const onComplete = vi.fn();

    startCombatPlayback([eventAtTick0, eventAtTick2], { onReveal, onComplete });

    // Révélation synchrone du tick 0, avant tout timer.
    expect(onReveal).toHaveBeenNthCalledWith(1, [eventAtTick0]);
    expect(onComplete).not.toHaveBeenCalled();
    expect(setIntervalSpy).toHaveBeenCalledTimes(1);
    expect(setIntervalSpy).toHaveBeenCalledWith(expect.any(Function), 100);

    vi.advanceTimersByTime(100); // currentTick = 1, rien de nouveau à révéler
    expect(onReveal).toHaveBeenNthCalledWith(2, [eventAtTick0]);
    expect(onComplete).not.toHaveBeenCalled();

    vi.advanceTimersByTime(100); // currentTick = 2 = maxTick
    expect(onReveal).toHaveBeenNthCalledWith(3, [eventAtTick0, eventAtTick2]);
    expect(onComplete).toHaveBeenCalledTimes(1);
    expect(onReveal).toHaveBeenCalledTimes(3);
  });
});
