// src/composables/useHubMusic.ts
import { watch } from 'vue';
import { useAudioSettingsStore } from '../stores/audioSettings';
import { hubMusicUrl } from './assetPaths';

export function useHubMusic(): void {
  const audioSettings = useAudioSettingsStore();
  const audio = new Audio(hubMusicUrl());
  audio.loop = true;

  watch(
    () => audioSettings.effectiveVolume,
    (volume) => {
      audio.volume = volume;
    },
    { immediate: true },
  );

  watch(
    () => audioSettings.enabled,
    (enabled) => {
      if (enabled) {
        audio.play().catch(() => {
          // Lecture bloquée par le navigateur si le geste utilisateur n'a pas
          // été détecté comme tel (ne devrait pas arriver depuis un clic direct
          // sur la case à cocher).
        });
      } else {
        audio.pause();
      }
    },
  );
}
