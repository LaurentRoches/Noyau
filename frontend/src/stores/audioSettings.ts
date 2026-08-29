// src/stores/audioSettings.ts
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { useGameRunStore } from './gameRun';

const DEFAULT_VOLUME = 0.7;
const DUCK_FACTOR = 0.4;

export const useAudioSettingsStore = defineStore('audioSettings', () => {
  const enabled = ref(false);
  const volume = ref(DEFAULT_VOLUME);

  function setEnabled(value: boolean): void {
    enabled.value = value;
  }

  const effectiveVolume = computed(() => {
    if (!enabled.value) {
      return 0;
    }
    const gameRun = useGameRunStore();
    return volume.value * (gameRun.isPlayingBack ? DUCK_FACTOR : 1);
  });

  return {
    enabled,
    volume,
    setEnabled,
    effectiveVolume,
  };
});
