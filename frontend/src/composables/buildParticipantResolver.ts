// src/composables/buildParticipantResolver.ts
import type { HeroDTO, AssignedItemDTO, OpponentAssignmentDTO, Side } from '../api/types';
import type { ParticipantResolver } from './formatCombatEvent';

/**
 * Indexe les objets des deux plateaux par côté, pour qu'un `sourceItemId` lu
 * dans le journal retrouve son héros et son nom.
 *
 * **Pourquoi `viewerSide` est le premier paramètre.** Le journal ne dit plus
 * `PLAYER` ni `OPPONENT` : il dit A ou B (D-19). Cette fonction reçoit d'un
 * côté l'inventaire du spectateur et de l'autre celui de son adversaire —
 * elle doit donc qu'on lui dise lequel des deux côtés est le spectateur, sans
 * quoi elle n'a aucun moyen de construire ses clés.
 *
 * Écrire `A:` en dur pour l'inventaire du joueur marcherait aujourd'hui, où
 * l'attribution est positionnelle et où le joueur est toujours A. Le jour où
 * l'attribution devient canonique, cette version-là afficherait les noms de
 * l'adversaire sur les actions du joueur, sans erreur ni test rouge.
 */
export function buildParticipantResolver(
  viewerSide: Side,
  roster: HeroDTO[],
  inventoryItems: AssignedItemDTO[],
  opponentRoster: HeroDTO[],
  opponentInventoryItems: OpponentAssignmentDTO[],
): ParticipantResolver {
  const enemySide: Side = viewerSide === 'A' ? 'B' : 'A';

  const heroNamesById = new Map<string, string>();
  for (const hero of [...roster, ...opponentRoster]) {
    heroNamesById.set(hero.id, hero.name);
  }

  const itemLookup = new Map<string, { itemName: string; heroId: string }>();
  for (const assigned of inventoryItems) {
    itemLookup.set(`${viewerSide}:${assigned.item.id}`, {
      itemName: assigned.item.name,
      heroId: assigned.heroId,
    });
  }
  for (const assigned of opponentInventoryItems) {
    itemLookup.set(`${enemySide}:${assigned.item.id}`, {
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
