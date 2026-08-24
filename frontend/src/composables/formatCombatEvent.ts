// src/composables/formatCombatEvent.ts
import type { CombatEventDTO, Side } from '../api/types';

export type ParticipantResolver = (
  itemId: string,
  side: Side,
) => { heroName: string; itemName: string } | null;

function targetLabel(side: Side): string {
  return side === 'PLAYER' ? 'ton Vestige' : 'le Vestige adverse';
}

function targetLabelWithPreposition(side: Side): string {
  return side === 'PLAYER' ? 'à ton Vestige' : 'au Vestige adverse';
}

function formatSourcedEvent(
  resolve: ParticipantResolver,
  sourceSide: Side,
  sourceItemId: string,
  phrase: (heroName: string, itemName: string) => string,
): string {
  const participant = resolve(sourceItemId, sourceSide);
  const heroName = participant?.heroName ?? 'Un héros inconnu';
  const itemName = participant?.itemName ?? sourceItemId;

  return phrase(heroName, itemName);
}

function formatDamageBreakdown(shieldDamage: number, hpDamage: number): string {
  if (shieldDamage === 0) {
    return '';
  }
  if (hpDamage === 0) {
    return ' — entièrement absorbés par le bouclier';
  }
  return ` — ${shieldDamage} absorbés par le bouclier, ${hpDamage} aux PV`;
}

export function formatCombatEvent(event: CombatEventDTO, resolve: ParticipantResolver): string {
  switch (event.type) {
    case 'DAMAGE_DEALT': {
      const { amount, shieldDamage, hpDamage, targetSide, sourceSide, sourceItemId } =
        event.payload as {
          amount: number;
          shieldDamage: number;
          hpDamage: number;
          targetSide: Side;
          sourceSide: Side;
          sourceItemId: string;
        };

      return formatSourcedEvent(
        resolve,
        sourceSide,
        sourceItemId,
        (heroName, itemName) =>
          `${heroName} inflige ${amount} dégâts ${targetLabelWithPreposition(targetSide)} (via ${itemName})${formatDamageBreakdown(shieldDamage, hpDamage)}`,
      );
    }
    case 'SHIELD_GAINED': {
      const { amount, targetSide, sourceSide, sourceItemId } = event.payload as {
        amount: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(
        resolve,
        sourceSide,
        sourceItemId,
        (heroName, itemName) =>
          `${heroName} donne ${amount} bouclier ${targetLabelWithPreposition(targetSide)} (via ${itemName})`,
      );
    }
    case 'HEAL_RECEIVED': {
      const { hpHealed, targetSide, sourceSide, sourceItemId } = event.payload as {
        hpHealed: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(
        resolve,
        sourceSide,
        sourceItemId,
        (heroName, itemName) =>
          `${heroName} soigne ${targetLabel(targetSide)} de ${hpHealed} PV (via ${itemName})`,
      );
    }
    case 'STATUS_APPLIED': {
      const { status, stacksApplied, targetSide, sourceSide, sourceItemId } = event.payload as {
        status: string;
        stacksApplied: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(
        resolve,
        sourceSide,
        sourceItemId,
        (heroName, itemName) =>
          `${heroName} applique ${stacksApplied} stack(s) de ${status} ${targetLabelWithPreposition(targetSide)} (via ${itemName})`,
      );
    }
    case 'STATUS_DAMAGE_DEALT': {
      const { status, amount, shieldDamage, hpDamage, targetSide } = event.payload as {
        status: string;
        amount: number;
        shieldDamage: number;
        hpDamage: number;
        targetSide: Side;
      };

      return `${status} inflige ${amount} dégâts ${targetLabelWithPreposition(targetSide)}${formatDamageBreakdown(shieldDamage, hpDamage)}`;
    }
    default:
      throw new Error(`Unsupported event type: ${event.type}`);
  }
}
