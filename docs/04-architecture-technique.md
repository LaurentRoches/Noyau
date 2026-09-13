# 04 — Architecture technique

**Autorité sur :** l'architecture logicielle, le déterminisme, le packaging, l'infrastructure.
**Révision :** 1.0 — 2 septembre 2026.

---

## 1. Stack

| Couche | Technologie |
|---|---|
| Moteur / backend | **PHP 8.3 natif**, architecture DDD construite à la main, aucun framework applicatif |
| Frontend | **Vue 3 / TypeScript / Pinia / Vite** |
| Persistance serveur | SQLite (dev, alpha) → **PostgreSQL** (à partir de J2) |
| Encapsulation desktop | **Electron** (décision du 02/09/2026) |
| Moteur embarqué client | Binaire PHP statique via `static-php-cli` / `phpmicro` |
| Tests | PHPUnit 12, Vitest |
| Qualité | PHPStan niveau 6, PHP CS Fixer, ESLint, Prettier, `vue-tsc` |
| CI | GitHub Actions (`ubuntu-latest`), portes bloquantes sur PR vers `dev` |
| Environnement | Windows + VSCode, Node via nvm-windows (`frontend/.nvmrc`) |

### Pourquoi PHP pour le moteur de simulation

Un combat d'auto-battler asynchrone est une **transformation de données pure** : `CombatLog = f(playerBoard, opponentBoard, seed)`. PHP y est bien adapté : cycle de vie court et stateless, aucune fuite de mémoire entre requêtes, isolation totale entre deux combats simulés en parallèle.

Ce choix n'a jamais été un handicap sur le moteur. Il n'en devient un que sur le packaging desktop — sujet traité en §4.

---

## 2. Couches

```
Domain → Application → Infrastructure → Persistence → Presentation → Http
```

**Règle de dépendance :** une couche ne dépend jamais d'une couche située à sa droite.

| Couche | Contenu | Contrainte |
|---|---|---|
| **Domain** | `Vestige`, `Hero`, `Item`, `CombatBoard`, `CombatVestige`, `CombatHero`, moteur de combat, statuts | Aucune I/O, aucun accès réseau ou base. Aucune source d'aléa non seedée |
| **Application** | `GameRun`, `GameRunFactory`, `HeroItemAllocator`, `ShopFactory`, `CombatBoardFactory`, `ScriptedOpponentFactory` | Orchestration. Injection explicite des dépendances, jamais de service locator |
| **Infrastructure** | Repositories JSON, chargement de configuration | Fail-fast sur configuration incomplète |
| **Persistence** | Journal d'actions rejouable, SQLite/PostgreSQL | Aucune sérialisation d'objet domaine |
| **Presentation** | Sérialisation de l'état de run pour le client | Aucune règle métier |
| **Http** | `Router`, `Request`, `ApiResponse`, `RunController` | Mapping exception → HTTP centralisé dans le `Router` |

**Structure de plateau :** `CombatBoard` = 1 `CombatVestige` + 1 à 3 `CombatHero`. Le `CombatVestige` porte les PV, le bouclier et les statuts. Les héros n'ont pas d'état de combat propre.

---

## 3. Moteur de combat

### 3.1 Pipeline

```
TickEngine → EventDispatcher → PendingAction → ActionProcessor
           → StatusProcessor → EnrageProcessor → CombatEvent → CombatLog
```

`Simulator(int $maxTicks = 500)::run(CombatBoard $player, CombatBoard $opponent, Randomizer $randomizer): SimulationResult`

- **1 tick = 100 ms.** `maxTicks = 500` par défaut, soit 50 secondes.
- La boucle interrompt le tick dès qu'une entité meurt — pas de frappe sur cadavre, ce qui rend le double-KO structurellement impossible.
- Un seul endroit du code avance le temps (`TickEngine::tick()`). Ne jamais dupliquer `advanceTick()` dans `Simulator` : c'est un piège déjà rencontré et corrigé.
- `SimulationResult { winner: ?CombatHero, totalTicks: int, log: CombatLog }`. `winner: null` couvre le timeout.

### 3.2 Déterminisme — contrainte non négociable

RNG : `\Random\Randomizer` sur `\Random\Engine\PcgOneseq128XslRr64($seed)`. **Aucune autre source d'aléa dans la couche Domaine** — ni `rand()`, ni `shuffle()`, ni `array_rand()`, ni horodatage.

Le déterminisme n'est pas un confort de développement : c'est ce qui rend possibles le replay client, le PvP asynchrone, le corpus de sunset et le débogage à distance. Toute régression sur ce point casse quatre systèmes à la fois.

**Vérification :** un test CI compare, sur un jeu de seeds fixes, le `CombatLog` produit par le serveur et celui produit par le binaire embarqué. Toute divergence échoue le build.

### 3.3 Règles de calcul stabilisées

- **Arrondis :** `cooldownTicks` toujours `floor()`, `value` toujours `ceil()`. Cette règle est définitive — la formulation antérieure (« réduction → floor, augmentation → ceil ») cassait sur le malus de cooldown de `SUNDERING`.
- **Ordre d'activation :** déterministe par ordre de déclaration des plateaux, le joueur avant l'adversaire. Aucune statistique d'initiative. Dette connue, à revisiter quand des combats multi-objets symétriques existeront.
- **Modèle de statut — instances indépendantes (D-20, 13/09/2026).** `CombatVestige` porte, **par type de statut, une liste d'instances**, chacune avec ses stacks, ses ticks restants et l'identifiant de sa source. Aucune fusion, aucun plafond écrit : le régime permanent se stabilise de lui-même à `ceil(durationTicks / cooldownTicks)` par source. `StatusProcessor` **agrège la somme des stacks vivants avant d'émettre** — un seul `CombatEvent` par statut et par tick, charge utile inchangée, lecteur de rejeu du frontend non impacté. Règle complète : `02` §7.4.
- **Répartition de la brûlure :** `intdiv($stacks * 3, 2)` sur le bouclier, puis `intdiv($leftover * 7, 15)` du surplus sur les PV. **Arithmétique entière exclusivement** — aucun flottant, aucun pourcentage calculé. Les deux divisions arrondissent au plancher, donc en faveur du défenseur. Un flottant ici casserait la parité serveur / moteur embarqué d'EX-J0-01.
- **Conséquence sur le format de snapshot (chantier 2).** L'état de statut n'est ni un entier ni un couple mais **une liste**. Le chantier 3b doit donc précéder le chantier 2, faute de quoi la première version du format serait à migrer immédiatement.

### 3.4 Dettes connues du moteur

| Dette | Description |
|---|---|
| Statuts fusionnés par type | `CombatVestige` indexe sur le seul type et `ActiveStatus::mergeWith()` fait `stacks +=` / `remainingTicks = max(...)`. Contredit le modèle par instances tranché en D-20. **Quatre des huit objets à statut empilent sans borne.** Traité au chantier 3b |
| Brûlure annulée par 1 point de bouclier | `takeDamage()` vide le bouclier avant les PV sans atténuation. La répartition 150 % / 70 % de `02` §7.4 n'est pas implémentée. Traité au chantier 3b |
| `Trigger` non lu | `ON_ATTACK` et `EVERY_N_TICKS` sont fonctionnellement identiques ; seul `cooldownTicks` pilote la cadence |
| Enrage non calibré | `triggerTick` et `baseDamage` posés par raisonnement, jamais ajustés par playtest |
| Fragmentation de budget | `HeroItemAllocator` en first-fit naïf peut laisser un slot libre insuffisant pour un `TWO_HAND`. Piste : swap N-vers-1 pondéré par `slotCost()` |
| Garde anti-cascade | `EventDispatcher` n'a pas de garde-fou anti-boucle-infinie. Non urgent tant qu'aucun effet n'en re-déclenche un autre |
| IA scriptée non recalibrée | Elle hérite des compétences de ses héros sans ajustement volontaire de la difficulté |

---

## 4. Packaging client

### 4.1 Décision

**Electron**, décidé le 2 septembre 2026 après comparaison avec Tauri (voir `corebound-packaging-tauri-vs-electron.md` pour l'analyse complète).

Motifs déterminants :

- `steamworks.js` fonctionne avec Electron et non avec Tauri, qui exigerait d'écrire du Rust pour les achievements, le cloud et l'overlay.
- L'overlay Steam ne s'accroche pas de façon fiable aux WebView, donc pas à Tauri.
- Le packaging Steam Linux est balisé sous Electron et documenté comme problématique sous Tauri.
- Chromium embarqué garantit un rendu identique sur les trois OS — un vrai gain sur une UI dense en tooltips et animations.
- L'écosystème PHP desktop (NativePHP) repose sur Electron.

**Coût accepté :** environ 85 Mo de binaire et 200 Mo de RAM supplémentaires. Sans conséquence pour un jeu affichant des cartes statiques, vendu 14,99 € sur Steam.

### 4.2 Architecture du client

```
Corebound.exe (Electron)
├── resources/
│   ├── corebound-engine.exe      ← phpmicro + PHAR du domaine (~10–15 Mo)
│   ├── snapshots-corpus.json     ← corpus d'archive (~25 Mo)
│   └── dist/                     ← build Vue.js
└── main process (Node)
        ├── steamworks.js         ← achievements, cloud, overlay
        ├── mode en ligne  → HTTPS → API PHP distante
        └── mode hors ligne → sidecar corebound-engine.exe
                              stdin  : { playerBoard, opponentSnapshot, seed }
                              stdout : { combatLog }
```

### 4.3 Moteur embarqué

Construit avec `static-php-cli` et `phpmicro` : un interpréteur PHP statique autonome, sans dépendance système, fusionné avec le code applicatif empaqueté en PHAR (`box-project/box`).

- Supporté sur Windows, Linux, macOS et FreeBSD.
- Builds Windows de référence : ~3 Mo (5 extensions) à ~8,5 Mo (40+ extensions).
- Compression UPX disponible sur Windows et Linux, −30 à −50 % de taille.
- Utilisé en production par Laravel Herd, FrankenPHP et NativePHP.

**Pourquoi cela fonctionne bien ici :** le moteur est du **domaine pur**. `Simulator`, `TickEngine`, `ActionProcessor`, `StatusProcessor`, `EnrageProcessor` et `CombatBoardFactory` ne font ni HTTP ni base de données — ils prennent des objets et rendent un `CombatLog`. Extensions requises : `json`, `mbstring`, `random` (natif en 8.2+), et `pdo_sqlite` si une persistance locale est retenue.

**Point d'entrée à créer :** un script CLI qui désérialise du JSON depuis stdin, appelle `Simulator::run()`, sérialise le `CombatLog` sur stdout. Le contrat est déjà défini par la signature existante.

**Voie de repli documentée.** Si `static-php-cli` posait un problème imprévu, le portage du moteur en TypeScript reste ouvert. Le déterminisme rend la parité **mécaniquement vérifiable** en CI. Coût : double implémentation à maintenir à vie. Ce n'est pas le premier choix, mais ce n'est pas une impasse.

**FrankenPHP est écarté côté client** — il exige WSL sous Windows, ce qui est inenvisageable sur Steam. Il reste en revanche un bon candidat **côté serveur** : binaire unique, 50 à 70 % de RAM en moins qu'un PHP-FPM classique.

### 4.4 Points d'attention packaging

- **Signature de code** obligatoire, sinon avertissement Windows SmartScreen au premier lancement.
- **Chemins d'écriture** : répertoire de données applicatives de l'OS (`%APPDATA%`), jamais le dossier d'installation Steam. À déclarer dans la configuration Steam Cloud.
- **Audit de licences** : dépendances npm, dépendances Electron, **et extensions PHP compilées dans `micro.sfx`**. PHP est sous licence permissive, certaines extensions tierces ne le sont pas. Compiler le jeu minimal d'extensions nécessaire, jamais le build « gigantic ».
- **Steam Deck** : candidat naturel pour un jeu tour par tour. À évaluer après J4, pas avant.

---

## 5. PvP asynchrone et corpus de sunset

### 5.1 Flux

```
Joueur A → snapshot du plateau → backend (stockage, indexé par numéro de manche)
Joueur B → demande d'adversaire → backend → snapshot A + plateau B
        → simulation déterministe → CombatLog → client B
```

Aucune session persistante, aucun WebSocket, aucun état en mémoire par partie. Chaque combat est une requête HTTP indépendante. Le `CombatLog` est cachable : mêmes snapshots + même seed = même résultat.

### 5.2 Règle d'or

> **Tout combat PvP doit pouvoir être transformé en un snapshot autonome, anonymisé, versionné et rejouable localement.**

Cette règle permet un arrêt de service propre : arrêt du serveur, génération d'un corpus final, publication d'une mise à jour Steam, et le jeu continue de proposer du PvP simulé pendant des années sans coût récurrent.

### 5.3 Ce que la règle impose

| Contrainte | Détail |
|---|---|
| **Moteur exécutable côté client** | Sans lui, le corpus est illisible : le plateau du joueur varie, donc aucun `CombatLog` ne peut être pré-calculé. **C'est la précondition de la règle, pas sa conséquence** |
| **Versionnement strict** | Champ `engineVersion` dans chaque snapshot, **dès le premier commit PvP**. L'ajouter après coup invalide le corpus déjà produit. Seul point de toute l'architecture qui devienne irrattrapable |
| **Politique de migration** | Un snapshot d'une version antérieure doit être soit interprétable, soit migrable, soit explicitement retiré du corpus. À définir avant J2 |
| **Volume du corpus** | Cible ≥ 5 000 snapshots répartis par manche et par palier de puissance. À ~5 Ko l'unité, ~25 Mo embarqués — négligeable |
| **Anonymisation** | Le pseudonyme affiché doit être dissociable ou remplaçable dans le corpus final |
| **Testabilité continue** | Une bascule manuelle en mode hors ligne, disponible dès J2. Ne pas découvrir au moment du sunset que le mode local est cassé depuis six versions |

### 5.4 Amorçage

Au lancement, la base est vide. `ScriptedOpponentFactory` fournit l'infrastructure d'adversaires de secours. Transparence recommandée envers le joueur (décision D-05, `02` §6.4).

---

## 6. Persistance

### 6.1 Journal d'actions rejouable

Une `GameRun` est reconstruite en **rejouant, sur une seed fixe, la liste ordonnée des actions qui lui ont été appliquées**, jamais par désérialisation d'un instantané. Ce choix évite de toucher aux classes domaine `final readonly`.

**Règle d'intégrité critique :** une action n'est journalisée **qu'après validation réussie**. Une action invalide journalisée corrompt définitivement l'historique de rejeu.

**Bénéfice de second ordre :** ce format rend la migration SQLite → PostgreSQL nettement moins risquée qu'une sérialisation d'objets, et fournit une base naturelle au versionnement des snapshots.

### 6.2 Migration SQLite → PostgreSQL

À effectuer **avant J2**, jamais pendant. Le journal d'actions étant un format de données simple et non lié aux classes PHP, la migration est mécanique.

---

## 7. API HTTP

`Router`, `Request`, `ApiResponse`, `RunController`.

**Mapping exception → HTTP, centralisé dans le `Router`, ordre de capture strict :**

| Exception | Code |
|---|---|
| `RunNotFoundException` | 404 |
| `InvalidArgumentException` | 400 |
| `LogicException` | 409 |

**Piège connu :** `php://input` se lit une seule fois. `Request` doit être construit une fois et transmis, jamais reconstruit par handler.

**Seed :** paramètre optionnel de `RunController::create()`, avec repli sur `random_int`. Les tests passent une seed fixe pour garantir le déterminisme.

---

## 8. Frontend

- **Store Pinia** (`gameRun.ts`) avec garde fail-fast `requireRunId()`.
- **Client API typé**, proxy Vite pour le développement.
- **File d'attente d'animations** (`combatPlayback.ts`) : le `CombatLog` est **dépilé via une queue asynchrone**, jamais appliqué directement au state. Horloge à 100 ms/tick, révélation cumulative, annulation propre via `stop()`.
- **Audio** : `audioSettings` avec atténuation à 40 % pendant le combat, désactivé par défaut pour respecter les politiques d'autoplay navigateur.
- **Convention de test** : la logique métier (client API, store, composables) est testée en TDD ; les composants Vue ne le sont pas.

---

## 9. Infrastructure serveur

### 9.1 Dimensionnement

Chaque combat, PvE comme PvP, est une requête serveur. Une manche comporte 2 combats et 2 phases de marchand, soit ~30 requêtes par run.

À 1 000 joueurs actifs à 3 runs/jour : ~90 000 requêtes/jour, soit **~1 requête/seconde en moyenne**, ~15/s en pic. La simulation elle-même coûte de l'ordre de la milliseconde sur ≤ 6 entités.

### 9.2 Trois paliers

| Palier | Joueurs | Architecture | Coût mensuel HT |
|---|---|---|---|
| **Alpha** | 0–100 | 1 VPS, Caddy, PHP, SQLite, sauvegarde cron vers stockage objet. Hetzner CX23 (2 vCPU / 4 Go / 40 Go / 20 To) | **~6 €** |
| **Lancement** | 100–1 000 | 1 VPS + PostgreSQL, monitoring, sauvegardes off-site. Hetzner CX43 (4 vCPU / 8 Go / 80 Go) | **20–45 €** |
| **Succès** | 1 000–10 000+ | 2 instances PHP + load balancer, PostgreSQL dédié, Redis, workers, sauvegardes PITR | **80–180 €** |

**Fournisseur retenu : Hetzner**, avec OVHcloud en repli si la localisation France ou le support francophone devient un critère. AWS est écarté : complexité disproportionnée pour ce profil de charge.

**CDN : inutile.** Steam distribue le binaire. Cloudflare gratuit suffit pour la démo web.

### 9.3 Ce que le coût serveur n'est pas

Le coût serveur ne dépassera jamais 3 % du chiffre d'affaires. **Aucune décision de design ne doit en dépendre.** Le vrai coût du palier « succès » n'est pas l'argent, c'est le temps d'astreinte d'un développeur solo.

---

## 10. CI/CD

- GitHub Actions sur `ubuntu-latest`, jobs `php-tests` et `frontend-tests`.
- Version de Node épinglée via `frontend/.nvmrc`, référencée par `node-version-file`.
- **Portes bloquantes sur toute PR vers `dev`** : validation JSON, PHPUnit, PHPStan niveau 6, PHP CS Fixer côté backend ; ESLint, Prettier, `vue-tsc`, Vitest côté frontend.
- **À ajouter avant J0 :** build matriciel du binaire `corebound-engine` pour Windows, Linux et macOS, et test de parité de déterminisme entre serveur et binaire embarqué.
- **Porte locale :** `check-all.ps1`, fail-fast, PHPUnit → PHPStan → CS Fixer, puis Prettier → ESLint → `vue-tsc` → Vitest.

---

## 11. Décisions d'architecture écartées

| Option | Motif du refus |
|---|---|
| **Unreal Engine** | Aucune valeur créée : pas de 3D, pas de physique, pas de rendu temps réel exigeant. 6 à 18 mois de réécriture pour un résultat fonctionnellement identique. Réutilisation du backend ≈ 0 % |
| **Tauri** | `steamworks.js` indisponible, overlay Steam non fiable sur WebView, packaging Steam Linux problématique, Rust requis sur le chemin critique |
| **FrankenPHP côté client** | Exige WSL sous Windows |
| **Epic Online Services comme backend** | Dépendance externe sur un système qui doit rester intégralement archivable |
| **Sérialisation d'instantané** | Incompatible avec les classes domaine `final readonly` ; le journal d'actions est supérieur en migration et en versionnement |
| **WebSocket / Mercure** | Bonus envisageable en V2+ (notifications, signal de fin de combat), jamais une brique structurante |
| **Framework applicatif PHP** | Le moteur est du domaine pur ; un framework n'apporterait que de la surface |
