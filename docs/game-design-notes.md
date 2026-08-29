# Projet Noyau — Corebound (auto-battler dark fantasy)

> **Note de mise à jour** : ce document remplace la version précédente, devenue partiellement obsolète depuis la session 005 (modèle Vestige/multi-héros) et la session 007 (boutique/économie). Le scope V1 est maintenant strictement délimité de la Roadmap V2+ (voir section dédiée en fin de document) pour éviter toute confusion entre ce qui est acté et ce qui est envisagé.

## Contexte et intention

Projet perso de Laurent, développeur Full Stack (PHP, Vue.js 3). Objectif : développer un jeu vidéo type roguelike deckbuilder / auto-battler asynchrone, dans l'esprit de **The Bazaar** (Tempo), mais avec un univers original — **Les Héritiers du Vide** — inspiré (sans copie) de **La Voie des Ombres** (Night Angel, Brent Weeks).

- **Nom du projet** : Projet Noyau
- **Nom du jeu** : Corebound
- **Univers** : Les Héritiers du Vide
- **Artefact central** : le Vestige

## Choix techniques confirmés

- **Backend** : PHP 8.3, natif (pas de framework applicatif type Symfony à ce stade — architecture DDD construite à la main, namespaces `App\Domain\...`).
- **Frontend** : Vue.js 3.
- **Qualité** : PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (validation JSON + PHPUnit + PHPStan + CS Fixer, tous bloquants sur PR vers `dev`).
- WebSocket/Mercure reste un bonus (notifications, signal de fin de combat) envisageable en V2+, pas une brique structurante du moteur.

### Pourquoi PHP pour le moteur de simulation

Un combat en auto-battler asynchrone est une transformation de données pure : `CombatLog = f(playerBoard, opponentBoard, seed)`. PHP est bien adapté à cet usage :

- **Cycle de vie court (stateless)** : une requête charge les plateaux, exécute la simulation en mémoire, génère le JSON du combat, le renvoie, puis libère tout.
- **Zéro fuite de mémoire** entre requêtes.
- **Isolation totale** entre deux combats simulés en parallèle.

## Univers et thème

Inspiration : **La Voie des Ombres** (Night Angel, Brent Weeks) — dark fantasy, artefacts magiques vivants liés à un porteur unique. **Point de vigilance IP** : ne reprendre que la structure mécanique (artefact vivant lié à un porteur, thématique par faction), jamais les noms ni pouvoirs exacts. L'univers du jeu s'appelle **Les Héritiers du Vide**, et l'artefact devient le **Vestige**.

Chaque futur Vestige aura sa propre **affinité** thématique (élément, mécanique signature, pool d'objets dédié).

---

## Cahier des charges V1 — état réel du code

### Vestige et héros du joueur

**1 seul Vestige en V1** : `shadow_vestige`, thème "Porteur de l'Ombre", affinité `shadow`. **1 seul héros en V1** : `shadow_bearer`. Aucun des deux n'est choisi par le joueur — les deux sont fixés en dur dans `GameRun` (`PLAYER_HERO_ID`) et dans le contenu (`config/game/vestiges.json`), en attendant un système de choix/marchand de héros en V2+.

Le Vestige porte l'intégralité de l'état vivant du plateau (HP, bouclier, statuts actifs) — les héros n'ont plus d'état de combat propre depuis la migration de session 005.

Champs du modèle `Vestige` (`Domain/Model/`), tous obligatoires, hydratés depuis `config/game/vestiges.json`, fail-fast si absent :
- `id`, `name`, `affinity`
- `baseHp`, `baseShield`
- `startingGold` — or de départ du joueur, lu uniquement par `GameRun` pour initialiser le `Wallet`.
- `startingIncome` — or crédité au `Wallet` à la fin de **chaque** manche, gagnée ou perdue. C'est ce qui garantit qu'une défaite n'empêche pas le joueur de progresser économiquement.

Le moteur de combat lui-même (`Simulator`/`ActionProcessor`/`StatusProcessor`) ignore totalement l'or — ces champs ne sont lus que par `Application/GameRun`.

### Héros et plateau

Un `CombatBoard` regroupe **1 `CombatVestige` + 1 à 3 `CombatHero`** (garde fail-fast dans le constructeur : entre 1 et 3 héros).

- Le Vestige définit l'affinité du plateau. Les héros et objets gardent leur propre affinité indépendante (sans effet mécanique en V1 — anticipe un futur système de synergie).
- 6 emplacements d'objets au total par plateau, comptés globalement (pas encore répartis par héros précis — voir Roadmap).
- Compétences de héros : hors scope V1.

### Raretés et objets

3 raretés : Commune / Rare / Légendaire.
- Multiplicateur de stats : ×1 / ×1.5 / ×2.5 (`Rarity::statMultiplier()`)
- Prix boutique : 10 / 25 / 50 or (`Rarity::basePrice()`)
- Modificateur de taux de drop : ×1 / ×0.25 / ×0.015 (`Rarity::dropRateModifier()`)

**30 objets au total, répartition définitive : 14 Common / 11 Rare / 5 Legendary.** Thème assassin/ombre (dagues, poisons/fioles, artefacts d'ombre). Cette répartition est close, ne pas la rouvrir.

**12 des 30 objets sont purement défensifs** (bouclier, soin, aucun dégât) — ce chiffre a directement motivé l'introduction du système d'enrage (voir Moteur de simulation ci-dessous) : sans lui, ces objets étaient structurellement dominés dans tout matchup qui n'aboutit pas à une mort franche avant `maxTicks`.

### Effets et statuts

Catégories d'effets implémentées : Dégâts, Bouclier, Soin, et 4 statuts à pulsation périodique :
- **Poison** : dégâts qui ignorent le bouclier
- **Burn** : dégâts qui passent par le bouclier normalement
- **Regen** : soin périodique (plafonné à `baseHp`)
- **Ward** : gain de bouclier périodique (sans plafond)

Règle de stacking : ré-application avant expiration → les stacks s'additionnent, la durée la plus longue des deux est conservée.

`Trigger::ON_ATTACK` et `Trigger::EVERY_N_TICKS` sont fonctionnellement équivalents dans le moteur actuel (seul `cooldownTicks` pilote la cadence). `ON_KILL`/`ON_DEATH` existent dans l'enum mais ne sont déclenchés par rien encore.

**Aucun objet ne génère d'or ou de mana en combat** — `ActionType::GAIN_GOLD` et `GAIN_MANA` ont été retirés de l'enum (erreur de cahier des charges initial, corrigée ; voir Économie ci-dessous pour les vraies sources d'or).

### Boutique / Économie

Système complet (`Domain/Shop/` + `Application/Factory/ShopFactory`) :
- `Wallet` : solde du joueur, méthodes `credit()`/`spend()`/`canAfford()`, garde contre montants négatifs et insolvabilité.
- `Shop` : agrégat de 4 `ShopOffer`, achat en deux phases (validation intégrale puis mutation), jamais de débit partiel.
- `ShopFactory` : génère les 4 offres via tirage partitionné (3 slots Common+Rare, 1 slot catalogue complet) — plafonne à 1 Legendary max par visite (~18,5 % de chance, contre ~53,8 % en tirage uniforme).

**Sources d'or en V1**, toutes créditées par `GameRun` à la résolution de chaque manche :
- Or de départ (`Vestige::startingGold`), une seule fois au lancement de la run.
- `Vestige::startingIncome`, crédité à chaque fin de manche, gagnée ou perdue.
- Récompense de victoire : `+10` or fixe (constante `GameRun::VICTORY_REWARD`), en plus de `startingIncome`.

**Hors scope V1** (voir Roadmap) : revente d'objets, récompenses liées à un Monstre.

### Inventaire du joueur

Deux instances de `Domain/Player/Inventory` (même classe, capacités différentes) portées par `GameRun` :
- **Plateau de combat** (`GameRun::getInventory()`) : capacité 6, ce sont exactement les objets équipés lors du prochain combat.
- **Coffre** (`GameRun::getStash()`) : capacité 3, stockage additionnel pour les objets achetés en surplus, sans qu'ils soient équipés.

`GameRun::purchaseItem(int $slotIndex)` débite le `Wallet` via `Shop::purchase()`, puis place l'objet automatiquement dans le plateau s'il reste de la place, sinon dans le coffre. Une garde vérifie qu'au moins un des deux a de la place **avant** de débiter — même principe d'atomicité que `Shop::purchase()` (validation intégrale avant mutation), pour ne jamais faire payer un objet qui n'a nulle part où aller.

`GameRun::swapWithStash(int $inventoryIndex, int $stashIndex)` échange un objet du plateau avec un objet du coffre — les deux retraits sont validés avant toute mutation, pour éviter qu'une erreur sur le second échange ne laisse le premier objet orphelin. C'est le seul mouvement d'objet possible entre les deux collections en V1 : pas de déplacement plateau→plateau (réordonnancement) ni de retrait sans remplacement.

### Boucle de jeu V1

```
Vestige et héros fixes (shadow_vestige, shadow_bearer) — aucun choix du joueur
  ↓
Wallet initialisé avec startingGold, Inventory et coffre vides
  ↓
┌─── Nouvelle manche (GameRun::playRound) ────────────────────────┐
│  Boutique (1 visite, 4 offres) — GameRun::openShop()/purchaseItem() │
│         ↓                                                          │
│  playRound() construit le CombatBoard du joueur à partir de        │
│  l'Inventory courant, génère l'adversaire scripté, lance Simulator │
│         ↓                                                          │
│  Victoire : +1 victoire, +10 or, +startingIncome                    │
│  Défaite ou timeout : +1 défaite, +startingIncome seul               │
└──────────────────────────────────────────────────────────────────┘
  ↓ (répéter tant que victoires < 10 ET défaites < 3)
Fin de run : 10 victoires (gagné) ou 3 défaites (perdu)
```

**Pas de PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking.

**Point ouvert, non bloquant** : `GameRun` ne force pas structurellement l'ordre "boutique avant combat" — rien n'empêche techniquement d'appeler `playRound()` sans être passé par la boutique au préalable. Cette discipline reposera sur le futur point d'entrée applicatif (API ou script), pas encore construit.

Orchestrateur : `Application/GameRun`, construit avec un `Vestige`, une `ShopFactory`, une `ScriptedOpponentFactory`, une `CombatBoardFactory`, un `Simulator` et un `Randomizer` déjà instanciés (injection explicite, pas de valeur par défaut cachée — même philosophie que `SimulationContext`).

### Moteur de simulation

Déterministe (`\Random\Randomizer` + `\Random\Engine\PcgOneseq128XslRr64($seed)`), architecture à événements :

```
TickEngine (horloge)
  → EventDispatcher (routeur pur, zéro accès en écriture)
  → PendingAction (DTO immuable)
  → ActionProcessor (résout Target::SELF/ENEMY, mute le CombatVestige cible)
  → StatusProcessor (pulsation des statuts actifs)
  → EnrageProcessor (dégâts de fin de combat forcée, voir ci-dessous)
  → CombatEvent → CombatLog
```

`Simulator::run()` : boucle jusqu'à `maxTicks` (défaut 500) ou mort d'un plateau — `break` immédiat dès qu'un Vestige meurt (pas de "frappe sur cadavre"), rendant le double-KO structurellement impossible **dans le cas général**. `SimulationResult { winner: ?CombatBoard, totalTicks: int, log: CombatLog }`.

#### Système d'enrage (`EnrageProcessor`)

Ajouté en cours de développement de `GameRun`, suite à un constat de game design : sans lui, un combat qui atteint `maxTicks` sans mort produit `winner: null`, que `GameRun` compte comme une défaite. Un joueur misant sur des objets purement défensifs (12 des 30 objets V1) n'avait alors **aucune ligne de jeu gagnante** face à un adversaire qui refuse de mourir sans jamais l'achever — ces objets étaient structurellement dominés, pas juste sous-optimaux.

Fonctionnement :
- À partir de `triggerTick = max(1, maxTicks - 50)`, inflige des dégâts croissants aux deux Vestiges, au même tick.
- Progression exponentielle : `damage = baseDamage × 2^(tick − triggerTick)`, `baseDamage = 5`.
- Les dégâts passent par le bouclier **normalement** (comme `DEAL_DAMAGE`, pas comme Poison) — décision explicite : le bouclier accumulé doit rester une vraie protection pendant l'enrage, sinon les builds défensifs sont punis une seconde fois.
- **Invariant du "pas de frappe sur cadavre" étendu à l'enrage** : si le premier board évalué meurt du coup d'enrage, le second n'est pas frappé au même tick — sans cette garde, deux plateaux symétriques (mêmes objets, faible écart de HP/bouclier) double-KO de façon quasi certaine dès que la progression exponentielle dépasse l'écart initial, quelle que soit sa taille.
- Conséquence assumée : cette garde introduit un biais d'ordre d'évaluation (le joueur est toujours évalué en premier via `getBoards()`, donc en cas d'égalité stricte de dégâts fatals au même tick, c'est le joueur qui meurt et l'adversaire qui est épargné ce tick-là) — cohérent avec le biais d'ordre déjà documenté ailleurs dans le moteur (activation des objets, joueur avant adversaire).

---

## Points d'attention techniques (toujours valables)

### Déterminisme et replays
Le moteur est 100 % déterministe via seed transmise en début de combat. Le serveur calcule le combat, génère un `CombatLog` JSON, le frontend le rejoue pas à pas sans recalculer.

### File d'attente d'animations (Vue.js 3)
Le `CombatLog` doit être dépilé via une queue d'animations asynchrone (`Promise`/`async-await`), jamais appliqué directement au state — sinon le combat se joue en une milliseconde à l'écran.

### Boucle à ticks fixes
1 tick = 100ms (10 ticks/seconde), pas de temps réel en flottant. Simplifie tests et débogage tick par tick.

---

## Mécanique différée : conversion d'affinité

Idée retenue mais **non implémentée en V1** : objets permettant de changer sa propre affinité (pivot de build). Changer l'affinité adverse jugé risqué (sensation "pas fun", complexité de résolution en cas de double conversion) — reporté V2+.

Le type d'action `SetAffinity` est prévu dans le schéma mental mais pas encore ajouté à l'enum `ActionType` (contrairement à `GAIN_GOLD`/`GAIN_MANA`, il n'a jamais été implémenté ni retiré — c'est un placeholder conscient, pas une erreur à corriger).

---

## Roadmap V2+ — idées actées comme différées, non implémentées

Cette section rassemble toutes les mécaniques discutées mais explicitement reportées après la V1. Elle ne doit pas être lue comme un scope engagé.

### Boucle de jeu enrichie
- **Choix du Vestige parmi 3 aléatoires** au démarrage (V1 : Vestige fixe unique).
- **Choix ou marchand de héros** : en V1, le héros du joueur (`shadow_bearer`) est fixe, jamais choisi. Un vrai système de sélection ou de marchand de héros est nécessaire pour que le multi-héros (1 à 3 par board, déjà supporté par `CombatBoard`) ait un sens côté joueur.
- **Deux phases marchand par manche** (avant combat + préparation combat, 4 offres chacune) au lieu d'une seule visite.
- **Choix de Monstre PvE** parmi 3, avec thématique propre, difficulté (faible/moyen/difficile), récompenses dédiées (un de ses objets, un de ses passifs rares, gain d'or fixe selon difficulté).
- **Combat asynchrone contre snapshot de joueur** (vrai PvP) — remplace ou complète l'IA scriptée, plateau adverse = snapshot figé d'un autre joueur à la même manche. Nécessite infrastructure de stockage/matchmaking de plateaux, volontairement non construite en V1.
- **Marchand pouvant influencer** le pool d'objets, les prix, ou proposer du rachat.
- **Vente d'objets** par le joueur (rachat, ex. 50% du prix de base) — nécessite de dépasser le simple coffre actuel.
- **Coffre extensible / déplacement plateau→plateau (réordonnancement)** : le coffre V1 a une capacité fixe de 3 et ne permet que l'échange direct avec le plateau (`swapWithStash`), pas de réordonnancement interne ni d'agrandissement.
- **Point d'entrée applicatif imposant l'ordre boutique → combat** : `GameRun` ne garantit pas structurellement cet ordre aujourd'hui.

### Combat et objets
- **Pondération de rareté dans `ScriptedOpponentFactory`** : la difficulté croissante actuelle (V1) ne fait varier que le *nombre* d'objets équipés par l'adversaire (`ceil(round / 2)`, plafonné à 6), jamais leur rareté — délibérément, faute de données de playtesting pour calibrer une pondération.
- **Calibration des paramètres d'enrage** (`triggerTick`, `baseDamage`, facteur de progression) — valeurs V1 posées par raisonnement, jamais ajustées par playtest.
- **Multi-affinité mécanique réelle** : bonus si affinité héros/objet == affinité du plateau (actuellement sans effet).
- **Compétences de héros** (bloc dédié envisagé sur le croquis de board) — non schématisées.
- **Taille d'objet (1 main / 2 mains)** influençant le budget de slots et le prix boutique — dette localisée à `CombatBoardFactory` et la boutique, jamais au moteur de combat lui-même.
- **Répartition des items par héros précis** (`itemIds: list<string>` → `array<heroId, list<string>>`) — actuellement un budget global de 6 slots, pas d'assignation par héros. Bloqué tant que le joueur n'a qu'un seul héros possible.
- **Ordre d'activation / initiative** entre objets de boards différents partageant un trigger au même tick — actuellement déterministe par ordre de déclaration (joueur avant adversaire), sans stat de vitesse.
- **Garde-fou anti-boucle-infinie** sur `EventDispatcher` en cas de cascade d'événements — non urgent tant qu'aucun effet ne re-déclenche un autre effet.
- **Persistance d'état entre combats** (mode "usure") — point d'entrée alternatif (`fromPreviousState()`) envisagé pour ne pas casser l'existant le jour venu.
- **Conversion d'affinité adverse** (sabotage) — écartée par analyse de fun, `SetAffinity` reste un placeholder pour la conversion de sa propre affinité uniquement.
- **Nombre `6` dupliqué à trois endroits indépendants** (`Hero::itemSlots`, `ScriptedOpponentFactory::MAX_ITEMS`, `GameRun::INVENTORY_CAPACITY`) — inoffensif tant qu'un seul héros existe dans le contenu, deviendra une vraie dette si un second héros à budget différent est ajouté sans centraliser la source de vérité.
- **Swap multi-items pondéré N-vers-1** : `swapWithStash` ne gère aujourd'hui que l'échange 1-pour-1. Un objet `TWO_HAND` peut rester bloqué au coffre si aucun héros n'a 2 slots contigus libres, même si la capacité totale du roster suffirait (fragmentation du budget par héros, comportement attendu du `HeroItemAllocator` naïf actuel). Piste retenue : autoriser un échange de plusieurs objets d'inventaire (même héros) contre un seul objet du coffre, sous contrainte `Σ coûts retirés ≥ coût ajouté` — nécessite un `Stash` à capacité pondérée par `slotCost()` pour absorber le différentiel d'objets libérés.

---

## Consigne de suivi pour Claude

Continuer à appliquer un regard critique et honnête sur les futures propositions de mécaniques — évaluer systématiquement l'impact en termes de complexité moteur, de jouabilité réelle (pas seulement l'idée sur le papier), et de cohérence avec le scope V1 tel que délimité ci-dessus, plutôt que de valider par défaut. Toute mécanique listée en Roadmap V2+ ne doit pas être implémentée sans une décision explicite de faire passer son scope en V1.