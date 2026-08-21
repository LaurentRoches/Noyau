# Corebound - Projet Noyau

> Auto-battler / roguelike deckbuilder asynchrone, univers dark fantasy original **Les Héritiers du Vide**.

Projet personnel développé pour apprendre et expérimenter sur un moteur de simulation de combat déterministe, dans l'esprit de *The Bazaar* (Tempo), avec un univers propre inspiré (sans copie) de *La Voie des Ombres* (Brent Weeks).

## Stack technique

- **Backend** : PHP 8.3+ - moteur de simulation stateless (`CombatLog = f(Joueur A, Joueur B, Seed)`)
- **Frontend** : Vue.js 3 - présentation et file d'attente d'animations
- **Bonus (non structurant)** : WebSocket/Mercure pour notifications et signaux de fin de combat

Le backend et le frontend ne partagent pas de code métier, seulement un contrat d'API (DTOs / schéma OpenAPI).

## Pourquoi ce stack

- Combats **automatiques**, calculés côté serveur : pas de temps réel dur à gérer
- PvP **asynchrone** (V2+) : affrontement contre une copie figée d'un plateau adverse, pas de matchmaking temps réel — la V1 utilise une IA scriptée à difficulté croissante, le PvP snapshot est différé après validation du moteur solo
- Cycle de vie court (stateless) en PHP : chaque combat est calculé, sauvegardé, puis la mémoire est libérée, pas de risque de fuite mémoire sur des milliers de combats

## Cahier des charges V1

- **Vestige fixe, roster de héros tiré aléatoirement** : `shadow_vestige` (affinité, `startingGold`, `startingIncome`) est fixe et sans choix du joueur. Le roster du joueur est composé de **3 héros tirés à la construction du run** (`HeroRosterFactory`), pondéré par la seed : le premier héros est contraint à l'affinité du Vestige, les deux autres sont tirés dans tout le catalogue, sans remise. Chaque héros porte `itemSlots: 2`.
- **`CombatBoard`** : 1 Vestige + 1 à 3 héros, budget d'objets total dérivé de la somme des `itemSlots` du roster (6 avec 3 héros à 2 emplacements chacun)
- **3 raretés** : Commune (x1) / Rare (x1.5) / Légendaire (x2.5)
- **30 objets** en contenu réel, thème assassin/ombre (14 Common / 11 Rare / 5 Legendary)
- **4 statuts** : Poison (ignore le bouclier), Burn, Regen (soin dans le temps), Ward (bouclier dans le temps)
- **Système d'enrage** : au-delà d'un certain tick, dégâts croissants exponentiellement infligés aux deux plateaux — force la résolution d'un combat, protège les builds purement défensifs d'un stalemate perdant par défaut
- **Compétences de héros** : chaque héros peut porter une compétence passive (`Hero::$skill`, `HeroSkillType`) qui filtre et modifie les objets qui lui sont assignés au moment de l'assemblage du plateau (`HeroSkillDecorator`, appliqué dans `CombatBoardFactory`) — jamais pendant le combat, le moteur de simulation reste inchangé. Catalogue V1 de 10 compétences (bonus de valeur d'action, bonus de stack de statut, modificateurs composés dégâts+vitesse pour les archétypes `TWO_HAND` et double `ONE_HAND`), assignées aux 10 héros réels de `heroes.json` (`SAVAGE` partagé par deux héros d'affinités différentes, `RESURGENT` non assigné, laissé en réserve)
- **Boutique** : 4 offres aléatoires par visite (tirage seedé, plafonné à 1 objet Légendaire par visite), prix croissant avec la rareté
- **Inventaire du joueur** : `Inventory` (objets équipés, chacun assigné à un héros précis du roster via `AssignedItem`) + un coffre `Stash` de 3 emplacements (objets achetés en surplus, sans héros assigné) ; échange manuel possible entre les deux (`GameRun::swapWithStash()`), affectation automatique à l'achat via `HeroItemAllocator` (premier héros du roster ayant assez de budget restant)
- **Économie de run** : or de départ (`startingGold`, une fois) + revenu de manche (`startingIncome`, à chaque manche gagnée ou perdue) + récompense de victoire (`+10` or fixe)
- **Boucle** (`GameRun::playRound()`) : construit le plateau du joueur à partir de son inventaire courant, génère l'adversaire scripté, lance le combat, comptabilise le résultat, avance la manche
- **Adversaire scripté** : composition fixe par héros (`config/game/scripted_opponent.json`), révélée progressivement selon un budget global croissant par manche — pas de tirage aléatoire côté IA, volontairement déterministe et indépendant du catalogue jouable par le joueur
- **Pas de vrai PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking

## Modèle de données

Structure objet/effet basée sur des couples **trigger → actions** :

```json
{
  "trigger": "EVERY_N_TICKS",
  "actions": [
    { "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 12 }
  ]
}
```

## Structure du projet

backend/
├── config/
│ └── game/
│ ├── heroes.json # Configuration de production des héros (catalogue jouable,
│ │ # compétence passive optionnelle par héros)
│ ├── items.json # Configuration de production des objets (V1 : 30 objets)
│ ├── vestiges.json # Configuration de production des Vestiges
│ └── scripted_opponent.json # Composition fixe par héros de l'adversaire scripté
├── src/
│ ├── Application/
│ │ ├── GameRun.php # Orchestrateur de run : wallet, roster, inventaire/coffre, manches,
│ │ │ # boutique, combat, condition de fin de run
│ │ └── Factory/ # CombatBoardFactory (assemblage + application des compétences de
│ │ # héros), ShopFactory, HeroRosterFactory, ScriptedOpponentFactory
│ ├── Presentation/ # ItemPresenter, EffectPresenter, ActionPresenter, HeroPresenter,
│ │ # WalletPresenter, ShopPresenter, InventoryPresenter, StashPresenter,
│ ├── Domain/
│ │ ├── Engine/ # Simulator, TickEngine, EventDispatcher, ActionProcessor,
│ │ │ # StatusProcessor, EnrageProcessor
│ │ ├── Enum/ # Trigger, Target, Rarity, ActionType, EventType, StatusType,
│ │ │ # HeroSkillType
│ │ ├── Event/ # CombatEvent
│ │ ├── Model/ # DTOs : Hero (skill optionnel), Item, Vestige, Effect, Action
│ │ ├── Player/ # Inventory (objets assignés à un héros via AssignedItem),
│ │ │ # Stash (pool d'objets sans héros), HeroItemAllocator
│ │ │ # (règle d'affectation objet → héros par budget de slots),
│ │ │ # HeroSkillDecorator (compétence passive appliquée aux objets
│ │ │ # d'un héros à l'assemblage du plateau)
│ │ ├── Runtime/ # Entités d'exécution : CombatHero, CombatItem, CombatVestige,
│ │ │ # CombatBoard, ActiveStatus
│ │ └── Shop/ # Wallet, ShopOffer, Shop (économie de boutique)
│ └── Infrastructure/
│ └── Repository/Json/ # JsonHeroRepository, JsonItemRepository, JsonVestigeRepository,
│ # JsonScriptedOpponentRepository
├── tests/
│ ├── Application/ # Tests de GameRun et de ses fabriques (Factory/)
│ ├── Presentation/ # Tests des fichiers de présentation
│ ├── Domain/ # Tests unitaires du moteur, de la boutique, de l'inventaire
│ ├── E2E/ # Tests de bout en bout (fichiers prod -> simulation)
│ ├── Fixtures/ # Fixtures de test isolées
│ └── Infrastructure/ # Tests des repositories JSON
frontend/
└── ... # Vue.js 3, file d'attente d'animations


## Avancement

- [x] Notes de design et cahier des charges V1
- [x] Modèle de données trigger → actions défini
- [x] Modèles du Domaine & Enums (`Hero`, `Item`, `Vestige`, `Effect`, `Action`, `Rarity`, `Trigger`, `ActionType`, `Target`, `StatusType`)
- [x] Hydratation & Repositories JSON (`JsonHeroRepository`, `JsonItemRepository`, `JsonVestigeRepository`, `JsonScriptedOpponentRepository`)
- [x] Fabrique d'assemblage (`CombatBoardFactory`) & validation des slots de héros
- [x] Moteur de simulation à ticks déterministe (`Simulator`, `TickEngine`, `ActionProcessor`)
- [x] Moteur de statuts (Poison, Burn, Regen, Ward) via `StatusProcessor`
- [x] Système d'enrage anti-stalemate (`EnrageProcessor`)
- [x] Contenu réel complet V1 (30 objets dans `config/game/items.json`, répartition 14/11/5 actée)
- [x] Suite de tests automatisés (unitaires, intégration, E2E avec 100 % de succès)
- [x] Boutique / économie (`Wallet`, `ShopOffer`, `Shop`, `ShopFactory` — tirage seedé plafonné en rareté)
- [x] Orchestration de la boucle de jeu (`GameRun` : wallet, manches, boutique, combat, condition de fin de run)
- [x] Catalogue de héros enrichi (10 héros, 5 shadow / 5 neutral) et roster de 3 héros tiré à la seed
      (`HeroRosterFactory`, premier héros contraint sur l'affinité du Vestige)
- [x] Inventaire hero-aware (`Inventory`/`AssignedItem`) séparé du coffre (`Stash`) et affectation
      automatique à l'achat par budget de slots (`HeroItemAllocator`)
- [x] IA scriptée à composition fixe par héros et difficulté croissante par manche
      (`ScriptedOpponentFactory`, `config/game/scripted_opponent.json`)
- [x] `GameRun::playRound()` construit lui-même le plateau du joueur à partir de son inventaire
      courant (répartition par héros) — la boucle V1 est jouable de bout en bout mécaniquement,
      validée sur `php run.php` avec seed fixe
- [x] Point d'entrée applicatif réel (API ou script) imposant l'ordre boutique → combat — `GameRun`
      ne l'impose pas structurellement aujourd'hui, seul un futur appelant discipliné le garantit
- [x] Système de compétences de héros (`HeroSkillType`, `HeroSkillDecorator`) : modificateur passif
      appliqué aux objets d'un héros à l'assemblage du plateau (`CombatBoardFactory`), jamais
      pendant le combat — moteur de simulation inchangé. Catalogue V1 de 10 compétences câblé de
      bout en bout, validé par test unitaire exhaustif par compétence, test d'intégration sur la
      fabrique, test E2E sur les 10 héros réels contre un objet de production, et `php run.php`
      manuel sur plusieurs seeds
- [ ] Frontend Vue.js (file d'attente d'animations)

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.