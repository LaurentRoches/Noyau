# Corebound / Projet Noyau — Résumé de session 010

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de
*The Bazaar* (mécanique) et *La Voie des Ombres* (thème). Voir `game-design-notes.md`
et `README.md` dans les fichiers projet pour l'état complet et à jour du cahier des
charges V1. Le point d'entrée applicatif CLI (`backend/run.php`) existe désormais
et a été validé de bout en bout — voir session 009.

**Stack** : PHP 8.3 natif, Vue.js 3 (non commencé). PHPUnit 12, PHPStan niveau 6,
PHP CS Fixer, CI GitHub Actions.

## État Git

- **`feature/cli-entrypoint`** : mergée vers `dev` (session 009).
- **`feature/item-hand-slots`** : chantier de cette session, terminé, tous checks
  verts (125 tests). PR ouverte vers `dev` ("Able to merge", pas de conflit),
  titre et description rédigés.
- **Prochaine branche à créer** : pas encore nommée — sujet suivant identifié
  (création de héros / marchand de héros), voir "Prochain chantier" plus bas.

## Décision de cadrage majeure — CLI validé, Frontend toujours prématuré

En clôture de session 009, un draft externe (non issu de Claude) proposait
d'attaquer l'API HTTP + Frontend Vue.js maintenant que le CLI existe, arguant
du besoin de playtest humain sur des mécaniques jamais testées (économie,
difficulté PvE, Enrage). Après discussion, ce raisonnement a été explicitement
écarté : ces mécaniques numériques sont déjà testables via le CLI (modifier la
seed/les paramètres, lire la sortie) sans avoir besoin d'un visuel. Ce que le
frontend testerait réellement (le *fun* ressenti du multi-héros, des synergies)
n'est pas encore construit — un frontend maintenant playtesterait la mauvaise
version du jeu. Le backend (assignation objet→héros, système de tailles) reste
donc prioritaire sur le frontend.

## Chantier terminé cette session — Item Hand-Slots System (`feature/item-hand-slots`)

### Cadrage préalable, avant tout code

Cinq idées V2+ apportées par l'utilisateur en une fois (création de héros,
2 slots/héros, coffre à 8, compétences liées aux héros, marchands thématiques
multiples). Un draft externe proposait de trancher pour l'Option B (ownership
complet `CombatHero::getItems()`) au nom d'une meilleure préparation aux futures
Compétences. **Ce raisonnement a été rejeté par Claude, argumenté et confirmé
par l'utilisateur** : vérification sur le code réel que `ON_ATTACK`/
`EVERY_N_TICKS` sont strictement équivalents (session 004, jamais changé),
aucune notion de "héros qui attaque" n'existe dans le moteur actuel — l'argument
du draft était factuellement faux sur l'état réel du code. Construire une
architecture d'ownership pour une mécanique de Compétences non définie aurait
reproduit l'anti-pattern déjà rencontré une fois sur ce projet (`heroSlots` sur
`Vestige`, ajouté puis retiré avant le premier test). **Option A retenue**
(validation par héros à la construction, liste plate pour le combat) — décision
technique désormais actée et implémentée, pas seulement discutée.

### Équilibrage par valeur — méthode actée

Constat initial : les 10 objets `TWO_HAND` déjà marqués dans `items.json`
(assignation thématique faite par l'utilisateur) avaient exactement la même
valeur totale que leur équivalent `ONE_HAND` — un objet 2-mains était donc
**strictement dominé** (même puissance, moins de flexibilité). Débat avec
l'utilisateur sur la correction :
- Rejeté : `×1.75` symétrique sur valeur ET prix (laisse le 2-mains strictement
  dominé, juste plus discrètement).
- **Retenu** : `×2` sur la valeur (neutralité en slots — 1 objet 2-mains ≈ 2 objets
  1-main en puissance brute), `×1.75` sur le prix seul (vrai rabais en or,
  compensant la perte de flexibilité — un seul trigger/cooldown au lieu de deux).

Les 10 objets `TWO_HAND` recalculés à la main (cooldowns inchangés), légendaires
composites doublés composante par composante par cohérence avec la tolérance
déjà appliquée à leur création (session 004).

### Bug découvert en cours de route — backing type d'enum

`ItemSize` initialement déclaré `int`-backed (`ONE_HAND = 1`) alors que
`items.json` stocke les tailles en toutes lettres (`"ONE_HAND"`) —
`ItemSize::from()` ne peut pas accepter une chaîne contre un enum `int`-backed,
crash à l'exécution (`TypeError`) détecté seulement via `composer run test`,
pas par PHPStan (cohérence interne du code correcte, l'incohérence est entre
le code et les données JSON réelles). Corrigé en passant `ItemSize` en
`string`-backed (aligné sur le patron `Rarity`), la valeur numérique déplacée
dans une méthode dédiée `slotCost()`.

### Cascade de correction — 3e occurrence du motif

23 `ArgumentCountError` sur 7 fichiers de tests (`ActionProcessorTest`,
`EventDispatcherTest`, `SimulatorTest`, `TickEngineTest`, `InventoryTest`,
`CombatBoardTest`, `CombatItemTest`) suite à l'ajout du champ obligatoire
`Item::size`. Corrigé mécaniquement comme en session 008 (`Vestige::startingGold`/
`startingIncome`), mais **c'est la 3e fois que ce motif survient** — noté
explicitement comme approchant réellement le seuil de justification d'un Test
Object Mother, à surveiller si une 4e propriété obligatoire est ajoutée à
`Item` ou `Vestige`.

### CombatBoardFactory — nouvelle signature et validation par héros

`createBoard()` : `itemIds: list<string>` → `itemIdsByHero: array<heroId, list<string>>`.
Validation par héros via somme de `slotCost()` (pas `count()` brut), message
d'erreur identifiant le héros fautif. `CombatBoardFactoryTest` étendu de 2 cas
prouvant explicitement la composition (4 objets `TWO_HAND` = 8 slots, dépasse
un budget de 6 que l'ancienne validation `count()` aurait laissé passer).

### Bug réel révélé par la nouvelle validation (pas un faux positif de test)

`ScriptedOpponentFactory` tirait un nombre fixe d'objets (`pickArrayKeys()`)
sans égard à leur taille — un tirage de plusieurs objets `TWO_HAND` pouvait
dépasser le budget de slots avec moins d'objets que la limite affichée,
`InvalidArgumentException` potentielle en pleine `GameRun` de façon non
déterministe selon seed/round. **Ce n'était pas un bug introduit par la
session : il était latent depuis la création de `ScriptedOpponentFactory`
(session 008), simplement invisible tant que la validation ne portait que sur
un compte brut d'objets.** Corrigé : tirage repensé en une passe (mélange complet
du catalogue via `shuffleArray()`, ajout glouton avec `continue` — pas `break`
— pour ne pas gâcher le budget sur un premier tirage malchanceux). `MAX_ITEMS`
renommé `MAX_SLOTS`.

### Résultat final de la branche

CS Fixer / PHPStan niveau 6 (0 erreur) / PHPUnit 125 tests tous verts. Deux
commits distincts : fondations (`ItemSize`, `Item::size`, pricing `ShopOffer`)
puis validation par héros (`CombatBoardFactory`, correctif `ScriptedOpponentFactory`).
PR ouverte vers `dev`, titre et description rédigés, prête à merger.

## Idées reportées / notées en cours de session (non implémentées)

- **`slotCost()` sur `ItemSize`** posé mais pas encore consommé ailleurs que
  dans `CombatBoardFactory` — normal, c'est son seul point d'usage prévu pour
  l'instant.
- **Double appel à `JsonItemRepository::find()`** dans `CombatBoardFactory`
  (une fois pour la validation de slot, une fois pour construire le
  `CombatItem`) — redondant mais jugé bon marché (JSON déjà en mémoire système),
  non traité.
- Toutes les idées différées des sessions précédentes (multi-affinité mécanique,
  compétences de héros, conversion d'affinité, ordre d'activation/initiative,
  garde-fou anti-boucle-infinie, persistance d'état entre combats, ownership
  complet objet→héros) restent valables et non retouchées cette session.

## Prochain chantier — Création de héros / Marchand de héros

Pas de nom de branche encore choisi. C'est la brique fondatrice identifiée dans
la chaîne de dépendances actée en début de session :
Création d'autres héros
→ Marchand de héros (catalogue à vendre)
→ 2 slots/héros (n'a de sens qu'avec plusieurs héros réellement équipables)
→ Compétences (vendues par un marchand spécialisé)
→ Marchands multiples thématiques

Rien n'a encore été discuté en détail sur ce chantier (pas d'architecture, pas
de première classe identifiée) — juste priorisé. Le coffre à 8 emplacements
reste noté comme un "quick win" indépendant, changement trivial d'une constante,
non traité cette session.

## Rappel des conventions

- Commits Conventional Commits, scope `domain`/`application` selon la nature
  du changement. Message multi-lignes rédigé via le panneau Source Control de
  VSCode (titre, ligne vide, corps).
- Un commit par brique logique terminée et verte — respecté cette session
  (2 commits distincts sur `feature/item-hand-slots`).
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, description détaillée incluant contexte, décisions
  actées/rejetées avec justification, bugs réels découverts, checks, et scope
  explicitement différé.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin.
- Mode pédagogique par défaut, direct sur demande explicite.
- **Vigilance particulière actée cette session** : ne pas accepter par défaut
  les recommandations d'outils externes (drafts, autres IA) même bien
  argumentées en apparence — vérifier sur le code réel avant de trancher.
  Un draft s'est trouvé factuellement faux sur l'état du moteur (session 010,
  argument sur les triggers par héros), un autre a proposé une inversion
  d'ordre de chantiers (slots avant héros) qui aurait produit du travail
  jetable si suivie telle quelle.