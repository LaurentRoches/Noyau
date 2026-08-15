# Projet Noyau — Corebound (auto-battler dark fantasy)

## Contexte et intention

Projet perso de Laurent, développeur Full Stack (PHP/Symfony, Vue.js 3, Node.js en apprentissage, WebSocket/Mercure en apprentissage). Objectif : développer un jeu vidéo type roguelike deckbuilder / auto-battler asynchrone, dans l'esprit de **The Bazaar** (Tempo), mais avec un univers original — **Les Héritiers du Vide** — inspiré (sans copie) de **La Voie des Ombres** (Night Angel, Brent Weeks).

- **Nom du projet** : Projet Noyau
- **Nom du jeu** : Corebound
- **Univers** : Les Héritiers du Vide
- **Artefact central** : le Vestige

## Choix techniques confirmés

- **Backend** : PHP (Symfony), pas Node.js.
- **Frontend** : Vue.js 3.
- WebSocket/Mercure reste un bonus (notifications, signal de fin de combat), pas une brique structurante du moteur.

### Pourquoi PHP pour le moteur de simulation

Un combat en auto-battler asynchrone n'est rien d'autre qu'une transformation de données : `CombatLog = f(Joueur A, Joueur B, Seed)`. C'est une fonction pure, et PHP est bien adapté à cet usage précis :

- **Cycle de vie court (stateless)** : une requête HTTP arrive avec l'ID du joueur et celui de l'adversaire, PHP charge les deux plateaux, exécute la simulation en mémoire (100 à 500 ticks), génère le JSON du combat, le sauvegarde en BDD, le renvoie au client, puis libère tout.
- **Zéro fuite de mémoire** : sur un serveur Node.js long-running, une référence mal nettoyée dans un tableau ou un listener d'événements s'accumule au fil des milliers de combats. En PHP, la mémoire est entièrement libérée à la fin de chaque requête.
- **Isolation totale** : deux combats simulés en parallèle ne risquent pas de polluer leur état respectif.

PHP 8.2+ apporte des outils bien adaptés à ce genre de logique métier :

- **Enums typés** pour les `Trigger` (`Trigger::ON_ATTACK`, `Trigger::EVERY_N_SECONDS`) et les types d'actions.
- **DTOs immutables (`readonly class`)** pour représenter l'état d'un objet ou d'un événement sans risque de modification accidentelle en cours de simulation.
- **JIT** : simuler un combat de 100 ticks avec quelques dizaines d'objets en mémoire prend de l'ordre de 2 à 5 ms — largement suffisant.

### Le piège du "code partagé" Node/Vue

L'argument habituel en faveur de Node.js est de pouvoir partager du code entre front et back. Dans le cas d'un auto-battler, ce n'est pas pertinent : le backend (PHP) exécute la logique des règles (dégâts, probabilités, mort, victoire), le frontend (Vue.js) exécute la logique de présentation (file d'attente d'animations, barres de vie interpolées, effets visuels/sonores). Les deux mondes n'ont pas besoin de partager du code métier, seulement un **contrat d'API** (DTOs / schéma OpenAPI, éventuellement des interfaces TypeScript générées depuis les DTOs PHP via un outil comme `spatie/typescript-transformer`).

### Structuration proposée du moteur (Symfony, DDD)

Composant PHP pur, découplé du framework :

```
src/
├── Domain/
│   ├── Model/          # Hero, Item, Board, Effect
│   ├── Event/          # DamageDealtEvent, ShieldGainedEvent...
│   ├── Enum/           # Trigger, Target, Rarity
│   └── Engine/         # Simulator, TickEngine, EventDispatcher
```

## Méthodologie de conception retenue

Ordre de travail volontairement inversé par rapport à l'approche "architecture-first" classique, pour valider le fun avant d'investir dans la technique :

1. **Définir la V1** (le "jeu de 15 minutes") — le plus petit jeu qui donne la bonne sensation
2. **Modèle de données** — suffisamment générique pour ne pas bloquer l'extension future, mais pas plus
3. **Moteur de simulation** — le cœur technique du projet
4. **Contenu** — objets, valeurs, équilibrage, une fois le moteur validé

Principe directeur : ne pas construire un moteur élégant dont personne ne sait s'il est amusant. Scoper étroit, tester tôt.

## Pourquoi ce genre de jeu est réaliste avec ce stack

- Les combats sont **automatiques** (simulation calculée côté serveur), pas de temps réel dur à gérer (pas de prédiction client / réconciliation / lag compensation)
- Le PvP est **asynchrone** : un joueur affronte une copie figée du plateau d'un adversaire (capturée à un instant T), pas un adversaire en direct — évite l'infrastructure lourde du multijoueur temps réel
- Le cœur du jeu (inventaire, boutique, objets avec effets/synergies) est une logique métier proche de ce que Laurent manipule déjà en Symfony/API Platform
- WebSocket/Mercure devient un bonus (notifications, animations de combat) et non une contrainte structurante

## Référence externe : The Bazaar (Tempo)

Roguelike deckbuilder / hero-builder / auto-battler asynchrone. Chaque run, le joueur construit un plateau d'objets synergiques via une boutique, puis affronte des copies de plateaux d'autres joueurs capturées au même "jour" de run. Pas de timer — le joueur peut reprendre une partie quand il veut. Combats résolus automatiquement une fois le plateau construit.

## Univers et thème (V1)

Inspiration : **La Voie des Ombres** (Night Angel, Brent Weeks) — dark fantasy, artefacts magiques vivants ("Ka'kari" dans le livre) liés à un porteur unique, donnant un pouvoir thématique propre (élément, capacité signature), avec un artefact "suprême" plus rare et polyvalent que les autres.

**Point de vigilance IP** : le concept des Ka'kari est une création protégée par le droit d'auteur (Brent Weeks / Orbit). Pour un usage perso/dev, aucun souci à travailler avec ce nom comme référence de travail. **Avant toute publication ou mise en portfolio public**, renommer l'artefact et la faction avec une identité propre (nom, couleurs, lore) — on ne reprend que la **structure mécanique** (artefact vivant lié à un porteur, thématique par faction), jamais les noms ni les pouvoirs exacts tels quels. Ce renommage est fait : l'univers du jeu s'appelle **Les Héritiers du Vide**, et l'artefact devient le **Vestige**.

**Déclinaison retenue** : le concept d'objet vivant lié à un porteur devient le **Vestige** — chaque futur héros/faction aura son propre Vestige avec une **affinité** thématique (élément, mécanique signature, pool d'objets dédié). Un Vestige "suprême" pourra être introduit plus tard comme contenu de fin de progression, à l'image de la structure du livre d'origine (six artefacts élémentaires + un artefact suprême).

## Cahier des charges V1

**Héros : 1 seul**
- Thème : "Porteur de l'Ombre" — assassin, affinité `shadow`
- Pas de mécanique de héros différenciante en V1 (juste HP de départ + emplacements) — retire une variable tant que le moteur n'est pas validé

**Emplacements** : 6 slots génériques (pas de distinction arme/armure pour l'instant)

**Raretés** : 3 — Commune / Rare / Légendaire (multiplicateur de stats : x1 / x1.5 / x2.5)

**Objets** : 30, thème assassin/ombre (dagues, poisons/fioles, artefacts d'ombre, objets de guilde/contrebande)

**Effets (10 max)**

| Catégorie | Effets |
|---|---|
| Offensifs | Dégâts, Critique, Poison (DoT), Burn (DoT) |
| Défensifs | Soin, Bouclier |
| Ressources | Génération d'or, Gain de mana |
| Timing | Vitesse/Cooldown, Esquive (remplace le "gel", plus thématique) |

**Boutique / économie** : 4 offres aléatoires par visite, or de départ fixe, prix croissant avec la rareté, une seule monnaie

**Boucle de jeu ("jeu de 15 minutes")**
1. Choix de départ (2-3 objets communs au choix)
2. Boutique (4 offres, achat/vente/passe)
3. Combat automatique contre une IA scriptée à difficulté croissante (**pas de vrai PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking)
4. Retour boutique jusqu'à mort ou victoire après N combats (ex. 10)

## Modèle de données — principes retenus

- Champ **`affinity`** présent dès la V1 sur le héros ET sur les objets, même avec une seule valeur possible pour l'instant (`shadow`). Permet d'ajouter des factions/héros futurs sans migration de schéma.
- Structure objet/effet basée sur des couples **trigger → actions** :

```json
{
  "trigger": "OnAttack",
  "actions": [
    { "type": "DealDamage", "value": 15 }
  ]
}
```

```json
{
  "trigger": "Every4Seconds",
  "actions": [
    { "type": "GainShield", "value": 20 }
  ]
}
```

- Prévoir un type d'action **`SetAffinity`** dans le schéma dès maintenant (même non implémenté en V1), pour anticiper la mécanique de conversion d'affinité (voir plus bas) sans réécrire le modèle plus tard.

## Moteur de simulation — principes retenus

Architecture à événements, découplée :

```
Tick
 ↓
Cooldowns
 ↓
Déclencheurs
 ↓
Création d'événements
 ↓
Résolution
 ↓
Nouveaux événements
 ↓
Fin du tick
```

Exemple de chaîne d'événements :

```
AttackStarted → Hit → DamageApplied → LifeLost → Death → OnKill → LootGenerated
```

Chaque objet n'est qu'un "listener" qui réagit à certains événements — pas de logique centralisée par objet.

## Points d'attention pour la mise en œuvre

### 1. Déterminisme et gestion des replays

Le serveur calculant le combat automatiquement, le moteur de simulation doit être **100 % déterministe**.

- Si le combat utilise du hasard (critiques, ciblage aléatoire, tirage d'effets), utiliser systématiquement une **graine aléatoire (seeded RNG)** transmise en début de combat.
- Pourquoi c'est crucial : le serveur peut exécuter un combat de 30 secondes en quelques millisecondes, générer un **journal d'événements (combat log)**, et envoyer ce tableau JSON léger à Vue.js. Le frontend n'a plus qu'à "rejouer" l'animation pas à pas, sans refaire le moindre calcul.

### 2. File d'attente d'animations côté frontend (Vue.js 3)

Point de blocage classique pour un développeur web qui passe au jeu vidéo. Le serveur génère un journal d'événements instantané, par exemple :
`[Attack, Hit, PoisonApplied, ShieldTriggered, Death]`

Si le state Vue.js est mis à jour directement à partir de ce journal, tout se joue en 1 milliseconde à l'écran — illisible pour le joueur.

- Implémenter côté Vue.js un système de **queue d'animations asynchrone**.
- Chaque événement JSON dépile une fonction d'animation (via `Promise`/`async-await`), attend la fin de l'effet visuel/sonore, puis passe au suivant.

### 3. Boucle de simulation (moteur à ticks)

Pour le moteur en PHP :

- Éviter le temps réel (floats de secondes) pour la résolution des effets. Utiliser une **grille de ticks fixes** (ex. 1 tick = 100 ms, soit 10 ticks/seconde).
- À chaque tick : décrémenter les cooldowns, évaluer les déclencheurs, résoudre la pile d'actions. Cela simplifie considérablement les tests unitaires et le débogage (possibilité de faire avancer un combat "tick par tick" dans des tests automatisés pour vérifier un bug d'équilibrage).

## Mécanique différée : conversion d'affinité

**Idée proposée** : des objets permettant de changer sa propre affinité (pivot de build) ou de tenter de changer l'affinité du plateau adverse (sabotage).

**Analyse** :
- *Changer sa propre affinité* : bonne idée, ajoute une dimension stratégique de pivot de build, complexité limitée, gain de fun réel probable.
- *Changer l'affinité adverse* : plus risqué. Comme le PvP est asynchrone (affrontement contre une photo figée), ça ne peut se produire que pendant le combat automatique — ce n'est pas une vraie interaction tactique réactive, mais un effet de plus dans le moteur (comparable à un silence/polymorphe). Risque de sensation "pas fun" si le joueur perd sa synergie sans avoir pu réagir. Coût technique réel : l'affinité doit passer d'un tag statique (filtrage boutique) à un **état runtime mutable**, avec gestion de durée (permanent/temporaire), priorité de résolution en cas de double conversion, et lisibilité côté joueur.

**Décision** : ne pas implémenter en V1. Prévoir uniquement le type d'action `SetAffinity` dans le schéma pour ne pas bloquer une implémentation en V2/V3, une fois le moteur de base validé.

## Consigne de suivi

Continuer à appliquer un regard critique et honnête sur les futures propositions de mécaniques — évaluer systématiquement l'impact en termes de complexité moteur, de jouabilité réelle (pas seulement l'idée sur le papier), et de cohérence avec le scope V1, plutôt que de valider par défaut.