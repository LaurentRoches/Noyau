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
        targetSide: 'B',
        sourceSide: 'A',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'A') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'A') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        poisonCleansed: 0,
        burnCleansed: 0,
        target: 'player_vestige',
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'A') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        targetSide: 'A',
        sourceSide: 'B',
        sourceItemId: 'venom_fang',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'venom_fang' && side === 'B') {
        return { heroName: 'Ravageur', itemName: 'Venom Fang' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'ENEMY',
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
        targetSide: 'B',
        sourceSide: 'A',
        sourceItemId: 'shadow_dagger',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shadow_dagger' && side === 'A') {
        return { heroName: 'Kestrel', itemName: 'Shadow Dagger' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        targetSide: 'A',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve, 'A');

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
        targetSide: 'A',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve, 'A');

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
        targetSide: 'A',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve, 'A');

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
        targetSide: 'A',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve, 'A');

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
        targetSide: 'A',
      },
    };

    const resolve = () => null;

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'La fureur inflige ' },
        { text: '40', colorClass: 'damage' },
        { text: ' dégâts à ton Vestige — 15 absorbés par le bouclier, 25 aux PV' },
      ],
    });
  });

  it('formats a BURN event splitting between shield and hp, with the attenuation named', () => {
    // Valeurs réelles de la règle : 10 stacks -> 15 majorés, 8 absorbés,
    // 7 de surplus, intdiv(7 * 7, 15) = 3 PV. 8 + 3 ne fait pas 15 : l'écart
    // est l'atténuation à 70 %, et le libellé doit le dire.
    const event: CombatEventDTO = {
      tick: 11,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'BURN',
        amount: 15,
        shieldDamage: 8,
        hpDamage: 3,
        remainingStacks: 10,
        remainingTicks: 10,
        target: 'opponent_vestige',
        targetSide: 'B',
      },
    };

    const result = formatCombatEvent(event, () => null, 'A');

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'BURN inflige ' },
        { text: '15', colorClass: 'burn' },
        {
          text: ' de brûlure au Vestige adverse — 8 absorbés par le bouclier, 3 aux PV après atténuation',
        },
      ],
    });
  });

  it('formats a BURN event fully absorbed by the shield', () => {
    const event: CombatEventDTO = {
      tick: 12,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'BURN',
        amount: 7,
        shieldDamage: 7,
        hpDamage: 0,
        remainingStacks: 5,
        remainingTicks: 19,
        target: 'player_vestige',
        targetSide: 'A',
      },
    };

    const result = formatCombatEvent(event, () => null, 'A');

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'BURN inflige ' },
        { text: '7', colorClass: 'burn' },
        { text: ' de brûlure à ton Vestige — entièrement absorbés par le bouclier' },
      ],
    });
  });

  it('formats a BURN event against an unshielded target', () => {
    const event: CombatEventDTO = {
      tick: 13,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'BURN',
        amount: 15,
        shieldDamage: 0,
        hpDamage: 7,
        remainingStacks: 10,
        remainingTicks: 19,
        target: 'player_vestige',
        targetSide: 'A',
      },
    };

    const result = formatCombatEvent(event, () => null, 'A');

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'BURN inflige ' },
        { text: '15', colorClass: 'burn' },
        { text: ' de brûlure à ton Vestige — 7 aux PV après atténuation' },
      ],
    });
  });

  it('says so when a BURN tick is entirely floored away', () => {
    // 1 stack -> intdiv(3, 2) = 1 majoré, puis intdiv(7, 15) = 0 PV.
    // Sans mention explicite, le journal annoncerait un montant sans effet.
    const event: CombatEventDTO = {
      tick: 14,
      type: 'STATUS_DAMAGE_DEALT',
      payload: {
        status: 'BURN',
        amount: 1,
        shieldDamage: 0,
        hpDamage: 0,
        remainingStacks: 1,
        remainingTicks: 19,
        target: 'player_vestige',
        targetSide: 'A',
      },
    };

    const result = formatCombatEvent(event, () => null, 'A');

    expect(result).toEqual({
      sourceSide: null,
      segments: [
        { text: 'BURN inflige ' },
        { text: '1', colorClass: 'burn' },
        { text: ' de brûlure à ton Vestige — sans effet après atténuation' },
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
        targetSide: 'B',
        sourceSide: 'A',
        sourceItemId: 'firesteel',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'firesteel' && side === 'A') {
        return { heroName: 'Shadow’s Arrow', itemName: 'Firesteel' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'shield',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'shield' && side === 'A') {
        return { heroName: "Shadow's Bastion", itemName: 'Shield' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
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
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'mercurochrome',
      },
    };

    const resolve = (itemId: string, side: string) => {
      if (itemId === 'mercurochrome' && side === 'A') {
        return { heroName: 'The Lifebringer', itemName: 'Mercurochrome' };
      }
      return null;
    };

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [
        { text: 'The Lifebringer applique ' },
        { text: '4', colorClass: 'heal' },
        { text: ' stack(s) de REGEN à ton Vestige (via Mercurochrome)' },
      ],
    });
  });

  it('names both cleansed statuses after a heal that also restored hp', () => {
    const event: CombatEventDTO = {
      tick: 15,
      type: 'HEAL_RECEIVED',
      payload: {
        amount: 58,
        hpHealed: 12,
        poisonCleansed: 1,
        burnCleansed: 1,
        target: 'player_vestige',
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'panacee',
      },
    };

    const resolve = (itemId: string, side: string) =>
      itemId === 'panacee' && side === 'A' ? { heroName: 'Kestrel', itemName: 'Panacée' } : null;

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [
        { text: 'Kestrel soigne ton Vestige de ' },
        { text: '12', colorClass: 'heal' },
        { text: ' PV (via Panacée) — nettoie 1 stack de POISON et 1 de BURN' },
      ],
    });
  });

  it('names only the cleansed status when a single one was present', () => {
    const event: CombatEventDTO = {
      tick: 16,
      type: 'HEAL_RECEIVED',
      payload: {
        amount: 10,
        hpHealed: 10,
        poisonCleansed: 0,
        burnCleansed: 1,
        target: 'player_vestige',
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'mercurocroum',
      },
    };

    const resolve = () => ({ heroName: 'Kestrel', itemName: 'Mercurocroum' });

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [
        { text: 'Kestrel soigne ton Vestige de ' },
        { text: '10', colorClass: 'heal' },
        { text: ' PV (via Mercurocroum) — nettoie 1 stack de BURN' },
      ],
    });
  });

  it('leads with the cleanse when the heal restored nothing', () => {
    // Le scénario que D-21 veut rendre lisible : un Vestige à pleine vie dont
    // le soin ne restaure rien mais purge un stack. Annoncer « soigne de 0 PV »
    // masquerait le seul effet réel de l'action.
    const event: CombatEventDTO = {
      tick: 17,
      type: 'HEAL_RECEIVED',
      payload: {
        amount: 25,
        hpHealed: 0,
        poisonCleansed: 1,
        burnCleansed: 0,
        target: 'player_vestige',
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'mercurocroum',
      },
    };

    const resolve = () => ({ heroName: 'Kestrel', itemName: 'Mercurocroum' });

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [{ text: 'Kestrel nettoie ton Vestige (via Mercurocroum) — 1 stack de POISON' }],
    });
  });

  it('keeps the plain heal wording when nothing was healed and nothing cleansed', () => {
    // Cas inesthétique mais exact, antérieur à D-21 : rien n'a été restauré et
    // il n'y avait rien à nettoyer. Laissé tel quel, le corriger relèverait
    // d'un autre sujet.
    const event: CombatEventDTO = {
      tick: 18,
      type: 'HEAL_RECEIVED',
      payload: {
        amount: 25,
        hpHealed: 0,
        poisonCleansed: 0,
        burnCleansed: 0,
        target: 'player_vestige',
        targetSide: 'A',
        sourceSide: 'A',
        sourceItemId: 'mercurocroum',
      },
    };

    const resolve = () => ({ heroName: 'Kestrel', itemName: 'Mercurocroum' });

    const result = formatCombatEvent(event, resolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [
        { text: 'Kestrel soigne ton Vestige de ' },
        { text: '0', colorClass: 'heal' },
        { text: ' PV (via Mercurocroum)' },
      ],
    });
  });
  // --- Le coeur du commit : les libelles sont relatifs au spectateur --------
  //
  // Le journal ne dit plus qui est « le joueur ». Les deux tests qui suivent
  // rejouent le MEME evenement, octet pour octet, vus des deux cotes. C'est
  // la seule paire qui prouve que la traduction depend du spectateur et non
  // d'une convention « A, c'est moi » — convention exacte aujourd'hui, et
  // fausse des que l'attribution canonique arrivera.

  const mirrorEvent: CombatEventDTO = {
    tick: 20,
    type: 'DAMAGE_DEALT',
    payload: {
      amount: 15,
      shieldDamage: 0,
      hpDamage: 15,
      target: 'shadow_vestige',
      targetSide: 'B',
      sourceSide: 'A',
      sourceItemId: 'shadow_dagger',
    },
  };

  const mirrorResolve = (itemId: string, side: string) =>
    itemId === 'shadow_dagger' && side === 'A'
      ? { heroName: 'Kestrel', itemName: 'Shadow Dagger' }
      : null;

  it('reads a side-A action as the viewer own when the viewer is A', () => {
    const result = formatCombatEvent(mirrorEvent, mirrorResolve, 'A');

    expect(result).toEqual({
      sourceSide: 'SELF',
      segments: [
        { text: 'Kestrel inflige ' },
        { text: '15', colorClass: 'damage' },
        { text: ' dégâts au Vestige adverse (via Shadow Dagger)' },
      ],
    });
  });

  it('reads the very same side-A action as the enemy when the viewer is B', () => {
    const result = formatCombatEvent(mirrorEvent, mirrorResolve, 'B');

    expect(result).toEqual({
      sourceSide: 'ENEMY',
      segments: [
        { text: 'Kestrel inflige ' },
        { text: '15', colorClass: 'damage' },
        { text: ' dégâts à ton Vestige (via Shadow Dagger)' },
      ],
    });
  });
});
