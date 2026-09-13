# Corebound / Projet Noyau — Résumé de session 015

## Contexte de départ

Reprise sur la branche `feature/frontend-visual-structure`, ouverte en fin de session 014 pour poser une structure visuelle cohérente (design tokens, layout) avant d'écrire du CSS composant par composant. Plan de design (palette, typographie, layout boutique/log/statut) validé en amont de tout code, avec deux arbitrages tranchés explicitement : direction sobre/minimaliste plutôt qu'atmosphérique, et log de combat en ordre chronologique naturel dans une fenêtre à hauteur fixe avec scroll auto vers le bas.

## Chantier 1 — Fondations du design system

- `style.css` réécrit : tokens de couleur (`--void`, `--shadow`, `--mist`, `--bone`, rareté `--common`/`--rare`/`--legendary`), typographie (Fraunces en display, Inter en corps, monospace pour les stats), reset minimal.
- `App.vue` restructuré : barre de statut persistante (manche/victoires/défaites/or), zones principales.
- Validé à l'écran, puis affiné : barre de statut agrandie et centrée, combat log placé dans une fenêtre à hauteur fixe avec scroll interne et auto-scroll vers le bas.

## Chantier 2 — Coloration du log de combat

Décision de contrat actée avant tout code : `formatCombatEvent` change de signature, `string` → `CombatEventDisplay` (`{ segments: { text, colorClass? }[], sourceSide: Side | null }`). Trois points tranchés explicitement avec l'utilisateur avant le premier test :
- Seuls les nombres sont isolés en segments colorés (pas les libellés autour) — robuste à toute reformulation.
- La couleur d'un tick de statut dépend du **statut appliqué** (`POISON`→violet, `BURN`→orange, `REGEN`→vert, `WARD`→jaune), pas du type d'event.
- Les détails de mitigation ("— X absorbés par le bouclier, Y aux PV") restent en texte neutre pour ce chantier.

Réécriture TDD des 11 tests existants vers la nouvelle forme + 4 tests supplémentaires (triangulation BURN/WARD/REGEN, pas seulement POISON) → 14 tests verts. `CombatLogView.vue` mis à jour pour consommer les segments (fond de ligne vert/rouge selon `sourceSide`, valeur colorée selon `colorClass`).

**Bug réel découvert et corrigé en cours de route** : `startNewRun()` ne réinitialisait ni `lastCombatLog` ni `opponentRoster`/`opponentInventory` — une nouvelle run affichait le log de l'ancienne, et le `participantResolver` aurait résolu de faux noms tant qu'aucun round n'était encore joué.

## Chantier 3 — Vestige exposé par l'API (petit aller-retour backend)

Motivé par l'affichage du panneau Vestige côté écran : `GameRun` ne possédait aucun accesseur au Vestige injecté. Trois briques TDD :
1. `GameRun::getVestige(): Vestige` (accesseur simple, manquant).
2. `VestigePresenter` (nouvelle classe, même patron statique pur que `HeroPresenter`/`WalletPresenter`).
3. Composition dans `RunStatePresenter` sous la clé `vestige`, avec triangulation dans `RunStatePresenterTest` (assertion de composition, pas de valeurs codées en dur — même logique que le test existant sur `roster`).

Décision actée avant de coder : le panneau affiche `baseHp`/`baseShield` (stats de définition), pas un état "actuel" — la persistance d'état entre combats (mode usure) n'existe pas en V1, un Vestige est toujours reconstruit à ses valeurs de base à chaque round.

236 tests / 936 assertions, CS Fixer / PHPStan niveau 6 tous verts.

## Chantier 4 — Layout 3 colonnes, Vestige/roster/coffre à l'écran

- `VestigePanel.vue` (nouveau) : carré placeholder, nom, affinité, PV (vert) et bouclier (jaune) colorés en réutilisant les tokens déjà posés pour le combat log, or de départ/revenu.
- `HeroRosterPanel.vue` (nouveau) : liste du roster avec nom/affinité/compétence, puis enrichi pour lister les objets équipés par héros.
- `StashPanel.vue` (nouveau) : contenu du coffre, message "Aucun objet dans le coffre" si vide.
- `App.vue` restructuré en 3 colonnes (Vestige à gauche, boutique+log au centre, roster+coffre à droite).

**Bug de scroll découvert et corrigé** : la page entière scrollait verticalement faute de hauteur verrouillée. Corrigé en cascade (`html`/`body`/`#app` à `100%`, `.app-shell` à `100vh` + `overflow: hidden`, `min-height: 0` propagé à travers les enfants flex) — point technique piégeux noté explicitement : sans `min-height: 0`, un enfant flex refuse de se comprimer sous la hauteur de son contenu et le scroll interne ne se déclenche jamais.

**Incident de lint découvert et corrigé** : `no-undef` d'ESLint signalait `HTMLElement` comme non défini dans `CombatLogView.vue` — faux positif, la règle ne connaît pas les globals DOM que `vue-tsc` vérifie déjà correctement. Désactivée pour `**/*.ts`/`**/*.vue` plutôt que d'ajouter une liste de globals (recommandation officielle `typescript-eslint`).

## Chantier 5 — Échange d'objet héros ↔ coffre à l'écran

`GameRun::swapWithStash()` existait déjà côté domaine (session antérieure) mais n'avait aucune UI pour le déclencher. Ajout de `useItemSwapSelection.ts` : composable à état singleton module-level (sélection UI transitoire, volontairement hors du store Pinia puisque ce n'est pas de l'état de jeu), partagé entre `HeroRosterPanel` (sélection d'un objet équipé, toggle si re-cliqué) et `StashPanel` (bouton d'échange, remonte les erreurs backend — ex. dépassement de budget de slots — au lieu de les avaler silencieusement).

**Auto-correction de convention** : le composable avait d'abord été livré sans test, sous prétexte d'"état UI transitoire" — incohérent avec la convention du projet déjà établie en session 013 (*"logique métier (client API, store, composables) testée en TDD ; composants Vue non testés"*). Corrigé : 6 tests ajoutés (sélection, toggle-désélection, remplacement de sélection, même `inventoryIndex` sous un `heroId` différent traité comme un slot distinct, `clear()`).

37 tests Vitest au total, ESLint/Prettier/`vue-tsc`/PHPUnit tous verts en fin de session (`check-all.ps1`).

## Décision de scope — réattribution d'objet héros ↔ héros

Demande formulée en fin de session : permettre un échange croisé entre deux héros (option B) et/ou un déplacement simple sans objet en retour, dans les deux sens (héros↔héros et héros↔coffre). Analysé et confirmé **hors scope V1**, documenté comme tel depuis plusieurs sessions (`game-design-notes.md` : *"Coffre V1 minimal... seul mouvement possible est l'échange direct plateau ↔ coffre"*, et le swap N-vers-1 pondéré déjà noté en Roadmap V2+ depuis la session 010 comme réponse à un problème voisin — la fragmentation de budget par héros).

**Non implémenté cette session**, en attente d'une décision explicite de faire passer ce scope en V1 (conformément à la consigne de suivi du projet : *"Toute mécanique listée en Roadmap V2+ ne doit pas être implémentée sans une décision explicite"*). Si la décision est prise, les fichiers à examiner avant de concevoir quoi que ce soit : `Inventory.php`, `AssignedItem.php`, `HeroItemAllocator.php`, `Stash.php` — pas encore vus cette session.

## Commits de la branche (chronologiques)

1. Fondations design system (`style.css` + `App.vue` structure)
2. Correctifs visuels (barre de statut, combat log fenêtré + scroll)
3. `formatCombatEvent` → segments colorés + `CombatLogView.vue` consommateur
4. `feat(presentation): expose vestige stats in run state` (backend)
5. `feat(frontend): display Vestige stats and hero roster in 3-column layout`
6. `fix(frontend): lock viewport height, add stash view and no-undef fix`
7. `feat(frontend): enable swapping items between a hero and the stash`

## État final

- Backend : 236 tests / 936 assertions, CS Fixer / PHPStan niveau 6 verts.
- Frontend : 37 tests Vitest, ESLint / Prettier / `vue-tsc` verts.
- Boucle V1 jouable de bout en bout à l'écran avec layout 3 colonnes, log de combat coloré, gestion d'inventaire (héros ↔ coffre) fonctionnelle.
- README.md régénéré pour refléter tout ce qui précède.

## Prochaine étape — non tranchée

Deux pistes en suspens à la fin de cette session, aucune démarrée :
1. Rythmer l'affichage du combat log selon les cooldowns réels des objets (évoqué en session, explicitement reporté à une branche dédiée `feature/combat-log-playback` — nécessite une conception d'architecture avant code, pas juste du CSS).
2. Décision à prendre sur la réattribution héros ↔ héros (V1 maintenant ou Roadmap V2+ confirmée) — voir section ci-dessus.
