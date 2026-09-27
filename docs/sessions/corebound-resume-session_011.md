# Corebound / Projet Noyau — Résumé de session 011

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré
de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème). Voir
`game-design-notes.md` et `README.md` pour l'état complet et à jour du cahier
des charges V1.

**Stack** : PHP 8.3 natif (pas de framework), architecture DDD à la main.
Vue.js 3 en frontend (non commencé). PHPUnit 12, PHPStan niveau 6, PHP CS Fixer,
CI GitHub Actions.

## État Git

- **`feature/multi-hero-roster`** (session 010) : mergée vers `dev` en amont de
  cette session.
- **`feature/hero-skills`** : chantier de cette session, **terminé, tous checks
  verts** (CS, PHPStan niveau 6, PHPUnit 167 tests / 795 assertions), validé
  manuellement via `php run.php` sur 3 seeds. Plusieurs commits distincts
  (contrairement au commit unique de la session 010), découpage par brique
  logique : un commit par compétence ajoutée à `HeroSkillDecorator`, un commit
  par refactor déclenché au seuil de récurrence, un commit pour le câblage dans
  `CombatBoardFactory`, un commit pour la couverture E2E. PR ouverte vers `dev`,
  titre et description rédigés.

## Chantier terminé cette session — Compétences de héros (`feature/hero-skills`)

### Phase 1 — Conception, avant tout code

Vision posée par l'utilisateur : la compétence comme "filtre" passif appliqué
aux objets standards à l'assemblage, plutôt qu'une action autonome — 100%
compatible avec le moteur stateless existant (`TickEngine`/`EventDispatcher`/
`ActionProcessor` inchangés). Deux vrais forks de conception tranchés avant
d'écrire une ligne de code :

- **Reconstruction des DTO readonly** (`Item`/`Effect`/`Action` imbriqués) :
  débat wither-methods (`Item::withEffects()`) vs. reconstruction inline dans
  le décorateur. Tranché pour l'inline — les DTO du projet ont toujours été
  décrits comme "statiques, sans comportement" ; ajouter des withers aurait été
  un changement de nature de ces classes pour un seul appelant. Idiome PHP 8.3
  (clone + réassignation d'une propriété readonly depuis la portée de la classe
  déclarante) identifié comme solution technique si le besoin resurgit ailleurs
  (ex. futur système d'affinité).
- **Signature du décorateur** : `decorate(HeroSkillType $skill, Item $item): Item`
  — acté tôt, jamais remis en cause ensuite, y compris pour `RELENTLESS` dont la
  précondition (loadout complet du héros) a été délibérément résolue en amont
  par `CombatBoardFactory`, pas par un élargissement de signature.

### Phase 2 — `HeroSkillDecorator` construit et testé en isolement complet (TDD bottom-up)

Catalogue de 10 compétences, dans l'ordre réel de construction :
`FRANTIC` → `VIRULENT` → `STALWART` → `VITALIC` → `SEARING` → `WARDEN` →
`RESURGENT` → `SAVAGE` → `SUNDERING` → `RELENTLESS`.

- **`FRANTIC`** (premier skill, cas trivial délibéré) : `-20%` `cooldownTicks`
  sur objets `ONE_HAND`, arrondi `floor()` (règle de maison actée ici : arrondir
  toujours en faveur du bonus joueur).
- **`VIRULENT`/`SEARING`/`WARDEN`/`RESURGENT`** : `+1` stack sur `Action::$stacks`
  quand `status` correspond (`POISON`/`BURN`/`WARD`/`REGEN`). Chaque nouveau
  skill de ce groupe a reçu un test de garde-fou contre un **autre statut**
  (pas juste une action non liée au statut) — leçon explicitement tirée après
  avoir réalisé que le garde-fou de `SEARING` ne protégeait pas `VIRULENT`
  contre une confusion `POISON`/`BURN` (deux méthodes indépendantes, chacune
  avec son propre prédicat à la main).
- **`STALWART`/`VITALIC`/`SAVAGE`** : `+20%` sur `Action::$value` quand `type`
  correspond (`GAIN_SHIELD`/`HEAL`/`DEAL_DAMAGE`), arrondi `ceil()`.
- **`SUNDERING`/`RELENTLESS`** : compositions dégâts+vitesse. Calibrage chiffré
  avec l'utilisateur avant implémentation — un vrai calcul de DPS réel
  (`valeur/cooldownTicks`) a révélé une asymétrie mathématique (bonus de
  vitesse plus fort que malus de vitesse au même %), ayant fait évoluer les
  propositions initiales (`+30-35%/-10-15%` et `+15%/+10-15%`) vers des valeurs
  finales calibrées en quasi-parité de DPS réel (`SUNDERING` +35%/-10% ≈
  +22.7%, `RELENTLESS` +10%/+10% ≈ +22.2%).

**Deux refactors déclenchés au seuil déjà établi sur ce projet** (attendre la
vraie récurrence, pas l'anticiper) :
- `applyStatusStackBonus(Item, StatusType): Item` à la 4e occurrence
  (`VIRULENT`/`SEARING`/`WARDEN`/`RESURGENT`).
- `applyActionValueBonus(Item, ActionType, float $multiplier): Item` à la 3e
  occurrence (`STALWART`/`VITALIC`/`SAVAGE`) — le paramètre `$multiplier` n'a
  été extrait qu'au moment où `SUNDERING` (+35%) a cassé l'hypothèse implicite
  d'un taux fixe partagé.
- Refactor symétrique du fichier de test (`createItem()`/`createEffect()`
  helpers), sur le même patron que `ShopOfferTest`/`InventoryTest` déjà
  existants.

### Phase 3 — Câblage réel dans `CombatBoardFactory`

Différé consciemment après la première tentative de conclure le chantier —
**une compétence testée en isolement n'a aucun effet sur le jeu tant qu'elle
n'est appelée nulle part** ; vérifié concrètement (`CombatBoardFactory` réel
ne référençait ni `HeroSkillDecorator` ni `HeroSkillType`, `Hero.php` n'avait
aucun champ pour porter un skill).

1. **`Hero::$skill`** (`?HeroSkillType`, défaut `null`) + conversion de
   `HeroSkillType` en enum backé `string` (nécessaire pour `::from()` depuis
   le JSON). Débat explicite sur la nullabilité : argumenté et tranché en
   faveur du `null` comme état métier réel (des héros ont existé sans skill
   avant que le concept existe), pas comme facilité pour éviter une cascade de
   refactor sur ~15-20 fichiers de test construisant `Hero`.
2. **Mapping skill → héros** décidé avec l'utilisateur sur les 10 héros réels
   de `heroes.json` (vérifiés fichier par fichier, pas supposés) : `SAVAGE`
   mutualisé entre `shadow_arrow`/`the_farshot` (deux affinités différentes,
   aucune contrainte d'unicité dans le code) ; `shadow_duelist` créé comme 10e
   héros dédié à `RELENTLESS` plutôt que de forcer ce skill sur un profil
   thématiquement peu cohérent.
3. **`CombatBoardFactory::createBoard()` restructurée en une seule passe** —
   l'ancienne implémentation aplatissait les items assignés avant de
   construire les `CombatItem`, perdant l'association héros→item nécessaire
   pour décorer. Effet de bord positif : élimine le double appel
   `itemRepository->find()` documenté comme dette depuis la session 009.
4. **Précondition dédiée `RELENTLESS`** (`hasFullOneHandLoadout`) : seul skill
   nécessitant de connaître l'ensemble du chargement d'un héros plutôt qu'un
   item isolé — piège de premier jet identifié et corrigé (vérifier "tous
   `ONE_HAND`" ne suffit pas, il faut aussi "autant d'objets que de slots").

### Phase 4 — Vérification de bout en bout

Nouveau test E2E dédié (`SimulationE2ETest`) parcourant les 10 héros réels
contre un objet de production, motivé par un vrai trou de couverture identifié
lors de vérifications manuelles `php run.php` : sur 3 seeds testées, seulement
2 des 10 compétences (`FRANTIC`, `SAVAGE`) avaient réellement tourné contre des
données de prod. `RELENTLESS`, `VIRULENT`, `WARDEN`, `STALWART`, `VITALIC`,
`SEARING`, `SUNDERING` n'avaient jamais été exécutées en conditions réelles
avant ce test.

## Bugs et écarts réels détectés en cours de route

- **`heroes.json`/`JsonHeroRepository` désynchronisés à plusieurs reprises**
  entre ce qui avait été vérifié tôt dans la session et l'état réel — payant
  d'avoir redemandé le contenu réel systématiquement plutôt que de réutiliser
  une version potentiellement obsolète (catalogue passé de 1 à 4 à 10 héros au
  fil de la conversation, `findAll()`/`getRawData()` déjà présents alors
  qu'une version plus ancienne ne les avait pas).
- **Règle d'arrondi initiale insuffisante** : "réduction → `floor()`,
  augmentation → `ceil()`" cassait sur le malus de cooldown de `SUNDERING`
  (une augmentation qui doit pourtant favoriser le joueur en restant basse).
  Reformulée en règle stable et definitive : `cooldownTicks` toujours
  `floor()`, `value` toujours `ceil()`.
- **Asymétrie DPS bonus/malus de vitesse** découverte par calcul avant
  l'implémentation de `SUNDERING`/`RELENTLESS` — a changé les valeurs finales
  proposées par l'utilisateur pour atteindre une vraie parité de DPS réel
  plutôt que nominale.
- **Item id de fixture utilisé par erreur en test E2E sur données de prod**
  (`rusty_dagger`, qui n'existe que dans `tests/Fixtures/items.json`) —
  détecté par l'erreur réelle, corrigé en `scimitar`.
- **Anomalie apparente en `php run.php`** (plusieurs défaites consécutives à
  exactement 454 ticks, sur deux seeds différentes) : investiguée en détail
  plutôt que supposée être un bug du décorateur — expliquée comme comportement
  correct de l'Enrage quand la stratégie d'achat naïve de `run.php` ne fournit
  aucun objet offensif au joueur en début de run. Notée en roadmap plutôt que
  corrigée dans ce chantier.
- **Claim non vérifiée corrigée en cours de conversation** : une affirmation
  ("pattern Wither déjà utilisé sur ce projet") s'est révélée fausse à la
  vérification — aucune méthode `withX()` n'existait nulle part avant ce
  chantier. Corrigé avant d'influencer une décision d'architecture.

## Idées différées cette session (Roadmap V2+)

- **Rééquilibrage de l'IA scriptée**, qui hérite désormais des compétences de
  ses héros (`shadow_bearer`/`the_bulwark`/`shadow_bastion`) sans recalibrage
  volontaire de la difficulté — conséquence naturelle du partage de
  `CombatBoardFactory`/`heroes.json` entre joueur et IA (acté en session 010),
  jamais évaluée pour les compétences spécifiquement.
- **Stratégie d'achat de `run.php`** ("première offre abordable") peut enchaîner
  plusieurs objets purement défensifs, menant à des combats résolus uniquement
  par l'Enrage — pas un bug, mais un axe d'amélioration identifié.
- **Typage d'objets par tags** (`weapon`, `melee`, etc.) — évoqué au moment de
  discuter les futurs skills `TWO_HAND`/`RELENTLESS`, explicitement différé à
  la V2+ (toucherait `Item`, ses repositories, ses fixtures, et les 30 objets
  de prod pour un besoin non encore exercé plus d'une fois).
- Toutes les idées différées des sessions précédentes (paliers manches 3/5,
  swap N-vers-1 pondéré, multi-affinité mécanique, conversion d'affinité, ordre
  d'activation/initiative, garde-fou anti-boucle-infinie, persistance d'état
  entre combats) restent valables et non retouchées cette session.

## Prochain chantier

Non discuté en détail cette session. Candidats identifiés dans les notes
précédentes et cette session : marchand de héros (dépendance amont posée
depuis la session 009), rééquilibrage de la difficulté (IA scriptée + skills),
frontend Vue.js (jamais commencé).

## Rappel des conventions

- Commits Conventional Commits, scope `domain`/`application`/`infrastructure`
  selon la nature du changement. Cette session : découpage fin par brique
  logique (un commit par compétence, par refactor, par étape de câblage) —
  à l'inverse du commit unique assumé en session 010.
- CS Fixer + PHPStan (niveau 6) + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, description détaillée : contexte, décisions actées,
  bugs réels découverts, scope explicitement différé, checks.
- TDD strict : test rouge → implémentation minimale → vert → refactor si
  besoin. Suivi rigoureusement sur les 10 compétences.
- Mode pédagogique par défaut, avec plusieurs échanges de conception (nommage,
  calibrage numérique, arbitrages d'architecture) menés en dialogue avant tout
  code — cohérent avec le reste du projet.
- **Vigilance sur les fichiers "déjà connus"** : payant à de multiples reprises
  cette session (`heroes.json`, `JsonHeroRepository.php`) — toujours redemander
  le contenu réel plutôt que réutiliser une version vue plus tôt dans la
  conversation, même de quelques échanges.
- **Vigilance symétrique sur les affirmations de l'utilisateur** : une
  affirmation non vérifiée ("pattern déjà utilisé") a été corrigée après
  contrôle direct du code, avant d'influencer une décision — la discipline de
  vérification s'applique dans les deux sens.