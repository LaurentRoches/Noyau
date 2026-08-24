# Corebound - Projet Noyau

> Auto-battler / roguelike deckbuilder asynchrone, univers dark fantasy original **Les Héritiers du Vide**.

Projet personnel développé pour apprendre et expérimenter sur un moteur de simulation de combat déterministe, dans l'esprit de *The Bazaar* (Tempo), avec un univers propre inspiré (sans copie) de *La Voie des Ombres* (Brent Weeks).

## Stack technique

- **Backend** : PHP 8.3+, sans framework — moteur de simulation stateless (`CombatLog = f(Joueur A, Joueur B, Seed)`) exposé via une API HTTP JSON maison (routeur, requête/réponse et contrôleurs écrits à la main, sans bibliothèque tierce)
- **Persistance de run** : SQLite (`database/database.sqlite`), via un **journal d'actions rejouable** (event log + seed) plutôt qu'un instantané sérialisé — chaque requête HTTP reconstruit l'état courant en rejouant l'historique des actions sur un `GameRun` frais, sans toucher au domaine
- **Frontend** : Vue.js 3 + TypeScript (Vite), Pinia pour l'état — squelette en cours (client API typé et store terminés, écrans à venir), consommera l'API HTTP décrite ci-dessous
- **Bonus (non structurant)** : WebSocket/Mercure pour notifications et signaux de fin de combat

Le moteur de simulation reste pur et stateless (aucune notion de HTTP ou de base de données n'y pénètre) ; c'est la couche `Persistence` qui porte la responsabilité de faire survivre l'état d'une run entre deux requêtes HTTP, en rejouant ses actions plutôt qu'en sérialisant ses objets.

## Pourquoi ce stack

- Combats **automatiques**, calculés côté serveur : pas de temps réel dur à gérer
- PvP **asynchrone** (V2+) : affrontement contre une copie figée d'un plateau adverse, pas de matchmaking temps réel — la V1 utilise une IA scriptée à difficulté croissante, le PvP snapshot est différé après validation du moteur solo
- Cycle de vie court (stateless) en PHP : chaque combat est calculé, sauvegardé, puis la mémoire est libérée, pas de risque de fuite mémoire sur des milliers de combats
- SQLite plutôt qu'un serveur MySQL/PostgreSQL pour la V1 : aucune administration, un seul fichier portable — cohérent avec un futur packaging Electron/Tauri (sauvegarde locale = un fichier, pas un service à faire tourner). Un vrai serveur de base de données ne redevient nécessaire qu'au moment du PvP asynchrone (plusieurs joueurs concurrents), pas avant.

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
- **Camp d'un événement de combat** : chaque `CombatEvent` porte un `targetSide` (`PLAYER`/`OPPONENT`), et pour les events déclenchés par un objet (`DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL`, `APPLY_STATUS`) également un `sourceSide` et un `sourceItemId` — nécessaire car le Vestige du joueur et celui de l'adversaire scripté partagent le même id en V1 (`shadow_vestige`), donc `target` seul ne permet pas de distinguer les deux camps
- **API HTTP** : les cinq actions de la boucle (créer une run, consulter l'état, acheter, échanger inventaire/coffre, résoudre une manche) sont exposées en JSON — voir [Contrat d'API](#contrat-dapi) ci-dessous
- **Persistance de run** : chaque action du joueur est journalisée (event log horodaté, `run_actions`) plutôt que l'état lui-même sérialisé ; l'état courant est reconstruit à la demande en rejouant ce journal sur un `GameRun` frais depuis sa seed d'origine
- **Pas de vrai PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking

## Contrat d'API

Aucune notion de compte joueur en V1 : une run est identifiée par un `run_id` opaque, généré à la création et à fournir à chaque appel suivant — pas de session ni de cookie, pour rester portable vers un futur client Electron/Tauri et préparer sans réécriture un futur `player_id` de PvP.

| Méthode | Route | Effet |
|---|---|---|
| `POST` | `/runs` | Crée une run (seed aléatoire, Vestige fixe), ouvre automatiquement la première boutique. Réponse : `{ run_id, state }` — seul endpoint à exposer `run_id` |
| `GET` | `/runs/{run_id}` | État courant complet (manche, or, boutique, inventaire, coffre, roster). Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/shop/buy` | `{ slotIndex }` — achète une offre de la boutique courante. Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/inventory/swap` | `{ inventoryIndex, stashIndex, heroId }` — échange un objet entre inventaire et coffre. Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/round/resolve` | Résout la manche courante (combat), ouvre automatiquement la boutique suivante si la run continue. Réponse : `{ state, combatLog }` — seul endpoint à exposer le log du combat qui vient d'être résolu |

Toutes les réponses sont du JSON, sérialisé par une couche `Presentation` dédiée (jamais le domaine directement). Les erreurs métier du domaine sont traduites en codes HTTP par le routeur : run introuvable → `404`, argument invalide (index hors bornes, payload malformé) → `400`, état incohérent (achat déjà effectué, fonds insuffisants) → `409`.

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

Un événement de combat résolu (`CombatEvent`, exposé dans `combatLog` par `POST /runs/{run_id}/round/resolve`) a cette forme :

```json
{
  "tick": 40,
  "type": "DAMAGE_DEALT",
  "payload": {
    "amount": 15,
    "shieldDamage": 0,
    "hpDamage": 15,
    "target": "opponent_vestige",
    "targetSide": "OPPONENT",
    "sourceSide": "PLAYER",
    "sourceItemId": "shadow_dagger"
  }
}
```

`sourceSide`/`sourceItemId` ne sont présents que pour les events déclenchés par un objet (`DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL_RECEIVED`, `STATUS_APPLIED`) — un statut qui pulse (`STATUS_DAMAGE_DEALT`, etc.) ou l'enrage (`ENRAGE_DAMAGE_DEALT`) n'ont pas de source ponctuelle et ne portent que `targetSide`.

## Structure du projet

```
backend/
├── config/
│   └── game/
│       ├── heroes.json              # Configuration de production des héros (catalogue jouable,
│       │                             # compétence passive optionnelle par héros)
│       ├── items.json               # Configuration de production des objets (V1 : 30 objets)
│       ├── vestiges.json            # Configuration de production des Vestiges
│       └── scripted_opponent.json   # Composition fixe par héros de l'adversaire scripté
├── database/
│   └── database.sqlite              # Base runtime (ignorée par Git), créée au premier accès
├── public/
│   ├── index.php                    # Point d'entrée HTTP réel (bootstrap PDO, routes, dispatch)
│   └── .htaccess                    # Réécriture vers index.php (Apache/WAMP)
├── src/
│   ├── Application/
│   │   ├── GameRun.php              # Orchestrateur de run : wallet, roster, inventaire/coffre,
│   │   │                             # manches, boutique, combat (conserve le dernier
│   │   │                             # SimulationResult via getLastCombatResult()), condition
│   │   │                             # de fin de run
│   │   └── Factory/                 # GameRunFactory (câblage unique des 7 dépendances d'un
│   │                                 # GameRun, partagé par run.php, les tests et le replayer),
│   │                                 # CombatBoardFactory, ShopFactory, HeroRosterFactory,
│   │                                 # ScriptedOpponentFactory
│   ├── Http/
│   │   ├── Request.php              # Requête HTTP testable (fromGlobals() / fake())
│   │   ├── ApiResponse.php          # Valeur pure (statusCode + body), sans I/O
│   │   ├── Response.php             # Émission réelle (headers/body/exit), non unit-testable
│   │   ├── Router.php               # Routeur maison (regex + mapping d'exceptions → HTTP)
│   │   └── Controller/
│   │       └── RunController.php    # Les 5 actions du contrat d'API (resolveRound expose
│   │                                 # désormais { state, combatLog })
│   ├── Persistence/
│   │   ├── Schema.php               # Création idempotente du schéma SQLite
│   │   ├── GameRunRecord.php / GameRunRepository.php       # Table `runs`
│   │   ├── GameRunActionRecord.php / GameRunActionsRepository.php  # Table `run_actions`
│   │   ├── GameRunActionType.php    # Enum technique (rejeu), distinct des enums du Domaine
│   │   ├── GameRunActionApplier.php # Traduit une action journalisée en appel réel sur GameRun
│   │   ├── GameRunReplayer.php      # Reconstruit un GameRun vivant à partir d'un run_id
│   │   └── RunNotFoundException.php # Distincte d'InvalidArgumentException (404 vs 400)
│   ├── Presentation/                # ItemPresenter, EffectPresenter, ActionPresenter,
│   │                                 # HeroPresenter, WalletPresenter, ShopPresenter,
│   │                                 # InventoryPresenter, StashPresenter, RunStatePresenter,
│   │                                 # CombatEventPresenter
│   ├── Domain/
│   │   ├── Engine/                  # Simulator, TickEngine, EventDispatcher, ActionProcessor,
│   │   │                             # StatusProcessor, EnrageProcessor, SimulationContext
│   │   │                             # (getSide() résout le camp d'un CombatBoard)
│   │   ├── Enum/                    # Trigger, Target, Rarity, ActionType, EventType, StatusType,
│   │   │                             # HeroSkillType, Side (PLAYER/OPPONENT)
│   │   ├── Event/                   # CombatEvent
│   │   ├── Model/                   # DTOs : Hero (skill optionnel), Item, Vestige, Effect, Action
│   │   ├── Player/                  # Inventory, Stash, HeroItemAllocator, HeroSkillDecorator
│   │   ├── Runtime/                 # CombatHero, CombatItem, CombatVestige, CombatBoard,
│   │   │                             # ActiveStatus
│   │   └── Shop/                    # Wallet, ShopOffer, Shop
│   └── Infrastructure/
│       └── Repository/Json/         # JsonHeroRepository, JsonItemRepository,
│                                     # JsonVestigeRepository, JsonScriptedOpponentRepository
├── tests/
│   ├── Application/                 # Tests de GameRun et de ses fabriques (Factory/)
│   ├── Http/                        # Request, ApiResponse, Router, Controller/RunController
│   ├── Persistence/                 # Schema, repositories, applier, factory, replayer
│   ├── Presentation/                # Tests des 10 presenters
│   ├── Domain/                      # Tests unitaires du moteur, de la boutique, de l'inventaire
│   ├── E2E/                         # Tests de bout en bout (fichiers prod -> simulation)
│   ├── Fixtures/                    # Fixtures de test isolées
│   ├── Infrastructure/               # Tests des repositories JSON
│   └── Support/                     # Traits partagés : CreatesRealGameRun, CreatesInMemoryDatabase
└── run.php                          # Point d'entrée CLI (délègue à GameRunFactory)

frontend/
├── src/
│   ├── api/
│   │   ├── enums.ts / types.ts      # DTOs miroir exact du contrat backend (vérifiés sur
│   │   │                             # fichiers réels, pas devinés)
│   │   ├── errors.ts                # RunNotFoundError / InvalidActionError / ConflictError,
│   │   │                             # miroir du mapping 404/400/409 du Router PHP
│   │   ├── runApi.ts                # Client HTTP typé, seul point d'appel à fetch()
│   │   └── runApi.test.ts           # Colocalisé (convention Vitest), fetch mocké
│   ├── stores/
│   │   ├── gameRun.ts               # Store Pinia : runId + state + lastCombatLog, seule
│   │   │                             # source de vérité ; runApi mocké en entier dans les tests
│   │   └── gameRun.test.ts
│   ├── composables/                 # À venir (useShop, etc.)
│   ├── components/                  # À venir (ShopView, CombatLogView, ...) — hors périmètre
│   │                                 # testé (décision : logique testée, UI visuelle non testée)
│   └── App.vue
├── vite.config.ts                   # Proxy /runs → backend PHP en dev
├── vitest.config.ts                 # Séparé de vite.config.ts (clé `test` non reconnue par
│                                     # defineConfig de 'vite')
├── eslint.config.js / .prettierrc.json
└── package.json                     # scripts : dev, build, preview, test, format, lint, typecheck
```

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
- [x] Boutique / économie (`Wallet`, `ShopOffer`, `Shop`, `ShopFactory` — tirage seedé plafonné en rareté)
- [x] Orchestration de la boucle de jeu (`GameRun` : wallet, manches, boutique, combat, condition de fin de run)
- [x] Catalogue de héros enrichi (10 héros, 5 shadow / 5 neutral) et roster de 3 héros tiré à la seed (`HeroRosterFactory`, premier héros contraint sur l'affinité du Vestige)
- [x] Inventaire hero-aware (`Inventory`/`AssignedItem`) séparé du coffre (`Stash`) et affectation automatique à l'achat par budget de slots (`HeroItemAllocator`)
- [x] IA scriptée à composition fixe par héros et difficulté croissante par manche (`ScriptedOpponentFactory`, `config/game/scripted_opponent.json`)
- [x] `GameRun::playRound()` construit lui-même le plateau du joueur à partir de son inventaire courant — la boucle V1 est jouable de bout en bout mécaniquement, validée sur `php run.php` avec seed fixe
- [x] Système de compétences de héros (`HeroSkillType`, `HeroSkillDecorator`) : modificateur passif appliqué aux objets d'un héros à l'assemblage du plateau, jamais pendant le combat. Catalogue V1 de 10 compétences câblé de bout en bout, validé unitairement, en intégration et en E2E sur les 10 héros réels
- [x] Couche de présentation (`ItemPresenter`, `EffectPresenter`, `ActionPresenter`, `HeroPresenter`, `WalletPresenter`, `ShopPresenter`, `InventoryPresenter`, `StashPresenter`, `RunStatePresenter`) — traduction pure domaine → JSON, aucune logique dupliquée
- [x] Persistance de run par journal d'actions rejouable (schéma SQLite, `GameRunRepository`, `GameRunActionsRepository`, `GameRunActionApplier`, `GameRunFactory` unifiée avec `run.php` et les tests, `GameRunReplayer`)
- [x] Point d'entrée applicatif réel imposant l'ordre boutique → combat — désormais garanti par la couche HTTP (`RunController`), qui journalise systématiquement un `OPEN_SHOP` à la création et après chaque manche
- [x] Couche HTTP maison (`Request`, `ApiResponse`, `Router` avec mapping d'exceptions → codes HTTP, `RunController` à 5 méthodes, `Response`, `public/index.php`) : les 5 endpoints du contrat sont implémentés, testés et vérifiés manuellement de bout en bout (vrai serveur PHP, vraie base SQLite sur disque)
- [x] Log de combat exposé via l'API (`Side` enum, `SimulationContext::getSide()`, `targetSide`/`sourceSide`/`sourceItemId` enrichis sur les events d'`ActionProcessor`/`StatusProcessor`/`EnrageProcessor`, `GameRun::getLastCombatResult()`, `CombatEventPresenter`) — `resolveRound` expose désormais `{ state, combatLog }`, seul endpoint à le faire
- [x] Squelette frontend Vue.js 3 + TypeScript (Vite, Pinia) : client API typé (`runApi`, DTOs miroir du contrat réel, mapping d'erreurs 404/400/409), store `gameRun` (runId/state/lastCombatLog, garde fail-fast sans run active), tooling ESLint + Prettier + `vue-tsc`, tests Vitest ciblés sur la logique (API + store), UI non testée par choix
- [ ] Écrans Vue (boutique cliquable, écran de combat brut affichant `combatLog`, boucle complète)

Suite de tests automatisés :
- **Backend** : 226 tests / 908 assertions, CI (PHPUnit + PHPStan niveau 6 + PHP CS Fixer) verte.
- **Frontend** : 8 tests Vitest (client API + store), ESLint/Prettier/`vue-tsc` propres — UI non couverte par choix (tests ciblés sur la logique, pas sur le visuel).

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.

Discipline TDD stricte sur l'ensemble de la couche API (Presentation, Persistence, Http) et du moteur de simulation : rouge → implémentation minimale → vert → CS Fixer → PHPStan → commit, avec triangulation systématique dès qu'une branche conditionnelle ou une composition le justifie.

Côté frontend, rigueur de test volontairement plus ciblée qu'au backend : logique pure (client API, store, composables) testée en TDD via Vitest ; composants Vue (présentation visuelle) non testés unitairement, vérifiés à l'œil — même principe que `Response::send()` côté backend, jamais testé unitairement pour la même raison (uniquement vérifiable visuellement/manuellement).