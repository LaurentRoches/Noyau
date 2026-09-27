<!-- src/components/hero/HeroOfferPanel.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useGameRunStore } from '../../stores/gameRun';
import { heroPortraitUrl, heroFrameUrl } from '../../composables/assetPaths';

const store = useGameRunStore();
const isChoosingHero = ref(false);

const candidates = computed(() => store.state?.pendingHeroOffer ?? []);

async function choose(heroId: string): Promise<void> {
  if (isChoosingHero.value) {
    return;
  }

  isChoosingHero.value = true;
  try {
    await store.chooseHero(heroId);
  } finally {
    isChoosingHero.value = false;
  }
}
</script>

<template>
  <div class="hero-offer">
    <h2 class="hero-offer__title">Choisissez un héros</h2>

    <ul class="hero-offer__list">
      <li v-for="hero in candidates" :key="hero.id">
        <button
          type="button"
          class="hero-offer__card"
          :disabled="isChoosingHero"
          @click="choose(hero.id)"
        >
          <div class="hero-offer__art">
            <img class="hero-offer__portrait" :src="heroPortraitUrl(hero.id)" :alt="hero.name" />
            <img
              class="hero-offer__frame"
              :src="heroFrameUrl(hero.affinity)"
              alt=""
              aria-hidden="true"
            />
          </div>

          <strong class="hero-offer__name">{{ hero.name }}</strong>
          <span class="hero-offer__affinity">{{ hero.affinity }}</span>
          <span class="hero-offer__skill">{{ hero.skill ?? 'Aucune compétence' }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.hero-offer {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
  padding: 24px;
}
.hero-offer__title {
  font-size: 20px;
  color: var(--bone);
  margin: 0;
}
.hero-offer__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  gap: 16px;
}
.hero-offer__card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 180px;
  padding: 12px;
  background: var(--shadow);
  border: 1px solid var(--shadow-border);
  border-radius: 4px;
  cursor: pointer;
}
.hero-offer__card:hover:not(:disabled) {
  border-color: var(--rare);
}
.hero-offer__card:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.hero-offer__art {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 5;
  border-radius: 4px;
  margin-bottom: 6px;
}
.hero-offer__portrait {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 4px;
}
.hero-offer__frame {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
.hero-offer__name {
  font-size: 14px;
  color: var(--bone);
}
.hero-offer__affinity {
  font-size: 11px;
  color: var(--mist);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.hero-offer__skill {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--mist);
}
</style>
