# Corebound / Projet Noyau — Résumé de session 010

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` et `README.md` dans les fichiers projet pour l'état complet et à jour du cahier des charges V1.

**Stack** : PHP 8.3 natif (pas de framework), architecture DDD à la main. Vue.js 3 en frontend (non commencé). PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions.

**Méthodologie confirmée cette session, plus que jamais payante** : demander systématiquement le contenu réel d'un fichier avant de coder dessus ou d'en modifier un autre qui en dépend — la session a enchaîné une longue chaîne de dépendances (`Hero` → `JsonHeroRepository` → `HeroRosterFactory` → `Inventory`/`Stash` → `HeroItemAllocator` → `GameRun` → `ScriptedOpponentFactory` → `run.php`) où quasiment chaque étape a révélé une signature, un champ ou un comportement différent de ce qu'un raisonnement a priori aurait supposé.

## État Git

- **`feature/item-hand-slots`** (session 009) : confirmée mergée vers `dev` en tout début de session.
- **`feature/multi-hero-roster`** : chantier de cette session, **terminé, tous checks verts** (CS, PHPStan, PHPUnit 139 tests, `php run.php` validé de bout en bout avec seed fixe). Pull Request ouverte vers `dev`, titre et description rédigés en fin de session. Un seul commit englobant, choix assumé de l'utilisateur plutôt qu'un découpage fin par brique — proposition de découpage en 10 commits logiques faite mais déclinée.

## Décision de scope actée en tout début de session

Le nom de branche initial ("création de plusieurs héros") a été volontairement élargi, par décision explicite de l'utilisateur appuyée sur une conversation externe, pour couvrir aussi le tirage aléatoire du roster côté joueur — alors que la chaîne de dépendances actée en session 009 (Création → Marchand de héros → 2 slots → Compétences) prévoyait un marchand de héros avant tout tirage. Le point de blocage identifié (le choix du Vestige, prérequis apparent du tirage pondéré par affinité, n'existe pas encore en V1) s'est résolu par une simplification : le Vestige étant fixe (`shadow_vestige`, `shadow`), pondérer vers "l'affinité du Vestige actuel" revient aujourd'hui à forcer le premier héros du tirage sur `shadow` — sans construire de sélection de Vestige, le mécanisme se généralisera de lui-même le jour où elle existera.

Les paliers de renouvellement du roster (manches 3 et 5, faisant partie de la vision initiale de l'utilisateur) ont été explicitement reportés à un chantier ultérieur — ce chantier ne couvre que le tirage initial à la construction du run.

## Chantier terminé cette session — Roster de héros multiples (`feature/multi-hero-roster`)

### Enchaînement de briques, dans l'ordre réel de construction (TDD bottom-up)

1. **`JsonHeroRepository::findAll()`** — ajoutée par symétrie avec `JsonItemRepository::findAll()` déjà existante. Extraction d'une méthode privée `getRawData()` pour éliminer la duplication de lecture/décodage JSON entre `find()` et `findAll()` — DRY appliqué délibérément sur la nouvelle classe sans reporter le même refactor sur `JsonItemRepository` (dette existante non touchée cette session, par discipline "un commit = une préoccupation").
2. **Fork de conception majeur, posé et tranché avec l'utilisateur** : comment un objet acheté sait-il "à quel héros" il appartient ? Deux options présentées (répartition automatique au moment du combat vs. association explicite dès l'achat) — l'utilisateur a choisi et argumenté lui-même la seconde, avec une reformulation clé : *"l'achat produit déjà une attribution, le combat ne fait que la consommer"*, évitant de faire porter à `GameRun` une règle métier qui ne le concerne pas.
3. **`Inventory` scindée en deux classes** : l'ancienne classe generic est renommée en **`Stash`** (pool d'objets sans héros, inchangée dans l'esprit) ; une nouvelle **`Inventory`** hero-aware est créée, portant des **`AssignedItem`** (objet-valeur `Item` + `heroId`, sur le patron de `ShopOffer`). `Inventory` ne stocke aucune capacité ni budget — volontairement, pour éviter une double représentation de la même contrainte avec le roster.
4. **`HeroItemAllocator`** (`Domain/Player/`) : décide si/à quel héros un objet peut être affecté, en recalculant naïvement (sans cache) la somme des `slotCost()` déjà assignés à un héros. Règle formulée précisément par l'utilisateur après une première approximation erronée de ma part (compter les objets plutôt que leur poids en slots — piège identique à celui déjà rencontré et corrigé sur `ScriptedOpponentFactory`). `canAssign()` lève une exception explicite si le `heroId` demandé n'appartient pas au roster.
5. **`HeroRosterFactory`** (`Application/Factory/`) : tirage sans remise sur le patron de `ShopFactory` (1er héros contraint par affinité, 2 autres dans tout le catalogue). Fixture `tests/Fixtures/heroes.json` enrichie de 1 à 4 héros pour permettre un test réel de la contrainte (sinon indiscernable d'une absence de choix).
6. **`GameRun`** entièrement rebranché : roster tiré à la construction (`HeroRosterFactory` injectée), `purchaseItem()` délègue l'affectation à `HeroItemAllocator`, `swapWithStash()` prend désormais un `heroId` explicite et applique un retrait temporaire + validation + rollback en cas de dépassement de budget (jugé sûr car `Inventory`/`Stash` sont de l'état interne pur, sans effet de bord externe observable).
7. **`ScriptedOpponentFactory` réécrite** — décision de conception apportée par l'utilisateur en cours de session : plutôt qu'un tirage aléatoire (approche initialement proposée par Claude), une composition **entièrement scriptée par héros**, déterministe, révélée progressivement par un budget global croissant par manche. Nouveau **`JsonScriptedOpponentRepository`** + `config/game/scripted_opponent.json`. Le trio de héros adverses (`shadow_bearer`/`the_bulwark`/`shadow_bastion`) est indépendant du roster tirable côté joueur — assumé et clarifié explicitement (l'IA scriptée n'a pas à respecter les mêmes règles que le joueur), mais doit néanmoins exister dans le même `heroes.json` tant qu'un seul repository/fichier est partagé entre les deux usages.

### Bugs réels détectés et corrigés en cours de route

- **Régression anticipée puis confirmée par les tests** : passer `shadow_bearer` de 6 à 2 slots (cohérence du catalogue) cassait `ScriptedOpponentFactory` (`MAX_SLOTS = 6` en dur, déconnecté de `Hero::itemSlots`) puis `GameRun`/`run.php` (`PLAYER_HERO_ID` unique portant tout le budget de combat) — exactement la dette documentée en Roadmap V2+ depuis la session 008 ("le `6` dupliqué à 3 endroits"), qui s'est manifestée concrètement dès le premier héros à budget différent ajouté.
- **`shadow_bulwork` inexistant dans le catalogue réel** — faute de frappe de l'utilisateur dans `scripted_opponent.json`, détectée en confrontant directement au `heroes.json` réel plutôt qu'en supposant. Corrigée en `the_bulwark`.
- **Fragmentation de budget par héros** : `HeroItemAllocator` en first-fit naïf peut laisser un héros avec 1 slot libre (ni comblé, ni suffisant pour un objet `TWO_HAND`), forçant cet objet au coffre même si la capacité totale du roster suffirait. Confirmé par un échec de test (`testSwapWithStashExchangesItemsBetweenBoardAndStash`) qui s'appuyait à tort sur un scénario RNG émergent plutôt qu'un scénario construit — corrigé en forçant explicitement l'achat d'objets `ONE_HAND` dans le test, et la fragmentation elle-même actée comme dette réelle (pas un bug), avec une piste de solution proposée par l'utilisateur (swap N-vers-1 pondéré par `slotCost()`) documentée en Roadmap V2+.
- **Plusieurs erreurs de cascade de compilation** (arguments manquants sur `ScriptedOpponentFactory`/`GameRun` dans les tests, mauvais nombre de `../` dans un chemin de fixture, fichier de test dupliqué localement avec une ancienne version) — toutes résolues en itérant sur la sortie réelle de `composer run test` fournie par l'utilisateur plutôt qu'en devinant une correction supplémentaire.

## Idées différées cette session (Roadmap V2+, ajoutées à `game-design-notes.md`)

- **Paliers de renouvellement du roster aux manches 3 et 5** (choix d'un nouveau héros parmi 3, remplaçant potentiellement un héros existant) — reporté, ce chantier ne couvre que le tirage initial.
- **Swap multi-items pondéré N-vers-1** entre `Inventory` et `Stash` — permettrait d'échanger plusieurs objets d'un même héros contre un seul objet plus coûteux du coffre, réglant la fragmentation de budget par héros. Nécessiterait un `Stash` à capacité pondérée par `slotCost()` pour absorber le différentiel d'objets libérés.

Toutes les idées différées des sessions précédentes (multi-affinité mécanique, compétences de héros, conversion d'affinité, ordre d'activation/initiative, garde-fou anti-boucle-infinie, persistance d'état entre combats) restent valables et non retouchées cette session.

## Prochain chantier — Compétences de héros

Annoncé en préambule de session par l'utilisateur, conditionné à ce que le chantier roster se passe bien — c'est le cas. Pas encore discuté en détail (pas d'architecture, pas de première classe identifiée). Point de départ probable : le système de compétence n'est aujourd'hui "pas encore nommé clairement" (mot de l'utilisateur) — la première étape sera vraisemblablement de clarifier le vocabulaire/modèle avant tout code, comme d'habitude sur ce projet.

## Rappel des conventions

- Commits Conventional Commits, scope `domain`/`application`/`infrastructure` selon la nature du changement. Cette session : un seul commit englobant, par choix assumé de l'utilisateur plutôt que le découpage fin habituel — décision consciente, pas un oubli de discipline.
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, description détaillée : contexte, décisions actées, bugs réels découverts, scope explicitement différé, checks.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin. Suivi rigoureusement tout au long de cette session, brique par brique.
- Mode pédagogique par défaut, direct sur demande explicite — non sollicité cette session, mode pédagogique utilisé de bout en bout.
- **Vigilance sur les propositions de conception, y compris venant de l'utilisateur lui-même** : deux fois cette session, une proposition initiale de l'utilisateur (règle "count < itemSlots" plutôt que somme de `slotCost()", nom de fichier de test correspondant à l'ancien catalogue) a été corrigée après vérification contre le code/les fichiers réels — la discipline de vérification s'applique symétriquement, pas seulement aux suggestions de Claude ou d'outils externes.
- **Toujours redemander le contenu réel d'un fichier qu'on croit connaître si plusieurs itérations se sont écoulées depuis** — payant à plusieurs reprises cette session pour éviter de corriger deux fois la même erreur sous des angles différents.
