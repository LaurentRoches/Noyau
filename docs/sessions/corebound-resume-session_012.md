# Session 12 — Couche HTTP, persistance SQLite et clôture de `feature/http-api`

## Contexte de départ

Session ouverte juste après la clôture de la session 11 (câblage complet de `Hero::$skill`/`CombatBoardFactory`/`RELENTLESS`, branche `feature/hero-skills` prête à merger). Objectif de cadrage initial : décider de la prochaine étape entre amélioration de la sélection des héros, système de logs d'équilibrage, persistance en base de données, ou démarrage du frontend.

Décision issue du cadrage : le frontend est le vrai prochain chantier (le domaine est fonctionnellement complet pour le scope V1), mais il nécessite d'abord une couche API HTTP + une stratégie de persistance — sans quoi il n'y a rien de réel à consommer côté client. Branche `feature/http-api` créée pour ce chantier complet.

## Décisions architecturales validées en amont du code

- **Pas de compte joueur en V1** : une run est identifiée par un `run_id` opaque (généré, pas une session cookie), pour rester portable vers un futur client Electron/Tauri et anticiper sans réécriture un futur `player_id` de PvP asynchrone.
- **SQLite plutôt que MySQL pour la V1** : pas de serveur à administrer, un seul fichier portable. Un vrai serveur de base de données ne devient nécessaire qu'au moment du PvP asynchrone (plusieurs joueurs concurrents), pas avant. Le PvP futur imposera de toute façon un vrai serveur permanent (comptes, boards partagés) — nuance clarifiée avec l'utilisateur, qui pensait initialement que la BDD locale suffirait aussi pour le PvP.
- **Persistance par journal d'actions rejouable (Option A) plutôt que sérialisation d'instantané (Option B)** : un `GameRun` est reconstruit en rejouant, sur une seed fixe, la liste ordonnée des actions qui lui ont été appliquées — plutôt que d'ajouter des méthodes `toArray()`/`fromArray()` au domaine. Choisi pour ne pas toucher aux classes domaine `final readonly`, et parce que le volume par run (13 manches max) rend le coût de rejeu négligeable. Effet de bord : ce journal peut aussi servir de base à un futur système de logs d'équilibrage.
- **Router maison, sans bibliothèque** : un code de référence d'un autre projet (Router/Request/Response/BaseController/AuthMiddleware) a servi d'inspiration de style (routage par regex nommé + middleware), mais adapté et allégé, pas copié — pas de comptes, pas de vues serveur, pas de CSRF en V1 API JSON pure.
- **Mapping d'exceptions centralisé dans le `Router`**, pas dans les contrôleurs : `RunNotFoundException` (créée cette session, distincte d'`InvalidArgumentException`) → 404, `InvalidArgumentException` → 400, `LogicException` → 409 — ordre des `catch` du plus spécifique au plus général, car `InvalidArgumentException` hérite de `LogicException` en PHP.
- **Contrôleurs "orchestration pure"** : jamais de `try`/`catch` dans `RunController`, les exceptions métier remontent telles quelles jusqu'au `Router`.

## Travail réalisé — Point 1 : Presenters

Neuf classes de présentation (fonctions statiques pures, domaine → tableau JSON), construites bottom-up en TDD strict, chacune testée isolément puis en composition :

`ActionPresenter` → `EffectPresenter` → `ItemPresenter` (bouclé après coup une fois les deux premiers prêts) → `HeroPresenter` → `WalletPresenter` → `ShopPresenter` → `InventoryPresenter` → `StashPresenter` → `RunStatePresenter` (compose tout, testé contre un vrai `GameRun` construit via repositories réels plutôt que mocké).

**Correction domaine découverte au passage** : `Stash` n'exposait aucun `getCapacity()` — ajouté avec son propre rouge/vert dédié, pour ne pas dupliquer la constante côté client (fragile face au futur passage prévu de 3 à 8 emplacements).

**Convention de triangulation appliquée systématiquement** : dès qu'une branche conditionnelle existe (`Action.target`/`status` nullable, `ShopOffer.purchased`) ou qu'une composition réelle est en jeu (roster, boutique ouverte dans `RunStatePresenter`), un second test force la preuve plutôt que de supposer la couverture acquise par le premier.

## Travail réalisé — Point 2 : Persistance

- **Schéma SQLite** (`Schema::initialize()`, idempotent) : tables `runs` (id, seed, vestige_id) et `run_actions` (run_id, sequence, action_type, payload JSON, created_at).
- **`GameRunRepository`** (`create`/`find`) et **`GameRunActionsRepository`** (`append`/`findAllForRun`/`countForRun`), chacun retournant des value objects `readonly` (`GameRunRecord`, `GameRunActionRecord`) plutôt que des tableaux bruts.
- **`GameRunActionApplier`** : traduit un `GameRunActionType` (`OPEN_SHOP`/`PURCHASE`/`SWAP`/`RESOLVE_ROUND`) en appel réel sur `GameRun`, sans jamais catcher les exceptions métier du domaine.
- **`GameRunFactory`** : extraction du câblage à 7 dépendances d'un `GameRun` (jusque-là dupliqué dans `run.php` et un trait de test) — 3ᵉ occurrence, seuil d'extraction habituel du projet, cette fois appliqué à du code de production. `run.php` et le trait `CreatesRealGameRun` ont été réalignés dessus, une seule source de vérité désormais.
- **`GameRunReplayer`** : reconstruit un `GameRun` vivant à partir d'un `run_id` (seed + vestige via `GameRunFactory`, puis rejeu ordonné du journal via `GameRunActionApplier`). Clôt le Point 2.

**Incident de régression découvert et corrigé en cours de route** : `ScriptedOpponentFactoryTest.php` portait un espace parasite dans son nom de fichier (`ScriptedOpponentFactoryTest .php`), l'excluant silencieusement de la découverte PHPUnit depuis l'ajout de `HeroSkillDecorator` à `CombatBoardFactory`. Une fois renommé, il a révélé un vrai bug (constructeur appelé avec 3 arguments au lieu de 4) — corrigé. Deux tests qui n'avaient jamais tourné depuis plusieurs sessions sont redevenus effectifs.

## Travail réalisé — Point 3 : Couche HTTP

- **`Request`** : wrapper testable via deux constructeurs nommés (`fromGlobals()`/`fake()`), n'expose que `method()`/`uri()`/`json()` (YAGNI vis-à-vis du contrat réel, pas de query params/cookies/upload).
- **`ApiResponse`** : value object pur (statusCode + body), sans `exit`, contrairement au `Response` du code de référence initial — pensé dès le départ pour être unit-testable.
- **`Router`** : routage par regex nommé (repris et allégé du code de référence), transmet désormais `(params, request)` aux handlers — correction d'un piège potentiel identifié avant qu'il ne devienne un bug de production (`php://input` est un flux à lecture unique, une reconstruction de `Request` dans chaque handler aurait silencieusement échoué en vrai serveur).
- **`RunController`** : les 5 méthodes du contrat (`create`, `show`, `buyItem`, `swapItem`, `resolveRound`), chacune suivant le schéma *replay → apply → journaliser seulement si succès → renvoyer l'état*. Ordre de journalisation délibérément après validation, jamais avant : journaliser une action invalide corromprait durablement toute run (le rejeu la reproduirait indéfiniment).
- **`Response::send()`** et **`public/index.php`/`public/.htaccess`** : couche d'émission réelle, jamais testée unitairement (structurellement impossible, `exit` en fait foi) — vérifiée manuellement.

**Vérification manuelle de bout en bout réussie** : serveur PHP intégré (`php -S localhost:8000 -t public public/index.php`), `POST /runs` puis `GET /runs/{run_id}` sur deux processus PHP distincts renvoient un état identique — preuve que la persistance SQLite fonctionne réellement entre deux requêtes, pas seulement en mémoire.

**Couverture volontairement absente, documentée en commentaire dans le test lui-même plutôt que passée sous silence** : `swapItem()` n'est testé côté contrôleur que sur son chemin d'échec. Le chemin de succès nécessiterait d'accumuler ~7 achats à travers plusieurs manches (via `resolveRound()`, qui n'existait pas encore au moment d'écrire ce test) pour remplir le coffre par la voie légitime — jugé disproportionné pour une preuve déjà apportée par `GameRunActionApplierTest`. Redevient possible facilement une fois `resolveRound()` posé, si on souhaite combler ce trou plus tard.

## État final de la branche

- **217 tests / 891 assertions**, CI complète verte (PHPUnit + PHPStan niveau 6 + PHP CS Fixer).
- Les 5 endpoints du contrat d'API fonctionnent réellement en HTTP, avec persistance SQLite vérifiée sur disque.
- `README.md` mis à jour (structure de projet, avancement, contrat d'API, stack).
- Erreurs récurrentes corrigées en cours de route, à garder en tête pour la suite : namespace de test oublié (`Tests\` au lieu d'`App\Tests\`, introduit puis corrigé sur les 9 fichiers Presenter), confusion entre import de namespace et inclusion de trait (`use X;` en tête de fichier vs `use X;` en corps de classe) rencontrée deux fois.

## Prochaine étape

Squelette Vue.js 3 (boutique cliquable, écran de combat brut, boucle complète), consommant l'API HTTP désormais fonctionnelle — objectif de départ de ce chantier : obtenir un prototype réellement jouable pour valider le fun avant d'investir davantage.
