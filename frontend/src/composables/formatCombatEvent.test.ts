// src/composables/formatCombatEvent.test.ts
import { describe, it, expect } from 'vitest';
import { formatCombatEvent } from './formatCombatEvent';
import type { CombatEventDTO } from '../api/types';

describe('formatCombatEvent', () => {
  it('formats a DAMAGE_DEALT event using the resolved hero and item names', () => {
    const event: CombatEventDTO = {
      tick: 3,
      type: 'DAMAGE_DEALT',
      payload: {
        amount: 15,
        shieldDamage: 0,
        hpDamage: 15,
        target: 'opponent_vestige',
        targetSide: 'OPPONENT',
        sourceSide: 'PLAYER',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'PLAYER') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe('Kestrel inflige 15 dégâts au Vestige adverse (via Shadow Dagger)');
  });

  it('formats a SHIELD_GAINED event using the resolved hero and item names', () => {
    const event: CombatEventDTO = {
      tick: 2,
      type: 'SHIELD_GAINED',
      payload: {
        amount: 20,
        shieldGained: 20,
        target: 'player_vestige',
        targetSide: 'PLAYER',
        sourceSide: 'PLAYER',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'PLAYER') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe('Kestrel donne 20 bouclier à ton Vestige (via Shadow Dagger)');
  });
});
