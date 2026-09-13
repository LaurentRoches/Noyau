# Corebound / Projet Noyau — Résumé de session

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` dans les fichiers projet pour le cahier des charges complet V1.

**Stack** : PHP 8.3 (Symfony, peut-être plus tard, actuellement natif), Vue.js 3 en frontend. Repo GitHub, développement en feature branches vers `dev`. Branche courante : `feature/simulation-engine`.

**Tooling** : PHPUnit 12, PHPStan niveau 6, PHP CS Fixer — tous les checks doivent rester verts après chaque étape (`composer run cs`, `composer run stan`, `composer run test`).

## Méthodologie de travail à respecter impérativement

**Mode d'accompagnement pédagogique actif** (skill `SKILL.md` dans les fichiers projet) :
- Poser des questions guidées plutôt que donner le code directement — sauf demande explicite de l'utilisateur ("je veux la réponse directement"), auquel cas répondre sans détour.
- TDD strict, bottom-up : toujours écrire le test avant l'implémentation, en commençant par la classe la plus simple / sans dépendance.
- Cycle Rouge → Vert → Refactor respecté à chaque étape.
- Appliquer un regard critique systématique sur les nouvelles idées de l'utilisateur (complexité moteur, jouabilité réelle, cohérence avec le scope V1) plutôt que de valider par défaut — c'est une consigne explicite du projet.
- Principe YAGNI appliqué de façon réfléchie : ne pas coder par anticipation, mais accepter les décisions qui coûtent peu maintenant et évitent une vraie dette plus tard (ex. paramètre par défaut `int $ticks = 1` sur `decrementCooldown()`).
- Toujours vérifier la cohérence entre code et tests avant de valider (namespace, casse, signatures).
- Célébrer explicitement les initiatives de l'utilisateur quand il dérive un concept ou une solution seul (ex. `ActionProcessor`, `Target` abstrait, `PendingAction`, refactor `min()`/`max()`).

## Architecture du moteur — validée et actée

Chaîne complète de responsabilités (Single Responsibility strict, "séparer l'intention de l'exécution") :

```
TickEngine (horloge)
  → génère un déclenchement temporel
  → EventDispatcher (routeur, ZÉRO accès en écriture)
  → renvoie une liste de PendingAction (intentions enrichies et immuables)
  → ActionProcessor (à construire — reçoit le SimulationContext complet)
  → résout Target::SELF/ENEMY en CombatHero concret
  → appelle les méthodes métier de CombatHero (mutation d'état réelle)
  → CombatHero produit un CombatEvent
  → réinjecté dans EventDispatcher (cascade, garde-fou anti-boucle-infinie à prévoir)
```

**Simulator** (non codé) : orchestrateur global, gère win/loss, ne connaît pas les règles de dégâts/bouclier.
**TickEngine** : gère le temps, ne sait pas qui gagne, ne connaît pas les triggers/actions en détail.
**EventDispatcher** : routeur pur, aucun accès en écriture au plateau, gère l'inscription et la notification, portera plus tard la logique anti-boucle-infinie et la priorisation.
**ActionProcessor** (non codé) : traduit une `PendingAction` en appel de méthode métier sur l'entité concrète. Reçoit le `SimulationContext` complet (2 boards + RNG) pour pouvoir résoudre n'importe quelle cible.
**CombatHero / CombatItem** : modèles riches, mutation d'état + règles internes (bouclier puis HP, plafonds).

### Séparation Définition (readonly) / Runtime (mutable) — décision fondatrice

- `Hero`, `Item`, `Effect`, `Action` (dans `Domain/Model/`) : DTOs `readonly`, statiques, chargés depuis JSON. Ne changent jamais pendant un combat.
- `CombatHero`, `CombatItem`, `CombatBoard` (dans `Domain/Runtime/`) : état mutable le temps d'un seul combat, reconstruit à zéro à chaque nouveau combat (décision actée : pas de persistance d'état entre combats en V1, mais changement bas coût à ajouter plus tard si besoin — via une méthode factory alternative de type `fromPreviousState()`, sans toucher à la logique métier existante).

### Target — résolution abstraite

`Enum Target { SELF, ENEMY, ALL_ENEMIES, ALL_ALLIES }` : une `Action` porte une valeur abstraite, jamais une référence concrète. C'est l'`ActionProcessor` (pas encore codé) qui interprète `Target` au moment de l'exécution, en fonction du `SimulationContext` complet — garde l'`Action` (donnée JSON statique) totalement indépendante du runtime.

## État d'avancement technique détaillé

### Structure du projet
```
backend/
├── config/game/        # heroes.json, items.json
├── src/Domain/
│   ├── Model/           # Hero, Item, Effect, Action (tous readonly)
│   ├── Enum/            # Rarity, Trigger, ActionType, Target, EventType
│   ├── Event/           # CombatEvent
│   ├── Runtime/         # CombatHero, CombatItem, CombatBoard
│   └── Engine/          # CombatLog, SimulationContext, TickEngine, EventDispatcher, PendingAction
└── tests/Domain/        # (structure miroir, attention : garder les namespaces cohérents
                            avec le chemin réel du fichier, un bug de mismatch a déjà été rencontré)
```

### ✅ Classes codées, testées et vertes

**`CombatHero`** (`Domain/Runtime/`)
- Construit à partir d'un `Hero` (definition), initialise `currentHp = baseHp`, `currentShield = baseShield`
- `takeDamage(int $damage)` : absorption bouclier puis HP, via `min()`/`max()` (refactor propre, sans if/elseif/else), garde `damage` toujours positif via `max(0, $damage)`
- `receiveHeal(int $heal)` : plafonné à `baseHp` (pas encore de `currentMaxHp` variable — reporté, cf. "Idées reportées")
- `gainShield(int $shield)` : SANS plafond (règle de design confirmée : un objet donnant du bouclier n'a jamais de limite haute)
- `isAlive(): bool` : `currentHp > 0`
- `getHp()`, `getShield()` : lecture

**`CombatItem`** (`Domain/Runtime/`)
- Construit à partir d'un `Item` (definition), initialise `currentCooldown = cooldownTicks`
- `decrementCooldown(int $ticks = 1)` : plafonné à 0 via `max()`. Le paramètre par défaut anticipe un futur effet d'accélération de cooldown sans casser l'API actuelle (décision validée : coût nul, bénéfice réel).
- `isReady(): bool` : `currentCooldown === 0`
- `resetCooldown()` : relit `cooldownTicks` depuis la définition
- `getCooldown()` : lecture
- `getEffects(): Effect[]` : expose les effets de l'`Item` sous-jacent (ajouté pour `EventDispatcher::registerBoard()`)

**`CombatBoard`** (`Domain/Runtime/`, anciennement nommé `Board` — renommé pour cohérence)
- `final readonly class`, construit avec un `CombatHero` et un `array<CombatItem>` (les deux déjà construits ailleurs — `CombatBoard` n'assemble pas, ne construit rien lui-même)
- `getHero()`, `getItems()` : lecture directe
- `getReadyItems(): CombatItem[]` : filtre via `isReady()` (principe "Tell, Don't Ask" nommé explicitement par l'utilisateur)
- `hasAliveHero(): bool` : délègue à `hero->isAlive()` — état factuel, PAS d'arbitrage de victoire/défaite (ça reste au `Simulator`)

**`CombatEvent`** (`Domain/Event/`)
- `final readonly class` avec `tick: int`, `type: EventType`, `payload: array` (non typé finement pour l'instant — YAGNI assumé, à affiner quand `ActionProcessor` précisera les formes de payload réelles)

**`EventType`** (`Domain/Enum/`)
- Actuellement 3 cas seulement, scope minimal : `DAMAGE_DEALT`, `HEAL_RECEIVED`, `SHIELD_GAINED` (correspond exactement à ce que `CombatHero` sait produire aujourd'hui)

**`CombatLog`** (`Domain/Engine/`)
- Classe mutable (pas readonly, contrairement à `CombatBoard` — le tableau `$events` doit grandir)
- `addEvent(CombatEvent $event)`, `getEvents(): CombatEvent[]`, `count(): int`

**`SimulationContext`** (`Domain/Engine/`)
- Classe mutable dans son ensemble (à cause de `$currentTick`, scalaire incrémenté), mais chaque propriété individuelle marquée `readonly` sauf `$currentTick`
- Constructeur : `CombatBoard $playerBoard`, `CombatBoard $opponentBoard`, `Randomizer $randomizer`, `CombatLog $log = new CombatLog()` (new in initializer, PHP 8.1+, pas de piège de partage d'instance — chaque appel construit un nouveau `CombatLog`)
- RNG natif PHP : `\Random\Randomizer` + `\Random\Engine\PcgOneseq128XslRr64($seed)` — PAS de wrapper `SeededRng` (YAGNI assumé : pas encore de besoin concret de `rollDice()`/`chance()`, sera ajouté quand un effet type "Critique" existera réellement)
- `getPlayerBoard()`, `getOpponentBoard()` : accès nommés (clarté API/restitution Vue.js)
- `getBoards(): array{CombatBoard, CombatBoard}` : pour permettre au `TickEngine` d'itérer symétriquement sans dupliquer de code
- `getRandomizer()`, `getLog()`, `getCurrentTick()`, `advanceTick()` (incrémente `$currentTick`)

**`TickEngine`** (`Domain/Engine/`) — **version minimale, phase de recharge uniquement**
- `tick(SimulationContext $context): void` :
  1. `$context->advanceTick()`
  2. Pour chaque board (via `getBoards()`), pour chaque item (`getItems()`), appelle `decrementCooldown()` sans condition (la garde à 0 est déjà assurée par `CombatItem` lui-même — pas besoin de dupliquer la vérification)
- **Volontairement mis en pause ici** : la phase suivante (détection des objets prêts via `getReadyItems()` + déclenchement des actions) nécessite `EventDispatcher`/`ActionProcessor`, qui viennent d'être construits/sont en cours. `CombatHero` n'a actuellement aucun état temporel propre (pas de DoT/poison en V1) donc rien à faire de son côté à chaque tick pour l'instant.
- Classe instanciée (pas statique) — anticipation consciente : `TickEngine` aura bientôt des dépendances collaborateurs (`EventDispatcher`) à recevoir en constructeur, pattern injection de dépendances Symfony-friendly.

**`Target`** (`Domain/Enum/`)
- `SELF`, `ENEMY`, `ALL_ENEMIES`, `ALL_ALLIES`

**`PendingAction`** (`Domain/Engine/`)
- `final readonly class` : `Action $action`, `CombatItem $sourceItem`, `CombatBoard $sourceBoard`
- Le DTO enrichi que produit `EventDispatcher::dispatch()`, permettant à l'`ActionProcessor` (futur) de résoudre `Target::SELF`/`ENEMY` par rapport à la bonne source

**`EventDispatcher`** (`Domain/Engine/`) — **cycle complet inscription + notification, terminé**
- Stockage interne : `array<string, list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}>>`, indexé par `$trigger->value` (recherche O(1))
- `register(Trigger, CombatBoard, CombatItem, Effect)` : inscription atomique d'un seul triplet
- `registerBoard(CombatBoard)` : boucle de confort sur tous les items/effects d'un board, construite au-dessus de `register()`
- `getListenersFor(Trigger): array` : lecture d'inspection (utile aux tests, potentiellement ailleurs)
- `dispatch(Trigger): list<PendingAction>` : résout tous les listeners du trigger, **déplie chaque `Effect::$actions` en une `PendingAction` par `Action` individuelle** (pas une seule PendingAction par effet — décision validée avec justification : la structure de `PendingAction` avec `action` au singulier impose ce choix, et ça simplifie le traitement séquentiel/atomique côté `ActionProcessor`)
- **Zéro accès en écriture** au plateau — garantie architecturale maintenue de bout en bout, jamais contredite

## Idées explorées puis reportées (à ne PAS implémenter maintenant)

- **Modèle multi-héros par board** (3 héros, slots armes 1-main/2-mains, compétences par héros, bouclier repensé comme modificateur plutôt que pool) — proposé via un croquis, reconnu par l'utilisateur lui-même comme contradictoire avec le cahier des charges V1 ("1 seul héros, retirer une variable tant que le moteur n'est pas validé"). Explicitement mis de côté pour une itération future, à ne reconsidérer qu'une fois le moteur solo à 1 héros validé et le fun démontré.
- **Cooldown variable/accéléré par effet externe** : anticipé via le paramètre par défaut `$ticks = 1` sur `decrementCooldown()`, mais aucune logique d'accélération réelle codée.
- **`currentMaxHp` variable** (distinction heal plafonné vs gainMaxHp qui repousse le plafond) : idée de design reconnue valide mais reportée. Si implémentée un jour, vivra dans `CombatHero` (jamais dans `Hero`, cohérent avec la séparation definition/runtime).
- **Persistance d'état entre combats** (mode de jeu "usure", HP/cooldowns non resetés) : reporté, mais un point d'entrée alternatif (`fromPreviousState()`) suffira à l'ajouter sans casser l'existant.
- **DoT/poison/burn** (effets sur la durée) : explicitement repoussés hors scope de `CombatHero`/`TickEngine` pour l'instant, dépendent d'un `TickEngine` plus mature.
- **Conversion d'affinité** (déjà actée dans `game-design-notes.md`) : type d'action `SetAffinity` prévu dans le schéma mais non implémenté.
- **`SeededRng` wrapper** (`rollDice()`, `chance()`) : pas construit, YAGNI — à ajouter seulement quand un effet concret (ex. Critique) en aura besoin.

## Prochaine étape immédiate

**Reprendre `TickEngine`** pour lui faire déclencher réellement les objets prêts, maintenant qu'`EventDispatcher` est fonctionnel :

1. Après la phase de recharge (déjà codée), ajouter la détection des objets prêts via `$board->getReadyItems()`.
2. Pour chaque objet prêt : déclencher son trigger via `EventDispatcher::dispatch()`, obtenir les `PendingAction` résultantes.
3. **Point bloquant à résoudre avant d'aller plus loin : `ActionProcessor` n'existe pas encore.** C'est lui qui doit recevoir les `PendingAction` + le `SimulationContext` complet, résoudre `Target::SELF`/`ENEMY` en `CombatHero` concret, appeler la méthode métier correspondante (`takeDamage`, `receiveHeal`, `gainShield`), et produire le `CombatEvent` résultant à ajouter au `CombatLog`.
4. Une fois un objet déclenché, il faut aussi `resetCooldown()` dessus.
5. Question ouverte à trancher en premier lieu à la reprise : est-ce que `TickEngine` doit recevoir l'`EventDispatcher` en dépendance de constructeur (cohérent avec la décision "instanciable, pas statique" déjà actée), ou le recevoir en paramètre de `tick()` aux côtés du `SimulationContext` ?

## Convention de travail (rappel)

- Commits Conventional Commits, scope `domain`, un commit par "brique" logique terminée et verte.
- Toujours vérifier CS Fixer + PHPStan + PHPUnit avant de committer.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin → nouveau test.
- Poser des questions guidées à chaque nouvelle classe/méthode avant de donner du code, sauf demande explicite de réponse directe.
