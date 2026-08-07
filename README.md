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

- **1 héros** : "Porteur de l'Ombre", affinité `shadow`, pas de mécanique différenciante en V1
- **6 emplacements** d'objets génériques
- **3 raretés** : Commune (x1) / Rare (x1.5) / Légendaire (x2.5)
- **30 objets** prévus, thème assassin/ombre
- **10 effets max** : Dégâts, Critique, Poison, Burn, Soin, Bouclier, Or, Mana, Vitesse/Cooldown, Esquive
- **Boutique** : 4 offres aléatoires, or de départ fixe, une seule monnaie
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
│       ├── heroes.json      # Configuration de production des héros (V1 : Shadow's Bearer)
│       └── items.json       # Configuration de production des objets (V1 : 30 objets)
├── src/
│   ├── Application/
│   │   └── Factory/         # CombatBoardFactory (assemblage DTO -> Runtime)
│   ├── Domain/
│   │   ├── Engine/          # Simulator, TickEngine, EventDispatcher, ActionProcessor
│   │   ├── Enum/            # Trigger, Target, Rarity, ActionType, EventType
│   │   ├── Event/           # DamageDealtEvent, ShieldGainedEvent...
│   │   ├── Model/           # DTOs : Hero, Item, Effect, Action
│   │   └── Runtime/         # Entités d'exécution : CombatHero, CombatItem, CombatBoard
│   └── Infrastructure/
│       └── Repository/      # JsonHeroRepository, JsonItemRepository
├── tests/
│   ├── Application/         # Tests de la couche Application
│   ├── Domain/              # Tests unitaires du moteur de simulation
│   ├── E2E/                 # Tests de bout en bout (fichiers prod -> simulation)
│   ├── Fixtures/            # Fixtures de test isolées
│   └── Infrastructure/      # Tests des repositories JSON
frontend/
└── ...                       # Vue.js 3, file d'attente d'animations
```

## Avancement

- [x] Notes de design et cahier des charges V1
- [x] Modèle de données trigger → actions défini
- [x] Modèles du Domaine & Enums (`Hero`, `Item`, `Effect`, `Action`, `Rarity`, `Trigger`, `ActionType`, `Target`)
- [x] Hydratation & Repositories JSON (`JsonHeroRepository`, `JsonItemRepository`)
- [x] Fabrique d'assemblage (`CombatBoardFactory`) & validation des slots de héros
- [x] Moteur de simulation à ticks déterministe (`Simulator`, `TickEngine`, `ActionProcessor`)
- [x] Suite de tests automatisés (unitaires, intégration, E2E avec 100 % de succès)
- [ ] Contenu réel complet V1 (30 objets dans `config/game/items.json`)
- [ ] Boutique / économie
- [ ] Frontend Vue.js (file d'attente d'animations)

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.