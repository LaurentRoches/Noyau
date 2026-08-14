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
- PvP **asynchrone** : affrontement contre une copie figée d'un plateau adverse, pas de matchmaking temps réel
- Cycle de vie court (stateless) en PHP : chaque combat est calculé, sauvegardé, puis la mémoire est libérée, pas de risque de fuite mémoire sur des milliers de combats

## Cahier des charges V1

- **Vestige + héros** : un `CombatBoard` regroupe 1 Vestige (affinité du plateau) et 1 à 3 héros (2 emplacements d'objets chacun, 6 au total)
- **3 raretés** : Commune (x1) / Rare (x1.5) / Légendaire (x2.5)
- **30 objets** prévus, thème assassin/ombre (14 Common / 11 Rare / 5 Legendary)
- **4 statuts** : Poison (ignore le bouclier), Burn, Regen (soin dans le temps), Ward (bouclier dans le temps)
- **Boutique** : 4 offres aléatoires par visite (tirage seedé, plafonné à 1 objet Légendaire par visite), or de départ fixe, une seule monnaie, prix croissant avec la rareté
- **Boucle** : choix de départ → boutique → combat auto contre IA scriptée → répétition jusqu'à mort ou victoire (N combats)
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
│   │   └── Factory/         # CombatBoardFactory, ShopFactory (assemblage DTO -> Runtime/Domain)
│   ├── Domain/
│   │   ├── Engine/          # Simulator, TickEngine, EventDispatcher, ActionProcessor, StatusProcessor
│   │   ├── Enum/             # Trigger, Target, Rarity, ActionType, EventType, StatusType
│   │   ├── Event/             # CombatEvent
│   │   ├── Model/               # DTOs : Hero, Item, Vestige, Effect, Action
│   │   ├── Runtime/               # Entités d'exécution : CombatHero, CombatItem, CombatVestige, CombatBoard, ActiveStatus
│   │   └── Shop/                   # Wallet, ShopOffer, Shop (économie de boutique)
│   └── Infrastructure/
│       └── Repository/Json/         # JsonHeroRepository, JsonItemRepository, JsonVestigeRepository
├── tests/
│   ├── Application/Factory/         # Tests des fabriques (CombatBoardFactory, ShopFactory)
│   ├── Domain/                       # Tests unitaires du moteur et de la boutique
│   ├── E2E/                           # Tests de bout en bout (fichiers prod -> simulation)
│   ├── Fixtures/                       # Fixtures de test isolées
│   └── Infrastructure/                  # Tests des repositories JSON
frontend/
└── ...                                   # Vue.js 3, file d'attente d'animations
```

## Avancement

- [x] Notes de design et cahier des charges V1
- [x] Modèle de données trigger → actions défini
- [x] Modèles du Domaine & Enums (`Hero`, `Item`, `Vestige`, `Effect`, `Action`, `Rarity`, `Trigger`, `ActionType`, `Target`, `StatusType`)
- [x] Hydratation & Repositories JSON (`JsonHeroRepository`, `JsonItemRepository`, `JsonVestigeRepository`)
- [x] Fabrique d'assemblage (`CombatBoardFactory`) & validation des slots de héros
- [x] Moteur de simulation à ticks déterministe (`Simulator`, `TickEngine`, `ActionProcessor`)
- [x] Moteur de statuts (Poison, Burn, Regen, Ward) via `StatusProcessor`
- [x] Contenu réel complet V1 (30 objets dans `config/game/items.json`, répartition 14/11/5 actée)
- [x] Suite de tests automatisés (unitaires, intégration, E2E avec 100 % de succès)
- [x] Boutique / économie (`Wallet`, `ShopOffer`, `Shop`, `ShopFactory` — tirage seedé plafonné en rareté)
- [ ] Orchestration de la boucle de jeu complète (choix de départ → boutique → combat IA → répétition jusqu'à mort/victoire)
- [ ] `ActionType::GAIN_GOLD` (récompense d'or après combat, non traité par `ActionProcessor`)
- [ ] IA scriptée à difficulté croissante
- [ ] Frontend Vue.js (file d'attente d'animations)

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.