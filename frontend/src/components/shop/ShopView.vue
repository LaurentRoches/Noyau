<!-- src/components/shop/ShopView.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { formatItemEffects } from '../../composables/formatItemEffect';
import { itemImageUrl, itemFrameUrl } from '../../composables/assetPaths';

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
      <div class="shop-offer__art" :class="`shop-offer__art--${offer.item.rarity.toLowerCase()}`">
        <img
          class="shop-offer__illustration"
          :src="itemImageUrl(offer.item.id)"
          :alt="offer.item.name"
        />
        <img class="shop-offer__frame" :src="itemFrameUrl()" alt="" aria-hidden="true" />
      </div>

      <div class="shop-offer__info">
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
      </div>
    </li>
  </ul>
</template>

<style scoped>
.shop-offers {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.shop-offer {
  display: flex;
  gap: 12px;
}
.shop-offer--purchased {
  opacity: 0.5;
}
.shop-offer__art {
  position: relative;
  width: 96px;
  height: 96px;
  flex-shrink: 0;
  border-radius: 6px;
}
.shop-offer__illustration {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
}
.shop-offer__frame {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
.shop-offer__art--common {
  box-shadow: 0 0 0 2px var(--common);
}
.shop-offer__art--rare {
  box-shadow: 0 0 8px 2px var(--rare);
}
.shop-offer__art--legendary {
  box-shadow: 0 0 12px 3px var(--legendary);
}
.shop-offer__info {
  flex: 1;
  min-width: 0;
}
</style>
