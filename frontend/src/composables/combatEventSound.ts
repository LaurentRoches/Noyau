// src/composables/combatEventSound.ts
import type { CombatEventDTO } from '../api/types';

export function combatEventSoundFile(event: CombatEventDTO): string | null {
  switch (event.type) {
    case 'DAMAGE_DEALT':
    case 'ENRAGE_DAMAGE_DEALT':
      return 'weapon_hit';
    case 'SHIELD_GAINED':
    case 'STATUS_SHIELD_GAINED':
      return 'shield_gain';
    case 'HEAL_RECEIVED':
    case 'STATUS_HEAL_RECEIVED':
      return 'heal';
    case 'STATUS_DAMAGE_DEALT': {
      const { status } = event.payload as { status: string };
      return status === 'POISON' ? 'poison_tick' : 'burn_tick';
    }
    // Aucun de ces trois événements n'a de son. Pour le départage de fin de
    // combat c'est une décision et non une omission : `default` aurait suffi,
    // le cas est écrit pour que le choix reste visible — et testé.
    case 'STATUS_APPLIED':
    case 'STATUS_EXPIRED':
    case 'RESOLUTION_TIEBREAK':
      return null;
    default:
      return null;
  }
}
