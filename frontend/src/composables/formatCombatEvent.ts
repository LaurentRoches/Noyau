// src/composables/formatCombatEvent.ts
import type { CombatEventDTO, Side } from '../api/types';

export type ParticipantResolver = (
  itemId: string,
  side: Side,
) => { heroName: string; itemName: string } | null;

function targetLabel(side: Side): string {
  return side === 'PLAYER' ? 'à ton Vestige' : 'au Vestige adverse';
}

export function formatCombatEvent(event: CombatEventDTO, resolve: ParticipantResolver): string {
  switch (event.type) {
    case 'DAMAGE_DEALT': {
      const { amount, targetSide, sourceSide, sourceItemId } = event.payload as {
        amount: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };
      const participant = resolve(sourceItemId, sourceSide);
      const heroName = participant?.heroName ?? 'Un héros inconnu';
      const itemName = participant?.itemName ?? sourceItemId;

      return `${heroName} inflige ${amount} dégâts ${targetLabel(targetSide)} (via ${itemName})`;
    }
    case 'SHIELD_GAINED': {
      const { amount, targetSide, sourceSide, sourceItemId } = event.payload as {
        amount: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };
      const participant = resolve(sourceItemId, sourceSide);
      const heroName = participant?.heroName ?? 'Un héros inconnu';
      const itemName = participant?.itemName ?? sourceItemId;

      return `${heroName} donne ${amount} bouclier ${targetLabel(targetSide)} (via ${itemName})`;
    }
    default:
      throw new Error(`Unsupported event type: ${event.type}`);
  }
}
