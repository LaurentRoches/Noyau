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
});
