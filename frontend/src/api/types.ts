// src/api/types.ts
export type Side = 'PLAYER' | 'OPPONENT';
export type ActionType = 'DEAL_DAMAGE' | 'APPLY_STATUS' | 'GAIN_SHIELD' | 'HEAL' | 'SET_AFFINITY';
export type EventType =
  | 'DAMAGE_DEALT' | 'HEAL_RECEIVED' | 'SHIELD_GAINED' | 'STATUS_APPLIED'
  | 'STATUS_DAMAGE_DEALT' | 'STATUS_HEAL_RECEIVED' | 'STATUS_SHIELD_GAINED'
  | 'STATUS_EXPIRED' | 'ENRAGE_DAMAGE_DEALT';
export type Target = 'SELF' | 'ENEMY' | 'ALL_ENEMIES' | 'ALL_ALLIES';
export type StatusType = 'POISON' | 'BURN' | 'REGEN' | 'WARD';
export type Rarity = 'COMMON' | 'RARE' | 'LEGENDARY';
export type ItemSize = 'ONE_HAND' | 'TWO_HAND';
export type HeroSkillType =
  | 'FRANTIC' | 'STALWART' | 'VITALIC' | 'SAVAGE' | 'VIRULENT'
  | 'SEARING' | 'WARDEN' | 'RESURGENT' | 'SUNDERING' | 'RELENTLESS';

export interface ActionDTO {
  type: ActionType;
  value: number | null;
  target: Target | null;
  status: StatusType | null;
  stacks: number | null;
  durationTicks: number | null;
}

export interface EffectDTO {
  trigger: string;
  actions: ActionDTO[];
  intervalTicks: number | null;
}

export interface ItemDTO {
  id: string;
  name: string;
  rarity: Rarity;
  affinity: string;
  size: ItemSize;
  cooldownTicks: number;
  effects: EffectDTO[];
}

export interface HeroDTO {
  id: string;
  name: string;
  affinity: string;
  itemSlots: number;
  skill: HeroSkillType | null;
}

export interface AssignedItemDTO {
  inventoryIndex: number;
  item: ItemDTO;
  heroId: string;
}

export interface ShopOfferDTO {
  slotIndex: number;
  item: ItemDTO;
  price: number;
  purchased: boolean;
}

export interface StashEntryDTO {
  stashIndex: number;
  item: ItemDTO;
}

export interface CombatEventDTO {
  tick: number;
  type: EventType;
  payload: Record<string, unknown>;
}

export interface RunStateDTO {
  round: number;
  victories: number;
  defeats: number;
  isOver: boolean;
  hasWon: boolean;
  wallet: { balance: number };
  shop: { offers: ShopOfferDTO[] } | null;
  inventory: { items: AssignedItemDTO[] };
  stash: { items: StashEntryDTO[]; capacity: number; isFull: boolean };
  roster: HeroDTO[];
}

export interface CreateRunResponse { run_id: string; state: RunStateDTO }
export interface RunActionResponse { state: RunStateDTO }
export interface ResolveRoundResponse { state: RunStateDTO; combatLog: CombatEventDTO[] }