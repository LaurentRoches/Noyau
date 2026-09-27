# Corebound / Projet Noyau — Résumé de session 014

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` et `README.md` dans les fichiers projet pour l'état complet et à jour du cahier des charges V1.

**Stack** : PHP 8.3 natif (pas de framework), PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions. Vue.js 3 + TypeScript + Pinia + Vite côté frontend, Vitest pour la logique testée.

## État Git en fin de session

- **`feature/opponent-board-presenter`** : mergée vers `dev` en tout début de session.
- **`feature/frontend-combat-view`** : mergée vers `dev` en cours de session.
- **`feature/frontend-shop-view`** : chantier de fin de session, tous checks verts, **prête pour PR** (texte de PR rédigé, non encore mergée à la clôture de la session).

## Chantier 1 — Board adverse exposé via l'API (`feature/opponent-board-presenter`)

### Point de départ

En concevant le composable `formatCombatEvent` (chantier 2), un trou de contrat a été découvert : `sourceItemId`/`sourceSide` du `combatLog` ne sont résolvables en nom lisible que côté joueur (`RunStateDTO.roster`/`inventory`). Rien n'exposait la composition du board adverse (héros scriptés + assignation d'items) — `ScriptedOpponentFactory::createOpponent()` calculait cette composition en interne mais ne retournait que le `CombatBoard` runtime, la jetant après usage.

### Décisions actées

- Exposition **uniquement dans la réponse de `resolveRound()`**, pas dans `RunStateDTO` en permanence — le board adverse n'a de sens qu'une fois affronté.
- **Classes dédiées**, pas de réutilisation d'`Inventory`/`AssignedItem` (porteuses de logique player-only : achat, swap, contrainte de stash — sémantiquement fausses pour un board scripté en lecture seule).
- Champs à plat dans la réponse (`opponentRoster` + `opponentInventory`), pas nichés sous une clé unique.

### Briques (TDD strict, rouge confirmé à chaque étape)

1. `OpponentAssignment` (value object domaine : `Item` + `heroId`) + `OpponentInventoryPresenter` (mapping pur)
2. `ScriptedOpponentFactory::createOpponent()` retourne désormais un `OpponentBoard` (`board` + `roster` + `assignments`) au lieu d'un `CombatBoard` nu
3. `GameRun` conserve `$lastOpponentRoster`/`$lastOpponentAssignments` après `playRound()`, exposés via deux nouveaux getters
4. `RunController::resolveRound()` expose `opponentRoster` (réutilise `HeroPresenter` tel quel) et `opponentInventory` (nouveau `OpponentInventoryPresenter`)

### Résultat

231 tests PHPUnit / 926 assertions à la fin du chantier. PR mergée vers `dev`.

## Chantier 2 — Écran de combat brut (`feature/frontend-combat-view`)

### Composables (logique testée, TDD strict)

- **`formatCombatEvent`** : transforme un `CombatEventDTO` en phrase française. Couvre les 9 `EventType` du contrat, avec détail de répartition dégâts bouclier/PV pour les 3 events de dégâts (`DAMAGE_DEALT`, `STATUS_DAMAGE_DEALT`, `ENRAGE_DAMAGE_DEALT`). Extraction d'un helper commun (`formatSourcedEvent`) à la 3e occurrence de la forme "source + item", conforme à la règle du projet.
- **`buildParticipantResolver`** : construit la table de résolution héros/item à partir de l'état courant, isolée par `side` pour éviter toute collision d'id entre joueur et adversaire (vérifié par un test dédié).

### Store (`gameRun.ts`)

`resolveRound()` stocke `opponentRoster`/`opponentInventory` en plus de `lastCombatLog`. Nouveau `computed` `participantResolver`, construit via `buildParticipantResolver`.

### Composant

`CombatLogView.vue` (`src/components/combat/`), non testé (UI), branché dans `App.vue` avec une UI minimale (démarrer un run / résoudre un round).

### Bugs d'environnement découverts en testant à l'écran

Deux trous invisibles jusque-là, révélés uniquement en testant manuellement dans le navigateur :
- **`main.ts` n'initialisait jamais Pinia** (`app.use(createPinia())` absent) — passait inaperçu tant qu'aucun store n'était consommé par un composant réellement monté.
- **`vite.config.ts` ne contenait plus le proxy `/runs`** vers le backend PHP, documenté comme ajouté en session 013 mais disparu depuis (retiré ou jamais committé, cause non tranchée).

### Résultat

22 tests Vitest à la fin du chantier (9 `formatCombatEvent` + 3 `buildParticipantResolver` + 5 `gameRun` store + 4 `runApi` + 1 nouveau sur le contrat enrichi), `vue-tsc`/ESLint/Prettier propres. Vérifié manuellement de bout en bout (round joué à vide, log affiché correctement, résolution du héros/item adverse confirmée). PR mergée vers `dev`.

## Chantier 3 — Écran de boutique (`feature/frontend-shop-view`, en cours, prêt pour PR)

### Décisions actées avant le premier test

- Niveau de détail **"détaillé"** pour chaque offre (nom, prix, rareté, affinité, effets décrits en phrases) plutôt que minimal.
- Achat refusé par le backend : **le bouton se désactive côté UI** si `price > wallet.balance`, le rejet backend ne devrait jamais survenir en usage normal.
- Formulation du déclencheur d'effet : **distinguer par trigger** ("À chaque attaque" pour `ON_ATTACK` vs "Toutes les X secondes" pour `EVERY_N_TICKS`), même si les deux sont mécaniquement identiques dans le moteur — cohérent avec l'intention narrative des données.
- Conversion ticks → secondes confirmée par l'utilisateur : **1 tick = 100ms (10 ticks/seconde)**.
- `ON_KILL`/`ON_DEATH` (dans l'enum `Trigger`, non utilisés par aucun item V1) : **fail-fast** si jamais rencontrés, même principe que le `default` de `formatCombatEvent`.

### Composable (`formatItemEffect.ts`)

`formatItemEffects(item: ItemDTO): string[]`. Couvre les 4 types d'action (`DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL`, `APPLY_STATUS`) × 2 triggers réellement utilisés, plus un test de caractérisation confirmant le cas multi-actions (`nightfang`, `excalibur`, etc.) sans modification de code supplémentaire nécessaire. 5 tests Vitest.

### Composant (`ShopView.vue`)

`src/components/shop/`, non testé (UI). Offres achetées visibles mais grisées, bouton désactivé si non finançable ou déjà acheté.

### Bug métier découvert en testant à l'écran — boutique jamais renouvelée

Après le round 1, la boutique ne se renouvelait plus jamais : seul `RunController::create()` journalisait une action `OPEN_SHOP` (une seule fois, à la création). `GameRun::playRound()` ne rouvrait rien après un combat.

**Discussion actée avant fix** : le choix entre plusieurs marchands est vérifié comme **explicitement V2+** dans les notes de design (`game-design-notes.md`), donc hors de portée immédiate — peu importe la forme choisie aujourd'hui pour la réouverture automatique, elle sera retravaillée le jour où le multi-marchand arrivera. Décision : **option A** — `GameRun::playRound()` rouvre lui-même une boutique à la fin (effet de bord automatique, même style que `recordVictory()`/`recordDefeat()`), sauf si ce round termine le run.

**Deuxième bug du même trou** : la boutique du dernier round restait affichée (figée, offres déjà achetées) après la fin du run — `playRound()` sautait la réouverture sans jamais vider `$currentShop`. Fix : `else { $this->currentShop = null; }`.

Un test initial (`testPlayRoundDoesNotOpenANewShopWhenThisRoundEndsTheRun`) s'est révélé trompeur — il passait sans rien prouver, faute d'avoir ouvert une boutique avant les défaites testées. Corrigé, puis fusionné avec le nouveau test qui couvrait exactement le même comportement sous un nom plus précis (`testPlayRoundClearsTheShopWhenThisRoundEndsTheRun`).

### Ajustement frontend — pas de moyen de rejouer

`App.vue` ne gérait que `runId === null` pour afficher le bouton de démarrage — une fois un run terminé, aucun moyen de recommencer. Fix : le bouton (libellé "Rejouer") réapparaît dès que `state.isOver` est vrai.

### Résultat

234 tests PHPUnit / 931 assertions (backend), 27 tests Vitest (frontend), tout vert. Vérifié manuellement de bout en bout : plusieurs manches jouées, achats pris en compte en combat, boutique renouvelée à chaque manche non terminale, disparition propre à la fin, bouton "Rejouer" fonctionnel. **PR non encore ouverte à la clôture de la session** — texte rédigé et prêt (`PR-frontend-shop-view.md`).

## Mises à jour de documentation

`README.md` mis à jour en fin de session : structure du projet (nouvelles classes des 3 chantiers), contrat d'API (`resolveRound` enrichi de `opponentRoster`/`opponentInventory`, comportement de réouverture/vidage de boutique précisé), avancement (2 lignes cochées), chiffres de suite de tests (234/931 backend, 27 Vitest frontend).

## Rappel des conventions (confirmées, aucune dérive cette session)

- TDD strict : rouge confirmé avant toute implémentation, y compris pour les tests de caractérisation (vérifiés comme vrais rouges ou vrais verts directs, jamais supposés).
- Un commit par brique logique terminée et verte, message Conventional Commits.
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit (backend) ; `npm run test`/`typecheck`/`lint`/`format` systématiquement (frontend).
- Fichiers réels demandés avant toute modification ou extension — plusieurs allers-retours cette session pour obtenir `ScriptedOpponentFactoryTest.php`, `RunControllerTest.php`, `GameRunTest.php`, `App.vue` (jamais deviné).
- Architecture discutée et actée (via questions à choix, `ask_user_input_v0`) avant le premier test à chaque nouvelle brique significative.
- Décisions rejetées documentées avec leur raison : réutilisation d'`Inventory` pour l'adversaire (rejetée, couplage sémantique faux), anticipation du multi-marchand dans le fix de réouverture de boutique (rejetée, hors-scope V2+ confirmé).
- Bugs d'environnement (Pinia, proxy Vite) traités avec la même rigueur que les bugs de logique métier — diagnostiqués avant correction, jamais corrigés à l'aveugle.

## Sur l'horizon

- Ouvrir et merger la PR `feature/frontend-shop-view` → `dev`
- Style visuel des écrans de combat et de boutique (actuellement listes brutes)
- Choix entre plusieurs marchands (V2+, confirmé hors-scope immédiat)
- `HelloWorld.vue` toujours présent mais inutilisé — ménage différé
- Paliers de renouvellement du roster de héros (manches 3 et 5), compétences de héros actives en combat, animations PixiJS — tous encore différés, non retouchés cette session
