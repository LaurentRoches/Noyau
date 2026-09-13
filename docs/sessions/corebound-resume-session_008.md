# Corebound / Projet Noyau — Résumé de session 008

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` (mis à jour deux fois cette session) et `README.md` dans les fichiers projet pour l'état complet et à jour du cahier des charges V1.

**Stack** : PHP 8.3 natif (pas de framework), architecture DDD à la main. Vue.js 3 en frontend (non commencé). PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions.

**Méthodologie confirmée et particulièrement payante cette session** : demander systématiquement le contenu réel d'un fichier avant de coder dessus (`ShopFactory`, `CombatBoardFactory`, `Simulator`, `EventType`), plutôt que de se fier aux résumés de sessions précédentes — un résumé passé s'est avéré faux sur un point (chemin de fixture des tests de repository), et ce réflexe de vérification a permis de détecter un vrai bug de double-KO dans `EnrageProcessor` avant qu'il ne soit mergé.

## État Git

- **`feature/core-gameplay-loop`** : mergée vers `dev` en cours de session.
- **Incident survenu et résolu** : un merge accidentel dans la mauvaise branche a déclenché un revert de la PR `feature/core-gameplay-loop` sur `dev` (PR #12, "Revert 'Feature/core gameplay loop'"), suivi d'un commit `restore core gameplay implementation` pour tout ramener. Confirmé par l'utilisateur comme intégralement rattrapé — aucune perte de travail au final, mais bon réflexe à garder : vérifier l'historique (`git log --oneline`) après tout comportement de merge/push inattendu.
- **`feature/player-inventory`** : créée depuis `dev` une fois `core-gameplay-loop` mergée. Dernier commit `bf1fffb` ("docs: document player inventory..."), poussé sur `origin`. **Prête à merger vers `dev`, mais le merge n'a pas encore été confirmé fait** à la fin de cette session — à vérifier en priorité à la reprise.
- **Deux incidents de commit terminal cette session** (message multi-lignes cassé par une apostrophe/des guillemets non échappés dans PowerShell/Git Bash) : les deux fois résolus en repassant par le panneau **Source Control de VSCode**, qui gère correctement les messages multi-lignes sans risque d'échappement shell. Recommandé comme méthode par défaut pour tout commit avec message détaillé désormais.

## Chantier 1 terminé — Fondations économiques du Vestige (`feature/core-gameplay-loop`)

### `Vestige::startingGold` et `Vestige::startingIncome`

Deux champs ajoutés en deux vagues séparées (startingGold d'abord, startingIncome ensuite), tous deux **obligatoires, fail-fast**, hydratés depuis `config/game/vestiges.json` sans `??` (cohérent avec `baseHp`). Chaque ajout a déclenché la cascade attendue de correction mécanique sur les **8 fichiers de tests unitaires** qui construisent `new Vestige(...)` directement (`CombatVestigeTest`, `CombatBoardTest`, `ActionProcessorTest`, `EventDispatcherTest`, `SimulationContextTest`, `SimulatorTest`, `StatusProcessorTest`, `TickEngineTest`) — 2ᵉ occurrence de ce motif, noté comme approchant (sans l'atteindre) le seuil de justification d'un Test Object Mother, volontairement pas construit (YAGNI, décision explicitement débattue et tranchée).

- `startingGold = 20` : calibré pour permettre l'achat de 2 objets Common au premier tour (prix Common = 10).
- `startingIncome = 5` : crédité par `GameRun` à **chaque** fin de manche (victoire ou défaite) — répond directement au problème "un joueur qui perd ne doit pas être bloqué économiquement".

### Correction de cahier des charges — `GAIN_GOLD`/`GAIN_MANA` retirés

Après vérification consciente (aucun des 30 objets réels ne les utilise), confirmé par l'utilisateur comme une erreur du cahier des charges initial plutôt qu'une mécanique V2+ à garder en placeholder. Les deux cas retirés de `ActionType`, sans effet de bord ailleurs dans le code (vérifié).

### Clarification majeure de scope V1 vs V2+ (nouveaux documents apportés par l'utilisateur)

Trois PDF/images (flowchart de boucle de jeu, diagramme systèmes, croquis de board) ont révélé un écart important entre `game-design-notes.md` (obsolète) et la vision réelle du jeu. Tranché explicitement via `ask_user_input_v0` :
- **Combat V1 = IA scriptée** (pas de snapshot PvP asynchrone, différé en V2+).
- **Vestige V1 = fixe** (`shadow_vestige`), pas de choix parmi 3 (V2+).
- **Boutique V1 = une seule visite par manche**, pas de double phase + choix de Monstre PvE (V2+).
- **Fin de run V1 = 10 victoires ou 3 défaites**, comptabilité confirmée applicable même en PvE (initialement formulée "en PvP" par l'utilisateur, clarifié).

`game-design-notes.md` entièrement réécrit pour refléter cet état, avec séparation stricte scope V1 actuel / Roadmap V2+ explicite.

## Chantier 2 terminé — `Application/GameRun`, orchestrateur de run (`feature/core-gameplay-loop`)

Construit incrémentalement, TDD strict, en s'appuyant sur les vraies signatures de `ShopFactory`, `CombatBoardFactory`, `Simulator` (vérifiées avant chaque étape) :

- **`GameRun::__construct()`** : reçoit `Vestige`, `ShopFactory`, `ScriptedOpponentFactory`, `CombatBoardFactory`, `Simulator`, `Randomizer` — tous déjà instanciés (injection explicite, cohérent avec `SimulationContext`). Initialise `Wallet` depuis `startingGold`.
- **Comptage victoires/défaites/manche** : `recordVictory()`/`recordDefeat()` (passées `private` une fois `playRound()` posé), `isOver()`, `hasWon()`, `getCurrentRound()` (démarre à 1, incrémenté à chaque résultat).
- **`openShop()`/`getCurrentShop()`** : délègue à `ShopFactory::createShop($randomizer)`.
- **Économie de manche** : `recordVictory()` crédite `+10` (constante `VICTORY_REWARD`) + `startingIncome` ; `recordDefeat()` crédite `startingIncome` seul.

### `Application/Factory/ScriptedOpponentFactory` (point 5 de la session 007, enfin fermé)

Génère l'adversaire PvE via `CombatBoardFactory`, héros/Vestige fixes (`shadow_vestige`/`shadow_bearer`). Nombre d'objets équipés croissant avec la manche : formule adoucie de `min($round, 6)` à `min(ceil($round / 2), 6)` après que l'utilisateur a soulevé, à raison, que la formule initiale rendait la difficulté PvE ingérable face à l'économie du joueur en début de run. **Pas de pondération de rareté** (tirage uniforme) — délibérément écarté faute de données de playtesting, même raisonnement que pour l'enrage.

### `Domain/Engine/EnrageProcessor` — ajout majeur non planifié, motivé par un vrai problème de design

Née d'une remarque de l'utilisateur : sans mécanisme de résolution forcée, un combat qui atteint `maxTicks` produit `winner: null`, compté comme défaite par `GameRun` — ce qui rend les **12 objets purement défensifs sur 30** structurellement perdants dans tout matchup de stalemate. Un retour externe détaillé (fourni par l'utilisateur) a challengé une première réponse trop prudente ("notons-le juste en V2+") en distinguant clairement décision d'architecture (à prendre maintenant) et calibration de paramètres (à playtester plus tard) — argument accepté et intégré.

- Déclenchement à `triggerTick = maxTicks - 50`, dégâts `baseDamage=5 × 2^(tick - triggerTick)`, appliqués aux deux Vestiges au même tick.
- **Décision corrigée en cours de route** : dégâts d'abord proposés en "raw" (ignorant le bouclier, façon Poison), corrigé par l'utilisateur en dégâts classiques (à travers le bouclier) — sinon les builds défensifs sont punis une seconde fois par la mécanique censée les protéger.
- **Bug réel détecté par le test de l'utilisateur** (`testEnrageForcesAResolutionBetweenTwoPurelyDefensiveBoards`) : deux plateaux symétriques double-KO systématiquement dès que la progression exponentielle dépasse l'écart de HP initial, quelle que soit sa taille — parce que `EnrageProcessor` frappait les deux boards dans un même `foreach` sans vérifier `isAlive()` entre les deux. Corrigé en étendant l'invariant "pas de frappe sur cadavre" (déjà garanti ailleurs dans `Simulator`) à l'enrage : `break` dès que le premier board frappé meurt. Conséquence assumée et documentée : biais d'ordre (le joueur, toujours évalué en premier, meurt en cas d'égalité stricte).

## Chantier 3 terminé — Inventaire du joueur (`feature/player-inventory`)

### `Domain/Player/Inventory`

Collection bornée générique (`add`, `removeAt`, `insertAt`, `isFull`, `count`, `getItems`, `getItemIds`), volontairement réutilisée pour deux rôles distincts plutôt que dupliquée.

### Intégration dans `GameRun`

- **Deux instances** : `inventory` (capacité 6, = le plateau de combat) et `stash` (capacité 3, coffre).
- **`purchaseItem(int $slotIndex)`** : débite le `Wallet` via `Shop::purchase()`, place l'objet dans le plateau si possible, sinon dans le coffre. Garde de disponibilité (plateau OU coffre) vérifiée **avant** le débit.
- **`swapWithStash(int $inventoryIndex, int $stashIndex)`** : ajouté après que l'utilisateur a fait remarquer, à juste titre, qu'un coffre sans échange n'a aucune utilité — première coupure de scope ("juste stocker, pas échanger") reconnue comme une mauvaise coupure et corrigée dans la foulée. Les deux retraits sont validés (lecture) avant toute mutation, pour éviter qu'un item ne devienne orphelin si le second échange échouait.
- **`playRound()` fermé** : ne prend plus de `CombatBoard` en paramètre externe — le construit lui-même via `CombatBoardFactory`, `PLAYER_HERO_ID` fixe (`'shadow_bearer'`, miroir du Vestige fixe), et `inventory->getItemIds()`. La boucle V1 est maintenant mécaniquement jouable de bout en bout.

### Décision pédagogique de séquencement

Question posée et tranchée avec l'utilisateur : `PLAYER_HERO_ID` en dur coûte peu à remplacer plus tard (une seule référence dans tout le code, contrairement aux 8 sites d'appel de `new Vestige(...)`) — raisonnement explicite sur le coût de réversibilité d'un raccourci, pas juste "on verra plus tard".

## Documentation mise à jour deux fois cette session

`game-design-notes.md` et `README.md` régénérés intégralement à deux reprises (une fois après `GameRun`+enrage, une fois après l'inventaire) pour rester synchronisés avec le code réel plutôt que de dériver comme l'ancien document l'avait fait entre les sessions 005 et cette session-ci. Contenu final : cahier des charges V1 complet et à jour, Roadmap V2+ explicitement séparée et enrichie de toutes les dettes identifiées cette session.

## Dettes et points ouverts, tous documentés en Roadmap V2+ ou notés explicitement dans le code

- **Nombre `6` dupliqué à trois endroits indépendants** : `Hero::itemSlots`, `ScriptedOpponentFactory::MAX_ITEMS`, `GameRun::INVENTORY_CAPACITY` — inoffensif tant qu'un seul héros existe, deviendra une vraie dette au premier second héros à budget différent.
- **`GameRun` n'impose pas structurellement l'ordre boutique → combat** — reposera sur un futur point d'entrée applicatif (API/script), pas encore construit.
- **Choix/marchand de héros absent** : le héros du joueur est fixe (`shadow_bearer`), comme le Vestige. Nécessaire pour que le multi-héros (1 à 3 par board, déjà supporté par `CombatBoard`) ait un sens côté joueur — fusionné dans la Roadmap avec la question déjà notée de répartition des items par héros précis, les deux étant bloquées par la même absence de choix.
- **Coffre V1 minimal** : capacité fixe (3), pas de réordonnancement interne, pas d'agrandissement, pas de vente — seul mouvement possible est l'échange direct plateau ↔ coffre.
- **Calibration de l'enrage et de la difficulté PvE** jamais playtestée — valeurs posées par raisonnement (`triggerTick`, `baseDamage`, `ceil(round/2)`), pas par données réelles.

## Prochaine étape — à trancher en priorité à la reprise

Deux chantiers candidats identifiés en fin de session, aucun encore commencé :

1. **Point d'entrée applicatif** (API HTTP minimale ou script CLI) permettant de réellement instancier et jouer une `GameRun` de bout en bout — actuellement, toute la mécanique existe et est testée unitairement, mais rien ne l'assemble en un point d'usage réel imposant l'ordre boutique → combat.
2. **Frontend Vue.js** (file d'attente d'animations du `CombatLog`) — gros chantier, probablement prématuré tant que le point 1 n'existe pas (rien à consommer côté frontend sans API).

Le point 1 semble la brique la plus feuille des deux (aucune dépendance sur un futur frontend), mais **non tranché avec l'utilisateur** — à commencer par cette question à la reprise, avant tout code.

**Vérification à faire en tout premier lieu à la reprise** : confirmer que `feature/player-inventory` a bien été mergée vers `dev` (statut incertain à la clôture de cette session), et vérifier l'état de `dev` après l'incident de revert survenu en cours de session (résolu selon l'utilisateur, mais jamais vérifié directement par Claude).

## Rappel des conventions

- Commits Conventional Commits, scope `domain`/`application`/`docs` selon la nature du changement.
- **Toujours demander le contenu réel d'un fichier avant de coder dessus ou d'en modifier un autre qui en dépend** — payant à plusieurs reprises cette session (signatures de `ShopFactory`/`CombatBoardFactory`/`Simulator` toutes différentes de ce qu'un résumé aurait pu laisser supposer sur un point ou un autre).
- **Messages de commit multi-lignes : utiliser le panneau Source Control de VSCode**, jamais un copier-coller direct dans le terminal (deux incidents cette session, tous deux résolus ainsi).
- TDD strict quand la fatigue n'impose pas de raccourcis explicitement demandés ; en mode "réponse directe", continuer à nommer les décisions de conception et leurs coûts plutôt que de les passer sous silence.
- Mode pédagogique par défaut, direct sur demande explicite — les deux ont été utilisés dans cette même session, bien distingués.
