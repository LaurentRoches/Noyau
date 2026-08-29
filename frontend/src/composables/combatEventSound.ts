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
    case 'STATUS_APPLIED':
    case 'STATUS_EXPIRED':
      return null;
    default:
      return null;
  }
}
