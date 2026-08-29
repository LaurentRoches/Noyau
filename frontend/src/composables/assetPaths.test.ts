// src/composables/assetPaths.test.ts
import { describe, it, expect } from 'vitest';
import {
  heroPortraitUrl,
  heroFrameUrl,
  itemImageUrl,
  itemFrameUrl,
  vestigeVideoUrl,
  vestigePosterUrl,
  vestigeFrameUrl,
  stashImageUrl,
} from './assetPaths';

describe('heroPortraitUrl', () => {
  it('builds the portrait path from the hero id', () => {
    expect(heroPortraitUrl('shadow_bearer')).toBe('/assets/heroes/shadow_bearer.jpg');
  });

  describe('heroFrameUrl', () => {
    it('builds the frame path from the affinity', () => {
      expect(heroFrameUrl('shadow')).toBe('/assets/heroes/frames/shadow.png');
      expect(heroFrameUrl('neutral')).toBe('/assets/heroes/frames/neutral.png');
    });
  });

  describe('itemImageUrl', () => {
    it('builds the illustration path from the item id', () => {
      expect(itemImageUrl('venom_fang')).toBe('/assets/items/venom_fang.jpg');
    });
  });

  describe('itemFrameUrl', () => {
    it('returns the single shared item frame path', () => {
      expect(itemFrameUrl()).toBe('/assets/items/frame.png');
    });
  });

  describe('vestigeVideoUrl', () => {
    it('builds the video path from the vestige id', () => {
      expect(vestigeVideoUrl('shadow_vestige')).toBe('/assets/vestiges/shadow_vestige.mp4');
    });
  });

  describe('vestigePosterUrl', () => {
    it('builds the poster path from the vestige id', () => {
      expect(vestigePosterUrl('shadow_vestige')).toBe('/assets/vestiges/shadow_vestige_poster.jpg');
    });
  });

  describe('vestigeFrameUrl', () => {
    it('builds the frame path from the affinity', () => {
      expect(vestigeFrameUrl('shadow')).toBe('/assets/vestiges/frames/shadow.png');
    });
  });

  describe('stashImageUrl', () => {
    it('returns close.jpg when the stash is empty', () => {
      expect(stashImageUrl(true)).toBe('/assets/stash/close.jpg');
    });

    it('returns open.jpg when the stash is not empty', () => {
      expect(stashImageUrl(false)).toBe('/assets/stash/open.jpg');
    });
  });
});
