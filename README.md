# Corebound - Projet Noyau

> Auto-battler / roguelike deckbuilder asynchrone, univers dark fantasy original **Les Héritiers du Vide**.

Projet personnel développé pour apprendre et expérimenter sur un moteur de simulation de combat déterministe, dans l'esprit de *The Bazaar* (Tempo), avec un univers propre inspiré (sans copie) de *La Voie des Ombres* (Brent Weeks).

## Stack technique

- **Backend** : PHP 8.3+, sans framework — moteur de simulation stateless (`CombatLog = f(Joueur A, Joueur B, Seed)`) exposé via une API HTTP JSON maison (routeur, requête/réponse et contrôleurs écrits à la main, sans bibliothèque tierce)
- **Persistance de run** : SQLite (`database/database.sqlite`), via un **journal d'actions rejouable** (event log + seed) plutôt qu'un instantané sérialisé — chaque requête HTTP reconstruit l'état courant en rejouant l'historique des actions sur un `GameRun` frais, sans toucher au domaine. Le schéma est **versionné** (`schema_version`, ligne unique) et une base obsolète est refusée au démarrage avec un message explicite plutôt qu'une erreur SQL brute ; chaque run porte l'**empreinte des catalogues** sous lesquels elle a été créée (`content_version`), et son rejeu est refusé si le contenu a changé depuis
- **Frontend** : Vue.js 3 + TypeScript (Vite), Pinia pour l'état — boucle de jeu jouable de bout en bout à l'écran (démarrer, boutique, combat, rejouer), avec le déroulé du combat rythmé en temps réel (playback à ticks simulés, pas un affichage instantané), les assets visuels et audio intégrés (illustrations, cadres, aura de rareté, portraits de héros, vidéo du Vestige, SFX synchronisés, musique de hub avec ducking pendant le combat), consomme l'API HTTP décrite ci-dessous
- **Bonus (non structurant)** : WebSocket/Mercure pour notifications et signaux de fin de combat

Le moteur de simulation reste pur et stateless (aucune notion de HTTP ou de base de données n'y pénètre) ; c'est la couche `Persistence` qui porte la responsabilité de faire survivre l'état d'une run entre deux requêtes HTTP, en rejouant ses actions plutôt qu'en sérialisant ses objets.

## Pourquoi ce stack

- Combats **automatiques**, calculés côté serveur : pas de temps réel dur à gérer
- PvP **asynchrone** (V2+) : affrontement contre une copie figée d'un plateau adverse, pas de matchmaking temps réel — la V1 utilise une IA scriptée à difficulté croissante, le PvP snapshot est différé après validation du moteur solo
- Cycle de vie court (stateless) en PHP : chaque combat est calculé, sauvegardé, puis la mémoire est libérée, pas de risque de fuite mémoire sur des milliers de combats
- SQLite plutôt qu'un serveur MySQL/PostgreSQL pour la V1 : aucune administration, un seul fichier portable — cohérent avec un futur packaging Electron/Tauri (sauvegarde locale = un fichier, pas un service à faire tourner). Un vrai serveur de base de données ne redevient nécessaire qu'au moment du PvP asynchrone (plusieurs joueurs concurrents), pas avant.

## Lore & direction artistique

Socle créatif construit hors-code (session 016), en amont de toute production d'asset réel, pour garantir une cohérence de fond entre l'univers, la mécanique et le visuel :

- **Bible de lore** (`docs/lore/corebound-lore-bible.md`) : nature du Vide (la Tressure, l'Effilochement), nature du lien Vestige-porteur — ancré directement dans la structure de run roguelike (le joueur incarne la conscience du Vestige, chaque run est une tentative avec un candidat, 3 défaites cumulées rompent le lien, 10 victoires prouvent qu'un point de fixation stable a été recréé), manifestation par les fils (marque littérale sur la peau, à l'origine du vocabulaire employé pour tout le reste), sélection par tempérament plutôt que par caste, perception sociale régionale (racine narrative du futur système de synergies d'affinité, V2+).
- **Guide de direction artistique**, bilingue (`docs/art/corebound-art-style-guide-fr.md` / `-en.md`) : direction générale (peinture semi-réaliste, base froide désaturée + un seul accent saturé par affinité), séparation stricte rareté (portée par l'aura CSS existante, `--common`/`--rare`/`--legendary`) / affinité (portée uniquement par l'illustration), hiérarchie de cadres à trois identités (Vestige en fils noués, héros en pierre/métal ouvragé + fils partiels si affinité non neutre, items en matière neutre — le motif de fil restant strictement réservé au vivant), formats techniques par type d'asset (héros en portrait 3:4/4:5, items et Vestige en carré 1:1), gabarit complet de l'affinité Ombre avec un fragment de style réutilisable pour la génération IA.
- **Guide de prompts pour les items** (`docs/art/corebound-item-render-prompts.md`) : fragments de style réutilisables (neutre / Ombre) combinés à une description courte par objet, pour les 30 items du catalogue V1.

Tous les assets visuels de la V1 (héros, items, Vestige animé, plateau, coffre, cadres) ont été générés par IA à partir de ce guide et rangés sous `frontend/public/assets/` (voir structure du projet ci-dessous). **Câblage visuel désormais complet** (session 017, `feature/frontend-visual-structure`) : illustration + cadre partagé + aura de rareté sur les objets (boutique, roster, coffre), portrait + cadre selon affinité sur les héros, image ouvert/fermé selon le contenu du coffre, vidéo en boucle + cadre sur le Vestige — hors plateau/hub (`board/`), qui reste sans composant correspondant et attend une refonte de layout séparée. La direction visuelle globale du frontend (`style.css`, tokens sobres posés en session 014) reste, elle, un chantier séparé non commencé.

## Cahier des charges V1

- **Vestige fixe, roster de héros construit progressivement par le joueur** : `shadow_vestige` (affinité, `baseHp`/`baseShield`, `startingGold`, `startingIncome`) est fixe et sans choix du joueur, exposé tel quel par l'API (`VestigePresenter`). Le roster du joueur, lui, commence **vide** à la création du run et se construit via une offre de 3 candidats proposée en manches 1, 3 et 5 (`HeroOffer`, `HeroOfferGenerator`, `GameRun::chooseHero()`) : en manche 1, un candidat de l'affinité du Vestige est garanti (tirage uniforme sur le reste) ; en manches 3 et 5, le tirage est pondéré (`WeightedDraw`, algorithme d'Efraimidis-Spirakis — poids ×2.0 pour l'affinité du Vestige, ×1.0 sinon), en excluant les héros déjà recrutés. Tant qu'une offre est en attente (`pendingHeroOffer`), la boutique reste fermée ; choisir un héros (`chooseHero()`) consomme l'offre et rouvre automatiquement la boutique. `itemSlots` varie selon le héros (`heroes.json`).
- **`CombatBoard`** : 1 Vestige + 1 à 3 héros, budget d'objets total dérivé de la somme des `itemSlots` du roster (6 avec 3 héros à 2 emplacements chacun)
- **3 raretés** : Commune (x1) / Rare (x1.5) / Légendaire (x2.5)
- **30 objets** en contenu réel, thème assassin/ombre (14 Common / 11 Rare / 5 Legendary)
- **4 statuts** : Poison (ignore le bouclier), Burn (brise-défense : 150 % de la valeur sur le bouclier, puis 70 % du surplus sur les PV, en arithmétique entière), Regen (soin dans le temps), Ward (bouclier dans le temps). **Modèle par instances indépendantes** (D-20) : chaque application crée sa propre instance porteuse de ses stacks, de sa durée et de son objet source, sans jamais fusionner avec une instance existante. Le régime permanent se stabilise seul entre `floor` et `ceil(durée / cooldown)` par source, sans qu'aucun plafond soit écrit. Les `CombatEvent` n'exposent qu'une projection agrégée par type et par tick, somme des stacks vivants et maximum des durées restantes (`AggregatedStatus`)
- **Le soin nettoie** (D-21) : chaque déclenchement d'une action `HEAL` retire 1 stack de `POISON` et 1 de `BURN`, sur l'instance à la plus longue durée restante, **sur le soin tenté et non réalisé** — un Vestige à pleine vie se purge donc quand même. `StatusType::isHostile()` est la seule source de la distinction hostile/bénéfique
- **Système d'enrage** : au-delà d'un certain tick, dégâts croissants exponentiellement infligés aux deux plateaux — force la résolution d'un combat, protège les builds purement défensifs d'un stalemate perdant par défaut
- **Compétences de héros** : chaque héros peut porter une compétence passive (`Hero::$skill`, `HeroSkillType`) qui filtre et modifie les objets qui lui sont assignés au moment de l'assemblage du plateau (`HeroSkillDecorator`, appliqué dans `CombatBoardFactory`) — jamais pendant le combat, le moteur de simulation reste inchangé. Catalogue V1 de 10 compétences (bonus de valeur d'action, bonus de stack de statut, modificateurs composés dégâts+vitesse pour les archétypes `TWO_HAND` et double `ONE_HAND`), assignées aux 10 héros réels de `heroes.json` (`SAVAGE` partagé par deux héros d'affinités différentes, `RESURGENT` non assigné, laissé en réserve)
- **Boutique** : 4 offres aléatoires par visite (tirage seedé, plafonné à 1 objet Légendaire par visite), prix croissant avec la rareté. Rouverte automatiquement après chaque manche non terminale (`GameRun::playRound()`), vidée si la manche termine le run
- **Inventaire du joueur** : `Inventory` (objets équipés, chacun assigné à un héros précis du roster via `AssignedItem`) + un coffre `Stash` de 6 emplacements (objets achetés en surplus, sans héros assigné) ; échange manuel possible entre inventaire et coffre (`GameRun::swapWithStash()`), affectation automatique à l'achat via `HeroItemAllocator` (premier héros du roster ayant assez de budget restant). Réattribution manuelle d'un objet **entre deux héros** (sans passer par le coffre) explicitement hors scope V1 — notée en Roadmap V2+, à trancher explicitement si le besoin devient réel
- **Économie de run** : or de départ (`startingGold`, une fois) + revenu de manche (`startingIncome`, à chaque manche gagnée ou perdue) + récompense de victoire (`+10` or fixe)
- **Boucle** (`GameRun::playRound()`) : construit le plateau du joueur à partir de son inventaire courant, génère l'adversaire scripté, lance le combat, comptabilise le résultat, avance la manche, rouvre une boutique si le run continue
- **Adversaire scripté** : composition fixe par héros (`config/game/scripted_opponent.json`), révélée progressivement selon un budget global croissant par manche — pas de tirage aléatoire côté IA, volontairement déterministe et indépendant du catalogue jouable par le joueur. Sa composition (roster + assignation d'items, `OpponentBoard`) est désormais conservée par `GameRun` et exposée après combat, distincte de l'inventaire du joueur (`OpponentAssignment`, valeur immuable, pas de réutilisation d'`Inventory`/`AssignedItem` — sémantiquement faux pour un board scripté en lecture seule)
- **Côté d'un événement de combat** : chaque `CombatEvent` porte un `targetSide` (`A`/`B`, **libellés neutres** — D-19), et pour les events déclenchés par un objet (`DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL`, `APPLY_STATUS`) également un `sourceSide` et un `sourceItemId` — nécessaire car le Vestige du joueur et celui de l'adversaire scripté partagent le même id en V1 (`shadow_vestige`), donc `target` seul ne permet pas de distinguer les deux camps. Les libellés sont neutres parce qu'en PvP le serveur simule **une seule fois** et les deux joueurs lisent le même journal : un côté nommé « joueur » y serait faux pour l'un des deux. **A est le plateau dont la photographie canonique est la plus petite en octets**, l'ordre des arguments tranchant à égalité. C'est la réponse HTTP, et non le journal, qui dit au client quel côté est le sien (`viewerSide`)
- **Déterminisme du combat** : `CombatLog = f(photographie A, photographie B, combatSeed)`. Un combat archivé se rejoue **à l'octet près** sans catalogue ni base de données, et l'ordre dans lequel on présente les deux plateaux n'a aucun effet sur le journal produit
- **API HTTP** : les cinq actions de la boucle (créer une run, consulter l'état, acheter, échanger inventaire/coffre, résoudre une manche) sont exposées en JSON — voir [Contrat d'API](#contrat-dapi) ci-dessous
- **Persistance de run** : chaque action du joueur est journalisée (event log horodaté, `run_actions`) plutôt que l'état lui-même sérialisé ; l'état courant est reconstruit à la demande en rejouant ce journal sur un `GameRun` frais depuis sa seed d'origine
- **Pas de vrai PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking
- **Choix entre plusieurs marchands** : explicitement classé V2+, non planifié à court terme

## Contrat d'API

Aucune notion de compte joueur en V1 : une run est identifiée par un `run_id` opaque, généré à la création et à fournir à chaque appel suivant — pas de session ni de cookie, pour rester portable vers un futur client Electron/Tauri et préparer sans réécriture un futur `player_id` de PvP.

| Méthode | Route | Effet |
|---|---|---|
| `POST` | `/runs` | Crée une run (seed aléatoire, Vestige fixe) avec une offre de héros initiale en attente — aucune boutique n'est ouverte tant qu'aucun héros n'est choisi. Réponse : `{ run_id, state }` — seul endpoint à exposer `run_id` |
| `GET` | `/runs/{run_id}` | État courant complet (manche, or, Vestige, boutique, inventaire, coffre, roster, offre de héros en attente). Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/hero/choose` | `{ heroId }` — choisit un héros parmi l'offre en attente (`pendingHeroOffer`), l'ajoute au roster et ouvre automatiquement la boutique. Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/shop/buy` | `{ slotIndex }` — achète une offre de la boutique courante. Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/inventory/swap` | `{ inventoryIndex, stashIndex, heroId }` — échange un objet entre inventaire et coffre. Réponse : `{ state }` |
| `POST` | `/runs/{run_id}/round/resolve` | Résout la manche courante (combat). Ouvre automatiquement la boutique suivante si la run continue — sauf en manches 3 et 5, où une nouvelle offre de héros est générée à la place (la boutique reste fermée jusqu'au choix). Vide la boutique si cette manche termine le run. Réponse : `{ state, combatLog, viewerSide, opponentRoster, opponentInventory }` — seul endpoint à exposer le log du combat qui vient d'être résolu, le côté (`"A"` ou `"B"`) occupé par le plateau du joueur, et la composition de l'adversaire affronté |

Toutes les réponses sont du JSON, sérialisé par une couche `Presentation` dédiée (jamais le domaine directement). Les erreurs métier du domaine sont traduites en codes HTTP par le routeur : run introuvable → `404`, argument invalide (index hors bornes, payload malformé) → `400`, état incohérent (achat déjà effectué, fonds insuffisants) → `409`.

Un corps d'erreur porte toujours `error`, et **facultativement** `code`, un identifiant machine réservé aux refus que le statut HTTP ne distingue pas. Un seul aujourd'hui :

```json
{
  "error": "Run \"...\" was created under content version ..., but the current content is ...",
  "code": "CONTENT_VERSION_MISMATCH"
}
```

C'est le refus de rejouer une run dont les catalogues ont changé. Il sort en `409` comme un conflit de séquence, mais appelle la réaction inverse — un conflit de séquence se corrige en rejouant autrement, celui-ci ne se corrige pas du tout. Les autres réponses d'erreur ne portent pas de `code`, et un `code` posé sur tous les `409` ne distinguerait rien.

`POST /runs` accepte par ailleurs un corps JSON optionnel `{ "seed": 42 }` — entier strict, tout autre type est rejeté en `400` — qui rend la run entièrement reproductible. En son absence, la graine est tirée aléatoirement.

`state.vestige` (nouveau) a cette forme :

```json
{
  "id": "shadow_vestige",
  "name": "Shadow Vestige",
  "affinity": "shadow",
  "baseHp": 100,
  "baseShield": 10,
  "startingGold": 20,
  "startingIncome": 5
}
```

`state.pendingHeroOffer` (nouveau) vaut `null` en dehors d'un choix de héros, ou une liste de 3 candidats en attente (manches 1, 3, 5) :

```json
{
  "pendingHeroOffer": [
    { "id": "shadow_bearer", "name": "Shadow's bearer", "affinity": "shadow", "itemSlots": 2, "skill": null }
  ]
}
```

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
    "target": "shadow_vestige",
    "targetSide": "B",
    "sourceSide": "A",
    "sourceItemId": "shadow_dagger"
  }
}
```

`targetSide` et `sourceSide` valent `"A"` ou `"B"`, jamais `"PLAYER"`/`"OPPONENT"`. Le journal ne sait pas qui regarde : c'est `viewerSide`, dans l'enveloppe de la réponse, qui le dit. En V1 les deux Vestiges portent d'ailleurs le **même** `target` (`shadow_vestige`), le catalogue n'en comptant qu'un — seul le côté les distingue.

`sourceSide`/`sourceItemId` ne sont présents que pour les events déclenchés par un objet (`DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL_RECEIVED`, `STATUS_APPLIED`) — un statut qui pulse (`STATUS_DAMAGE_DEALT`, etc.) ou l'enrage (`ENRAGE_DAMAGE_DEALT`) n'ont pas de source ponctuelle et ne portent que `targetSide`.

La composition de l'adversaire affronté (`opponentRoster` + `opponentInventory`, exposés par `POST /runs/{run_id}/round/resolve`) permet de résoudre `sourceItemId`/`sourceSide` du log en noms lisibles côté adversaire, symétriquement à `roster`/`inventory` côté joueur :

```json
{
  "opponentRoster": [
    { "id": "shadow_hero_1", "name": "Shadow's Bearer", "affinity": "shadow", "itemSlots": 2, "skill": null }
  ],
  "opponentInventory": {
    "items": [
      { "item": { "id": "dagger", "name": "Dagger", "...": "..." }, "heroId": "shadow_hero_1" }
    ]
  }
}
```

## Structure du projet

```
docs/
├── lore/
│   └── corebound-lore-bible.md      # Bible de lore (le Vide, les Vestiges, perception sociale, ton)
└── art/
    ├── corebound-art-style-guide-fr.md   # Guide de direction artistique, version de travail
    ├── corebound-art-style-guide-en.md   # Guide de direction artistique, version prompts IA
    └── corebound-item-render-prompts.md  # Fragments de style + description par item (30 objets)

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
│   │   ├── GameRun.php              # Orchestrateur de run : wallet, roster (vide à la
│   │   │                             # construction, peuplé progressivement via chooseHero()),
│   │   │                             # pendingHeroOffer (offre en attente en manches 1/3/5,
│   │   │                             # bloque openShop()/purchaseItem() tant qu'elle existe),
│   │   │                             # inventaire/coffre, manches, boutique (réouverture/vidage
│   │   │                             # automatique après chaque manche, sauf en 3/5 où une
│   │   │                             # nouvelle offre remplace la réouverture), combat (conserve
│   │   │                             # le dernier SimulationResult et la composition adverse via
│   │   │                             # getLastCombatResult()/getLastOpponentRoster()/
│   │   │                             # getLastOpponentAssignments()), condition de fin de run,
│   │   │                             # getVestige() (accesseur au Vestige injecté)
│   │   ├── CombatSeed.php           # Graine propre à un combat : sha256('combat|<seed>|<round>').
│   │   │                             # Le calcul appartient à l'Application, la dérivation des
│   │   │                             # deux flux au Domaine — c'est ce découpage qui permet au
│   │   │                             # moteur embarqué de rejouer sans connaître la seed du run
│   │   ├── RoundOutcome.php          # Enum VICTORY/DEFEAT, charge utile de RESOLVE_ROUND
│   │   └── Factory/                 # GameRunFactory (câblage unique des 7 dépendances d'un
│   │                                 # GameRun, partagé par run.php, les tests et le replayer),
│   │                                 # CombatBoardFactory, ShopFactory, HeroOfferGenerator
│   │                                 # (buildInitialOffer()/buildWeightedOffer(), remplace
│   │                                 # HeroRosterFactory — supprimée, le roster n'est plus
│   │                                 # peuplé automatiquement à la construction),
│   │                                 # ScriptedOpponentFactory (retourne un OpponentBoard :
│   │                                 # board + roster + assignments, pas un CombatBoard nu),
│   │                                 # OpponentBoard (value object de sortie)
│   ├── Http/
│   │   ├── Request.php              # Requête HTTP testable (fromGlobals() / fake())
│   │   ├── ApiResponse.php          # Valeur pure (statusCode + body), sans I/O
│   │   ├── Response.php             # Émission réelle (headers/body/exit), non unit-testable
│   │   ├── Router.php               # Routeur maison (regex + mapping d'exceptions → HTTP)
│   │   └── Controller/
│   │       └── RunController.php    # Les 6 actions du contrat d'API (create() ne journalise
│   │                                 # plus d'OPEN_SHOP automatique — chooseHero() est le
│   │                                 # véritable point de transition vers la boutique ;
│   │                                 # resolveRound expose { state, combatLog, opponentRoster,
│   │                                 # opponentInventory })
│   ├── Persistence/
│   │   ├── Schema.php               # Création idempotente du schéma SQLite + table
│   │   │                             # schema_version (ligne unique, version 3 : runs,
│   │   │                             # run_actions, combat_records). initialize() crée et
│   │   │                             # estampille, assertUpToDate() contrôle et refuse — deux
│   │   │                             # méthodes distinctes, les fusionner imposerait les deux
│   │   │                             # effets à tout appelant. Aucun ALTER TABLE : une base
│   │   │                             # obsolète est refusée, jamais rattrapée (D-18)
│   │   ├── ObsoleteSchemaException.php  # Étend RuntimeException et non LogicException, pour ne
│   │   │                             # jamais être transformée en 409 par le Router — le refus
│   │   │                             # est un 503 émis par le bootstrap, hors du Router
│   │   ├── ContentVersionMismatchException.php  # Étend LogicException. Le Router la capture
│   │   │                             # AVANT le cas générique et y attache le code machine
│   │   │                             # CONTENT_VERSION_MISMATCH
│   │   ├── GameRunRecord.php / GameRunRepository.php       # Table `runs` (id, seed, vestige_id,
│   │   │                             # content_version TEXT NOT NULL, created_at)
│   │   ├── CombatRecordsRepository.php  # Table `combat_records` (run_id, round, board_a,
│   │   │                             # board_b, combat_seed, engine_version, resolution,
│   │   │                             # winner_side, created_at ; clé composite (run_id, round)).
│   │   │                             # ÉCRITURE SEULE : personne ne relit encore ces lignes, et
│   │   │                             # une forme de retour écrite aujourd'hui serait figée avant
│   │   │                             # qu'on sache ce qu'on en attend. Les enveloppes sont
│   │   │                             # stockées telles que CanonicalJson les écrit, jamais
│   │   │                             # éclatées en colonnes SQL
│   │   ├── GameRunActionRecord.php / GameRunActionsRepository.php  # Table `run_actions`
│   │   ├── GameRunActionType.php    # Enum technique (rejeu), distinct des enums du Domaine —
│   │   │                             # OPEN_SHOP/PURCHASE/SWAP/RESOLVE_ROUND/CHOOSE_HERO
│   │   ├── GameRunActionApplier.php # Traduit une action journalisée en appel réel sur GameRun
│   │   │                             # (match exhaustif sur les 5 cas, pas de branche default)
│   │   ├── GameRunReplayer.php      # Reconstruit un GameRun vivant à partir d'un run_id.
│   │   │                             # Porte la garde de version de contenu — entonnoir unique
│   │   │                             # des six points d'entrée du contrôleur, contrôle placé
│   │   │                             # avant la reconstruction ET avant la lecture des actions
│   │   │                             # (une run sans action a déjà un état)
│   │   └── RunNotFoundException.php # Distincte d'InvalidArgumentException (404 vs 400)
│   ├── Presentation/                # ItemPresenter, EffectPresenter, ActionPresenter,
│   │                                 # HeroPresenter, WalletPresenter, ShopPresenter,
│   │                                 # InventoryPresenter, StashPresenter, VestigePresenter,
│   │                                 # RunStatePresenter (expose désormais pendingHeroOffer,
│   │                                 # null en dehors d'un choix de héros), CombatEventPresenter,
│   │                                 # OpponentInventoryPresenter
│   ├── Domain/
│   │   ├── Engine/                  # Simulator, TickEngine, EventDispatcher, ActionProcessor,
│   │   │                             # StatusProcessor, EnrageProcessor, SimulationContext
│   │   │                             # (getSide() résout le côté A/B d'un CombatBoard,
│   │   │                             # getBoards() les rend dans l'ordre canonique),
│   │   │                             # CanonicalJson (règles d'octets partagées : ksort
│   │   │                             # SORT_STRING sur les clés texte, listes jamais triées,
│   │   │                             # flottants refusés avec le chemin fautif nommé),
│   │   │                             # CombatLogSerializer, CombatSeed / RandomStream
│   │   │                             # (deux flux `order` et `effects` dérivés par
│   │   │                             # sha256(tag ‖ combatSeed)), EngineVersion::CURRENT
│   │   ├── Snapshot/                # L'ALLER — BoardSnapshot (la photographie : objets DÉJÀ
│   │   │                             # décorés, définition du Vestige, héros et compétences, or
│   │   │                             # de début de combat — champs nuls omis, jamais écrits
│   │   │                             # null), SnapshotRecipe et BoardRecord (l'enveloppe de
│   │   │                             # provenance : recette, contentVersion, FORMAT_VERSION,
│   │   │                             # engineVersion), CombatRecord (la rencontre : deux
│   │   │                             # enveloppes rangées par côté, combatSeed, resolution,
│   │   │                             # winnerSide).
│   │   │                             # LE RETOUR — BoardHydrator::fromCanonicalJson(string) :
│   │   │                             # ne reçoit QU'UNE CHAÎNE, pas de dépôt ni de chemin de
│   │   │                             # configuration, si bien que relire un catalogue au rejeu
│   │   │                             # est impossible et non seulement déconseillé.
│   │   │                             # HydratedVestigeProfile / HydratedHeroProfile implémentent
│   │   │                             # les deux ports de Runtime ; UnreadableBoardRecordException
│   │   │                             # est le refus unique, quelle que soit la façon dont la
│   │   │                             # ligne est abîmée
│   │   ├── Content/                 # ContentVersion : la RÈGLE d'empreinte (sha256 de
│   │   │                             # l'enveloppe canonique des quatre catalogues), pure,
│   │   │                             # sans accès disque, et le jeu exact des catalogues est
│   │   │                             # vérifié et non supposé
│   │   ├── Enum/                    # Trigger, Target, Rarity, ActionType, EventType, StatusType,
│   │   │                             # HeroSkillType, Side (A/B — libellés neutres, pour que les
│   │   │                             # deux joueurs d'un futur PvP lisent le même journal)
│   │   ├── Event/                   # CombatEvent
│   │   ├── Model/                   # DTOs : Hero (skill optionnel), Item, Vestige, Effect,
│   │   │                             # Action, OpponentAssignment (item + heroId, dédié à la
│   │   │                             # composition adverse, distinct d'AssignedItem/Inventory),
│   │   │                             # HeroOffer (VO immuable : 3 candidats, contains()/find(),
│   │   │                             # invariants cardinalité + anti-doublon)
│   │   │   └── Draw/                # WeightedDraw (tirage pondéré sans remise, algorithme
│   │   │                             # d'Efraimidis-Spirakis, pur — reçoit des flottants déjà
│   │   │                             # tirés, jamais un Randomizer directement)
│   │   ├── Player/                  # Inventory, Stash, HeroItemAllocator, HeroSkillDecorator
│   │   ├── Runtime/                 # CombatHero, CombatItem, CombatVestige, CombatBoard,
│   │   │                             # ActiveStatus, AggregatedStatus, et les deux PORTS de
│   │   │                             # profil de combat : VestigeProfile (id/baseHp/baseShield)
│   │   │                             # et HeroProfile (id/skill). Déclarés ici et non auprès
│   │   │                             # des modèles — c'est le combat qui énonce son besoin, le
│   │   │                             # catalogue qui s'y conforme. Sans cette inversion, rejouer
│   │   │                             # une archive imposerait d'inventer nom, affinité et
│   │   │                             # emplacements, que la photographie ne porte pas
│   │   └── Shop/                    # Wallet, ShopOffer, Shop
│   └── Infrastructure/
│       ├── Content/                 # ContentCatalogReader : le LECTEUR. Dérive les noms de
│       │                             # fichiers de ContentVersion::CATALOGS (jamais un glob),
│       │                             # gèle l'empreinte au premier appel, et rhabille toute
│       │                             # panne de configuration en RuntimeException — un catalogue
│       │                             # absent ou corrompu est une faute de déploiement, pas un
│       │                             # 400 ni un 409
│       └── Repository/Json/         # JsonHeroRepository, JsonItemRepository,
│                                     # JsonVestigeRepository, JsonScriptedOpponentRepository
├── tests/
│   ├── Application/                 # Tests de GameRun et de ses fabriques (Factory/)
│   ├── Http/                        # Request, ApiResponse, Router, Controller/RunController
│   ├── Persistence/                 # Schema, repositories, applier, factory, replayer
│   ├── Presentation/                # Tests des presenters (dont VestigePresenter)
│   ├── Domain/                      # Tests unitaires du moteur, de la boutique, de l'inventaire
│   ├── E2E/                         # Tests de bout en bout (fichiers prod -> simulation)
│   ├── Fixtures/                    # Fixtures de test isolées
│   ├── Infrastructure/               # Tests des repositories JSON et du lecteur de catalogues
│   ├── Determinism/                 # NF-01 — ReferenceCombatReplayTest (relit quatre fichiers
│   │   │                             # figés, hydrate, resimule, compare le journal octet pour
│   │   │                             # octet : l'ancre de non-régression du moteur) et
│   │   │                             # ArchivedCombatReplayTest (joue une vraie run, l'archive,
│   │   │                             # relit la ligne en SQL direct, resimule et compare le
│   │   │                             # combat À LUI-MÊME — aucune constante figée, donc
│   │   │                             # insensible à un rééquilibrage)
│   │   └── fixtures/reference-combat/   # board-a.json, board-b.json, combat-log.json, meta.json
│   │                                 # Capturés d'une vraie run (graine 46, manche 6). Combat
│   │                                 # MIROIR : les deux camps portent le même id de Vestige,
│   │                                 # seul le côté les distingue
│   └── Support/                     # Traits partagés : CreatesRealGameRun, CreatesInMemoryDatabase
├── capture-reference-combat.php     # Régénère la fixture ci-dessus. Committé avec elle : sans
│                                     # lui, quatre fichiers que personne ne sait reproduire.
│                                     # `--scan` mesure la couverture en types d'événement de
│                                     # plusieurs graines sans rien écrire
└── run.php                          # Point d'entrée CLI (délègue à GameRunFactory)

frontend/
├── public/
│   └── assets/                      # Servi tel quel par Vite (pas src/assets/) : nécessaire pour
│       │                             # résoudre un chemin par id à la volée (item.id/hero.id),
│       │                             # sans import statique fichier par fichier — résolu par
│       │                             # composables/assetPaths.ts, jamais construit en dur ailleurs
│       ├── heroes/                  # Un .jpg par id de heroes.json (portrait 3:4/4:5)
│       │   └── frames/               # neutral.png / shadow.png (cadre pierre/métal + fils partiels,
│       │                             # partagé par affinité, pas par héros)
│       ├── items/                   # Un .jpg par id de items.json (carré 1:1) + frame.png (cadre
│       │                             # unique partagé par tous les objets, quelle que soit l'affinité)
│       ├── vestiges/                 # shadow_vestige.mp4 (boucle continue, muet) +
│       │   │                         # shadow_vestige_poster.jpg (affiché pendant le chargement)
│       │   └── frames/               # shadow.png (cadre fils noués ; marge transparente ~9,6%
│       │                             # intégrée au fichier, compensée par un zoom CSS empirique
│       │                             # côté VestigePanel.vue, pas par le fichier lui-même)
│       ├── stash/                   # close.jpg (coffre vide) / open.jpg (coffre non vide)
│       ├── board/                   # Plateau/hub (vue du dessus, symétrique) — pas encore
│       │                             # intégré, aucun composant de layout ne correspond à une vue
│       │                             # de plateau ; exclu explicitement de la passe visuelle 017
│       └── audio/
│           ├── sfx/                 # weapon_hit / shield_gain / heal / poison_tick / burn_tick /
│           │                         # chest_open / chest_close / shop_purchase / round_victory /
│           │                         # round_defeat / hover / ui_click — mappés depuis un
│           │                         # CombatEventDTO par combatEventSoundFile() ; STATUS_APPLIED
│           │                         # et STATUS_EXPIRED restent volontairement silencieux (pas
│           │                         # de fichier dédié), round_victory/round_defeat/hover/
│           │                         # ui_click/chest_* pas encore câblés (pas de point
│           │                         # d'accroche UI pour l'instant)
│           └── music/
│               └── hub_ambiance.ogg # Boucle longue, jouée par useHubMusic.ts, volume et
│                                     # activation pilotés par le store audioSettings
├── src/
│   ├── api/
│   │   ├── enums.ts / types.ts      # DTOs miroir exact du contrat backend (dont VestigeDTO),
│   │   │                             # vérifiés sur fichiers réels, pas devinés
│   │   ├── errors.ts                # RunNotFoundError / InvalidActionError / ConflictError,
│   │   │                             # miroir du mapping 404/400/409 du Router PHP
│   │   ├── runApi.ts                # Client HTTP typé, seul point d'appel à fetch()
│   │   │                             # (dont chooseHero(runId, heroId))
│   │   └── runApi.test.ts           # Colocalisé (convention Vitest), fetch mocké
│   ├── stores/
│   │   ├── gameRun.ts               # Store Pinia : runId + state + chooseHero() (symétrique à
│   │   │                             # buyItem/swapItem, met à jour state depuis la réponse
│   │   │                             # serveur) + lastCombatLog +
│   │   │                             # visibleCombatLog + isPlayingBack +
│   │   │                             # opponentRoster/opponentInventory + participantResolver
│   │   │                             # (computed). resolveRound() ne commit plus `state`
│   │   │                             # immédiatement : la réponse est différée jusqu'à la fin
│   │   │                             # du playback (startCombatPlayback), pour ne pas spoiler
│   │   │                             # le résultat (victoires/or) avant que le combat se soit
│   │   │                             # visuellement terminé. opponentRoster/opponentInventory,
│   │   │                             # eux, restent commités immédiatement (nécessaires pour
│   │   │                             # résoudre les noms pendant l'animation). Chaque nouvel
│   │   │                             # event révélé par le playback déclenche un SFX via
│   │   │                             # playCombatSfx (delta calculé sur la longueur du log
│   │   │                             # visible). startNewRun() stoppe un playback en cours
│   │   │                             # avant de repartir (couverture non testée, notée en
│   │   │                             # commentaire). runApi mocké en entier dans les tests,
│   │   │                             # Audio simulé via un stub FakeAudio (absent de jsdom)
│   │   ├── gameRun.test.ts
│   │   ├── audioSettings.ts         # Store Pinia pur : enabled/volume (défaut désactivé, 70%,
│   │   │                             # pour respecter la politique d'autoplay des navigateurs)
│   │   │                             # + effectiveVolume (computed, dépend de
│   │   │                             # gameRun.isPlayingBack pour un ducking à 40% du volume
│   │   │                             # choisi pendant le combat, sans fondu)
│   │   └── audioSettings.test.ts
│   ├── composables/                 # formatCombatEvent (9 EventType → CombatEventDisplay :
│   │   │                             # segments colorés + sourceSide, pas une simple string),
│   │   │                             # buildParticipantResolver (résolution héros/item,
│   │   │                             # joueur + adversaire, isolée par side), formatItemEffect
│   │   │                             # (description statique d'un item), useItemSwapSelection
│   │   │                             # (état UI partagé pour l'échange héros ↔ coffre),
│   │   │                             # combatPlayback.ts (startCombatPlayback : horloge à tick
│   │   │                             # simulée 100ms/tick, révélation cumulative des events,
│   │   │                             # stop() d'annulation, complète immédiatement sans timer
│   │   │                             # si le log est vide ou se termine au tick 0),
│   │   │                             # assetPaths.ts (résolution pure de tous les chemins
│   │   │                             # d'assets par id/affinité, vérifiée contre l'arborescence
│   │   │                             # réelle du disque), combatEventSound.ts
│   │   │                             # (combatEventSoundFile : CombatEventDTO → nom de fichier
│   │   │                             # SFX ou null) — logique testée (Vitest). combatSfxPlayer.ts
│   │   │                             # (playCombatSfx, volume fixe indépendant du ducking
│   │   │                             # musique) et useHubMusic.ts (Audio de la musique de hub,
│   │   │                             # réactif à enabled/effectiveVolume) sont des effets de
│   │   │                             # bord réels, non testés, vérifiés à l'oreille
│   ├── components/
│   │   ├── combat/CombatLogView.vue # Fenêtre à hauteur fixe + scroll interne + auto-scroll bas ;
│   │   │                             # fond de ligne vert/rouge selon l'acteur (sourceSide), valeur
│   │   │                             # colorée par nature (dégâts/poison/burn/bouclier/soin) ;
│   │   │                             # consomme désormais visibleCombatLog (pas lastCombatLog),
│   │   │                             # donc se remplit au rythme réel du playback — non testé (UI)
│   │   ├── shop/ShopView.vue        # Liste des offres, achat ; chaque offre affiche désormais
│   │   │                             # illustration + cadre partagé + aura de rareté (box-shadow
│   │   │                             # sur les tokens --common/--rare/--legendary existants) —
│   │   │                             # non testé (UI)
│   │   ├── vestige/VestigePanel.vue # Vidéo en boucle muette (poster pendant le chargement) +
│   │   │                             # cadre superposé (zoom CSS empirique pour compenser la
│   │   │                             # marge transparente du fichier source), nom, affinité,
│   │   │                             # PV/bouclier colorés, or de départ/revenu — non testé (UI)
│   │   ├── hero/HeroOfferPanel.vue  # Écran de choix de héros (manches 1/3/5) : 3 cartes
│   │   │                             # cliquables (portrait + cadre par affinité), clic = choix
│   │   │                             # immédiat sans confirmation, état local isChoosingHero
│   │   │                             # pour désactiver les cartes pendant l'appel réseau
│   │   │                             # (protection double-clic) — non testé (UI)
│   │   ├── hero/HeroRosterPanel.vue # Portrait + cadre selon affinité par héros, compétence,
│   │   │                             # objets équipés (miniature illustration + cadre + aura de
│   │   │                             # rareté) sélectionnables pour l'échange avec le coffre —
│   │   │                             # itère génériquement sur roster (1 à 3 héros selon les
│   │   │                             # choix faits), aucune hypothèse sur sa taille —
│   │   │                             # non testé (UI)
│   │   └── stash/StashPanel.vue     # Image ouvert/fermé selon le contenu du coffre, miniatures
│   │                                 # d'objets (même patron que le roster), bouton d'échange
│   │                                 # vers le héros sélectionné, remonte les erreurs backend
│   │                                 # (budget de slots dépassé) — non testé (UI)
│   └── App.vue                      # Boucle complète : démarrer/rejouer, écran de choix de
│                                     # héros (HeroOfferPanel, tant que pendingHeroOffer n'est
│                                     # pas null) intercalé avant la boutique, boutique (verrouillée
│                                     # visuellement et fonctionnellement pendant le playback via
│                                     # isPlayingBack), résolution de manche (bouton désactivé
│                                     # pendant le playback), log de combat ; case à cocher +
│                                     # curseur de volume pour la musique de hub dans le header ;
│                                     # layout 3 colonnes (Vestige | boutique + log | roster +
│                                     # coffre), verrouillé à 100vh (aucun scroll de page, scroll
│                                     # interne par colonne)
├── vite.config.ts                   # Proxy /runs → backend PHP en dev
├── vitest.config.ts                 # Séparé de vite.config.ts (clé `test` non reconnue par
│                                     # defineConfig de 'vite')
├── eslint.config.js / .prettierrc.json  # no-undef désactivée sur .ts/.vue (redondante avec
│                                     # vue-tsc pour les globals DOM comme HTMLElement)
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
- [x] Catalogue de héros enrichi (10 héros, 5 shadow / 5 neutral) et roster de 3 héros tiré à la seed (`HeroRosterFactory`, premier héros contraint sur l'affinité du Vestige) — *mécanisme depuis remplacé par la sélection progressive du joueur, voir session 018 plus bas*
- [x] Inventaire hero-aware (`Inventory`/`AssignedItem`) séparé du coffre (`Stash`) et affectation automatique à l'achat par budget de slots (`HeroItemAllocator`)
- [x] IA scriptée à composition fixe par héros et difficulté croissante par manche (`ScriptedOpponentFactory`, `config/game/scripted_opponent.json`)
- [x] `GameRun::playRound()` construit lui-même le plateau du joueur à partir de son inventaire courant — la boucle V1 est jouable de bout en bout mécaniquement, validée sur `php run.php` avec seed fixe
- [x] Système de compétences de héros (`HeroSkillType`, `HeroSkillDecorator`) : modificateur passif appliqué aux objets d'un héros à l'assemblage du plateau, jamais pendant le combat. Catalogue V1 de 10 compétences câblé de bout en bout, validé unitairement, en intégration et en E2E sur les 10 héros réels
- [x] Couche de présentation (`ItemPresenter`, `EffectPresenter`, `ActionPresenter`, `HeroPresenter`, `WalletPresenter`, `ShopPresenter`, `InventoryPresenter`, `StashPresenter`, `VestigePresenter`, `RunStatePresenter`) — traduction pure domaine → JSON, aucune logique dupliquée
- [x] Persistance de run par journal d'actions rejouable (schéma SQLite, `GameRunRepository`, `GameRunActionsRepository`, `GameRunActionApplier`, `GameRunFactory` unifiée avec `run.php` et les tests, `GameRunReplayer`)
- [x] Point d'entrée applicatif réel imposant l'ordre boutique → combat — désormais garanti par la couche HTTP (`RunController`), qui journalise systématiquement un `OPEN_SHOP` à la création, et par `GameRun::playRound()` lui-même pour les manches suivantes (réouverture automatique si le run continue, vidage automatique s'il se termine)
- [x] Couche HTTP maison (`Request`, `ApiResponse`, `Router` avec mapping d'exceptions → codes HTTP, `RunController` à 5 méthodes, `Response`, `public/index.php`) : les 5 endpoints du contrat sont implémentés, testés et vérifiés manuellement de bout en bout (vrai serveur PHP, vraie base SQLite sur disque)
- [x] Log de combat exposé via l'API (`Side` enum, `SimulationContext::getSide()`, `targetSide`/`sourceSide`/`sourceItemId` enrichis sur les events d'`ActionProcessor`/`StatusProcessor`/`EnrageProcessor`, `GameRun::getLastCombatResult()`, `CombatEventPresenter`) — `resolveRound` expose désormais `{ state, combatLog }`, seul endpoint à le faire
- [x] Board adverse exposé via l'API (`OpponentAssignment`, `OpponentBoard`, `OpponentInventoryPresenter`) — `resolveRound` expose désormais `opponentRoster` + `opponentInventory`, permettant de nommer héros/objet adverse dans le log de combat
- [x] Squelette frontend Vue.js 3 + TypeScript (Vite, Pinia) : client API typé (`runApi`, DTOs miroir du contrat réel, mapping d'erreurs 404/400/409), store `gameRun` (runId/state/lastCombatLog/opponentRoster/opponentInventory/participantResolver, garde fail-fast sans run active), tooling ESLint + Prettier + `vue-tsc`, tests Vitest ciblés sur la logique (API + store + composables), UI non testée par choix
- [x] Écran de combat brut (`formatCombatEvent`, `buildParticipantResolver`, `CombatLogView.vue`) et écran de boutique (`formatItemEffect`, `ShopView.vue`) — boucle de jeu V1 jouable de bout en bout à l'écran (démarrer → acheter → combattre → boutique renouvelée automatiquement → victoire/défaite → rejouer)
- [x] **Vestige exposé par l'API** (`GameRun::getVestige()`, `VestigePresenter`, clé `state.vestige`) — nom, affinité, `baseHp`/`baseShield`, or de départ/revenu, plutôt que codé en dur côté client, en prévision d'un second Vestige post-V1
- [x] **Structure visuelle du frontend** (`feature/frontend-visual-structure`) : design tokens (`style.css` — palette ombre/violet, typo Fraunces/Inter/mono, rareté colorée), layout 3 colonnes (Vestige | boutique + log | roster + coffre) verrouillé à la hauteur de l'écran (aucun scroll de page, scroll interne par colonne)
- [x] **Log de combat coloré** : `formatCombatEvent` retourne des segments (`{ segments, sourceSide }`) plutôt qu'une simple chaîne — fond de ligne vert/rouge selon l'acteur, valeur numérique colorée selon sa nature (dégâts rouge, poison violet, burn orange, bouclier jaune, soin vert)
- [x] **Panneaux Vestige, roster et coffre** (`VestigePanel.vue`, `HeroRosterPanel.vue`, `StashPanel.vue`) : stats du Vestige, objets équipés par héros, contenu du coffre avec message si vide
- [x] **Échange d'objet héros ↔ coffre côté écran** (`useItemSwapSelection.ts` + `swapWithStash()` existant) : sélection d'un objet équipé, échange avec un objet du coffre, erreurs backend (budget de slots dépassé) remontées à l'écran plutôt que silencieuses. Réattribution directe **héros ↔ héros**, ou déplacement simple sans objet en retour (héros ↔ héros ou héros ↔ coffre), identifiée comme hors scope V1 lors de ce chantier — notée en Roadmap V2+, pas implémentée
- [x] **Bible de lore et guide de direction artistique complets** (`docs/lore/`, `docs/art/`) — session hors-code : nature du Vide et lien Vestige-porteur ancré dans la structure de run, grammaire visuelle rareté (aura CSS)/affinité (illustration), hiérarchie de cadres à trois identités, formats techniques par asset, gabarit Ombre + fragment de style réutilisable. Tous les assets visuels V1 générés par IA et rangés sous `frontend/public/assets/` (héros, items, Vestige animé + poster, plateau, coffre, cadres) — la refonte de `style.css` pour refléter cette direction reste un chantier séparé, non commencé
- [x] **Déroulé du combat en temps réel** (`feature/combat-log-playback`) : `combatPlayback.ts` (horloge à tick simulée 100ms/tick, révélation cumulative des events, `stop()` d'annulation) remplace l'affichage instantané du log complet. Le store `gameRun` diffère désormais le commit de `state` (manche/victoires/or) jusqu'à la fin du playback, pour ne jamais spoiler le résultat avant que le combat se soit visuellement terminé ; `isPlayingBack` verrouille le bouton "Résoudre le round" et la boutique pendant l'animation
- [x] **Intégration visuelle complète des assets** (`feature/frontend-visual-structure`, suite) : `assetPaths.ts` résout tous les chemins d'assets (héros, items, Vestige, coffre) contre l'arborescence réelle du disque. Boutique/roster/coffre affichent illustration + cadre partagé + aura de rareté par objet ; roster affiche portrait + cadre selon affinité par héros ; coffre bascule entre image ouverte/fermée selon son contenu ; Vestige affiche une vidéo en boucle muette avec cadre superposé (zoom CSS empirique pour compenser une marge transparente du fichier source). Plateau/hub (`board/`) explicitement exclu de cette passe — aucun composant de layout ne lui correspond
- [x] **Intégration audio** (`feature/frontend-audio-structure`) : `combatEventSoundFile` mappe chaque event de combat vers un SFX (STATUS_APPLIED/STATUS_EXPIRED restent silencieux, pas de fichier dédié) ; chaque nouvel event révélé par le playback déclenche son SFX (`playCombatSfx`, volume fixe). Musique de hub en boucle (`useHubMusic.ts`), pilotée par le store `audioSettings` (case à cocher + curseur de volume dans le header, désactivée par défaut pour respecter la politique d'autoplay des navigateurs) avec ducking automatique à 40% du volume choisi pendant le déroulé d'un combat, sans fondu
- [x] **Sélection progressive des héros** (`feature/hero-selection`, session 018) — rouvre le scope V1, premier chantier à toucher au domaine et au contrat de `GameRun` depuis la V1 initiale :
  - **Domaine** : `HeroOffer` (VO immuable, 3 candidats, invariants cardinalité + anti-doublon, `contains()`/`find()`) ; `WeightedDraw` (tirage pondéré sans remise, Efraimidis-Spirakis, pur — testé par injection de flottants connus plutôt que sur un seed, pour ne pas coupler les tests à une séquence RNG particulière) ; `HeroOfferGenerator` (`buildInitialOffer()` : 1 candidat garanti de l'affinité du Vestige + 2 uniformes ; `buildWeightedOffer()` : pool filtré des héros déjà recrutés puis pondéré ×2.0/×1.0). `HeroRosterFactory` supprimée (remplacée, plus de tirage automatique à la construction)
  - **`GameRun`** : roster vide à la construction, `pendingHeroOffer` généré immédiatement (manche 1) ; `chooseHero()` consomme l'offre, ajoute au roster, ouvre la boutique ; manches 3 et 5 génèrent une nouvelle offre pondérée au lieu de rouvrir la boutique automatiquement ; `openShop()`/`purchaseItem()` refusent tant qu'une offre est en attente
  - **Persistance/HTTP** : nouvelle action journalisée `CHOOSE_HERO` (`GameRunActionType`, `GameRunActionApplier`), nouvel endpoint `POST /runs/{run_id}/hero/choose` (`RunController::chooseHero()`) ; `create()` ne journalise plus d'`OPEN_SHOP` automatique — c'est désormais `CHOOSE_HERO` qui ouvre la boutique, en manche 1 comme en 3/5 ; `RunStatePresenter` expose `pendingHeroOffer`
  - **Frontend** : `HeroOfferPanel.vue` (3 cartes cliquables, choix immédiat sans confirmation, protection double-clic par état local), branché dans `App.vue` entre l'écran de démarrage et la boutique ; `runApi.chooseHero()` et l'action `chooseHero()` du store `gameRun`, symétriques à `buyItem`/`swapItem`
  - **Stash** porté à 6 emplacements (`STASH_CAPACITY`), changement indépendant du reste du chantier
  - Validé manuellement sur un run complet jusqu'à la manche 5, offres de héros et pondération d'affinité observées au bon moment
- [x] **Chantiers 0 et 3b** (`fix/engine-death-guards`, `fix/application-play-round-guard`, `fix/application-swap-with-stash-rollback`, `feature/status-instances`, session 019) — deux chantiers d'audit issus de `07`, sans nouvelle mécanique de jeu :
  - **Chantier 0, gardes de mort** : garde de vie entre la phase de statuts et l'enrage dans `Simulator::run()` (E-03) ; garde `pendingHeroOffer` dans `playRound()`, symétrique à `purchaseItem()` et `openShop()` (E-06) ; rollback de `swapWithStash()` réparé par `try`/`finally`, `HeroItemAllocator::canAssign()` **levant** au lieu de retourner `false` sur un héros hors roster, ce qui court-circuitait la restauration et détruisait l'objet retiré (E-10). Sept tests de caractérisation du comportement antérieur.
  - **Chantier 3b, modèle de statut** : `ActiveStatus::mergeWith()` supprimé au profit d'une liste d'instances par type dans `CombatVestige`, avec `AggregatedStatus` comme seule projection exposée aux `CombatEvent` (E-02, D-20) ; répartition de la brûlure 150 % / 70 % en arithmétique entière (`CombatVestige::takeBurnDamage()`) ; nettoyage par le soin (D-21) avec `StatusType::isHostile()` comme source unique ; pénalité de `SUNDERING` supprimée sur les deux mains sans `DEAL_DAMAGE` (E-04) ; code mort retiré (E-07), `EventDispatcher` tombant à deux méthodes publiques et `Effect::intervalTicks` disparaissant de bout en bout, `EffectDTO` compris.
  - **Valeurs de référence mesurées et consignées** : `shadow_armor` produit **1 253** de bouclier sur 500 ticks sous le modèle par instances, contre **7 155** sous le modèle à fusion. Les estimations antérieures de 1 415 décrivaient le plafond de D-13, périmée, et non D-20.
  - **Journal de combat** : `amount` porte la valeur majorée pour la brûlure et non plus les stacks ; `HEAL_RECEIVED` gagne `poisonCleansed` et `burnCleansed` ; `STATUS_EXPIRED` voit son sens restreint à l'expiration par la durée. Libellés frontend correspondants dans `formatCombatEvent.ts`.
  - **Corpus** : quatre affirmations fausses corrigées dans `02`, `04` et `07`, dont « 1 point de bouclier annule intégralement la brûlure », que `takeDamage()` contredisait par son `min()`.

- [x] **Chantier 2 — snapshot versionné et déterminisme** (`feature/versioned-combat-snapshot`, sessions 020-021), précédé d'un cadrage de sept décisions sans code (D-14 à D-19 et D-22, branche `docs/combat-snapshot-framing`). **Terminé le 26/09/2026 — 19 commits de code pour 14 annoncés** (26 sur la branche, dont 5 documentaires), la porte EX-J0-03 franchie :
  - **Schéma versionné** (`schema_version`, ligne unique) : trois états distingués — base neuve, base versionnée, base **antérieure au versionnement** — par lecture de `sqlite_master` **avant** toute création. Sans cette lecture préalable, le `CREATE TABLE IF NOT EXISTS` rend les trois indiscernables et une base de développement ancienne se fait estampiller « à jour », ce que la table existe précisément pour empêcher. `ObsoleteSchemaException` étend `RuntimeException` et non `LogicException`, sans quoi le Router annoncerait au client un schéma obsolète comme un conflit d'état
  - **Sérialisation canonique du journal de combat** (D-19) : `CombatLogSerializer` dans le Domaine, séparé du presenter, tri `ksort`/`SORT_STRING` sur les tableaux à clés texte **et jamais sur les listes** — l'ordre des objets d'un plateau est une donnée de jeu, pas une présentation. Flottants refusés à toute profondeur : leur écriture JSON dépend de `serialize_precision`, réglage que le serveur et le binaire embarqué peuvent ne pas partager, et NF-01 tomberait sans qu'aucun calcul ne soit faux
  - **Graine de combat explicite** (D-22) : `Simulator::run()` ne reçoit plus le `Randomizer` de la run mais une graine opaque, dont il tire deux flux indépendants (`order` pour l'initiative, `effects` pour les tirages d'effets) par `sha256(tag ‖ combatSeed)`. Le calcul de la graine appartient à l'Application (`CombatSeed::forRound()`), sa dérivation au Domaine : c'est ce découpage qui permettra au moteur embarqué de rejouer un combat **sans jamais connaître la seed du run**
  - **Graine de run atteignable depuis l'API** (E-13) : `POST /runs` lit `seed` dans le corps JSON, source unique — `$params['seed']` est retiré et non conservé en second canal, `POST /runs` n'ayant aucun placeholder et `Request::fromGlobals()` coupant la chaîne de requête. Entier strict, rejeté sinon : `(int) 'abc'` vaut 0 et produirait une run parfaitement déterministe sur la mauvaise graine
  - **Libellés de côté neutres A/B** (D-19) et champ `viewerSide` dans la réponse de `resolveRound` : le journal ayant cessé de dire qui est le joueur, l'enveloppe doit le dire, et la valeur vient du moteur plutôt que d'être écrite en dur côté Http. Seul commit du chantier à toucher au frontend
  - **Match nul supprimé et résolution nommée** (D-15) : `winner` devient non nullable, un champ `resolution` à trois valeurs dit **pourquoi** le combat s'est terminé, et un événement `RESOLUTION_TIEBREAK` consigne le motif du départage dans le journal
  - **Règle de résolution hybride par phase** (D-14) : statuts et enrage en résolution simultanée avec constat des morts en fin de phase et départage sur l'état **d'avant** la phase ; actions d'objets en séquentiel, interrompu à la première mort, l'ordre des deux plateaux étant tiré au sort à chaque tick où les deux ont une action en attente
  - **Format de snapshot** (D-16, EX-J0-03) : `CanonicalJson` extrait comme règle d'octets partagée entre le journal et le snapshot ; `goldAtCombatStart` devient un paramètre **requis** de `CombatBoard`, embarqué avant même que la compétence qui le lira existe, parce qu'un champ qu'on ne peut pas ajouter après coup sans invalider un corpus n'est pas de l'anticipation ; `BoardSnapshot` porte la **photographie** — objets déjà décorés, définition du Vestige, héros et compétences —, `BoardRecord` l'enveloppe de provenance avec `SnapshotRecipe`, `contentVersion`, `FORMAT_VERSION` et `engineVersion`. Taille mesurée sur le maximum structurel (3 héros × 2 emplacements, 6 objets légendaires à 2 actions) : **2 129 octets**, soit ~10 Mo pour les 5 000 snapshots de NF-12, contre ~25 Mo estimés
  - **Attribution canonique des côtés** : A est le plateau à la plus petite photographie en ordre d'octets, `SimulationContext::getBoards()` devenant lui-même canonique — trois processeurs le parcourent et deux écrivent un événement par plateau, donc l'ordre des arguments était observable dans le journal. Ce seul commit a fait basculer **dix-neuf assertions** de tests moteur
  - **Empreinte de version de contenu** (D-18 volets 2 et 3) : `ContentVersion` porte la règle dans le Domaine — `sha256` de l'enveloppe canonique des quatre catalogues, `scripted_opponent.json` compris —, `ContentCatalogReader` la lit sur disque en Infrastructure et la gèle au premier appel, `runs.content_version` l'épingle à la création, et `GameRunReplayer::replay()` refuse de rejouer une run dont le contenu a changé. Le refus sort en `409` avec le code machine `CONTENT_VERSION_MISMATCH`, par une exception dédiée capturée **avant** la `LogicException` générique — sans cet ordre, il serait indiscernable d'un conflit de séquence
  - **La base de développement est jetable jusqu'à J1** (D-18) : le passage du schéma en version 2 refuse toute base antérieure au lieu de la migrer. Une empreinte remplie après coup serait inventée, et les runs qu'elle porte passeraient le contrôle sans que personne ne sache sous quel catalogue elles ont commencé
  - **Issue de combat enregistrée et rejeu sans moteur** (D-18 volet 1, E-11) : l'issue d'une manche est écrite dans la charge utile de `RESOLVE_ROUND` (`RoundOutcome`) et le rejeu l'**applique** au lieu de resimuler. Le chemin est réservé à `GameRunReplayer` ; `RunController::resolveRound()` simule toujours lui-même et ignore tout champ d'issue venu de la requête — une issue acceptée depuis le client serait une victoire déclarée par le joueur. Les deux chemins partagent la même transition de fin de manche : deux copies, et un run rejoué n'aboutirait plus au même état qu'un run joué
  - **Archive de combat en base** (schéma version 3, table `combat_records`) : deux enveloppes rangées **par côté** et non « joueur d'abord », `combat_seed`, `engine_version`, `resolution`, `winner_side`. Clé composite `(run_id, round)`, qui transforme une double écriture en erreur franche plutôt qu'en doublon silencieux — dernier filet face à l'absence de garde anti-double-soumission. L'archive est écrite **après** le journal : un échec d'archivage laisse un trou réparable, l'ordre inverse laisserait une archive pour une manche que le rejeu ignore
  - **Ports de profil de combat** (`VestigeProfile`, `HeroProfile`) : `CombatVestige` et `CombatHero` ne retiennent plus une entrée de catalogue mais les cinq valeurs qu'un combat consomme réellement. Les ports vivent dans `Domain\Runtime` — chez le consommateur — et `Vestige`/`Hero` s'y conforment par des accesseurs qui rendent des champs déjà publics. Les soixante-six sites de construction des tests n'ont pas bougé, ce qui est exactement ce qui a départagé cette option des deux autres
  - **Hydratation d'un plateau archivé** (`BoardHydrator`) : `fromCanonicalJson(string): CombatBoard`. Pas de tableau décodé, pas de dépôt, pas de chemin de configuration — **l'isolement est structurel et non déclaratif**, une signature qui n'accepte qu'une chaîne rendant impossible la relecture d'un catalogue au rejeu. Le décorateur n'est pas réappliqué (la photographie porte les objets déjà décorés) et le budget d'emplacements n'est pas revérifié. `formatVersion` décide du droit de reconstruire, `engineVersion` du droit de resimuler — deux questions distinctes, et les confondre ferait refuser des plateaux parfaitement reconstructibles
  - **E-15 — l'ordre des effets d'un objet dépendait de l'ordre des arguments** : `EventDispatcher` indexait ses écouteurs par `Trigger`, en ordre d'enregistrement des plateaux. Mesuré : `run($a, $b)` et `run($b, $a)` produisaient **deux journaux différents à l'octet près**, sur les mêmes plateaux et la même graine. NF-01 ne tenait pas, et trois documents classaient le défaut comme une dette théorique du chantier 3 — il était latent faute d'objet à deux déclencheurs, pas absent. Corrigé par une liste plate ; `EngineVersion` relevée de 1 à 2, gratuit tant qu'aucun corpus n'existe
  - **Rejeu octet pour octet** (`tests/Determinism/`, critère de sortie) : une ancre figée — quatre fichiers capturés d'une vraie run, hydratés et resimulés — et un test de chaîne réelle qui archive, relit en SQL direct et compare le combat à lui-même. Le premier rougit si le moteur dérive, le second si l'archivage casse ; le second n'a aucune constante figée, donc un rééquilibrage ne peut pas le faire rougir. Vérifié que l'ancre **sait échouer** : trois perturbations indépendantes de la fixture font diverger le journal
  - **Ce que le critère ne couvre pas** : la fixture traverse six des dix types d'événement. Un balayage de **soixante-dix combats PvE** n'a produit que des KO, le plus long à 192 ticks sur 500 — la fureur en exige 450 et le départage l'absence de KO, donc ni l'un ni l'autre n'est atteignable en conditions réelles. Fait consigné pour le chantier 10 : la fureur n'est calibrée par rien

**Prochain chantier** : **1b — coquille Electron et Steam** (`07` §5.2, rang 5), sans préalable, porte EX-J0-02 partielle. Le chantier 1a — moteur embarqué — vient ensuite et dépend du chantier 2 : c'est lui qui exercera pour de bon la parité octet pour octet entre le serveur et le binaire, dont seul un côté est aujourd'hui figé.

**Reste ouvert à la sortie du chantier 2** : la porte de CI sur l'empreinte de version de contenu (`04` §10, `06` §4.2), dont le motif d'exclusion — aucune valeur de référence à comparer — est tombé maintenant que `tests/Determinism/fixtures/` existe.

Suite de tests automatisés :
- **Backend** : 472 tests / 1 543 assertions, CI (PHPUnit + PHPStan niveau 6 + PHP CS Fixer) verte.
- **Frontend** : 81 tests Vitest (client API + store + composables, dont `combatPlayback`, `assetPaths`, `combatEventSound`, `audioSettings`, `chooseHero`), ESLint/Prettier/`vue-tsc` propres — UI et effets de bord audio réels (`combatSfxPlayer`, `useHubMusic`) non couverts par choix (tests ciblés sur la logique pure, pas sur le visuel/sonore).

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.

Discipline TDD stricte sur l'ensemble de la couche API (Presentation, Persistence, Http) et du moteur de simulation : rouge → implémentation minimale → vert → CS Fixer → PHPStan → commit, avec triangulation systématique dès qu'une branche conditionnelle ou une composition le justifie.

Côté frontend, rigueur de test volontairement plus ciblée qu'au backend : logique pure (client API, store, composables) testée en TDD via Vitest ; composants Vue (présentation visuelle) et effets de bord audio réels (lecture `Audio`, autoplay) non testés unitairement, vérifiés à l'œil ou à l'oreille — même principe que `Response::send()` côté backend, jamais testé unitairement pour la même raison (uniquement vérifiable visuellement/manuellement). `jsdom` (environnement de test) n'implémente pas l'API `Audio` : tout test touchant du code appelant `new Audio(...)` doit stubber ce global explicitement (`vi.stubGlobal('Audio', FakeAudio)`), sans quoi le test échoue pour une raison d'environnement et non de logique.