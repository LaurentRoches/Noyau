// src/stores/audioSettings.test.ts
import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAudioSettingsStore } from './audioSettings';
import { useGameRunStore } from './gameRun';

describe('useAudioSettingsStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('defaults to disabled with a 70% volume', () => {
    const store = useAudioSettingsStore();

    expect(store.enabled).toBe(false);
    expect(store.volume).toBe(0.7);
  });

  it('returns an effective volume of 0 when disabled, regardless of combat state', () => {
    const store = useAudioSettingsStore();
    const gameRun = useGameRunStore();

    store.setEnabled(false);
    gameRun.isPlayingBack = true;

    expect(store.effectiveVolume).toBe(0);
  });

  it('returns the raw volume when enabled and no combat is playing back', () => {
    const store = useAudioSettingsStore();
    const gameRun = useGameRunStore();

    store.setEnabled(true);
    gameRun.isPlayingBack = false;

    expect(store.effectiveVolume).toBe(0.7);
  });

  it('applies the 40% duck factor when combat is playing back', () => {
    const store = useAudioSettingsStore();
    const gameRun = useGameRunStore();

    store.setEnabled(true);
    gameRun.isPlayingBack = true;

    expect(store.effectiveVolume).toBeCloseTo(0.28); // 0.7 * 0.4
  });
});
