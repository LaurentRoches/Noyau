# Corebound / Projet Noyau — Résumé de session 007

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` dans les fichiers projet pour le cahier des charges V1 (partiellement dépassé sur "1 seul héros" — voir session 005 pour le modèle Vestige + multi-héros actuel).

**Stack** : PHP 8.3, Vue.js 3 en frontend. PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (JSON validation, PHPUnit, PHPStan, CS Fixer — tous bloquants, déclenchés sur Pull Request vers `dev`).

**Méthodologie à respecter impérativement** (rappel, cf. `SKILL.md` projet et sessions précédentes) :
- Mode pédagogique actif : questions guidées plutôt que réponses directes, sauf demande explicite de réponse directe.
- TDD strict, bottom-up : test rouge → implémentation minimale → vert → refactor.
- Regard critique systématique sur toute nouvelle idée (complexité moteur, jouabilité réelle, cohérence V1, YAGNI) plutôt que validation par défaut.
- Vérifier CS Fixer + PHPStan + PHPUnit avant chaque commit.
- Célébrer les initiatives de l'utilisateur quand il dérive un concept/solution seul.

## État Git

- **`feature/status-effects-engine`** : mergée vers `dev` (confirmé en début de session).
- **`feature/shop-economy`** : chantier de cette session, **terminé, tous checks verts**. Pull Request ouverte vers `dev` ("Able to merge", pas de conflit) en toute fin de session, titre et description rédigés.
- **Prochaine branche à créer** : pas encore nommée — dépend de la priorité choisie en session 008 (voir "Prochain chantier" plus bas).

## Chantier terminé cette session — Shop / Economy

Objectif du cahier des charges V1 couvert : *"4 offres aléatoires par visite, or de départ fixe, prix croissant avec la rareté, une seule monnaie"* (achat/vente/passe — vente différée, voir plus bas).

### Nouvelles classes

**`Wallet`** (`Domain/Shop/`) : classe mutable, patron `CombatVestige`/`CombatLog` (état runtime qui évolue, mutation encapsulée via méthodes CQS).
- `__construct(int $initialBalance)` : garde `\InvalidArgumentException` si `< 0` (message explicite testé).
- `getBalance(): int` : lecture.
- `canAfford(int $amount): bool` : query pure, garde `assertPositiveAmount()` partagée, `$amount <= $balance`.
- `credit(int $amount): void` : garde partagée, addition directe.
- `spend(int $amount): void` : délègue entièrement à `canAfford()` (garantit par construction l'ordre de priorité garde-argument > règle-métier, sans dupliquer `assertPositiveAmount()`), lève `\LogicException` si insolvable, sinon débite.
- `assertPositiveAmount()` privée, extraite au moment exact où la règle des 3 était atteinte (`canAfford`, `credit`, puis `spend` planifiée) — **incident de discipline noté et assumé** : extraite par anticipation à 2 occurrences réelles (`canAfford`/`credit`), avant que `spend()` n'existe. Reconnu explicitement par l'utilisateur comme une entorse consciente à la règle qu'il venait lui-même de poser.
- 8 tests, tous verts. Séquencement méthodique : lecture pure (`canAfford`) testée avant mutation (`credit`/`spend`), cas nominal avant garde à chaque méthode.

**`Rarity::basePrice()`** (ajout sur l'enum existant, `Domain/Enum/`) : `COMMON = 10`, `RARE = 25`, `LEGENDARY = 50`. Courbe volontairement plus accentuée que `statMultiplier()` (×1/×1.5/×2.5) — décision de game design assumée (le légendaire doit demander un effort d'épargne réel).
- **Piège rencontré et corrigé** : première implémentation en `: float` (copié par réflexe visuel du patron voisin `statMultiplier()`/`dropRateModifier()`, toutes deux `float`). PHP, même sous `strict_types=1`, élargit silencieusement un `int` littéral en `float` quand le type de retour déclaré est `float` — `match` retournait bien `10` (int) mais la méthode remontait `10.0`, faisant échouer `assertSame(10, ...)`. PHPStan niveau 6 ne détecte rien (le code est cohérent avec sa propre signature, il ne connaît pas l'intention métier). Corrigé en `: int`, conformément au contrat acté en amont ("compatibilité directe avec `Wallet::spend(int)` sans conversion").
- **Dette de test comblée au passage** : `statMultiplier()` existait dans le code depuis la session 001 mais n'avait jamais eu de test dédié dans `RarityTest.php` (seul `dropRateModifier()` était couvert). Ajouté par initiative de l'utilisateur, hors scope strict du chantier mais jugé à coût nul.

**`ShopOffer`** (`Domain/Shop/`) : classe mutable, anti-Primitive-Obsession — regroupe `Item` + prix résolu + état d'achat plutôt que de laisser `Shop` manipuler des tableaux associatifs non typés.
- `__construct(Item $item)` : `$item` et `$price` tous deux `readonly`, prix dérivé de `$item->rarity->basePrice()` au moment de la construction (jamais recalculé après).
- **Décision explicitement révisée en session** : le constructeur avait initialement un second paramètre optionnel `?int $price = null` (fallback sur le prix de base si non fourni), ajouté par réflexe d'anticipation. Retiré consciemment après coup — reconnu comme non piloté par un test, YAGNI strict appliqué (la V1 ne permet aucune override de prix).
- `getItem()`, `getPrice()`, `isPurchased()` : lecture.
- `markAsPurchased(): void` : mutation unique, garde fail-fast `\LogicException` si déjà achetée — empêche par construction tout double-débit en amont, avant même que `Shop` ait besoin de sa propre garde.
- 3 tests, tous verts.

**`Shop`** (`Domain/Shop/`) : agrégat d'achat, **entièrement déterministe** — reçoit sa liste de `ShopOffer` déjà construite dans son constructeur, aucun RNG interne (mirroring exact de `SimulationContext` qui reçoit un `Randomizer` déjà instancié plutôt que de le fabriquer).
- `__construct(array $offers)` — `list<ShopOffer>` readonly.
- `getOffers(): array` — lecture pure, ajoutée en fin de session pour débloquer `ShopFactoryTest` (cycle TDD séparé, `assertSame` sur le tableau entier pour vérifier contenu + ordre + identité en une passe).
- `purchase(int $slotIndex, Wallet $wallet): Item` — séquence stricte en deux phases (validation intégrale, puis mutation), trois gardes ordonnées par niveau de priorité :
  1. `\InvalidArgumentException` si `$slotIndex` hors bornes (`array_key_exists`, couvre index négatif et positif hors limites d'un seul coup) — contrat d'API.
  2. `\LogicException` si l'offre est déjà achetée — état du domaine, testé en amont pour ne jamais toucher au `Wallet` sur un slot vendu.
  3. `\LogicException` si le solde est insuffisant (`canAfford()` vérifié explicitement en amont, **redondant consciemment** avec la propre garde interne de `spend()` — assumé comme défense en profondeur : `Shop` ne doit jamais atteindre `spend()` sur un achat impossible, `Wallet` doit rester incorruptible même appelé directement par un autre code).
- **Piège identifié et évité avant codage, par raisonnement à la main plutôt que par un test qui aurait échoué** : une version "élégante" DRY avait été proposée (inverser l'ordre `markAsPurchased()` puis `spend()`, en laissant `ShopOffer` porter seule la garde de double-achat, sans dupliquer de condition dans `Shop`). Rejetée par l'utilisateur lui-même : en cas de solde insuffisant, l'offre serait irrécupérablement marquée "vendue" sans qu'aucun or n'ait changé de main — rupture de la strong exception safety sur `ShopOffer`. Retenu : validation complète d'abord, mutations groupées ensuite, aucune méthode de mutation appelée avant que toutes les gardes soient passées.
- 4 tests (nominal, index invalide × négatif/positif, offre déjà vendue avec preuve de non-débit, solde insuffisant avec preuve de non-mutation de l'offre + non-débit).

**`ShopFactory`** (`Application/Factory/`, pas `Domain/Shop/` — cohérent avec `CombatBoardFactory`, orchestration Domain + Infrastructure) : génère une instance `Shop` avec 4 offres tirées de `config/game/items.json` via `JsonItemRepository`, seedée par un `Randomizer` reçu en paramètre de `createShop()`.
- **Décision de pondération, débattue en profondeur** : un tirage uniforme sans remise sur les 30 objets (14 Common / 11 Rare / 5 Legendary) donnait par calcul $1 - \binom{25}{4}/\binom{30}{4} \approx 53{,}8\%$ de chance qu'au moins un Legendary apparaisse par visite — jugé contraire à l'intention de rareté du game design (`guide_creation_item.md`, "un légendaire doit demander un vrai effort"). Un algorithme de tirage pondéré sans remise complet (urne, poids recalculés à chaque tirage) a été jugé disproportionné pour la V1 (Option A, écartée). **Retenu (Option B)** : tirage partitionné — 3 slots garantis tirés dans le pool Common+Rare uniquement, 1 dernier slot tiré dans le catalogue restant complet. Plafonne mécaniquement à 1 Legendary maximum par visite, fait chuter la probabilité à ~18,5%, sans construire de système de pondération générique.
- `pickArrayKeys()` (natif `\Random\Randomizer`, PHP 8.2+) utilisé pour le tirage sans remise, cohérent avec le RNG déjà standard du projet (`\Random\Engine\PcgOneseq128XslRr64`).
- 3 tests : distinction des 4 offres (`array_unique` sur les ids), plafond Legendary vérifié par échantillonnage sur 200 seeds (test structurel de non-régression, la vraie garantie vient de la partition du pool, pas d'un hasard statistique), déterminisme pour une seed fixe (deux appels, même seed, mêmes offres).

### Erreurs de placement corrigées en session (piège de mismatch namespace, déjà rencontré en session 001)

- `ShopFactory` initialement placée par erreur dans `Domain/Shop/` — corrigée vers `Application/Factory/` après que l'utilisateur a fourni une capture d'écran de l'arborescence VS Code montrant `ShopFactoryTest.php` déjà positionné à côté de `CombatBoardFactoryTest.php`. Alignement a posteriori sur la décision déjà actée en session 002 ("`CombatBoardFactory` orchestrates both Domain and Infrastructure layers").
- RNG initialement supposé `Pcg64` par erreur — corrigé vers `\Random\Engine\PcgOneseq128XslRr64` après vérification du code source réel (`CombatBoardFactory.php` fourni par l'utilisateur, bien que cette classe n'utilise elle-même aucun RNG — vérification faite directement sur `SimulationContext` par le passé, confirmée cette session).
- `createMock(JsonItemRepository::class)` proposé initialement pour `ShopFactoryTest` — **aurait échoué** : `JsonItemRepository` est `final`, PHP ne permet pas de mocker une classe finale (le mock repose sur une sous-classe générée à la volée). Corrigé en pointant le test directement vers `config/game/items.json` réel (30 objets de production, répartition 14/11/5 déjà actée comme close en session 006), cohérent avec le patron déjà utilisé par `JsonItemRepositoryTest` (zéro mock, vraies fixtures/fichiers).

## Résultat final de la branche

CS Fixer / PHPStan niveau 6 (0 erreur) / PHPUnit tous verts (83 tests avant `ShopFactory`, davantage après son ajout — dernier chiffre non communiqué explicitement en session, à vérifier au prochain lancement). PR ouverte vers `dev`, titre et description rédigés, prête à merger.

## Question ouverte posée et tranchée en fin de session — "Le moteur est-il opérationnel pour la V1 ?"

Réponse honnête donnée à l'utilisateur, à ne pas perdre de vue : **non, pas encore**, malgré deux systèmes indépendamment complets (combat + boutique). Ce qui manque concrètement pour la boucle de jeu complète décrite dans `game-design-notes.md` :

1. **Aucune classe d'orchestration** ne relie `Shop`/`ShopFactory` et `Simulator` — les deux systèmes s'ignorent totalement aujourd'hui. Pas de `GameRun`/`GameSession` ou équivalent.
2. **`ActionType::GAIN_GOLD`** toujours non traité par `ActionProcessor` (connu depuis la session 004) — bloquant si l'or doit être gagné en combat pour alimenter les visites de boutique suivantes.
3. **Étape 1 de la boucle** ("choix de départ, 2-3 objets communs") non codée. Piste apportée par l'utilisateur en fin de session, non tranchée techniquement : lier l'or de départ fixe au choix du Vestige par le joueur (chaque Vestige aurait potentiellement son propre or de départ, ou une constante partagée déclenchée au moment du choix) — à creuser en session 008.
4. **IA scriptée à difficulté croissante** : aucune classe ne construit un `CombatBoard` adverse, ni ne fait progresser sa difficulté sur les 10 combats.
5. **Pas de notion de run/session persistante** : rien ne compte les combats gagnés, ne détecte la victoire au Nᵉ combat ni la défaite globale.

## Prochain chantier — Orchestration de la boucle de jeu (nom de branche non encore choisi)

Pas de décision d'architecture prise sur ce chantier — juste identifié et priorisé en toute fin de session. Points à trancher en premier à la reprise, dans l'esprit des questions habituelles (quelle est la brique la plus feuille ?) :

1. Où vit l'or de départ — constante fixe indépendante du Vestige, ou champ ajouté sur le modèle `Vestige` lui-même (`Domain/Model/Vestige`) ? Piste de l'utilisateur à creuser : lier l'or de départ au choix du Vestige.
2. `ActionType::GAIN_GOLD` — probablement le prérequis technique le plus urgent et le plus isolé (un seul `match` à étendre dans `ActionProcessor`, sur le modèle de `APPLY_STATUS`), à traiter indépendamment de la question d'orchestration globale.
3. Nom et forme de la classe d'orchestration (`GameRun` ? `GameSession` ?) — où vit-elle (`Application/` par analogie avec `CombatBoardFactory`/`ShopFactory`, ou un nouveau namespace `Domain/Game/` ?).
4. Étape 1 (choix de départ, 2-3 objets communs) — mécanique non détaillée du tout, à concevoir de zéro.
5. IA scriptée à difficulté croissante — sujet non ouvert, aucune piste technique discutée.

## Idées reportées / notées en cours de session (non implémentées)

- **Marchands variables** (offres différentes selon un "vendeur" tiré au hasard, prix modulés par vendeur) — idée soulevée par l'utilisateur en pleine réflexion sur le pricing, explicitement classée V2+ par lui-même, à noter à côté des autres différées (multi-affinité mécanique, compétences de héros, conversion d'affinité).
- **Taille d'objet (1 main / 2 mains) influençant le prix boutique** — réflexion approfondie de l'utilisateur, comparée consciemment au refacto Vestige (session 005) pour évaluer si c'est une dette technique urgente. Conclusion commune : la dette est réelle mais **localisée** — elle touche uniquement `CombatBoardFactory` (actuellement un simple `count($itemIds)` contre le budget total de slots, deviendrait une somme pondérée par taille) et la boutique, **jamais le moteur de combat lui-même** (le prix n'est lu nulle part dans `Simulator`/`TickEngine`/`ActionProcessor`). Contrairement à Vestige, pas de risque de double réécriture de tests d'intégration combat. Peut être différée sereinement, à ne traiter qu'au moment où `Item` gagnera effectivement un champ `size`/`slotCost`.
- **Vente d'objets** (rachat par le joueur) : confirmée hors scope de ce chantier — nécessite un `Inventory` inexistant et une règle de rachat (ex. 50% du `basePrice()`), notée explicitement V2+.
- Toutes les idées différées des sessions 001-006 (multi-affinité mécanique, compétences de héros, persistance d'état entre combats, garde-fou anti-boucle-infinie, répartition des items par héros précis, factorisations mineures `applyHpDamage()`/`pulsePoison()`-`pulseBurn()`) restent valables et non retouchées cette session.

## Rappel des conventions

- Commits Conventional Commits, scope `domain` pour les briques `Domain/Shop/`, cohérent avec le reste du moteur (confirmé en début de session, pas de scope `shop` séparé retenu).
- Un commit par brique logique terminée et verte — respecté strictement cette session (`Wallet`, `Rarity::basePrice()`, `ShopOffer`, `Shop`, `Shop::getOffers()`, `ShopFactory` : six commits distincts).
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, CI à vérifier avant merge (non encore confirmée au moment de la rédaction de ce résumé — PR tout juste ouverte).
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin → nouveau test. Deux incidents de discipline reconnus et assumés consciemment par l'utilisateur cette session (extraction anticipée de `assertPositiveAmount()` à 2 occurrences ; paramètre `$price` optionnel non piloté par un test, retiré après coup) — aucun des deux jugé problématique en soi, la valeur de la session vient précisément de leur reconnaissance explicite plutôt que de leur absence.
- Mode pédagogique par défaut : questions guidées avant le code, sauf demande explicite de réponse directe.
