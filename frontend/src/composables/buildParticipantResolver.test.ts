// src/composables/buildParticipantResolver.test.ts
import { describe, it, expect } from 'vitest';
import { buildParticipantResolver } from './buildParticipantResolver';
import type { HeroDTO, AssignedItemDTO, OpponentAssignmentDTO, ItemDTO } from '../api/types';

function createItem(id: string, name: string): ItemDTO {
  return {
    id,
    name,
    rarity: 'COMMON',
    affinity: 'shadow',
    size: 'ONE_HAND',
    cooldownTicks: 4,
    effects: [],
  };
}

function createHero(id: string, name: string): HeroDTO {
  return { id, name, affinity: 'shadow', itemSlots: 6, skill: null };
}

describe('buildParticipantResolver', () => {
  it('resolves an item of the viewer own board, indexed under the viewer side', () => {
    const roster: HeroDTO[] = [createHero('player_hero_1', 'Kestrel')];
    const inventoryItems: AssignedItemDTO[] = [
      {
        inventoryIndex: 0,
        item: createItem('shadow_dagger', 'Shadow Dagger'),
        heroId: 'player_hero_1',
      },
    ];
    const opponentRoster: HeroDTO[] = [];
    const opponentInventoryItems: OpponentAssignmentDTO[] = [];

    const resolve = buildParticipantResolver(
      'A',
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    // Le journal dit « A », pas « PLAYER » : c'est au resolver de savoir que
    // A est le plateau du spectateur, parce qu'on le lui a dit.
    expect(resolve('shadow_dagger', 'A')).toEqual({
      heroName: 'Kestrel',
      itemName: 'Shadow Dagger',
    });
  });

  it('resolves an item of the other board, indexed under the other side', () => {
    const roster: HeroDTO[] = [];
    const inventoryItems: AssignedItemDTO[] = [];
    const opponentRoster: HeroDTO[] = [createHero('shadow_hero_1', 'Ravageur')];
    const opponentInventoryItems: OpponentAssignmentDTO[] = [
      { item: createItem('venom_fang', 'Venom Fang'), heroId: 'shadow_hero_1' },
    ];

    const resolve = buildParticipantResolver(
      'A',
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    expect(resolve('venom_fang', 'B')).toEqual({
      heroName: 'Ravageur',
      itemName: 'Venom Fang',
    });
  });

  /**
   * Le test qui fait tout le travail.
   *
   * Exactement les mêmes rosters, exactement les mêmes inventaires — seul le
   * côté attribué au spectateur change. Les clés d'indexation doivent suivre.
   * Sans lui, une implémentation qui écrirait `A:` en dur pour l'inventaire du
   * joueur passerait les deux tests précédents et serait fausse.
   */
  it('indexes the viewer own board under B when the viewer was assigned B', () => {
    const roster: HeroDTO[] = [createHero('player_hero_1', 'Kestrel')];
    const inventoryItems: AssignedItemDTO[] = [
      {
        inventoryIndex: 0,
        item: createItem('shadow_dagger', 'Shadow Dagger'),
        heroId: 'player_hero_1',
      },
    ];
    const opponentRoster: HeroDTO[] = [createHero('shadow_hero_1', 'Ravageur')];
    const opponentInventoryItems: OpponentAssignmentDTO[] = [
      { item: createItem('venom_fang', 'Venom Fang'), heroId: 'shadow_hero_1' },
    ];

    const resolve = buildParticipantResolver(
      'B',
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    expect(resolve('shadow_dagger', 'B')).toEqual({
      heroName: 'Kestrel',
      itemName: 'Shadow Dagger',
    });
    expect(resolve('venom_fang', 'A')).toEqual({
      heroName: 'Ravageur',
      itemName: 'Venom Fang',
    });

    // Et surtout : les mêmes objets cherchés du mauvais côté ne répondent pas.
    expect(resolve('shadow_dagger', 'A')).toBeNull();
    expect(resolve('venom_fang', 'B')).toBeNull();
  });

  it('returns null when the item id is not found for the given side', () => {
    const roster: HeroDTO[] = [createHero('player_hero_1', 'Kestrel')];
    const inventoryItems: AssignedItemDTO[] = [
      {
        inventoryIndex: 0,
        item: createItem('shadow_dagger', 'Shadow Dagger'),
        heroId: 'player_hero_1',
      },
    ];
    const opponentRoster: HeroDTO[] = [];
    const opponentInventoryItems: OpponentAssignmentDTO[] = [];

    const resolve = buildParticipantResolver(
      'A',
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    // shadow_dagger existe côté A, pas côté B.
    expect(resolve('shadow_dagger', 'B')).toBeNull();
  });
});
