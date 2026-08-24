// src/composables/buildParticipantResolver.ts
import type { HeroDTO, AssignedItemDTO, OpponentAssignmentDTO, Side } from '../api/types';
import type { ParticipantResolver } from './formatCombatEvent';

export function buildParticipantResolver(
  roster: HeroDTO[],
  inventoryItems: AssignedItemDTO[],
  opponentRoster: HeroDTO[],
  opponentInventoryItems: OpponentAssignmentDTO[],
): ParticipantResolver {
  const heroNamesById = new Map<string, string>();
  for (const hero of [...roster, ...opponentRoster]) {
    heroNamesById.set(hero.id, hero.name);
  }

  const itemLookup = new Map<string, { itemName: string; heroId: string }>();
  for (const assigned of inventoryItems) {
    itemLookup.set(`PLAYER:${assigned.item.id}`, {
      itemName: assigned.item.name,
      heroId: assigned.heroId,
    });
  }
  for (const assigned of opponentInventoryItems) {
    itemLookup.set(`OPPONENT:${assigned.item.id}`, {
      itemName: assigned.item.name,
      heroId: assigned.heroId,
    });
  }

  return (itemId: string, side: Side) => {
    const entry = itemLookup.get(`${side}:${itemId}`);
    if (entry === undefined) {
      return null;
    }
    const heroName = heroNamesById.get(entry.heroId) ?? entry.heroId;
    return { heroName, itemName: entry.itemName };
  };
}
