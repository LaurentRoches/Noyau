# Corebound - Projet Noyau

> Auto-battler / roguelike deckbuilder asynchrone, univers dark fantasy original **Les Héritiers du Vide**.

Projet personnel développé pour apprendre et expérimenter sur un moteur de simulation de combat déterministe, dans l'esprit de *The Bazaar* (Tempo), avec un univers propre inspiré (sans copie) de *La Voie des Ombres* (Brent Weeks).

## Stack technique

- **Backend** : PHP 8.3+ - moteur de simulation stateless (`CombatLog = f(Joueur A, Joueur B, Seed)`)
- **Frontend** : Vue.js 3 - présentation et file d'attente d'animations
- **Bonus (non structurant)** : WebSocket/Mercure pour notifications et signaux de fin de combat

Le backend et le frontend ne partagent pas de code métier, seulement un contrat d'API (DTOs / schéma OpenAPI).

## Pourquoi ce stack

- Combats **automatiques**, calculés côté serveur : pas de temps réel dur à gérer
- PvP **asynchrone** (V2+) : affrontement contre une copie figée d'un plateau adverse, pas de matchmaking temps réel — la V1 utilise une IA scriptée à difficulté croissante, le PvP snapshot est différé après validation du moteur solo
- Cycle de vie court (stateless) en PHP : chaque combat est calculé, sauvegardé, puis la mémoire est libérée, pas de risque de fuite mémoire sur des milliers de combats

## Cahier des charges V1

- **Vestige + héros fixes** : `shadow_vestige` (affinité, `startingGold`, `startingIncome`) et un unique héros `shadow_bearer`, présents sans choix du joueur en V1
- **`CombatBoard`** : 1 Vestige + 1 à 3 héros, 6 emplacements d'objets au total
- **3 raretés** : Commune (x1) / Rare (x1.5) / Légendaire (x2.5)
- **30 objets** en contenu réel, thème assassin/ombre (14 Common / 11 Rare / 5 Legendary)
- **4 statuts** : Poison (ignore le bouclier), Burn, Regen (soin dans le temps), Ward (bouclier dans le temps)
- **Système d'enrage** : au-delà d'un certain tick, dégâts croissants exponentiellement infligés aux deux plateaux — force la résolution d'un combat, protège les builds purement défensifs d'un stalemate perdant par défaut
- **Boutique** : 4 offres aléatoires par visite (tirage seedé, plafonné à 1 objet Légendaire par visite), prix croissant avec la rareté
- **Inventaire du joueur** : 6 emplacements de combat (`Inventory`) + un coffre de 3 emplacements (`stash`) pour les objets achetés en surplus ; échange manuel possible entre les deux (`GameRun::swapWithStash()`)
- **Économie de run** : or de départ (`startingGold`, une fois) + revenu de manche (`startingIncome`, à chaque manche gagnée ou perdue) + récompense de victoire (`+10` or fixe)
- **Boucle** (`GameRun::playRound()`) : construit le plateau du joueur à partir de son inventaire courant, génère l'adversaire scripté, lance le combat, comptabilise le résultat, avance la manche
- **Pas de vrai PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking

## Modèle de données

Structure objet/effet basée sur des couples **trigger → actions** :

```json
{
  "trigger": "EVERY_N_TICKS",
  "actions": [
    { "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 12 }
  ]
}
```

## Structure du projet

```
backend/
├── config/
│   └── game/
│       ├── heroes.json      # Configuration de production des héros
│       ├── items.json       # Configuration de production des objets (V1 : 30 objets)
│       └── vestiges.json    # Configuration de production des Vestiges
├── src/
│   ├── Application/
│   │   ├── GameRun.php       # Orchestrateur de run : wallet, inventaire/coffre, manches,
│   │   │                     # boutique, combat, condition de fin de run
│   │   └── Factory/           # CombatBoardFactory, ShopFactory, ScriptedOpponentFactory
│   ├── Domain/
│   │   ├── Engine/            # Simulator, TickEngine, EventDispatcher, ActionProcessor,
│   │   │                      # StatusProcessor, EnrageProcessor
│   │   ├── Enum/               # Trigger, Target, Rarity, ActionType, EventType, StatusType
│   │   ├── Event/               # CombatEvent
│   │   ├── Model/                 # DTOs : Hero, Item, Vestige, Effect, Action
│   │   ├── Player/                  # Inventory (utilisée à la fois pour le plateau
│   │   │                            # de combat et le coffre, capacités différentes)
│   │   ├── Runtime/                   # Entités d'exécution : CombatHero, CombatItem, CombatVestige,
│   │   │                              # CombatBoard, ActiveStatus
│   │   └── Shop/                       # Wallet, ShopOffer, Shop (économie de boutique)
│   └── Infrastructure/
│       └── Repository/Json/             # JsonHeroRepository, JsonItemRepository, JsonVestigeRepository
├── tests/
│   ├── Application/                     # Tests de GameRun et de ses fabriques (Factory/)
│   ├── Domain/                           # Tests unitaires du moteur, de la boutique, de l'inventaire
│   ├── E2E/                               # Tests de bout en bout (fichiers prod -> simulation)
│   ├── Fixtures/                           # Fixtures de test isolées
│   └── Infrastructure/                      # Tests des repositories JSON
frontend/
└── ...                                       # Vue.js 3, file d'attente d'animations
```

## Avancement

- [x] Notes de design et cahier des charges V1
- [x] Modèle de données trigger → actions défini
- [x] Modèles du Domaine & Enums (`Hero`, `Item`, `Vestige`, `Effect`, `Action`, `Rarity`, `Trigger`, `ActionType`, `Target`, `StatusType`)
- [x] Hydratation & Repositories JSON (`JsonHeroRepository`, `JsonItemRepository`, `JsonVestigeRepository`)
- [x] Fabrique d'assemblage (`CombatBoardFactory`) & validation des slots de héros
- [x] Moteur de simulation à ticks déterministe (`Simulator`, `TickEngine`, `ActionProcessor`)
- [x] Moteur de statuts (Poison, Burn, Regen, Ward) via `StatusProcessor`
- [x] Système d'enrage anti-stalemate (`EnrageProcessor`)
- [x] Contenu réel complet V1 (30 objets dans `config/game/items.json`, répartition 14/11/5 actée)
- [x] Suite de tests automatisés (unitaires, intégration, E2E avec 100 % de succès)
- [x] Boutique / économie (`Wallet`, `ShopOffer`, `Shop`, `ShopFactory` — tirage seedé plafonné en rareté)
- [x] Orchestration de la boucle de jeu (`GameRun` : wallet, manches, boutique, combat, condition de fin de run)
- [x] IA scriptée à difficulté croissante (`ScriptedOpponentFactory`, nombre d'objets croissant par manche)
- [x] Inventaire du joueur persistant entre manches + coffre (`Inventory`, `GameRun::purchaseItem()`
      débordant automatiquement vers le coffre, `swapWithStash()` pour l'échange manuel)
- [x] `GameRun::playRound()` construit lui-même le plateau du joueur à partir de son inventaire
      courant — la boucle V1 est jouable de bout en bout mécaniquement
- [x] Point d'entrée applicatif réel (API ou script) imposant l'ordre boutique → combat — `GameRun`
      ne l'impose pas structurellement aujourd'hui, seul un futur appelant discipliné le garantit
- [ ] Frontend Vue.js (file d'attente d'animations)

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.