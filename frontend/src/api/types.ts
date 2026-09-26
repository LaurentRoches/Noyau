// src/api/types.ts
/**
 * Côté d'un plateau tel que le journal de combat le porte (D-19).
 *
 * Neutre par construction : le journal est écrit une seule fois pour les deux
 * joueurs, il ne peut donc nommer ni « moi » ni « l'adversaire ».
 */
export type Side = 'A' | 'B';

/**
 * Le même côté, traduit pour celui qui regarde.
 *
 * C'est le seul vocabulaire dont l'interface a besoin. Un composant qui saurait
 * distinguer A de B saurait une chose de trop, et redeviendrait faux le jour où
 * l'attribution des côtés cessera d'être positionnelle.
 */
export type ViewerRelativeSide = 'SELF' | 'ENEMY';
export type ActionType = 'DEAL_DAMAGE' | 'APPLY_STATUS' | 'GAIN_SHIELD' | 'HEAL' | 'SET_AFFINITY';
export type EventType =
  | 'DAMAGE_DEALT'
  | 'HEAL_RECEIVED'
  | 'SHIELD_GAINED'
  | 'STATUS_APPLIED'
  | 'STATUS_DAMAGE_DEALT'
  | 'STATUS_HEAL_RECEIVED'
  | 'STATUS_SHIELD_GAINED'
  | 'STATUS_EXPIRED'
  | 'ENRAGE_DAMAGE_DEALT'
  | 'RESOLUTION_TIEBREAK';
export type Target = 'SELF' | 'ENEMY' | 'ALL_ENEMIES' | 'ALL_ALLIES';
export type StatusType = 'POISON' | 'BURN' | 'REGEN' | 'WARD';
export type Rarity = 'COMMON' | 'RARE' | 'LEGENDARY';
export type ItemSize = 'ONE_HAND' | 'TWO_HAND';
export type HeroSkillType =
  | 'FRANTIC'
  | 'STALWART'
  | 'VITALIC'
  | 'SAVAGE'
  | 'VIRULENT'
  | 'SEARING'
  | 'WARDEN'
  | 'RESURGENT'
  | 'SUNDERING'
  | 'RELENTLESS';

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

export interface VestigeDTO {
  id: string;
  name: string;
  affinity: string;
  baseHp: number;
  baseShield: number;
  startingGold: number;
  startingIncome: number;
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
  vestige: VestigeDTO;
  wallet: { balance: number };
  shop: { offers: ShopOfferDTO[] } | null;
  inventory: { items: AssignedItemDTO[] };
  stash: { items: StashEntryDTO[]; capacity: number; isFull: boolean };
  roster: HeroDTO[];
  pendingHeroOffer: HeroDTO[] | null;
}

export interface CreateRunResponse {
  run_id: string;
  state: RunStateDTO;
}
export interface RunActionResponse {
  state: RunStateDTO;
}
export interface ResolveRoundResponse {
  state: RunStateDTO;
  combatLog: CombatEventDTO[];
  opponentRoster: HeroDTO[];
  opponentInventory: OpponentInventoryDTO;
  /**
   * Côté occupé par celui qui a joué cette manche.
   *
   * Sans lui, le client ne peut plus écrire « ton Vestige » : les libellés du
   * journal ne le disent plus. Le supposer — « A, c'est moi » — serait exact
   * aujourd'hui et faux dès l'attribution canonique, **en silence**.
   */
  viewerSide: Side;
}

export interface OpponentAssignmentDTO {
  item: ItemDTO;
  heroId: string;
}

export interface OpponentInventoryDTO {
  items: OpponentAssignmentDTO[];
}
