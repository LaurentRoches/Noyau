// src/composables/formatCombatEvent.ts
import type { CombatEventDTO, Side } from '../api/types';

export type ParticipantResolver = (
  itemId: string,
  side: Side,
) => { heroName: string; itemName: string } | null;

export type ValueColor = 'damage' | 'poison' | 'burn' | 'shield' | 'heal';

export interface CombatEventSegment {
  text: string;
  colorClass?: ValueColor;
}

export interface CombatEventDisplay {
  segments: CombatEventSegment[];
  sourceSide: Side | null;
}

function targetLabel(side: Side): string {
  return side === 'PLAYER' ? 'ton Vestige' : 'le Vestige adverse';
}

function targetLabelWithPreposition(side: Side): string {
  return side === 'PLAYER' ? 'à ton Vestige' : 'au Vestige adverse';
}

function statusDamageColor(status: string): ValueColor {
  return status === 'POISON' ? 'poison' : 'burn';
}

function statusApplyColor(status: string): ValueColor {
  switch (status) {
    case 'POISON':
      return 'poison';
    case 'BURN':
      return 'burn';
    case 'REGEN':
      return 'heal';
    case 'WARD':
      return 'shield';
    default:
      return 'damage';
  }
}

function formatDamageBreakdownText(shieldDamage: number, hpDamage: number): string {
  if (shieldDamage === 0) {
    return '';
  }
  if (hpDamage === 0) {
    return ' — entièrement absorbés par le bouclier';
  }
  return ` — ${shieldDamage} absorbés par le bouclier, ${hpDamage} aux PV`;
}

function formatSourcedEvent(
  resolve: ParticipantResolver,
  sourceSide: Side,
  sourceItemId: string,
  buildSegments: (heroName: string, itemName: string) => CombatEventSegment[],
): CombatEventDisplay {
  const participant = resolve(sourceItemId, sourceSide);
  const heroName = participant?.heroName ?? 'Un héros inconnu';
  const itemName = participant?.itemName ?? sourceItemId;

  return {
    sourceSide,
    segments: buildSegments(heroName, itemName),
  };
}

export function formatCombatEvent(
  event: CombatEventDTO,
  resolve: ParticipantResolver,
): CombatEventDisplay {
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

      return formatSourcedEvent(resolve, sourceSide, sourceItemId, (heroName, itemName) => [
        { text: `${heroName} inflige ` },
        { text: `${amount}`, colorClass: 'damage' },
        {
          text: ` dégâts ${targetLabelWithPreposition(targetSide)} (via ${itemName})${formatDamageBreakdownText(shieldDamage, hpDamage)}`,
        },
      ]);
    }
    case 'SHIELD_GAINED': {
      const { amount, targetSide, sourceSide, sourceItemId } = event.payload as {
        amount: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(resolve, sourceSide, sourceItemId, (heroName, itemName) => [
        { text: `${heroName} donne ` },
        { text: `${amount}`, colorClass: 'shield' },
        { text: ` bouclier ${targetLabelWithPreposition(targetSide)} (via ${itemName})` },
      ]);
    }
    case 'HEAL_RECEIVED': {
      const { hpHealed, targetSide, sourceSide, sourceItemId } = event.payload as {
        hpHealed: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(resolve, sourceSide, sourceItemId, (heroName, itemName) => [
        { text: `${heroName} soigne ${targetLabel(targetSide)} de ` },
        { text: `${hpHealed}`, colorClass: 'heal' },
        { text: ` PV (via ${itemName})` },
      ]);
    }
    case 'STATUS_APPLIED': {
      const { status, stacksApplied, targetSide, sourceSide, sourceItemId } = event.payload as {
        status: string;
        stacksApplied: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      return formatSourcedEvent(resolve, sourceSide, sourceItemId, (heroName, itemName) => [
        { text: `${heroName} applique ` },
        { text: `${stacksApplied}`, colorClass: statusApplyColor(status) },
        {
          text: ` stack(s) de ${status} ${targetLabelWithPreposition(targetSide)} (via ${itemName})`,
        },
      ]);
    }
    case 'STATUS_DAMAGE_DEALT': {
      const { status, amount, shieldDamage, hpDamage, targetSide } = event.payload as {
        status: string;
        amount: number;
        shieldDamage: number;
        hpDamage: number;
        targetSide: Side;
      };

      return {
        sourceSide: null,
        segments: [
          { text: `${status} inflige ` },
          { text: `${amount}`, colorClass: statusDamageColor(status) },
          {
            text: ` dégâts ${targetLabelWithPreposition(targetSide)}${formatDamageBreakdownText(shieldDamage, hpDamage)}`,
          },
        ],
      };
    }
    case 'STATUS_HEAL_RECEIVED': {
      const { status, hpHealed, targetSide } = event.payload as {
        status: string;
        hpHealed: number;
        targetSide: Side;
      };

      return {
        sourceSide: null,
        segments: [
          { text: `${status} soigne ${targetLabel(targetSide)} de ` },
          { text: `${hpHealed}`, colorClass: 'heal' },
          { text: ' PV' },
        ],
      };
    }
    case 'STATUS_SHIELD_GAINED': {
      const { status, amount, targetSide } = event.payload as {
        status: string;
        amount: number;
        targetSide: Side;
      };

      return {
        sourceSide: null,
        segments: [
          { text: `${status} donne ` },
          { text: `${amount}`, colorClass: 'shield' },
          { text: ` bouclier ${targetLabelWithPreposition(targetSide)}` },
        ],
      };
    }
    case 'STATUS_EXPIRED': {
      const { status, targetSide } = event.payload as {
        status: string;
        targetSide: Side;
      };

      return {
        sourceSide: null,
        segments: [{ text: `${status} se dissipe sur ${targetLabel(targetSide)}` }],
      };
    }
    case 'ENRAGE_DAMAGE_DEALT': {
      const { amount, shieldDamage, hpDamage, targetSide } = event.payload as {
        amount: number;
        shieldDamage: number;
        hpDamage: number;
        targetSide: Side;
      };

      return {
        sourceSide: null,
        segments: [
          { text: 'La fureur inflige ' },
          { text: `${amount}`, colorClass: 'damage' },
          {
            text: ` dégâts ${targetLabelWithPreposition(targetSide)}${formatDamageBreakdownText(shieldDamage, hpDamage)}`,
          },
        ],
      };
    }
    default:
      throw new Error(`Unsupported event type: ${event.type}`);
  }
}