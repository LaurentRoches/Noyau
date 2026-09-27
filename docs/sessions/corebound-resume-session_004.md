# Corebound / Projet Noyau — Résumé de session 004

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` et `README.md` dans les fichiers projet pour le cahier des charges complet V1.

**Stack** : PHP 8.3, Vue.js 3 en frontend. PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (JSON validation, PHPUnit, PHPStan, CS Fixer — tous bloquants, déclenchés sur Pull Request vers `dev`).

**Méthodologie à respecter impérativement** (rappel, cf. `SKILL.md` projet et sessions précédentes) :
- Mode pédagogique actif : questions guidées plutôt que réponses directes, sauf demande explicite de réponse directe.
- TDD strict, bottom-up : test rouge → implémentation minimale → vert → refactor.
- Regard critique systématique sur toute nouvelle idée (complexité moteur, jouabilité réelle, cohérence V1, YAGNI) plutôt que validation par défaut — consigne explicite du projet.
- Vérifier CS Fixer + PHPStan + PHPUnit avant chaque commit.
- Célébrer les initiatives de l'utilisateur quand il dérive un concept/solution seul.

## État Git

- **`feature/json-hydratation`** et **`feature/simulation-engine`** : mergées vers `dev` (sessions précédentes).
- **Branche à créer pour le contenu** : `feature/items-v1-content` — les 30 objets ont été rédigés au fil de cette session, à committer sur cette branche si ce n'est pas déjà fait.
- **Prochaine branche à créer** : `feature/status-effects-engine` (voir section "Chantier en cours" plus bas) — scope de commit proposé : `domain` (cohérent avec le reste du moteur).

## Contenu V1 — les 30 objets (état final de cette session)

Fichier cible : `config/game/items.json`. Contenu final tel qu'uploadé par l'utilisateur, à considérer comme la version de référence actuelle :

```json
[
  { "id": "dagger", "name": "Dagger", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 10 }] }] },
  { "id": "stiletto", "name": "Stiletto", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 10,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 5 }] }] },
  { "id": "shortsword", "name": "Shortsword", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 30,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 15 }] }] },
  { "id": "longsword", "name": "Longsword", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 50,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 25 }] }] },
  { "id": "shortbow", "name": "Shortbow", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 10 }] }] },
  { "id": "longbow", "name": "Longbow", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 20 }] }] },
  { "id": "venomous_vial", "name": "Venomous vial", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "APPLY_STATUS", "target": "ENEMY", "status": "POISON", "stacks": 1, "durationTicks": 30 }] }] },
  { "id": "firesteel", "name": "Firesteel", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "APPLY_STATUS", "target": "ENEMY", "status": "BURN", "stacks": 2, "durationTicks": 20 }] }] },
  { "id": "shield", "name": "Shield", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "GAIN_SHIELD", "target": "SELF", "value": 10 }] }] },
  { "id": "buckler", "name": "Buckler", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 10,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "GAIN_SHIELD", "target": "SELF", "value": 5 }] }] },
  { "id": "scutum", "name": "Scutum", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 50,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "GAIN_SHIELD", "target": "SELF", "value": 25 }] }] },
  { "id": "cataplasm", "name": "Cataplasm", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "HEAL", "target": "SELF", "value": 25 }] }] },
  { "id": "mercurocroum", "name": "Mercurocroum", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 16,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "HEAL", "target": "SELF", "value": 10 }] }] },
  { "id": "medical_kit", "name": "Medical Kit", "rarity": "COMMON", "affinity": "neutral", "cooldownTicks": 56,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "HEAL", "target": "SELF", "value": 35 }] }] },

  { "id": "shadow_dagger", "name": "Shadow Dagger", "rarity": "RARE", "affinity": "shadow", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 15 }] }] },
  { "id": "shadow_longsword", "name": "Shadow Longsword", "rarity": "RARE", "affinity": "shadow", "cooldownTicks": 50,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 38 }] }] },
  { "id": "shadow_venomous_vial", "name": "Shadow Venomous Vial", "rarity": "RARE", "affinity": "shadow", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "APPLY_STATUS", "target": "ENEMY", "status": "POISON", "stacks": 2, "durationTicks": 30 }] }] },
  { "id": "shadow_scutum", "name": "Shadow Scutum", "rarity": "RARE", "affinity": "shadow", "cooldownTicks": 50,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "GAIN_SHIELD", "target": "SELF", "value": 38 }] }] },
  { "id": "shadow_cataplasm", "name": "Shadow Cataplasm", "rarity": "RARE", "affinity": "shadow", "cooldownTicks": 40,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "HEAL", "target": "SELF", "value": 38 }] }] },
  { "id": "katana", "name": "Katana", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 50,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 38 }] }] },
  { "id": "scimitar", "name": "Scimitar", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 30,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 23 }] }] },
  { "id": "elfic_bow", "name": "Elfic bow", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 30 }] }] },
  { "id": "molotov_cocktail", "name": "Molotov Cocktail", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [{ "type": "APPLY_STATUS", "target": "ENEMY", "status": "BURN", "stacks": 3, "durationTicks": 20 }] }] },
  { "id": "tower_shield", "name": "Tower Shield", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 20,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "GAIN_SHIELD", "target": "SELF", "value": 15 }] }] },
  { "id": "healing_potion", "name": "Healing Potion", "rarity": "RARE", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [{ "type": "HEAL", "target": "SELF", "value": 38 }] }] },

  { "id": "nightfang", "name": "Nightfang", "rarity": "LEGENDARY", "affinity": "shadow", "cooldownTicks": 10,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [
      { "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 9 },
      { "type": "APPLY_STATUS", "target": "ENEMY", "status": "POISON", "stacks": 1, "durationTicks": 30 }
    ] }] },
  { "id": "silent_death", "name": "Silent death", "rarity": "LEGENDARY", "affinity": "shadow", "cooldownTicks": 30,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [
      { "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 27 },
      { "type": "APPLY_STATUS", "target": "ENEMY", "status": "BURN", "stacks": 2, "durationTicks": 20 }
    ] }] },
  { "id": "excalibur", "name": "Excalibur", "rarity": "LEGENDARY", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "ON_ATTACK", "actions": [
      { "type": "DEAL_DAMAGE", "target": "ENEMY", "value": 40 },
      { "type": "GAIN_SHIELD", "target": "SELF", "value": 10 }
    ] }] },
  { "id": "panacee", "name": "Panacée", "rarity": "LEGENDARY", "affinity": "neutral", "cooldownTicks": 40,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [
      { "type": "HEAL", "target": "SELF", "value": 58 },
      { "type": "APPLY_STATUS", "target": "SELF", "status": "REGEN", "stacks": 1, "durationTicks": 30 }
    ] }] },
  { "id": "shadow_armor", "name": "Shadow armor", "rarity": "LEGENDARY", "affinity": "shadow", "cooldownTicks": 18,
    "effects": [{ "trigger": "EVERY_N_TICKS", "actions": [
      { "type": "GAIN_SHIELD", "target": "SELF", "value": 17 },
      { "type": "APPLY_STATUS", "target": "SELF", "status": "WARD", "stacks": 1, "durationTicks": 30 }
    ] }] }
]
```

## Méthodologie de rédaction des objets — principes établis durant cette session

- **Monnaie de puissance V1** (adaptée du guide générique `guide_creation_item.md`, propre aux 10 effets réels du cahier des charges) :

| Effet | Valeur indicative |
|---|---:|
| 10 dégâts | 10 |
| 10 soin | 8 |
| 10 bouclier | 10 |
| 1 stack Poison (par pulsation) | 1 dégât brut, ignore le bouclier — **discount volontaire** car plus fort à valeur égale qu'un dégât classique |
| 1 stack Burn (par pulsation) | 1 dégât, passe par le bouclier normalement — pas de discount |
| Or / Mana | valeurs non fixées, jugées peu fiables (dépendent d'une économie de boutique non encore conçue) |

- **Budget par rareté** : dérivé directement du multiplicateur de stats déjà acté dans `Rarity::statMultiplier()` (Commune ×1 = 100 pts, Rare ×1.5 = 150 pts, Légendaire ×2.5 = 250 pts) — pas de table de budget séparée inventée, cohérence stricte avec le code existant.
- **Combat de référence** : 20 secondes (200 ticks), 1 tick = 100ms.
- **Calcul standard** : `budget rareté / nombre d'activations sur 20 sec = points disponibles par activation`, répartis entre dégâts directs et effets de statut selon l'identité voulue de l'objet.
- **`affinity: "neutral"`** introduite dès cette session comme décision de modélisation volontaire (coût nul aujourd'hui, `affinity` n'étant lue nulle part dans le code) : les objets génériques sont `neutral`, réservant `shadow` aux variantes thématiques liées à la faction (Shadow Dagger, Shadow Longsword, etc.). Question ouverte pour plus tard : règle de filtrage boutique exacte pour `neutral` (visible par tous les héros, ou pool séparé).
- **Paires stat-pour-stat volontaires** (ex. Shadow Longsword / Katana, Shadow Cataplasm / Healing Potion) : assumées comme identiques en V1, la différenciation viendra en V2/V3 via le système de multiplicateurs d'affinité (×0.9 malus / ×1.0 neutre / ×1.1 bonus, valeurs arbitraires à ce stade) déjà anticipé dans `game-design-notes.md`.
- **Convention de nommage** : noms d'objets en anglais, pas d'article ni de fioriture pour les objets de base servant de point de comparaison (ex. "Dagger" plutôt que "Rusty dagger").
- **Statuts introduits** : `POISON`, `BURN` (déjà présents dans l'ancien exemple), plus deux nouveaux noms créés durant cette session pour les Légendaires — `REGEN` (soin dans le temps, sur soi) et `WARD` (bouclier dans le temps, sur soi). Noms utilisés dans le fichier final sans contestation ultérieure, considérés comme adoptés.

## Vérifications techniques faites sur le vrai code source (uploadé durant cette session)

- `Action.php` : `target` est un champ explicite à renseigner sur chaque action (`ENEMY`/`SELF`), même si nullable dans le constructeur.
- `Rarity.php` : confirme `statMultiplier()` (×1 / ×1.5 / ×2.5) et un `dropRateModifier()` séparé (×1 / ×0.25 / ×0.015) — ce dernier n'a pas encore été utilisé dans la rédaction du contenu, pertinent pour la future boutique.
- `Trigger.php` : `ON_ATTACK`, `EVERY_N_TICKS`, `ON_KILL`, `ON_DEATH`.
- **Découverte importante** : `TickEngine.tick()` n'appelle jamais `EventDispatcher::dispatch(Trigger)` (le broadcast global) — uniquement `dispatchForItem($board, $readyItem)`, qui filtre sur l'objet précis **sans jamais lire la valeur du `Trigger`**. Conséquence : `ON_ATTACK` et `EVERY_N_TICKS` sont **strictement équivalents dans le moteur actuel** — seule la valeur de `cooldownTicks` pilote la cadence de déclenchement. Le champ `Trigger` est aujourd'hui de la pure métadonnée sans effet sur la simulation. `ON_KILL`/`ON_DEATH` existent dans l'enum mais rien ne les déclenche encore (le `dispatch()` global existe dans le code mais n'est appelé par personne — probablement le point d'entrée prévu pour ces triggers réactifs, à brancher depuis `ActionProcessor` ou `Simulator` plus tard).
- **Confirmation** : `Effect::$intervalTicks` n'est lu nulle part dans `EventDispatcher` ni `TickEngine` — champ mort hérité de l'ancien exemple, non utilisé dans le contenu final.
- `ActionType.php` : `APPLY_STATUS`, `GAIN_GOLD`, `GAIN_MANA`, `SET_AFFINITY` existent dans l'enum mais ne sont **pas traités** par `ActionProcessor` (`default => throw LogicException`). Seuls `DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL` sont fonctionnels aujourd'hui.
- `EventType.php` : seulement `DAMAGE_DEALT`, `HEAL_RECEIVED`, `SHIELD_GAINED` — pas encore de cas pour les statuts (`STATUS_APPLIED`, dégâts/soins de tick périodique) — à prévoir dans le prochain chantier.

## Chantier en cours — décidé en toute fin de session, pas encore commencé

**Sujet** : implémenter la gestion des statuts (`APPLY_STATUS`, Poison/Burn/Regen/Ward) dans le moteur, **avant** de passer à la boutique/économie — décision motivée et validée : plusieurs objets du contenu V1 fraîchement rédigé restent inertes/crashants tant que ce chantier n'est pas fait, et finaliser le moteur avant la couche méta-jeu respecte la promesse architecturale `CombatLog = f(A, B, Seed)`.

**Nom de branche retenu** : `feature/status-effects-engine` (cohérent avec `feature/simulation-engine`, `feature/json-hydratation`, `feature/items-v1-content`). Scope de commit : `domain`.

**Décisions actées pour ce chantier** :
- Une seule classe plate `ActiveStatus` (pas de hiérarchie polymorphe par type de statut) — probablement `statusType`, `stacks: int`, `remainingTicks: int`.
- **Règle de stacking tranchée** : quand un statut est ré-appliqué avant expiration, **les stacks s'additionnent**, et **la durée la plus longue des deux applications est conservée** (pas de simple remplacement, pas de rafraîchissement systématique de la durée à chaque application).
- Comportement différencié par type de statut (Poison ignore le bouclier / Burn passe par le bouclier / Regen soigne / Ward donne du bouclier) — géré via un `match` dans un futur `StatusProcessor`, sur le modèle de ce que fait déjà `ActionProcessor` avec `ActionType`.
- Ordre proposé dans le tick (à confirmer/tester) : `advanceTick()` → décrément cooldowns → **pulsation des statuts actifs (dégâts/soins + décrément durée)** → détection objets prêts → dispatch. Cohérent avec la logique déjà actée ailleurs ("consommation avant notification").
- Premier test TDD à écrire : `ActiveStatus` seule, sans dépendance — équivalent de ce qu'ont été `CombatHero`/`CombatItem` au tout début du moteur.

**Questions ouvertes, non tranchées, à reprendre en premier lieu à la prochaine session** :
1. Où vit `ActiveStatus` : nouvelle propriété directement sur `CombatHero`, ou faut-il d'abord faire la migration `PlayerState`/`VestigeState` déjà notée comme "recommandée pendant que le codebase est petit" (le sujet des statuts touchant directement à la propriété HP/bouclier du héros, c'est peut-être le bon moment pour trancher les deux migrations ensemble plutôt que de re-toucher `CombatHero` deux fois) ?
2. Où vit `StatusProcessor` dans le graphe de dépendances : dépendance de constructeur sur `TickEngine` (comme `EventDispatcher`), ou collaborateur séparé invoqué directement par `Simulator` ?
3. Nouveaux cas `EventType` à ajouter pour que le `CombatLog` reste exploitable côté frontend (ex. `STATUS_APPLIED`, dégâts/soins de tick) — pas encore définis.
4. Répartition finale 14/11/5 vs 14/10/6 des 30 objets (cf. section précédente) — à confirmer avant de considérer le contenu V1 comme clos.

## Rappel des conventions

- Commits Conventional Commits, scope `domain` pour le moteur (contenu JSON = à définir, peut-être `content`).
- Un commit par brique logique terminée et verte.
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, CI vérifiée avant merge.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin → nouveau test.
- Mode pédagogique par défaut : questions guidées avant le code, sauf demande explicite de réponse directe.
