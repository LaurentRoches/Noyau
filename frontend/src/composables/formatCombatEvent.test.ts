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

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'Kestrel inflige ' },
        { text: '15', colorClass: 'damage' },
        { text: ' dégâts au Vestige adverse (via Shadow Dagger)' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'Kestrel donne ' },
        { text: '20', colorClass: 'shield' },
        { text: ' bouclier à ton Vestige (via Shadow Dagger)' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'Kestrel soigne ton Vestige de ' },
        { text: '20', colorClass: 'heal' },
        { text: ' PV (via Shadow Dagger)' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: 'OPPONENT',
      segments: [
        { text: 'Ravageur inflige ' },
        { text: '20', colorClass: 'damage' },
        {
          text: ' dégâts à ton Vestige (via Venom Fang) — 12 absorbés par le bouclier, 8 aux PV',
        },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'Kestrel applique ' },
        { text: '2', colorClass: 'poison' },
        { text: ' stack(s) de POISON au Vestige adverse (via Shadow Dagger)' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'POISON inflige ' },
        { text: '3', colorClass: 'poison' },
        { text: ' dégâts à ton Vestige' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'REGEN soigne ton Vestige de ' },
        { text: '5', colorClass: 'heal' },
        { text: ' PV' },
      ],
    });
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

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'WARD donne ' },
        { text: '6', colorClass: 'shield' },
        { text: ' bouclier à ton Vestige' },
      ],
    });
  });

  it('formats a STATUS_EXPIRED event', () => {
    const event: CombatEventDTO = {
      tick: 10,
      type: 'STATUS_EXPIRED',
      payload: {
        status: 'POISON',
        target: 'player_vestige',
        targetSide: 'PLAYER',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: null,
      segments: [{ text: 'POISON se dissipe sur ton Vestige' }],
    });
  });

  it('formats an ENRAGE_DAMAGE_DEALT event', () => {
    const event: CombatEventDTO = {
      tick: 40,
      type: 'ENRAGE_DAMAGE_DEALT',
      payload: {
        amount: 40,
        shieldDamage: 15,
        hpDamage: 25,
        target: 'player_vestige',
        targetSide: 'PLAYER',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'La fureur inflige ' },
        { text: '40', colorClass: 'damage' },
        { text: ' dégâts à ton Vestige — 15 absorbés par le bouclier, 25 aux PV' },
      ],
    });
  });

  it('colors the STATUS_DAMAGE_DEALT amount as burn when the status is BURN', () => {
    const event: CombatEventDTO = {
      tick: 11,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'BURN',
        amount: 5,
        shieldDamage: 0,
        hpDamage: 5,
        remainingStacks: 2,
        remainingTicks: 10,
        target: 'opponent_vestige',
        targetSide: 'OPPONENT',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'BURN inflige ' },
        { text: '5', colorClass: 'burn' },
        { text: ' dégâts au Vestige adverse' },
      ],
    });
  });

  it('colors the STATUS_APPLIED stacks as burn when the applied status is BURN', () => {
    const event: CombatEventDTO = {
      tick: 12,
      type: 'STATUS_APPLIED',
      payload: {
        status: 'BURN',
        stacksApplied: 3,
        durationTicksApplied: 20,
        totalStacks: 3,
        remainingTicks: 20,
        target: 'opponent_vestige',
        targetSide: 'OPPONENT',
        sourceSide: 'PLAYER',
        sourceItemId: 'firesteel',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'firesteel' && side === 'PLAYER') {
        return { heroName: 'Shadow’s Arrow', itemName: 'Firesteel' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'Shadow’s Arrow applique ' },
        { text: '3', colorClass: 'burn' },
        { text: ' stack(s) de BURN au Vestige adverse (via Firesteel)' },
      ],
    });
  });

  it('colors the STATUS_APPLIED stacks as shield when the applied status is WARD', () => {
    const event: CombatEventDTO = {
      tick: 13,
      type: 'STATUS_APPLIED',
      payload: {
        status: 'WARD',
        stacksApplied: 1,
        durationTicksApplied: 20,
        totalStacks: 1,
        remainingTicks: 20,
        target: 'player_vestige',
        targetSide: 'PLAYER',
        sourceSide: 'PLAYER',
        sourceItemId: 'shield',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shield' && side === 'PLAYER') {
        return { heroName: "Shadow's Bastion", itemName: 'Shield' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: "Shadow's Bastion applique " },
        { text: '1', colorClass: 'shield' },
        { text: ' stack(s) de WARD à ton Vestige (via Shield)' },
      ],
    });
  });

  it('colors the STATUS_APPLIED stacks as heal when the applied status is REGEN', () => {
    const event: CombatEventDTO = {
      tick: 14,
      type: 'STATUS_APPLIED',
      payload: {
        status: 'REGEN',
        stacksApplied: 4,
        durationTicksApplied: 20,
        totalStacks: 4,
        remainingTicks: 20,
        target: 'player_vestige',
        targetSide: 'PLAYER',
        sourceSide: 'PLAYER',
        sourceItemId: 'mercurochrome',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'mercurochrome' && side === 'PLAYER') {
        return { heroName: 'The Lifebringer', itemName: 'Mercurochrome' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve);

    expect(result).toEqual({
      sourceSide: 'PLAYER',
      segments: [
        { text: 'The Lifebringer applique ' },
        { text: '4', colorClass: 'heal' },
        { text: ' stack(s) de REGEN à ton Vestige (via Mercurochrome)' },
      ],
    });
  });
});
