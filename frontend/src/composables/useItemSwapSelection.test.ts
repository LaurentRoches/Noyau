// src/composables/useItemSwapSelection.test.ts
import { describe, it, expect, beforeEach } from 'vitest';
import { useItemSwapSelection } from './useItemSwapSelection';

describe('useItemSwapSelection', () => {
  beforeEach(() => {
    // État module-level partagé entre tous les appelants : on le remet à
    // zéro avant chaque test pour éviter toute fuite d'un test à l'autre.
    const { clear } = useItemSwapSelection();
    clear();
  });

  it('has no selection initially', () => {
    const { selection } = useItemSwapSelection();

    expect(selection.value).toBeNull();
  });

  it('selects an item', () => {
    const { selection, select } = useItemSwapSelection();

    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });

    expect(selection.value).toEqual({
      inventoryIndex: 2,
      heroId: 'shadow_bearer',
      itemName: 'Dagger',
    });
  });

  it('deselects when the same slot is selected again', () => {
    const { selection, select } = useItemSwapSelection();

    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });
    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });

    expect(selection.value).toBeNull();
  });

  it('replaces the selection when a different slot is selected', () => {
    const { selection, select } = useItemSwapSelection();

    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });
    select({ inventoryIndex: 5, heroId: 'shadow_arrow', itemName: 'Buckler' });

    expect(selection.value).toEqual({
      inventoryIndex: 5,
      heroId: 'shadow_arrow',
      itemName: 'Buckler',
    });
  });

  it('treats the same inventoryIndex under a different heroId as a different slot', () => {
    const { selection, select } = useItemSwapSelection();

    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });
    select({ inventoryIndex: 2, heroId: 'shadow_arrow', itemName: 'Dagger' });

    // Ne doit pas être interprété comme un toggle : le heroId diffère, donc
    // c'est un nouveau choix, pas une désélection.
    expect(selection.value).toEqual({
      inventoryIndex: 2,
      heroId: 'shadow_arrow',
      itemName: 'Dagger',
    });
  });

  it('clears the selection', () => {
    const { selection, select, clear } = useItemSwapSelection();

    select({ inventoryIndex: 2, heroId: 'shadow_bearer', itemName: 'Dagger' });
    clear();

    expect(selection.value).toBeNull();
  });
});
