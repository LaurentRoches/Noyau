<!-- src/components/shop/ShopView.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { formatItemEffects } from '../../composables/formatItemEffect';

const store = useGameRunStore();

const offers = computed(() => store.state?.shop?.offers ?? []);
const walletBalance = computed(() => store.state?.wallet.balance ?? 0);

function canAfford(price: number, purchased: boolean): boolean {
  return !purchased && price <= walletBalance.value;
}
</script>

<template>
  <div v-if="store.state?.shop === null">Aucune boutique ouverte pour ce round.</div>

  <ul v-else class="shop-offers">
    <li
      v-for="offer in offers"
      :key="offer.slotIndex"
      class="shop-offer"
      :class="{ 'shop-offer--purchased': offer.purchased }"
    >
      <strong>{{ offer.item.name }}</strong>
      ({{ offer.item.rarity }}, {{ offer.item.affinity }}) — {{ offer.price }} or

      <ul>
        <li v-for="(line, index) in formatItemEffects(offer.item)" :key="index">{{ line }}</li>
      </ul>

      <button
        :disabled="!canAfford(offer.price, offer.purchased)"
        @click="store.buyItem(offer.slotIndex)"
      >
        {{ offer.purchased ? 'Déjà acheté' : 'Acheter' }}
      </button>
    </li>
  </ul>
</template>

<style scoped>
.shop-offer--purchased {
  opacity: 0.5;
}
</style>
