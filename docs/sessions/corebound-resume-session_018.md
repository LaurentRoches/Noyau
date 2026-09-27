# Corebound — Résumé de session 018

**Branche** : `feature/hero-selection`
**Chantier** : sélection progressive des héros au fil des manches (1, 3, 5) + stash à 6 emplacements

---

## Contexte et objectif

Premier chantier depuis la V1 initiale à rouvrir le domaine et le contrat de `GameRun`. Jusqu'ici, le roster du joueur (3 héros) était tiré automatiquement et intégralement à la construction du run, sans aucun choix de sa part — un vestige du prototype initial, jamais remis en question depuis. Objectif de la session : donner au joueur un vrai choix, progressif, cohérent avec l'intention produit du jeu (un auto-battler où la composition d'équipe est une décision stratégique, pas un tirage subi).

## Cadrage produit (avant tout code)

Déroulé validé avec Laurent avant la moindre ligne de code :

- **Manche 1** : le Vestige Ombre propose 3 héros — 1 garanti de l'affinité du Vestige (tirage uniforme sur le reste), 2 aléatoires dans tout le catalogue.
- **Manches 3 et 5** : nouvelle offre de 3 héros, avec une pondération positive légère vers l'affinité du Vestige (poids ×2.0 vs ×1.0), excluant les héros déjà recrutés — un candidat déjà présent dans le roster ne peut jamais être reproposé.
- **Séquencement identique à chaque fois** : début de manche → offre → choix du joueur → boutique → combat. La manche 3 ne propose pas le héros juste après la victoire de la manche 2 : l'offre appartient à la phase de début de manche 3 elle-même.
- **Stash porté à 6 emplacements** (contre 3) — décision indépendante, sans lien mécanique avec la sélection de héros.

## Conception du domaine

Trois maillons discutés et validés avant TDD, dans l'ordre de dépendance :

### `HeroOffer` (Domain/Model)
Value object immuable portant exactement 3 candidats (`Hero[]`). Aucune notion de cycle de vie, de manche ou d'état "actif/consommé" — ce sont des responsabilités de `GameRun`, pas de l'offre elle-même. Invariants protégés au constructeur : cardinalité exacte (3) et absence de doublon, tous deux via `\InvalidArgumentException` avec message explicite. Deux méthodes de lecture : `contains(string $heroId): bool` et `find(string $heroId): ?Hero` — la seconde ajoutée en cours de route, quand `GameRun::chooseHero()` s'est révélé avoir besoin de récupérer l'objet `Hero` complet, pas seulement de vérifier son appartenance.

### `WeightedDraw` (Domain/Model/Draw)
Le point de conception le plus structurant de la session. `\Random\Randomizer` natif de PHP n'offre qu'un tirage uniforme (`pickArrayKeys`) — aucune méthode pondérée. Rejeté d'emblée : dupliquer les héros pondérés dans un pool virtuel avant tirage uniforme, car `pickArrayKeys` tire des clés uniques et rien n'empêche qu'un héros dupliqué soit tiré deux fois, violant l'invariant anti-doublon de `HeroOffer`.

Solution retenue : l'algorithme d'Efraimidis-Spirakis (tirage pondéré sans remise par clé `u^(1/w)`, tri décroissant, on garde le top N). Conçu comme une classe **pure**, sans dépendance à `Randomizer` : elle reçoit directement une liste de flottants déjà tirés (`nextFloat()` appelé en amont par l'appelant), ce qui la rend triviale à tester avec des valeurs connues plutôt que de coupler les tests à une séquence RNG particulière. Un garde-fou de conception explicite a été posé après une première proposition de test jugée fragile ("poids 1000 vs 1 sur tel seed") : préférer un test qui injecte des `u` connus et prouve l'inversion d'ordre par le calcul, plutôt qu'un test qui prouve une propriété accidentelle d'une séquence RNG précise.

### `HeroOfferGenerator` (Application/Factory)
Deux méthodes distinctes plutôt qu'un paramètre booléen de mode — décision prise pour éviter qu'un appelant doive connaître une règle métier ("true seulement en manche 1") :
- `buildInitialOffer()` : tire le héros garanti, l'exclut du pool, tire les 2 autres uniformément.
- `buildWeightedOffer()` : filtre le pool (retire les héros déjà recrutés), calcule les poids (×2.0/×1.0), délègue à `WeightedDraw`.

`HeroRosterFactory` (et son test), désormais du code mort, supprimée une fois l'intégration validée.

## Intégration dans `GameRun`

Changement de contrat le plus sensible de la session : le roster n'est plus peuplé au constructeur. Nouvel état :

```
GameRun créé
├── roster = []
├── pendingHeroOffer = 3 candidats (offre initiale, générée immédiatement)
└── shop = null

chooseHero(heroId)
├── refuse si le run est terminé ou si aucune offre n'est en attente
├── héros ajouté au roster
├── pendingHeroOffer = null
└── shop ouvert automatiquement

playRound()
└── si prochaine manche ∈ {3, 5}
    ├── pendingHeroOffer = nouvelle offre pondérée
    └── shop reste fermé (pas de réouverture automatique)
```

`openShop()` et `purchaseItem()` refusent explicitement tant qu'une offre est en attente (`\LogicException`). `HeroItemAllocator`, qui prenait le roster en constructeur, est désormais reconstruit à la volée à chaque usage plutôt que stocké comme champ figé — nécessaire puisque le roster grandit après la construction du `GameRun`.

## Cascade de rupture de contrat

Le changement de signature du constructeur de `GameRun` (`HeroRosterFactory` → `HeroOfferGenerator`) a cassé, en cascade, la quasi-totalité des couches qui en dépendaient :

- **`GameRunFactory`** : câblage mis à jour.
- **Couche persistance/HTTP** : la plus grosse zone d'impact. `RunController::create()` ouvrait automatiquement une boutique (`OPEN_SHOP` journalisé) en supposant l'ancien contrat — désormais impossible tant qu'aucun héros n'est choisi. Décision : retirer entièrement l'`OPEN_SHOP` automatique de `create()`, introduire une nouvelle action journalisée `CHOOSE_HERO` (`GameRunActionType`, `GameRunActionApplier`, nouvel endpoint `POST /runs/{run_id}/hero/choose`), qui devient le véritable point de transition vers la boutique — autant pour la manche 1 que pour les manches 3/5.
- **Fixtures de test** (`CreatesRealGameRun`) : scindées en deux, un run "réellement frais" (`createRealGameRun`) et un run "prêt à jouer" (`createRealGameRunReadyToPlay`, héros garanti déjà choisi) — impossible de satisfaire les deux besoins avec un seul helper une fois que choisir un héros et ouvrir la boutique deviennent un seul geste indissociable.
- **`RunStatePresenter`** : nouvelle clé `pendingHeroOffer`, nécessaire pour que le frontend sache quand afficher l'écran de choix.
- Plusieurs tests ont dû être réévalués au cas par cas plutôt que mécaniquement corrigés : `testPurchaseItemThrowsWhenNoShopIsOpen` (état encore atteignable, mais seulement *avant* tout choix, plus après), `testSwapWithStashExchangesItemsBetweenBoardAndStash` (nombre d'achats recalculé dynamiquement à partir des `itemSlots` du héros réellement tiré, plutôt qu'un nombre en dur qui supposait un roster à 3 héros).

## Frontend

`HeroOfferPanel.vue` (nouveau) : 3 cartes cliquables, choix immédiat sans étape de confirmation (cohérent avec le reste de l'app — achat en boutique = un clic), état local `isChoosingHero` pour désactiver les cartes pendant l'appel réseau (`ShopView.vue` vérifié au passage : aucun pattern de chargement existant à réutiliser, ce composant introduit donc la première protection anti-double-clic de l'app). Branché dans `App.vue` entre l'écran de démarrage et la boutique, sur la condition `pendingHeroOffer !== null`. `HeroRosterPanel.vue` n'a nécessité aucune modification : il itère déjà génériquement sur le roster, sans hypothèse sur sa taille.

## Validation finale

Backend : 248 tests / 971 assertions, CI verte (PHPUnit, PHPStan niveau 6, PHP CS Fixer). Frontend : 65 tests Vitest, ESLint/Prettier/`vue-tsc` propres. Run complet testé manuellement jusqu'à la manche 5 — offres et pondération d'affinité observées au bon moment.

## Ce qui a changé par rapport au plan initial

- `HeroOffer::find()` non prévue dans la conception initiale (seul `contains()` était prévu) — ajoutée en cours d'intégration quand `chooseHero()` s'est révélée avoir besoin de l'objet `Hero`, pas seulement d'un booléen. Traitée comme sa propre brique TDD plutôt que glissée sans test dans `GameRun`.
- L'action `OPEN_SHOP` reste supportée par `GameRunActionApplier` (rejeu d'anciens journaux), mais n'est plus jamais produite par `RunController` — devenue un vestige de l'ancien contrat, volontairement non supprimée pour ne pas élargir le diff au-delà de cette intégration.

## Prochain chantier

À définir — candidats identifiés en Roadmap V2+ (compétences de héros manquantes, marché/marchand alternatif, PvP asynchrone) ou reprise de la refonte visuelle de `style.css`, en attente depuis la session 014.
