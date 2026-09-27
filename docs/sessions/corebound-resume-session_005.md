# Corebound / Projet Noyau — Résumé de session 005

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` dans les fichiers projet pour le cahier des charges complet V1 (attention : ce document est maintenant partiellement dépassé sur le point "1 seul héros", voir plus bas).

**Stack** : PHP 8.3, Vue.js 3 en frontend. PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (JSON validation, PHPUnit, PHPStan, CS Fixer — tous bloquants, déclenchés sur Pull Request vers `dev`).

**Méthodologie à respecter impérativement** (rappel, cf. `SKILL.md` projet et sessions précédentes) :
- Mode pédagogique actif : questions guidées plutôt que réponses directes, sauf demande explicite de réponse directe.
- TDD strict, bottom-up : test rouge → implémentation minimale → vert → refactor.
- Regard critique systématique sur toute nouvelle idée (complexité moteur, jouabilité réelle, cohérence V1, YAGNI) plutôt que validation par défaut — consigne explicite du projet.
- Vérifier CS Fixer + PHPStan + PHPUnit avant chaque commit.
- Célébrer les initiatives de l'utilisateur quand il dérive un concept/solution seul.

## État Git

- **Branche `feature/vestige-board-refactor`** : terminée, tous les checks verts (CS Fixer, PHPStan niveau 6, PHPUnit 51 tests). Prête à être committée (message de commit proposé en fin de session, voir historique) puis mergée vers `dev` via Pull Request.
- **Prochaine branche à créer** : `feature/status-effects-engine`, à partir de `dev` à jour une fois le merge ci-dessus fait. C'est le chantier annoncé depuis la session 004, maintenant débloqué par une base architecturale saine.

## Mise à jour majeure du cahier des charges V1 — décidée cette session

**Le modèle "1 seul héros" acté dans `game-design-notes.md` est révisé.** Un croquis apporté par l'utilisateur (board avec Vestige central + 3 blocs héros, chacun avec 2 emplacements d'objets + une compétence future) a fait émerger une réinterprétation qui **préserve un invariant déjà validé** : le total de 6 slots d'objets par joueur (déjà acté) est maintenant réparti en **3 héros × 2 slots**, plutôt qu'un seul héros × 6 slots. Ce n'est donc pas une extension de scope arbitraire.

**Ce qui est acté maintenant :**
- Un `CombatBoard` regroupe **1 `CombatVestige` + 1 à 3 `CombatHero`** (garde fail-fast dans le constructeur).
- Le **Vestige** définit l'affinité du **plateau**. Les héros et les objets gardent chacun leur propre affinité indépendante, sans influence sur le plateau pour l'instant — anticipe un futur système de synergie (bonus si affinité héros/objet == affinité Vestige), non implémenté en V1.
- Les **compétences de héros** (bloc "Compétence" du croquis) restent explicitement **hors scope** de ce chantier et de la V1 — notées pour plus tard, pas de schéma anticipé.
- Le **multi-affinité par board** (3 héros d'affinités différentes sous un même Vestige) est une conséquence naturelle de ce modèle, mais reste sans effet mécanique tant que le système de synergie n'est pas construit.

## Chantier terminé cette session — Migration Vestige + Multi-héros

**Décision de méthode validée en amont (analyse de l'utilisateur, non de Claude)** : faire la migration du pool HP/Shield vers `CombatVestige` **avant** d'implémenter les statuts, pour éviter une double réécriture de `CombatHero`, `ActionProcessor` et de tous les tests concernés. Analyse jugée solide et actée telle quelle.

### Nouvelles classes

**`Vestige`** (`Domain/Model/`) : `final readonly class` — `id`, `name`, `affinity`, `baseHp`, `baseShield`. Copie stricte du patron `Hero`.

**`CombatVestige`** (`Domain/Runtime/`) : porte tout l'état vivant du plateau — `currentHp`, `currentShield`. Méthodes : `takeDamage()` (bouclier puis HP, via `min()`/`max()`), `receiveHeal()` (plafonné à `baseHp`), `gainShield()` (sans plafond), `isAlive()`, `getHp()`, `getShield()`, `getId()`. Copie exacte de l'ancien `CombatHero`, TDD rouge→vert→refactor complet (10 tests, tous les cas limites : bouclier seul, bouclier+HP, HP à 0, soin plafonné/non plafonné, mort).

**`JsonVestigeRepository`** (`Infrastructure/Repository/Json/`) : même patron que `JsonHeroRepository`/`JsonItemRepository`.

### Classes modifiées

**`CombatHero`** (`Domain/Runtime/`) : désolidarisé de tout état de combat. Ne garde que `getId()` et `getDefinition()` — pure identité + (futur) conteneur d'objets.

**`CombatBoard`** (`Domain/Runtime/`) : signature changée de `(CombatVestige, CombatHero, array $items)` à `(CombatVestige, array $heroes, array $items)`. Garde fail-fast dans le constructeur : `\InvalidArgumentException` si moins de 1 ou plus de 3 héros. `getHero()` remplacé par `getHeroes(): array`. `isAlive()` délègue à `$vestige->isAlive()` (pas aux héros — la mort du plateau dépend uniquement du Vestige).

**`ActionProcessor`** (`Domain/Engine/`) : `resolveTargetBoard()` inchangé dans son fonctionnement, mais `processDealDamage()`/`processGainShield()`/`processHeal()` opèrent maintenant sur `CombatVestige` directement (paramètre `$targetBoard` retiré de leur signature, devenu inutile). Le payload `CombatEvent` utilise maintenant `'target' => $targetVestige->getId()` au lieu de l'id du héros.

**`SimulationResult`** (`Domain/Engine/`) : `$winner` retypé de `?CombatHero` à `?CombatBoard` — c'est le plateau entier qui gagne, pas un héros isolé (cohérent avec le multi-héros : un `CombatHero` unique n'a plus de sens comme "vainqueur").

**`Simulator`** (`Domain/Engine/`) : résolution du vainqueur (`match(true)`) retourne désormais `$playerBoard`/`$opponentBoard` au lieu de `->getHero()`.

**`CombatBoardFactory`** (`Application/Factory/`) : signature `createBoard(string $vestigeId, array $heroIds, array $itemIds = [])`. Construit un `CombatVestige` via `JsonVestigeRepository`, boucle sur `$heroIds` pour construire un `CombatHero` par id. **Validation du budget de slots actée comme provisoire** : somme de `itemSlots` sur tous les héros du board (`$totalItemSlots`), les items ne sont pas encore assignés à un héros précis — juste comptés globalement. Message d'exception : `"Cannot equip %d items: exceeds total slot budget (%d) across %d hero(es)"`.

### Tests mis à jour (cascade complète, tous verts)

`CombatVestigeTest` (nouveau, 10 tests), `CombatHeroTest` (réduit à `testGetIdDelegatesToHeroDefinition`), `CombatBoardTest` (héros en tableau, + 3 nouveaux tests de garde : 3 héros acceptés, 0 rejeté, 4 rejeté), `ActionProcessorTest` (vestige id distinct du hero id dans `createBoard()` — piège identifié : les deux boards avaient le même id `shadow_vestige` par défaut, ce qui aurait rendu les assertions `target` silencieusement fausses), `EventDispatcherTest`, `SimulationContextTest`, `SimulatorTest` (assertions `$result->winner` comparent maintenant à `$playerBoard`/`$opponentBoard`), `TickEngineTest`, `CombatBoardFactoryTest` (message d'exception mis à jour, `getHeroes()[0]`), `SimulationE2ETest` (`heroIds` en tableau).

**Résultat final** : `composer run cs` / `composer run stan` (niveau 6, 0 erreur) / `composer run test` (51 tests, tous verts) confirmés par l'utilisateur.

## Point ouvert, non bloquant, à garder en tête

**Répartition des items par héros non implémentée.** `CombatBoardFactory::createBoard()` prend toujours `array $itemIds` à plat (pas d'association à un héros précis) — le budget de 6 slots est vérifié globalement sur le board, pas 2 par héros. Le modèle "2 slots par héros" du croquis n'est donc pas encore appliqué structurellement, juste le nombre total. Si un jour on veut vraiment assigner un objet à un héros précis (ex. pour un système de compétence qui dépend des objets équipés par CE héros), il faudra faire évoluer `itemIds: list<string>` vers une structure `array<heroId, list<string>>`. Noté pour plus tard, pas urgent tant qu'aucune mécanique ne distingue "quel héros porte quel objet".

## Prochain chantier — Reprise de `feature/status-effects-engine`

Toutes les décisions actées en session 004 restent valables, la base est maintenant prête pour les accueillir sans dette supplémentaire :

- Une seule classe plate `ActiveStatus` (pas de hiérarchie polymorphe) — `statusType`, `stacks: int`, `remainingTicks: int`.
- **Où vit `ActiveStatus`** : la question ouverte en session 004 ("`CombatHero` ou attendre la migration Vestige ?") est maintenant tranchée par construction — `ActiveStatus[]` vivra sur **`CombatVestige`**, puisque c'est lui qui porte tout l'état vivant du plateau (HP, shield, et maintenant les statuts).
- **Règle de stacking** : ré-application avant expiration → les stacks s'additionnent, la durée la plus longue des deux est conservée.
- Comportement différencié par type de statut (Poison ignore le bouclier / Burn passe par le bouclier / Regen soigne / Ward donne du bouclier) via un `match` dans un futur `StatusProcessor`, sur le modèle de `ActionProcessor`/`ActionType`.
- Ordre proposé dans le tick (à confirmer/tester) : `advanceTick()` → décrément cooldowns → pulsation des statuts actifs (dégâts/soins + décrément durée) → détection objets prêts → dispatch.
- Premier test TDD à écrire : `ActiveStatus` seule, sans dépendance.

**Questions encore ouvertes, à trancher en priorité à la reprise :**
1. Où vit `StatusProcessor` dans le graphe de dépendances : dépendance de constructeur sur `TickEngine` (comme `EventDispatcher`), ou collaborateur séparé invoqué directement par `Simulator` ?
2. Nouveaux cas `EventType` à ajouter pour le `CombatLog` (ex. `STATUS_APPLIED`, dégâts/soins de tick) — pas encore définis.
3. Répartition finale 14/11/5 vs 14/10/6 des 30 objets déjà rédigés — à confirmer avant de considérer le contenu V1 comme clos (question en suspens depuis la session 004, indépendante du chantier statuts).
4. Cohérence à vérifier une fois `ActiveStatus` posé sur `CombatVestige` : est-ce que `CombatVestige::takeDamage()` doit rester complètement ignorant des statuts (le `StatusProcessor` appelant `takeDamage()`/`receiveHeal()`/`gainShield()` depuis l'extérieur, comme le fait déjà `ActionProcessor`), ou est-ce que certains statuts ont besoin d'un accès plus direct ? À trancher au moment d'écrire le premier test `StatusProcessor`, pas avant.

## Rappel des conventions

- Commits Conventional Commits, scope `domain` pour le moteur.
- Un commit par brique logique terminée et verte (ou un commit récapitulatif si le chantier n'a pas été découpé au fil de l'eau — cas de cette session, assumé consciemment).
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, CI vérifiée avant merge.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin → nouveau test.
- Mode pédagogique par défaut : questions guidées avant le code, sauf demande explicite de réponse directe.