// src/composables/formatItemEffect.test.ts
import { describe, it, expect } from 'vitest';
import { formatItemEffects } from './formatItemEffect';
import type { ItemDTO } from '../api/types';

describe('formatItemEffects', () => {
  it('formats a single ON_ATTACK effect dealing damage to the enemy', () => {
    const item: ItemDTO = {
      id: 'dagger',
      name: 'Dagger',
      rarity: 'COMMON',
      affinity: 'neutral',
      size: 'ONE_HAND',
      cooldownTicks: 20,
      effects: [
        {
          trigger: 'ON_ATTACK',
          intervalTicks: null,
          actions: [
            {
              type: 'DEAL_DAMAGE',
              value: 10,
              target: 'ENEMY',
              status: null,
              stacks: null,
              durationTicks: null,
            },
          ],
        },
      ],
    };

    const result = formatItemEffects(item);

    expect(result).toEqual(['Vitesse d’attaque : 2s — inflige 10 dégâts à l’ennemi']);
  });

  it('formats a single EVERY_N_TICKS effect gaining shield', () => {
    const item: ItemDTO = {
      id: 'shield',
      name: 'Shield',
      rarity: 'COMMON',
      affinity: 'neutral',
      size: 'ONE_HAND',
      cooldownTicks: 20,
      effects: [
        {
          trigger: 'EVERY_N_TICKS',
          intervalTicks: null,
          actions: [
            {
              type: 'GAIN_SHIELD',
              value: 10,
              target: 'SELF',
              status: null,
              stacks: null,
              durationTicks: null,
            },
          ],
        },
      ],
    };

    const result = formatItemEffects(item);

    expect(result).toEqual(['Toutes les 2s : gagne 10 bouclier']);
  });

  it('formats a single EVERY_N_TICKS effect healing self', () => {
    const item: ItemDTO = {
      id: 'cataplasm',
      name: 'Cataplasm',
      rarity: 'COMMON',
      affinity: 'neutral',
      size: 'ONE_HAND',
      cooldownTicks: 40,
      effects: [
        {
          trigger: 'EVERY_N_TICKS',
          intervalTicks: null,
          actions: [
            {
              type: 'HEAL',
              value: 25,
              target: 'SELF',
              status: null,
              stacks: null,
              durationTicks: null,
            },
          ],
        },
      ],
    };

    const result = formatItemEffects(item);

    expect(result).toEqual(['Toutes les 4s : soigne 25 PV']);
  });
});
