# Corebound - Projet Noyau

> Auto-battler / roguelike deckbuilder asynchrone, univers dark fantasy original **Les Héritiers du Vide**.

Projet personnel développé pour apprendre et expérimenter sur un moteur de simulation de combat déterministe, dans l'esprit de *The Bazaar* (Tempo), avec un univers propre inspiré (sans copie) de *La Voie des Ombres* (Brent Weeks).

## Stack technique

- **Backend** : PHP 8.2+ - moteur de simulation stateless (`CombatLog = f(Joueur A, Joueur B, Seed)`)
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
  "trigger": "ON_ATTACK",
  "actions": [
    { "type": "DEAL_DAMAGE", "value": 12 }
  ]
}
```

Le champ `affinity` est présent dès la V1 sur le héros et sur les objets (valeur unique `shadow` pour l'instant), afin d'anticiper l'ajout de factions/héros futurs sans migration de schéma. Un type d'action `SetAffinity` est prévu dans le schéma (non implémenté en V1) pour la mécanique différée de conversion d'affinité.

## Moteur de simulation

Le moteur doit être **100 % déterministe** (seeded RNG transmise en début de combat), pour permettre au frontend de rejouer un combat depuis un simple journal d'événements JSON, sans recalcul.

Architecture à ticks fixes (1 tick = 100 ms), à événements découplés :

```
Tick → Cooldowns → Déclencheurs → Création d'événements → Résolution → Nouveaux événements → Fin du tick
```

Chaque objet est un "listener" qui réagit à des événements (`AttackStarted → Hit → DamageApplied → LifeLost → Death → OnKill → LootGenerated`) — pas de logique centralisée par objet.

## Structure du projet

```
backend/
├── config/
│   └── game/
│       ├── heroes.json      # Données des héros (V1 : Shadow's Bearer)
│       └── items.json       # Données des objets (V1 : en cours de rédaction, 30 prévus)
├── src/
│   ├── Domain/
│   │   ├── Model/           # Hero, Item, Board, Effect
│   │   ├── Event/           # DamageDealtEvent, ShieldGainedEvent...
│   │   ├── Enum/            # Trigger, Target, Rarity
│   │   └── Engine/          # Simulator, TickEngine, EventDispatcher
frontend/
└── ...                       # Vue.js 3, file d'attente d'animations
```

## Avancement

- [x] Notes de design et cahier des charges V1
- [x] Modèle de données trigger → actions défini
- [x] Premiers fichiers de données (`heroes.json`, 1 héros ; `items.json`, 3 objets de test)
- [ ] DTOs PHP `readonly` (Hero, Item, Effect, enums Trigger/Rarity)
- [ ] Moteur de simulation à ticks (testable tick par tick)
- [ ] Contenu complet (30 objets, équilibrage)
- [ ] Boutique / économie
- [ ] Frontend Vue.js (file d'attente d'animations)

## Point de vigilance IP

L'univers ("Les Héritiers du Vide") et l'artefact central ("le Vestige") ont été renommés pour ne conserver que la structure mécanique inspirée de *La Voie des Ombres*, jamais les noms ni les pouvoirs exacts. Vigilance à maintenir avant toute publication ou mise en portfolio public.

## Méthodologie

Ordre de travail volontairement inversé par rapport à une approche "architecture-first" : valider le fun (V1 jouable) avant d'investir dans la technique. Ne pas construire un moteur élégant dont personne ne sait s'il est amusant.