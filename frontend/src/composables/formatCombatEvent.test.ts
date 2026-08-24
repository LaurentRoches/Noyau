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

  it('formats a HEAL_RECEIVED event using the resolved hero and item names', () => {
    const event: CombatEventDTO = {
      tick: 4,
      type: 'HEAL_RECEIVED',
      payload: {
        amount: 30,
        hpHealed: 20,
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

    expect(result).toBe('Kestrel soigne ton Vestige de 20 PV (via Shadow Dagger)');
  });

  it('formats a DAMAGE_DEALT event with a partial shield absorption breakdown', () => {
    const event: CombatEventDTO = {
      tick: 5,
      type: 'DAMAGE_DEALT',
      payload: {
        amount: 20,
        shieldDamage: 12,
        hpDamage: 8,
        target: 'player_vestige',
        targetSide: 'PLAYER',
        sourceSide: 'OPPONENT',
        sourceItemId: 'venom_fang',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'venom_fang' && side === 'OPPONENT') {
        return { heroName: 'Ravageur', itemName: 'Venom Fang' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe(
      'Ravageur inflige 20 dégâts à ton Vestige (via Venom Fang) — 12 absorbés par le bouclier, 8 aux PV',
    );
  });

  it('formats a STATUS_APPLIED event using the resolved hero and item names', () => {
    const event: CombatEventDTO = {
      tick: 6,
      type: 'STATUS_APPLIED',
      payload: {
        status: 'POISON',
        stacksApplied: 2,
        durationTicksApplied: 30,
        totalStacks: 2,
        remainingTicks: 30,
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

    expect(result).toBe(
      'Kestrel applique 2 stack(s) de POISON au Vestige adverse (via Shadow Dagger)',
    );
  });

  it('formats a STATUS_DAMAGE_DEALT event without a source', () => {
    const event: CombatEventDTO = {
      tick: 7,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'POISON',
        amount: 3,
        shieldDamage: 0,
        hpDamage: 3,
        remainingStacks: 3,
        remainingTicks: 19,
        target: 'player_vestige',
        targetSide: 'PLAYER',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe('POISON inflige 3 dégâts à ton Vestige');
  });

  it('formats a STATUS_HEAL_RECEIVED event without a source', () => {
    const event: CombatEventDTO = {
      tick: 8,
      type: 'STATUS_HEAL_RECEIVED',
      payload: {
        status: 'REGEN',
        amount: 8,
        hpHealed: 5,
        remainingStacks: 8,
        remainingTicks: 29,
        target: 'player_vestige',
        targetSide: 'PLAYER',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe('REGEN soigne ton Vestige de 5 PV');
  });

  it('formats a STATUS_SHIELD_GAINED event without a source', () => {
    const event: CombatEventDTO = {
      tick: 9,
      type: 'STATUS_SHIELD_GAINED',
      payload: {
        status: 'WARD',
        amount: 6,
        shieldGained: 6,
        remainingStacks: 6,
        remainingTicks: 29,
        target: 'player_vestige',
        targetSide: 'PLAYER',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toBe('WARD donne 6 bouclier à ton Vestige');
  });
});
