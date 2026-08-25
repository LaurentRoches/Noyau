// src/composables/useItemSwapSelection.ts
import { ref } from 'vue';

export interface SwapSelection {
  inventoryIndex: number;
  heroId: string;
  itemName: string;
}

// État module-level volontaire : c'est de l'UI transitoire partagée entre
// deux composants frères (HeroRosterPanel / StashPanel), pas de l'état de
// jeu — ne mérite pas de vivre dans le store Pinia.
const selection = ref<SwapSelection | null>(null);

export function useItemSwapSelection() {
  function select(next: SwapSelection): void {
    // Cliquer à nouveau sur le même objet déjà sélectionné le désélectionne.
    if (
      selection.value?.inventoryIndex === next.inventoryIndex &&
      selection.value?.heroId === next.heroId
    ) {
      selection.value = null;
      return;
    }
    selection.value = next;
  }

  function clear(): void {
    selection.value = null;
  }

  return { selection, select, clear };
}
