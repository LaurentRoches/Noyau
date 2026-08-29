// src/composables/combatEventSound.test.ts
import { describe, it, expect } from 'vitest';
import { combatEventSoundFile } from './combatEventSound';
import type { CombatEventDTO } from '../api/types';

function event(
  type: CombatEventDTO['type'],
  payload: Record<string, unknown> = {},
): CombatEventDTO {
  return { tick: 0, type, payload };
}

describe('combatEventSoundFile', () => {
  it('maps DAMAGE_DEALT and ENRAGE_DAMAGE_DEALT to weapon_hit', () => {
    expect(combatEventSoundFile(event('DAMAGE_DEALT'))).toBe('weapon_hit');
    expect(combatEventSoundFile(event('ENRAGE_DAMAGE_DEALT'))).toBe('weapon_hit');
  });

  it('maps SHIELD_GAINED and STATUS_SHIELD_GAINED to shield_gain', () => {
    expect(combatEventSoundFile(event('SHIELD_GAINED'))).toBe('shield_gain');
    expect(combatEventSoundFile(event('STATUS_SHIELD_GAINED'))).toBe('shield_gain');
  });

  it('maps HEAL_RECEIVED and STATUS_HEAL_RECEIVED to heal', () => {
    expect(combatEventSoundFile(event('HEAL_RECEIVED'))).toBe('heal');
    expect(combatEventSoundFile(event('STATUS_HEAL_RECEIVED'))).toBe('heal');
  });

  it('maps STATUS_DAMAGE_DEALT to poison_tick or burn_tick depending on the status', () => {
    expect(combatEventSoundFile(event('STATUS_DAMAGE_DEALT', { status: 'POISON' }))).toBe(
      'poison_tick',
    );
    expect(combatEventSoundFile(event('STATUS_DAMAGE_DEALT', { status: 'BURN' }))).toBe(
      'burn_tick',
    );
  });

  it('returns null for STATUS_APPLIED and STATUS_EXPIRED, which have no dedicated sound', () => {
    expect(combatEventSoundFile(event('STATUS_APPLIED'))).toBeNull();
    expect(combatEventSoundFile(event('STATUS_EXPIRED'))).toBeNull();
  });
});
