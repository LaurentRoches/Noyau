// src/composables/combatSfxPlayer.ts
import { combatEventSoundFile } from './combatEventSound';
import { sfxUrl } from './assetPaths';
import type { CombatEventDTO } from '../api/types';

const SFX_VOLUME = 0.7;

export function playCombatSfx(event: CombatEventDTO): void {
  const soundFile = combatEventSoundFile(event);
  if (soundFile === null) {
    return;
  }

  const audio = new Audio(sfxUrl(soundFile));
  audio.volume = SFX_VOLUME;
  audio.play().catch(() => {
    // Lecture bloquée par le navigateur (politique d'autoplay) : un SFX manqué
    // n'est pas bloquant pour la partie, on ignore silencieusement l'erreur.
  });
}
