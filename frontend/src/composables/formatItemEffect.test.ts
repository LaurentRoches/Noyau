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
});
