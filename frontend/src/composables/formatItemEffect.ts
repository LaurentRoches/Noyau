// src/composables/formatItemEffect.ts
import type { ItemDTO, EffectDTO, ActionDTO } from '../api/types';

const TICKS_PER_SECOND = 10;

function ticksToSeconds(ticks: number): number {
  return ticks / TICKS_PER_SECOND;
}

function formatAction(action: ActionDTO): string {
  switch (action.type) {
    case 'DEAL_DAMAGE': {
      const targetLabel = action.target === 'SELF' ? 'à soi' : 'à l’ennemi';
      return `inflige ${action.value} dégâts ${targetLabel}`;
    }
    default:
      throw new Error(`Unsupported action type: ${action.type}`);
  }
}

function formatEffect(effect: EffectDTO, itemCooldownTicks: number): string {
  const actionsText = effect.actions.map(formatAction).join(', ');

  switch (effect.trigger) {
    case 'ON_ATTACK': {
      const seconds = ticksToSeconds(itemCooldownTicks);
      return `Vitesse d’attaque : ${seconds}s — ${actionsText}`;
    }
    default:
      throw new Error(`Unsupported trigger: ${effect.trigger}`);
  }
}

export function formatItemEffects(item: ItemDTO): string[] {
  return item.effects.map((effect) => formatEffect(effect, item.cooldownTicks));
}
