# Projet Noyau — Corebound (auto-battler dark fantasy)

> **Note de mise à jour** : ce document remplace la version précédente, devenue partiellement obsolète depuis la session 005 (modèle Vestige/multi-héros) et la session 007 (boutique/économie). Le scope V1 est maintenant strictement délimité de la Roadmap V2+ (voir section dédiée en fin de document) pour éviter toute confusion entre ce qui est acté et ce qui est envisagé.

## Contexte et intention

Projet perso de Laurent, développeur Full Stack (PHP, Vue.js 3). Objectif : développer un jeu vidéo type roguelike deckbuilder / auto-battler asynchrone, dans l'esprit de **The Bazaar** (Tempo), mais avec un univers original — **Les Héritiers du Vide** — inspiré (sans copie) de **La Voie des Ombres** (Night Angel, Brent Weeks).

- **Nom du projet** : Projet Noyau
- **Nom du jeu** : Corebound
- **Univers** : Les Héritiers du Vide
- **Artefact central** : le Vestige

## Choix techniques confirmés

- **Backend** : PHP 8.3, natif (pas de framework applicatif type Symfony à ce stade — architecture DDD construite à la main, namespaces `App\Domain\...`).
- **Frontend** : Vue.js 3.
- **Qualité** : PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (validation JSON + PHPUnit + PHPStan + CS Fixer, tous bloquants sur PR vers `dev`).
- WebSocket/Mercure reste un bonus (notifications, signal de fin de combat) envisageable en V2+, pas une brique structurante du moteur.

### Pourquoi PHP pour le moteur de simulation

Un combat en auto-battler asynchrone est une transformation de données pure : `CombatLog = f(playerBoard, opponentBoard, seed)`. PHP est bien adapté à cet usage :

- **Cycle de vie court (stateless)** : une requête charge les plateaux, exécute la simulation en mémoire, génère le JSON du combat, le renvoie, puis libère tout.
- **Zéro fuite de mémoire** entre requêtes.
- **Isolation totale** entre deux combats simulés en parallèle.

## Univers et thème

Inspiration : **La Voie des Ombres** (Night Angel, Brent Weeks) — dark fantasy, artefacts magiques vivants liés à un porteur unique. **Point de vigilance IP** : ne reprendre que la structure mécanique (artefact vivant lié à un porteur, thématique par faction), jamais les noms ni pouvoirs exacts. L'univers du jeu s'appelle **Les Héritiers du Vide**, et l'artefact devient le **Vestige**.

Chaque futur Vestige aura sa propre **affinité** thématique (élément, mécanique signature, pool d'objets dédié).

---

## Cahier des charges V1 — état réel du code

### Vestige

**1 seul Vestige en V1** : `shadow_vestige`, thème "Porteur de l'Ombre", affinité `shadow`.

Le Vestige porte l'intégralité de l'état vivant du plateau (HP, bouclier, statuts actifs) — les héros n'ont plus d'état de combat propre depuis la migration de session 005.

Champs du modèle `Vestige` (`Domain/Model/`), tous obligatoires, hydratés depuis `config/game/vestiges.json`, fail-fast si absent :
- `id`, `name`, `affinity`
- `baseHp`, `baseShield`
- `startingGold` — or de départ du joueur, lu uniquement par `GameRun` pour initialiser le `Wallet`.
- `startingIncome` — or crédité au `Wallet` à la fin de **chaque** manche, gagnée ou perdue. C'est ce qui garantit qu'une défaite n'empêche pas le joueur de progresser économiquement.

Le moteur de combat lui-même (`Simulator`/`ActionProcessor`/`StatusProcessor`) ignore totalement l'or — ces champs ne sont lus que par `Application/GameRun`.

### Héros et plateau

Un `CombatBoard` regroupe **1 `CombatVestige` + 1 à 3 `CombatHero`** (garde fail-fast dans le constructeur : entre 1 et 3 héros).

- Le Vestige définit l'affinité du plateau. Les héros et objets gardent leur propre affinité indépendante (sans effet mécanique en V1 — anticipe un futur système de synergie).
- 6 emplacements d'objets au total par plateau, comptés globalement (pas encore répartis par héros précis — voir Roadmap).
- Compétences de héros : hors scope V1.

### Raretés et objets

3 raretés : Commune / Rare / Légendaire.
- Multiplicateur de stats : ×1 / ×1.5 / ×2.5 (`Rarity::statMultiplier()`)
- Prix boutique : 10 / 25 / 50 or (`Rarity::basePrice()`)
- Modificateur de taux de drop : ×1 / ×0.25 / ×0.015 (`Rarity::dropRateModifier()`)

**30 objets au total, répartition définitive : 14 Common / 11 Rare / 5 Legendary.** Thème assassin/ombre (dagues, poisons/fioles, artefacts d'ombre). Cette répartition est close, ne pas la rouvrir.

**12 des 30 objets sont purement défensifs** (bouclier, soin, aucun dégât) — ce chiffre a directement motivé l'introduction du système d'enrage (voir Moteur de simulation ci-dessous) : sans lui, ces objets étaient structurellement dominés dans tout matchup qui n'aboutit pas à une mort franche avant `maxTicks`.

### Effets et statuts

Catégories d'effets implémentées : Dégâts, Bouclier, Soin, et 4 statuts à pulsation périodique :
- **Poison** : dégâts qui ignorent le bouclier
- **Burn** : dégâts qui passent par le bouclier normalement
- **Regen** : soin périodique (plafonné à `baseHp`)
- **Ward** : gain de bouclier périodique (sans plafond)

Règle de stacking : ré-application avant expiration → les stacks s'additionnent, la durée la plus longue des deux est conservée.

`Trigger::ON_ATTACK` et `Trigger::EVERY_N_TICKS` sont fonctionnellement équivalents dans le moteur actuel (seul `cooldownTicks` pilote la cadence). `ON_KILL`/`ON_DEATH` existent dans l'enum mais ne sont déclenchés par rien encore.

**Aucun objet ne génère d'or ou de mana en combat** — `ActionType::GAIN_GOLD` et `GAIN_MANA` ont été retirés de l'enum (erreur de cahier des charges initial, corrigée ; voir Économie ci-dessous pour les vraies sources d'or).

### Boutique / Économie

Système complet (`Domain/Shop/` + `Application/Factory/ShopFactory`) :
- `Wallet` : solde du joueur, méthodes `credit()`/`spend()`/`canAfford()`, garde contre montants négatifs et insolvabilité.
- `Shop` : agrégat de 4 `ShopOffer`, achat en deux phases (validation intégrale puis mutation), jamais de débit partiel.
- `ShopFactory` : génère les 4 offres via tirage partitionné (3 slots Common+Rare, 1 slot catalogue complet) — plafonne à 1 Legendary max par visite (~18,5 % de chance, contre ~53,8 % en tirage uniforme).

**Sources d'or en V1**, toutes créditées par `GameRun` à la résolution de chaque manche :
- Or de départ (`Vestige::startingGold`), une seule fois au lancement de la run.
- `Vestige::startingIncome`, crédité à chaque fin de manche, gagnée ou perdue.
- Récompense de victoire : `+10` or fixe (constante `GameRun::VICTORY_REWARD`), en plus de `startingIncome`.

**Hors scope V1** (voir Roadmap) : revente d'objets, récompenses liées à un Monstre.

### Boucle de jeu V1

```
Choix du Vestige : aucun (shadow_vestige fixe)
  ↓
Wallet initialisé avec startingGold
  ↓
┌─── Nouvelle manche (GameRun::playRound) ────────────────┐
│  Boutique (1 visite, 4 offres)                           │
│         ↓                                                 │
│  Combat contre IA scriptée (PvE, ScriptedOpponentFactory) │
│  Nombre d'objets adverses croît avec le round             │
│  (ceil(round / 2), plafonné à 6)                           │
│         ↓                                                 │
│  Victoire : +1 victoire, +10 or, +startingIncome           │
│  Défaite ou timeout : +1 défaite, +startingIncome seul      │
└─────────────────────────────────────────────────────────┘
  ↓ (répéter tant que victoires < 10 ET défaites < 3)
Fin de run : 10 victoires (gagné) ou 3 défaites (perdu)
```

**Pas de PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking.

Orchestrateur : `Application/GameRun`, construit avec un `Vestige`, une `ShopFactory`, une `ScriptedOpponentFactory`, un `Simulator` et un `Randomizer` déjà instanciés (injection explicite, pas de valeur par défaut cachée — même philosophie que `SimulationContext`). `GameRun` ne construit pas le `CombatBoard` du joueur : il le reçoit en paramètre de `playRound()`, la gestion d'un inventaire persistant entre manches reste à construire (voir Roadmap).

### Moteur de simulation

Déterministe (`\Random\Randomizer` + `\Random\Engine\PcgOneseq128XslRr64($seed)`), architecture à événements :

```
TickEngine (horloge)
  → EventDispatcher (routeur pur, zéro accès en écriture)
  → PendingAction (DTO immuable)
  → ActionProcessor (résout Target::SELF/ENEMY, mute le CombatVestige cible)
  → StatusProcessor (pulsation des statuts actifs)
  → EnrageProcessor (dégâts de fin de combat forcée, voir ci-dessous)
  → CombatEvent → CombatLog
```

`Simulator::run()` : boucle jusqu'à `maxTicks` (défaut 500) ou mort d'un plateau — `break` immédiat dès qu'un Vestige meurt (pas de "frappe sur cadavre"), rendant le double-KO structurellement impossible **dans le cas général**. `SimulationResult { winner: ?CombatBoard, totalTicks: int, log: CombatLog }`.

#### Système d'enrage (`EnrageProcessor`)

Ajouté en cours de développement de `GameRun`, suite à un constat de game design : sans lui, un combat qui atteint `maxTicks` sans mort produit `winner: null`, que `GameRun` compte comme une défaite. Un joueur misant sur des objets purement défensifs (12 des 30 objets V1) n'avait alors **aucune ligne de jeu gagnante** face à un adversaire qui refuse de mourir sans jamais l'achever — ces objets étaient structurellement dominés, pas juste sous-optimaux.

Fonctionnement :
- À partir de `triggerTick = max(1, maxTicks - 50)`, inflige des dégâts croissants aux deux Vestiges, au même tick.
- Progression exponentielle : `damage = baseDamage × 2^(tick − triggerTick)`, `baseDamage = 5`.
- Les dégâts passent par le bouclier **normalement** (comme `DEAL_DAMAGE`, pas comme Poison) — décision explicite : le bouclier accumulé doit rester une vraie protection pendant l'enrage, sinon les builds défensifs sont punis une seconde fois.
- **Invariant du "pas de frappe sur cadavre" étendu à l'enrage** : si le premier board évalué meurt du coup d'enrage, le second n'est pas frappé au même tick — sans cette garde, deux plateaux symétriques (mêmes objets, faible écart de HP/bouclier) double-KO de façon quasi certaine dès que la progression exponentielle dépasse l'écart initial, quelle que soit sa taille.
- Conséquence assumée : cette garde introduit un biais d'ordre d'évaluation (le joueur est toujours évalué en premier via `getBoards()`, donc en cas d'égalité stricte de dégâts fatals au même tick, c'est le joueur qui meurt et l'adversaire qui est épargné ce tick-là) — cohérent avec le biais d'ordre déjà documenté ailleurs dans le moteur (activation des objets, joueur avant adversaire).

---

## Points d'attention techniques (toujours valables)

### Déterminisme et replays
Le moteur est 100 % déterministe via seed transmise en début de combat. Le serveur calcule le combat, génère un `CombatLog` JSON, le frontend le rejoue pas à pas sans recalculer.

### File d'attente d'animations (Vue.js 3)
Le `CombatLog` doit être dépilé via une queue d'animations asynchrone (`Promise`/`async-await`), jamais appliqué directement au state — sinon le combat se joue en une milliseconde à l'écran.

### Boucle à ticks fixes
1 tick = 100ms (10 ticks/seconde), pas de temps réel en flottant. Simplifie tests et débogage tick par tick.

---

## Mécanique différée : conversion d'affinité

Idée retenue mais **non implémentée en V1** : objets permettant de changer sa propre affinité (pivot de build). Changer l'affinité adverse jugé risqué (sensation "pas fun", complexité de résolution en cas de double conversion) — reporté V2+.

Le type d'action `SetAffinity` est prévu dans le schéma mental mais pas encore ajouté à l'enum `ActionType` (contrairement à `GAIN_GOLD`/`GAIN_MANA`, il n'a jamais été implémenté ni retiré — c'est un placeholder conscient, pas une erreur à corriger).

---

## Roadmap V2+ — idées actées comme différées, non implémentées

Cette section rassemble toutes les mécaniques discutées mais explicitement reportées après la V1. Elle ne doit pas être lue comme un scope engagé.

### Boucle de jeu enrichie
- **Choix du Vestige parmi 3 aléatoires** au démarrage (V1 : Vestige fixe unique).
- **Deux phases marchand par manche** (avant combat + préparation combat, 4 offres chacune) au lieu d'une seule visite.
- **Choix de Monstre PvE** parmi 3, avec thématique propre, difficulté (faible/moyen/difficile), récompenses dédiées (un de ses objets, un de ses passifs rares, gain d'or fixe selon difficulté).
- **Combat asynchrone contre snapshot de joueur** (vrai PvP) — remplace ou complète l'IA scriptée, plateau adverse = snapshot figé d'un autre joueur à la même manche. Nécessite infrastructure de stockage/matchmaking de plateaux, volontairement non construite en V1.
- **Marchand pouvant influencer** le pool d'objets, les prix, ou proposer du rachat.
- **Vente d'objets** par le joueur (rachat, ex. 50% du prix de base) — nécessite un `Inventory` inexistant.
- **Gestion d'inventaire persistant entre manches** : `GameRun::playRound()` reçoit aujourd'hui le `CombatBoard` du joueur déjà construit ; rien ne trace encore "quels objets le joueur possède" après un achat en boutique.

### Combat et objets
- **Pondération de rareté dans `ScriptedOpponentFactory`** : la difficulté croissante actuelle (V1) ne fait varier que le *nombre* d'objets équipés par l'adversaire (`ceil(round / 2)`, plafonné à 6), jamais leur rareté — délibérément, faute de données de playtesting pour calibrer une pondération.
- **Calibration des paramètres d'enrage** (`triggerTick`, `baseDamage`, facteur de progression) — valeurs V1 posées par raisonnement, jamais ajustées par playtest.
- **Multi-affinité mécanique réelle** : bonus si affinité héros/objet == affinité du plateau (actuellement sans effet).
- **Compétences de héros** (bloc dédié envisagé sur le croquis de board) — non schématisées.
- **Taille d'objet (1 main / 2 mains)** influençant le budget de slots et le prix boutique — dette localisée à `CombatBoardFactory` et la boutique, jamais au moteur de combat lui-même.
- **Répartition des items par héros précis** (`itemIds: list<string>` → `array<heroId, list<string>>`) — actuellement un budget global de 6 slots, pas d'assignation par héros.
- **Ordre d'activation / initiative** entre objets de boards différents partageant un trigger au même tick — actuellement déterministe par ordre de déclaration (joueur avant adversaire), sans stat de vitesse.
- **Garde-fou anti-boucle-infinie** sur `EventDispatcher` en cas de cascade d'événements — non urgent tant qu'aucun effet ne re-déclenche un autre effet.
- **Persistance d'état entre combats** (mode "usure") — point d'entrée alternatif (`fromPreviousState()`) envisagé pour ne pas casser l'existant le jour venu.
- **Conversion d'affinité adverse** (sabotage) — écartée par analyse de fun, `SetAffinity` reste un placeholder pour la conversion de sa propre affinité uniquement.
- **MAX_ITEMS codé en dur (6) dans `ScriptedOpponentFactory`**, plutôt que dérivé de `Hero::itemSlots` — inoffensif tant qu'un seul héros existe dans le contenu, cassera silencieusement dès qu'un second héros avec un budget de slots différent sera ajouté.

---

## Consigne de suivi pour Claude

Continuer à appliquer un regard critique et honnête sur les futures propositions de mécaniques — évaluer systématiquement l'impact en termes de complexité moteur, de jouabilité réelle (pas seulement l'idée sur le papier), et de cohérence avec le scope V1 tel que délimité ci-dessus, plutôt que de valider par défaut. Toute mécanique listée en Roadmap V2+ ne doit pas être implémentée sans une décision explicite de faire passer son scope en V1.