# Corebound / Projet Noyau — Résumé de session 002

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` dans les fichiers projet pour le cahier des charges complet V1.

**Stack** : PHP 8.3, Vue.js 3 en frontend. PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (JSON validation, PHPUnit, PHPStan, CS Fixer — tous bloquants, déclenchés sur Pull Request vers `dev`).

**Méthodologie à respecter impérativement** (rappel, cf. `SKILL.md` projet et session précédente) :
- Mode pédagogique actif : questions guidées plutôt que réponses directes, sauf demande explicite de réponse directe.
- TDD strict, bottom-up : test rouge → implémentation minimale → vert → refactor.
- Regard critique systématique sur toute nouvelle idée (complexité moteur, jouabilité réelle, cohérence V1, YAGNI) plutôt que validation par défaut — consigne explicite du projet.
- Vérifier CS Fixer + PHPStan + PHPUnit avant chaque commit.
- Célébrer les initiatives de l'utilisateur quand il dérive un concept/solution seul.

## État Git

- **Branche `feature/simulation-engine`** : terminée, mergée vers `dev` via Pull Request (titre `feat(domain): implement complete combat simulation engine`), CI vérifiée sur GitHub avant merge.
- **Nouvelle branche à créer** : `feature/json-hydratation`, à partir de `dev` à jour. C'est le chantier en cours au moment de la reprise — **la branche n'a probablement pas encore été créée/les premiers fichiers pas encore commités**, à vérifier avec l'utilisateur en début de session.

## Le Walking Skeleton — Architecture complète validée et testée (branche mergée)

Chaîne complète, TDD bottom-up, chaque brique testée unitairement ET en combat réel de bout en bout :

```
Simulator (orchestrateur)
  → TickEngine (horloge, avance le temps, décrémente cooldowns, détecte objets prêts)
    → EventDispatcher (routeur pur, zéro accès en écriture)
      → PendingAction (DTO immuable, intention enrichie)
        → ActionProcessor (résout Target, appelle les méthodes métier, produit un CombatEvent)
          → CombatHero / CombatItem (mutation d'état réelle)
```

### Classes finalisées et vertes

**`CombatHero`** (`Domain/Runtime/`) : `getId()` (délégation vers `Hero::$id`), `takeDamage()` (bouclier puis HP, `void`, CQS strict), `receiveHeal()` (plafonné `baseHp`), `gainShield()` (sans plafond), `isAlive()`, `getHp()`, `getShield()`.

**`CombatItem`** (`Domain/Runtime/`) : `decrementCooldown(int $ticks = 1)`, `isReady()`, `resetCooldown()`, `getCooldown()`, `getEffects()`.

**`CombatBoard`** (`Domain/Runtime/`) : `final readonly class`, `getHero()`, `getItems()`, `getReadyItems()`, `hasAliveHero()`.

**`EventDispatcher`** (`Domain/Engine/`) :
- `register()`, `registerBoard()` (boucle sur tous les items/effects d'un board)
- `getListenersFor(Trigger)` : lecture d'inspection
- `dispatch(Trigger)` : broadcast global (tous les listeners du trigger)
- `dispatchForItem(CombatBoard, CombatItem)` : déclenchement **ciblé** sur un objet précis, résout le piège du "broadcast qui déclenche aussi les objets non prêts partageant le même trigger" — méthode dédiée plutôt qu'un flag argument (cohérent avec `getReadyItems()` vs `getItems(bool)`)
- `toPendingActions()` : méthode privée commune de dépliage Effect→Action, partagée entre `dispatch()` et `dispatchForItem()` (refactor post-vert)
- **Décision actée** : `registerBoard()` est un appel de **setup unique**, responsabilité du `Simulator`, jamais rappelé à chaque tick (risque d'explosion de listeners dupliqués sinon).

**`TickEngine`** (`Domain/Engine/`) : `__construct(EventDispatcher $eventDispatcher)` (dépendance stable en constructeur, pas en paramètre de `tick()`). `tick(SimulationContext): array` (retourne `list<PendingAction>`) :
1. `$context->advanceTick()`
2. Décrémente tous les cooldowns (les deux boards)
3. Pour chaque item prêt : `resetCooldown()` **avant** `dispatchForItem()` (décision actée : la charge est "consommée" au moment du déclenchement, indépendamment de quand les conséquences sont calculées — sinon un effet d'accélération de cooldown serait annulé par un reset tardif)
4. Retourne la liste agrégée des `PendingAction` du tick

**Piège identifié et corrigé** : avec `cooldownTicks = 1`, un objet frappe **à chaque tick**, pas "1 tick de délai puis frappe". À vérifier systématiquement à la main avant d'écrire un test avec cooldown.

**`SimulationContext`** (`Domain/Engine/`) : `playerBoard`, `opponentBoard` (readonly), `randomizer` (natif `\Random\Randomizer` + `\Random\Engine\PcgOneseq128XslRr64($seed)`, pas de wrapper `SeededRng` — YAGNI), `log` (mutable `CombatLog`), `currentTick` (mutable). `getBoards(): array` pour itération symétrique. `advanceTick()`.
- **`getOppositeBoard(CombatBoard $board): CombatBoard`** : ajouté pour résoudre `Target::ENEMY`. Lance `\InvalidArgumentException` si le board n'est ni playerBoard ni opponentBoard — **fail-fast assumé consciemment** (protection d'invariant réel, pas anticipation type YAGNI — distinction actée explicitement avec l'utilisateur).

**`PendingAction`** (`Domain/Engine/`) : `final readonly class` — `action`, `sourceItem`, `sourceBoard`.

**`ActionProcessor`** (`Domain/Engine/`) : **stateless, pas de constructeur avec dépendances** (contrairement à `TickEngine`) — tout arrive via les paramètres de `process()`.
- `process(PendingAction, SimulationContext): CombatEvent`
- Résout la cible via un `match` privé sur `Target` (`ENEMY` → `getOppositeBoard()`, `SELF` → sourceBoard, `default => throw LogicException` pour garder le match "exhaustif" explicitement)
- Dispatch sur `ActionType` via `match` (même pattern `default => throw`)
- **Les 3 `ActionType` du scope V1 sont tous implémentés et testés** :
  - `DEAL_DAMAGE` → `processDealDamage()` → `EventType::DAMAGE_DEALT`, payload `{amount, shieldDamage, hpDamage, target}`
  - `GAIN_SHIELD` → `processGainShield()` → `EventType::SHIELD_GAINED`, payload `{amount, shieldGained, target}`
  - `HEAL` → `processHeal()` → `EventType::HEAL_RECEIVED`, payload `{amount, hpHealed, target}` (respecte le plafond `baseHp` — `hpHealed` peut être < `amount`)
- **Pattern de calcul systématique** : capture état avant/après (`getHp()`/`getShield()`), delta calculé en dehors de `CombatHero` (CQS préservé : `CombatHero` reste orienté commande pure, `ActionProcessor` observe et construit le fait historique)

**`CombatEvent`** (`Domain/Event/`) : `final readonly class` — `tick: int`, `type: EventType`, `payload: array`.

**`CombatLog`** (`Domain/Engine/`) : mutable, `addEvent(CombatEvent)`, `getEvents()`, `count()`.

**`Simulator`** (`Domain/Engine/`) : `final class`.
- `__construct(int $maxTicks = 500, ?ActionProcessor $actionProcessor = null)`
- `run(CombatBoard $playerBoard, CombatBoard $opponentBoard, Randomizer $randomizer): SimulationResult`
- Flux : (1) setup — `new EventDispatcher()`, `registerBoard()` sur les deux boards, une seule fois ; (2) boucle `while (currentTick < maxTicks && bothHeroesAlive)` : appelle `tickEngine->tick($context)` (qui gère `advanceTick()` en interne — **piège corrigé** : ne pas dupliquer `advanceTick()` dans `Simulator`, un seul endroit doit avancer le temps) ; parcourt les `PendingAction`, `break` **immédiat** dès qu'un héros meurt (pas de "frappe sur cadavre"), sinon `ActionProcessor::process()` + `$context->getLog()->addEvent($event)` ; (3) résolution du vainqueur via `match(true)` sur l'état final des deux héros (3 cas exclusifs : joueur vivant seul, adversaire vivant seul, `null` sinon — couvre timeout ET double-KO, ce dernier étant **structurellement impossible** grâce au `break`, prouvé par raisonnement explicite).

**`SimulationResult`** (`Domain/Engine/`) : `winner: ?CombatHero`, `totalTicks: int`, `log: CombatLog`.

### Tests de bout en bout validés (preuves solides, calculées à la main avant exécution)

1. **Combat asymétrique** (1 objet actif d'un seul côté) : valide le Walking Skeleton complet.
2. **Combat symétrique** (deux objets identiques, un de chaque côté) : valide le `break` (pas de riposte fantôme) et l'ordre déterministe par déclaration des boards (joueur toujours en premier).
3. **Combat mixte 3 ActionType** (dague + bouclier côté joueur, bâton + potion côté adversaire) : valide l'absorption réelle du bouclier, l'extension de survie par le soin, et la cohérence du `CombatLog` multi-types. **Piège rencontré et corrigé deux fois** : calcul "tick par tick" en agrégeant une perte nette est faux — il faut tracer **action par action à l'intérieur du tick** (l'ordre des 4 actions dans le tick compte, notamment que la dague du joueur agit en premier et peut tuer l'adversaire avant que son propre tour d'actions ne s'exécute).

## Idées reportées (V2+, ne pas implémenter maintenant)

- **Ordre d'activation des items / initiative** : quand plusieurs items de boards différents partagent un trigger au même tick, l'ordre actuel est déterministe par ordre de déclaration des boards (joueur avant adversaire), sans stat de vitesse/initiative. Sujet identifié en pleine conscience via l'écriture du test de combat symétrique — reporté à une V2 avec combats symétriques complexes.
- **Garde-fou anti-boucle-infinie** sur `EventDispatcher`/cascade d'événements : mentionné dès la conception initiale de l'architecture, mais non implémenté — non urgent tant qu'aucun effet ne re-déclenche un autre effet (pas de cascade réelle dans le scope V1 actuel).
- Multi-héros par board, cooldown variable/accéléré, `currentMaxHp` variable, persistance d'état entre combats, DoT/poison/burn, conversion d'affinité (`SetAffinity`), `SeededRng` wrapper — tous déjà actés comme différés dans la session précédente (voir `corebound-resume-session_001.md`), toujours valables.

## Point en discussion au moment de l'interruption (NON TRANCHÉ — à reprendre en priorité)

L'utilisateur a proposé un plan pour le chantier `feature/json-hydratation` :
1. Interfaces `Domain/Repository/` (`HeroRepositoryInterface`, `ItemRepositoryInterface`) façon architecture hexagonale
2. Implémentations concrètes `Infrastructure/Repository/Json/` (`JsonHeroRepository`, `JsonItemRepository`)
3. Fixtures JSON dans `config/fixtures/` (incohérent avec `config/game/` déjà acté dans `game-design-notes.md` — à clarifier)
4. Mapping manuel explicite (pas de Symfony Serializer) — bon réflexe YAGNI/zéro-magie, non contesté

**Trois questions ont été posées à l'utilisateur, sans réponse encore reçue :**
1. Le pattern Repository + Interface est-il un besoin réel maintenant (un seul format de données prévu : JSON), ou une abstraction par anticipation qui contredit le YAGNI déjà appliqué ailleurs (`SeededRng`) ? → à trancher avant de coder quoi que ce soit.
2. `config/fixtures/` vs `config/game/` déjà acté — lequel garder ?
3. Une interface unique et générique (`find`/`findAll`) partagée par Hero et Item a-t-elle une vraie valeur maintenant avec un seul héros et 30 objets, ou est-ce prématuré ?

**Prochaine étape immédiate à la reprise** : obtenir les réponses de l'utilisateur à ces trois questions avant d'écrire la moindre ligne de code sur ce chantier. Ne pas re-proposer le plan initial tel quel sans qu'il ait été challengé consciemment sur ces points.

## Rappel des conventions

- Commits Conventional Commits, scope `domain` (probablement `infra` ou similaire à définir pour la nouvelle branche, à clarifier avec l'utilisateur).
- Un commit par brique logique terminée et verte.
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, CI vérifiée avant merge (JSON validation, PHPUnit, PHPStan, CS Fixer, tous bloquants sur `ubuntu-latest`).
