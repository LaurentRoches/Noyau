// src/composables/formatCombatEvent.ts
import type { CombatEventDTO, Side, ViewerRelativeSide } from '../api/types';

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
  /**
   * `null` pour les événements sans source — statuts, fureur —, qui
   * n'appartiennent à personne.
   */
  sourceSide: ViewerRelativeSide | null;
}

/**
 * Traduit un côté du journal en côté du spectateur.
 *
 * Tout le commit tient dans cette fonction : le journal dit A ou B, le lecteur
 * veut savoir si c'est lui. Personne d'autre dans le frontend n'a besoin de
 * connaître A et B.
 */
function relativeTo(viewerSide: Side, side: Side): ViewerRelativeSide {
  return side === viewerSide ? 'SELF' : 'ENEMY';
}

function targetLabel(viewerSide: Side, side: Side): string {
  return relativeTo(viewerSide, side) === 'SELF' ? 'ton Vestige' : 'le Vestige adverse';
}

function targetLabelWithPreposition(viewerSide: Side, side: Side): string {
  return relativeTo(viewerSide, side) === 'SELF' ? 'à ton Vestige' : 'au Vestige adverse';
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

/**
 * La brûlure est le seul effet à deux taux : 150 % sur le bouclier, puis 70 %
 * du surplus sur les PV (02 §7.4). La ventilation ne somme donc jamais au
 * montant annoncé — l'écart est l'atténuation, et le libellé le dit.
 *
 * Les deux divisions entières arrondissent au plancher, si bien qu'un montant
 * majoré faible peut ne rien infliger du tout.
 */
function formatBurnBreakdownText(shieldDamage: number, hpDamage: number): string {
  if (shieldDamage === 0 && hpDamage === 0) {
    return ' — sans effet après atténuation';
  }
  if (hpDamage === 0) {
    return ' — entièrement absorbés par le bouclier';
  }
  if (shieldDamage === 0) {
    return ` — ${hpDamage} aux PV après atténuation`;
  }
  return ` — ${shieldDamage} absorbés par le bouclier, ${hpDamage} aux PV après atténuation`;
}

/**
 * Liste des stacks retirés par le nettoyage du soin (D-21).
 *
 * Le premier statut cité porte le mot « stack », les suivants s'en dispensent :
 * « 1 stack de POISON et 1 de BURN ». L'accord est calculé bien que D-21 ne
 * retire qu'un stack par type et par déclenchement, pour que le libellé
 * survive à un changement de cette valeur.
 *
 * Renvoie une chaîne vide quand rien n'a été nettoyé, ce qui laisse la ligne
 * de soin exactement dans son état antérieur à D-21.
 */
function formatCleansedList(poisonCleansed: number, burnCleansed: number): string {
  const cleansed: Array<[number, string]> = [];
  if (poisonCleansed > 0) {
    cleansed.push([poisonCleansed, 'POISON']);
  }
  if (burnCleansed > 0) {
    cleansed.push([burnCleansed, 'BURN']);
  }

  return cleansed
    .map(([count, status], index) =>
      index === 0 ? `${count} stack${count > 1 ? 's' : ''} de ${status}` : `${count} de ${status}`,
    )
    .join(' et ');
}

function formatSourcedEvent(
  resolve: ParticipantResolver,
  viewerSide: Side,
  sourceSide: Side,
  sourceItemId: string,
  buildSegments: (heroName: string, itemName: string) => CombatEventSegment[],
): CombatEventDisplay {
  // Le resolver, lui, travaille en A/B : il indexe des inventaires, pas un
  // point de vue. C'est buildParticipantResolver qui sait lequel est le nôtre.
  const participant = resolve(sourceItemId, sourceSide);
  const heroName = participant?.heroName ?? 'Un héros inconnu';
  const itemName = participant?.itemName ?? sourceItemId;

  return {
    sourceSide: relativeTo(viewerSide, sourceSide),
    segments: buildSegments(heroName, itemName),
  };
}

export function formatCombatEvent(
  event: CombatEventDTO,
  resolve: ParticipantResolver,
  viewerSide: Side,
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

      return formatSourcedEvent(
        resolve,
        viewerSide,
        sourceSide,
        sourceItemId,
        (heroName, itemName) => [
          { text: `${heroName} inflige ` },
          { text: `${amount}`, colorClass: 'damage' },
          {
            text: ` dégâts ${targetLabelWithPreposition(viewerSide, targetSide)} (via ${itemName})${formatDamageBreakdownText(shieldDamage, hpDamage)}`,
          },
        ],
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
        viewerSide,
        sourceSide,
        sourceItemId,
        (heroName, itemName) => [
          { text: `${heroName} donne ` },
          { text: `${amount}`, colorClass: 'shield' },
          {
            text: ` bouclier ${targetLabelWithPreposition(viewerSide, targetSide)} (via ${itemName})`,
          },
        ],
      );
    }
    case 'HEAL_RECEIVED': {
      const {
        hpHealed,
        poisonCleansed = 0,
        burnCleansed = 0,
        targetSide,
        sourceSide,
        sourceItemId,
      } = event.payload as {
        hpHealed: number;
        poisonCleansed?: number;
        burnCleansed?: number;
        targetSide: Side;
        sourceSide: Side;
        sourceItemId: string;
      };

      const cleansedList = formatCleansedList(poisonCleansed, burnCleansed);

      // Amorce distincte quand le soin ne restaure rien mais nettoie : c'est
      // exactement le scénario que D-21 veut rendre lisible, un Vestige à
      // pleine vie qui se purge. Annoncer « soigne de 0 PV » y masquerait le
      // seul effet réel de l'action.
      if (hpHealed === 0 && cleansedList !== '') {
        return formatSourcedEvent(
          resolve,
          viewerSide,
          sourceSide,
          sourceItemId,
          (heroName, itemName) => [
            {
              text: `${heroName} nettoie ${targetLabel(viewerSide, targetSide)} (via ${itemName}) — ${cleansedList}`,
            },
          ],
        );
      }

      const cleanseSuffix = cleansedList === '' ? '' : ` — nettoie ${cleansedList}`;

      return formatSourcedEvent(
        resolve,
        viewerSide,
        sourceSide,
        sourceItemId,
        (heroName, itemName) => [
          { text: `${heroName} soigne ${targetLabel(viewerSide, targetSide)} de ` },
          { text: `${hpHealed}`, colorClass: 'heal' },
          { text: ` PV (via ${itemName})${cleanseSuffix}` },
        ],
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
        viewerSide,
        sourceSide,
        sourceItemId,
        (heroName, itemName) => [
          { text: `${heroName} applique ` },
          { text: `${stacksApplied}`, colorClass: statusApplyColor(status) },
          {
            text: ` stack(s) de ${status} ${targetLabelWithPreposition(viewerSide, targetSide)} (via ${itemName})`,
          },
        ],
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

      // Pour la brûlure, `amount` porte la valeur majorée à 150 %, pas les
      // stacks : `remainingStacks` continue de les porter.
      const burnSuffix = ` de brûlure ${targetLabelWithPreposition(viewerSide, targetSide)}${formatBurnBreakdownText(shieldDamage, hpDamage)}`;
      const plainSuffix = ` dégâts ${targetLabelWithPreposition(viewerSide, targetSide)}${formatDamageBreakdownText(shieldDamage, hpDamage)}`;

      return {
        sourceSide: null,
        segments: [
          { text: `${status} inflige ` },
          { text: `${amount}`, colorClass: statusDamageColor(status) },
          { text: status === 'BURN' ? burnSuffix : plainSuffix },
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
          { text: `${status} soigne ${targetLabel(viewerSide, targetSide)} de ` },
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
          { text: ` bouclier ${targetLabelWithPreposition(viewerSide, targetSide)}` },
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
        segments: [{ text: `${status} se dissipe sur ${targetLabel(viewerSide, targetSide)}` }],
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
            text: ` dégâts ${targetLabelWithPreposition(viewerSide, targetSide)}${formatDamageBreakdownText(shieldDamage, hpDamage)}`,
          },
        ],
      };
    }
    case 'RESOLUTION_TIEBREAK': {
      const { decidedBy, valueA, valueB, winnerSide } = event.payload as {
        criterion: string;
        decidedBy: 'COMPARISON' | 'RANDOM';
        valueA: number;
        valueB: number;
        winnerSide: Side;
      };

      const outcome =
        relativeTo(viewerSide, winnerSide) === 'SELF'
          ? "tu l'emportes"
          : "ton adversaire l'emporte";

      // `criterion` n'est volontairement pas affiché. Le champ sert au rejeu
      // et au diagnostic ; l'afficher obligerait à écrire dès maintenant une
      // branche pour un critère que le moteur n'émet pas encore.
      if (decidedBy === 'RANDOM') {
        return {
          sourceSide: null,
          segments: [{ text: `Départage au tirage : ${outcome} (égalité stricte à ${valueA}).` }],
        };
      }

      // Les deux valeurs sont réordonnées pour que le joueur lise toujours la
      // sienne en premier. Le journal les donne par côté, pas dans un ordre de
      // lecture — c'est au client de choisir celui qui a du sens pour lui.
      const viewerValue = viewerSide === 'A' ? valueA : valueB;
      const enemyValue = viewerSide === 'A' ? valueB : valueA;

      return {
        sourceSide: null,
        segments: [{ text: `Départage : ${outcome}, ${viewerValue} contre ${enemyValue}.` }],
      };
    }
    default:
      throw new Error(`Unsupported event type: ${event.type}`);
  }
}
