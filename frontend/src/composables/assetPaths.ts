export function heroPortraitUrl(heroId: string): string {
  return `/assets/heroes/${heroId}.jpg`;
}

export function heroFrameUrl(affinity: string): string {
  return `/assets/heroes/frames/${affinity}.png`;
}

export function itemImageUrl(itemId: string): string {
  return `/assets/items/${itemId}.jpg`;
}

export function itemFrameUrl(): string {
  return '/assets/items/frame.png';
}

export function vestigeVideoUrl(vestigeId: string): string {
  return `/assets/vestiges/${vestigeId}.mp4`;
}

export function vestigePosterUrl(vestigeId: string): string {
  return `/assets/vestiges/${vestigeId}_poster.jpg`;
}

export function vestigeFrameUrl(affinity: string): string {
  return `/assets/vestiges/frames/${affinity}.png`;
}

export function stashImageUrl(isEmpty: boolean): string {
  return `/assets/stash/${isEmpty ? 'close' : 'open'}.jpg`;
}
