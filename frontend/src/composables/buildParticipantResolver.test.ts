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
  it('resolves a player-side item to its hero and item name', () => {
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
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    const result = resolve('shadow_dagger', 'PLAYER');

    expect(result).toEqual({ heroName: 'Kestrel', itemName: 'Shadow Dagger' });
  });

  it('resolves an opponent-side item to its hero and item name', () => {
    const roster: HeroDTO[] = [];
    const inventoryItems: AssignedItemDTO[] = [];
    const opponentRoster: HeroDTO[] = [createHero('shadow_hero_1', 'Ravageur')];
    const opponentInventoryItems: OpponentAssignmentDTO[] = [
      { item: createItem('venom_fang', 'Venom Fang'), heroId: 'shadow_hero_1' },
    ];

    const resolve = buildParticipantResolver(
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    const result = resolve('venom_fang', 'OPPONENT');

    expect(result).toEqual({ heroName: 'Ravageur', itemName: 'Venom Fang' });
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
      roster,
      inventoryItems,
      opponentRoster,
      opponentInventoryItems,
    );

    // Le side ne correspond pas — shadow_dagger existe côté PLAYER, pas côté OPPONENT
    const result = resolve('shadow_dagger', 'OPPONENT');

    expect(result).toBeNull();
  });
});
