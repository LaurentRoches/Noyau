# Projet Noyau — Corebound (auto-battler dark fantasy)

> **Note de mise à jour** : cette version remplace intégralement la précédente, qui était restée figée autour de la session 007-008 (héros unique fixe, objets sans taille, pas de compétences de héros) alors que plusieurs chantiers majeurs ont été terminés depuis (roster progressif de héros, compétences de héros, tailles d'objets, boutique). Ce document distingue maintenant trois niveaux : **ce qui est construit** (V1 réelle), **ce qui est la vision cible du jeu** (décrite par l'utilisateur mais pas encore codée), et **la Roadmap V2+** (dette connue / idées différées). Ne jamais supposer qu'une mécanique de la vision cible ou de la Roadmap est implémentée sans vérifier le code réel ou le dernier résumé de session.

## Contexte et intention

Projet perso de Laurent, développeur Full Stack (PHP, Vue.js 3). Objectif : développer un jeu vidéo type roguelike deckbuilder / auto-battler asynchrone, dans l'esprit de **The Bazaar** (Tempo), mais avec un univers original — **Les Héritiers du Vide** — inspiré (sans copie) de **La Voie des Ombres** (Night Angel, Brent Weeks).

- **Nom du projet** : Projet Noyau
- **Nom du jeu** : Corebound
- **Univers** : Les Héritiers du Vide
- **Artefact central** : le Vestige

## Choix techniques confirmés

- **Backend** : PHP 8.3 natif (pas de framework applicatif — architecture DDD construite à la main, namespaces `App\Domain\...`). PHPUnit 12, PHPStan niveau 6, PHP CS Fixer.
- **Frontend** : Vue.js 3 / TypeScript / Pinia / Vite. Vitest, ESLint, Prettier, `vue-tsc`.
- **Persistance** : SQLite, via un **journal d'actions rejouable** (un `GameRun` est reconstruit en rejouant, sur une seed fixe, la liste ordonnée des actions qui lui ont été appliquées) plutôt qu'une sérialisation d'instantané — choisi pour ne pas toucher aux classes domaine `final readonly`.
- **CI/CD** : GitHub Actions (`ubuntu-latest`), toutes les portes bloquantes sur PR vers `dev` (validation JSON, PHPUnit, PHPStan, CS Fixer côté backend ; ESLint/Prettier/`vue-tsc`/Vitest côté frontend).
- **Environnement** : Windows + VSCode, Node géré via nvm-windows (`frontend/.nvmrc` + `engines` dans `package.json`).
- WebSocket/Mercure reste un bonus (notifications, signal de fin de combat) envisageable en V2+, pas une brique structurante du moteur.

### Pourquoi PHP pour le moteur de simulation

Un combat en auto-battler asynchrone est une transformation de données pure : `CombatLog = f(playerBoard, opponentBoard, seed)`. PHP est bien adapté à cet usage : cycle de vie court (stateless), zéro fuite de mémoire entre requêtes, isolation totale entre deux combats simulés en parallèle.

## Univers et thème

Inspiration : **La Voie des Ombres** (Night Angel, Brent Weeks) — dark fantasy, artefacts magiques vivants liés à un porteur unique. **Point de vigilance IP** : ne reprendre que la structure mécanique (artefact vivant lié à un porteur, thématique par faction), jamais les noms ni pouvoirs exacts. L'univers du jeu s'appelle **Les Héritiers du Vide**, et l'artefact devient le **Vestige**.

Les Vestiges sont des fragments vivants d'une fracture cosmologique (la Tressure / l'Effilochement), faits de fil ; leur lien à un porteur se manifeste par des marques de fil visibles sous la peau. La structure de run (3 défaites brisent le lien, 10 victoires prouvent un point de fixation stable) est directement ancrée dans cette cosmologie. Chaque futur Vestige aura sa propre **affinité** thématique (élément, mécanique signature, pool d'objets dédié). Voir `corebound-lore-bible.md` et `corebound-art-style-guide-fr.md` pour la bible narrative et la direction artistique complètes.

---

## Cahier des charges V1 — état réel du code

### Vestige

**1 seul Vestige en V1** : `shadow_vestige`, thème "Porteur de l'Ombre", affinité `shadow`. Fixé en dur (`config/game/vestiges.json`) — le choix du Vestige parmi plusieurs n'existe pas encore (voir Vision cible).

Champs du modèle `Vestige` (`Domain/Model/`), tous obligatoires, fail-fast si absent : `id`, `name`, `affinity`, `baseHp`, `baseShield`, `startingGold` (or de départ, une seule fois), `startingIncome` (or crédité à `Wallet` à chaque fin de manche, gagnée ou perdue — garantit qu'une défaite n'empêche pas de progresser économiquement).

Le Vestige porte l'intégralité de l'état vivant du plateau (HP, bouclier, statuts actifs) — les héros n'ont plus d'état de combat propre depuis la migration de session 005. Le moteur de combat lui-même ignore totalement l'or ; ces champs ne sont lus que par `Application/GameRun`.

### Roster de héros — sélection progressive

Le joueur termine une run avec **exactement 3 héros**, choisis progressivement et jamais en double :

- **Manche 1** : choix parmi 3 héros, dont au moins un partage l'affinité du Vestige.
- **Manche 3** et **manche 5** : nouveau choix parmi 3 héros, avec une pondération ×2.0 en faveur de l'affinité du Vestige, exclusion des héros déjà recrutés.

Architecture : `HeroOffer` (value object), `WeightedDraw`, `HeroOfferGenerator`. `HeroRosterFactory` (ancien système de tirage automatique intégral) a été supprimé — le choix du héros est une vraie étape du flow de run (`pendingHeroOffer` exposé côté frontend), pas un tirage subi.

Catalogue actuel : **10 héros** (`config/game/heroes.json`), affinité `shadow` ou `neutral` :

| Héros | Affinité | Skill |
|---|---|---|
| Shadow's Bearer | shadow | FRANTIC |
| Shadow's Bastion | shadow | WARDEN |
| Shadow's Venomancer | shadow | VIRULENT |
| Shadow's Duelist | shadow | RELENTLESS |
| Shadow's Arrow | shadow | SAVAGE |
| The Ironblade | neutral | SUNDERING |
| The Farshot | neutral | SAVAGE |
| The Lifebringer | neutral | VITALIC |
| The Flameborn | neutral | SEARING |
| The Bulwark | neutral | STALWART |

Chaque héros porte un champ `itemSlots` **fixé à 2**, appliqué comme une vraie contrainte individuelle (pas un budget global partagé entre les 3 héros) — nécessaire pour qu'un skill ne finisse pas par modifier plus d'objets que prévu.

### Compétences de héros (skills)

**Implémentées et actives en combat** (branche `feature/hero-skills`, session 011) — ce n'est plus une idée différée. Une compétence agit comme un **filtre passif appliqué aux objets du héros au moment de l'assemblage du plateau** (`CombatBoardFactory` + `HeroSkillDecorator`), pas comme une action autonome — 100% compatible avec le moteur stateless (`TickEngine`/`EventDispatcher`/`ActionProcessor` inchangés).

Catalogue de 10 compétences (`HeroSkillType`) :

| Skill | Effet |
|---|---|
| `FRANTIC` | −20% `cooldownTicks` sur les objets `ONE_HAND` (arrondi `floor()`) |
| `VIRULENT` | +1 stack sur les actions `APPLY_STATUS(POISON)` |
| `SEARING` | +1 stack sur les actions `APPLY_STATUS(BURN)` |
| `WARDEN` | +1 stack sur les actions `APPLY_STATUS(WARD)` |
| `RESURGENT` | +1 stack sur les actions `APPLY_STATUS(REGEN)` |
| `STALWART` | +20% sur la valeur des actions `GAIN_SHIELD` (arrondi `ceil()`) |
| `VITALIC` | +20% sur la valeur des actions `HEAL` |
| `SAVAGE` | +20% sur la valeur des actions `DEAL_DAMAGE` |
| `SUNDERING` | Composition dégâts+vitesse (+35% dégâts / −10% cooldown, calibré en quasi-parité de DPS réel ≈ +22.7%) |
| `RELENTLESS` | Composition dégâts+vitesse (+10%/+10% ≈ +22.2% DPS), nécessite un chargement complet d'objets `ONE_HAND` sur le héros (précondition `hasFullOneHandLoadout`) |

Nullable côté modèle (`Hero::$skill`, `?HeroSkillType`) — décision assumée : des héros ont existé sans skill avant que le concept existe, ce n'est pas qu'une facilité technique.

### Objets et raretés

3 raretés : Commune / Rare / Légendaire. Multiplicateur de stats ×1 / ×1.5 / ×2.5, prix boutique 10 / 25 / 50 or, modificateur de taux de drop ×1 / ×0.25 / ×0.015.

**30 objets, répartition définitive et close : 14 Common / 11 Rare / 5 Legendary.** Thème assassin/ombre. 12 des 30 objets sont purement défensifs (bouclier, soin, aucun dégât) — ce chiffre a motivé le système d'enrage (voir plus bas).

**Taille d'objet** : `ONE_HAND` ou `TWO_HAND` (`Item::size`, `ItemSize::slotCost()`). Un objet `TWO_HAND` vaut ×2 en valeur brute (neutralité en slots : 1 objet 2-mains ≈ 2 objets 1-main en puissance) mais seulement ×1.75 en prix (vrai rabais compensant la perte de flexibilité).

**Attribution objet → héros** : un objet acheté est attribué à un héros précis dès l'achat, pas au moment du combat (`AssignedItem` = `Item` + `heroId`). `HeroItemAllocator` valide la faisabilité en sommant les `slotCost()` déjà assignés à un héros contre son budget `itemSlots` (2), pas un simple compte d'objets. **Seul l'échange héros ↔ coffre est implémenté aujourd'hui** (`swapWithStash`) — un transfert direct héros ↔ héros n'existe pas encore (voir Vision cible : c'est la cible, pas le choix final).

### Effets et statuts

Dégâts, Bouclier, Soin, et 4 statuts à pulsation périodique :
- **Poison** : dégâts qui ignorent le bouclier
- **Burn** : dégâts qui passent par le bouclier normalement
- **Regen** : soin périodique (plafonné à `baseHp`)
- **Ward** : gain de bouclier périodique (sans plafond)

Règle de stacking : ré-application avant expiration → les stacks s'additionnent, la durée la plus longue des deux est conservée. `ON_ATTACK` et `EVERY_N_TICKS` sont fonctionnellement équivalents (seul `cooldownTicks` pilote la cadence).

### Boutique / Économie

Système complet mais volontairement minimal en V1 : `Wallet` (solde, `credit()`/`spend()`/`canAfford()`), `Shop` (4 `ShopOffer`, achat en deux phases — validation intégrale puis mutation, jamais de débit partiel), `ShopFactory` (tirage partitionné : 3 slots Common+Rare, 1 slot catalogue complet — plafonne à 1 Legendary max par visite, ~18,5% de chance).

**Sources d'or** : or de départ (`Vestige::startingGold`, une fois), `startingIncome` (chaque fin de manche), récompense de victoire (+10 or fixe). **Hors scope V1** : revente d'objets, récompenses liées à un type de Monstre.

### Inventaire du joueur

- **Plateau de combat** : capacité 6 (objets équipés au prochain combat, répartis entre les 3 héros selon leur budget individuel de 2).
- **Coffre** : capacité **6** (augmentée de 3 à 6 avec le chantier de sélection progressive des héros), stockage additionnel sans équipement.

Seul mouvement possible entre les deux collections : `swapWithStash` (échange direct plateau ↔ coffre). Pas de réordonnancement interne, pas de transfert héros ↔ héros.

### Boucle de jeu V1

```
Vestige fixe (shadow_vestige) — pas de choix
  ↓
Manche 1 : choix d'un héros parmi 3 (≥1 de l'affinité du Vestige)
  ↓
┌─── Nouvelle manche ──────────────────────────────────────────┐
│  Boutique (1 visite, 4 offres)                                │
│         ↓                                                      │
│  Construction du plateau (3 héros dès qu'ils sont recrutés)    │
│  Combat PvE contre IA scriptée (difficulté croissante)         │
│         ↓                                                      │
│  Victoire : +1 victoire, +10 or, +startingIncome                │
│  Défaite ou timeout : +1 défaite, +startingIncome seul           │
└──────────────────────────────────────────────────────────────┘
  ↓ (aux manches 3 et 5 : choix d'un nouveau héros parmi 3, pondéré, sans doublon)
  ↓ (répéter tant que victoires < 10 ET défaites < 3)
Fin de run : 10 victoires (gagné) ou 3 défaites (perdu)
```

**Pas de PvP asynchrone en V1** — le moteur solo doit être validé avant d'investir dans le stockage de plateaux / matchmaking. **Pas de choix de difficulté PvE en V1** — un seul combat par manche, IA scriptée, difficulté croissante automatique (voir Vision cible).

### Moteur de simulation

Déterministe (`\Random\Randomizer` + `\Random\Engine\PcgOneseq128XslRr64($seed)`), architecture à événements : `TickEngine → EventDispatcher → PendingAction → ActionProcessor → StatusProcessor → EnrageProcessor → CombatEvent → CombatLog`.

`Simulator::run()` : boucle jusqu'à `maxTicks` (défaut 500) ou mort d'un plateau — `break` immédiat dès qu'un Vestige meurt, rendant le double-KO structurellement impossible dans le cas général.

**Système d'enrage** : à partir de `triggerTick = max(1, maxTicks - 50)`, dégâts croissants exponentiels sur les deux Vestiges au même tick (`damage = baseDamage × 2^(tick − triggerTick)`, `baseDamage = 5`), passant par le bouclier normalement. Garde anti-double-KO étendue à l'enrage (biais d'ordre : le joueur est toujours évalué en premier).

---

## Points d'attention techniques (toujours valables)

- **Déterminisme et replays** : moteur 100% déterministe via seed. Le serveur calcule le combat, génère un `CombatLog` JSON, le frontend le rejoue pas à pas sans recalculer.
- **File d'attente d'animations (Vue.js 3)** : le `CombatLog` doit être dépilé via une queue d'animations asynchrone, jamais appliqué directement au state.
- **Boucle à ticks fixes** : 1 tick = 100ms (10 ticks/seconde).

---

## Vision cible du jeu — au-delà de la V1 (décrite par l'utilisateur, pas encore codée)

Cette section capture la vision complète du jeu telle qu'exprimée par l'utilisateur, distincte de la Roadmap V2+ ci-dessous (qui liste des dettes/idées ponctuelles plutôt qu'un projet de boucle de jeu cohérent).

- **Choix du Vestige** parmi plusieurs au démarrage (V1 : Vestige fixe unique).
- **Choix du marchand** : parmi 3 marchands à chaque manche, chacun spécialisé dans la vente et/ou le rachat d'un certain type d'objet, plus un marchand plus rare vendant des **passifs de plateau** (modificateurs qui s'appliquent à tout le plateau du joueur, distincts des skills de héros). V1 : un seul marchand générique, sans spécialisation.
- **Choix du combat PvE** : parmi 3 (facile/récompense faible, moyen/récompense normale, difficile/récompense meilleure). V1 : un seul combat aléatoire par manche.
- **Deuxième phase marchand** après le combat PvE, précédant un **combat PvP asynchrone** qui deviendrait le vrai compteur de fin de manche (remplaçant ou complétant l'IA scriptée, plateau adverse = snapshot figé d'un autre joueur). V1 : une seule visite marchand, pas de PvP.
- **Échange libre d'objets héros ↔ héros** (en plus de héros ↔ coffre existant) — cible explicite, pas juste un manque accidentel : le joueur doit pouvoir déplacer un objet d'un héros à l'autre directement, sans réordonnancement artificiel, sans pénalité, sans limitation.
- **Système d'affinité étendu** : au-delà de l'affinité unique actuelle (`shadow`), la cible est un système à 4 relations — bonus si même affinité que le Vestige, petit bonus si affinité liée positivement, aucun effet si neutre, petit malus si liée négativement. S'applique au skill du héros (déjà partiellement vrai puisque les skills modifient déjà le comportement des objets) et aux objets eux-mêmes selon l'affinité du héros qui les porte.
- **Système de niveau/fusion d'objets** : objets démarrant en rang Bronze ; deux objets identiques de même rang possédés simultanément fusionnent en un objet de rang supérieur (Bronze → Argent → Or → possiblement Diamant). Non tranché : la fusion doit-elle changer uniquement les chiffres, ou le comportement de l'objet (préférence exprimée pour un changement de comportement, pour éviter que la fusion devienne un réflexe automatique plutôt qu'une vraie décision).

---

## Discussion externe — priorisation proposée pour les prochains chantiers (non actée)

Un référent externe a proposé un ordre de traitement pour la Vision cible ci-dessus, après relecture de l'état réel du projet. **Ceci reste une proposition à valider explicitement avant tout début de chantier**, conformément à la Consigne de suivi en fin de document — ce n'est pas un scope engagé.

1. **Échange libre héros ↔ héros** — argument central : la friction actuelle (obligation de passer par le coffre) n'est pas une décision stratégique intéressante, juste une manipulation en plus. Pas de nouveau contenu, juste lever la friction sur les 6 slots existants.
2. **Deuxième affinité** — traitée comme un prototype de design (2 affinités seulement, pas 10 d'un coup) pour tester si l'affinité crée réellement des builds différents ou n'est qu'un multiplicateur de puissance. Argument pour la faire après le point 1 : sans échange libre d'objets, impossible de savoir si un mauvais résultat vient du système d'affinité ou de la friction d'ergonomie.
3. **Repenser la fusion** — prototype à 3 niveaux seulement (Bronze → Argent → Or, Diamant repoussé), avec une évolution comportementale plutôt qu'une simple multiplication de stats.
4. **Vrai système de marchand** — spécialisation, rachat, marchands multiples, marchand rare de passifs. Volontairement après les points 1-3, pour savoir ce que le joueur cherche réellement à acheter avant de construire l'offre.
5. **Diversification du PvE / récompenses** (choix de difficulté).
6. **Deuxième phase marchand.**
7. **PvP asynchrone.**
8. **Équilibrage de la boucle 10 victoires / 3 défaites.**

Point de vigilance soulevé en parallèle : le roster de 3 héros ne doit pas nécessairement pousser vers "trois héros de la même famille" — l'objectif est que les opportunités offertes par la run déterminent la stratégie adoptée (carry + supports, synergie totale, ou hybride), pas l'inverse. La pondération ×2 vers l'affinité du Vestige aux manches 3 et 5 doit créer une tension (renforcer une direction vs. pivoter) sans rendre le choix déterministe.

La refonte visuelle (CSS ornementale/atmosphérique, en attente depuis la session 014-016) reste volontairement en dehors de cette liste — à faire ponctuellement pour l'UX, sans devenir le chantier principal tant que ces systèmes ne sont pas validés.

---

## Roadmap V2+ — dette connue / idées différées (hors vision cible ci-dessus)

- **Pondération de rareté dans `ScriptedOpponentFactory`** : la difficulté croissante ne fait varier que le *nombre* d'objets équipés par l'adversaire, jamais leur rareté — faute de données de playtesting.
- **Calibration des paramètres d'enrage** (`triggerTick`, `baseDamage`) — posés par raisonnement, jamais ajustés par playtest.
- **Fragmentation de budget par héros** : `HeroItemAllocator` en first-fit naïf peut laisser un héros avec 1 slot libre insuffisant pour un objet `TWO_HAND`, forçant cet objet au coffre même si la capacité totale du roster suffirait. Piste proposée : swap N-vers-1 pondéré par `slotCost()` entre `Inventory` et `Stash`.
- **Ordre d'activation / initiative** entre objets de boards différents partageant un trigger au même tick — actuellement déterministe (joueur avant adversaire), sans stat de vitesse.
- **Garde-fou anti-boucle-infinie** sur `EventDispatcher` en cas de cascade d'événements — non urgent tant qu'aucun effet ne re-déclenche un autre effet.
- **Persistance d'état entre combats** (mode "usure") — point d'entrée alternatif (`fromPreviousState()`) envisagé.
- **Conversion d'affinité adverse** (sabotage) — écartée par analyse de fun ; `SetAffinity` reste un placeholder pour la conversion de sa propre affinité uniquement.
- **Typage d'objets par tags** (`weapon`, `melee`, etc.) — différé faute de besoin exercé plus d'une fois.
- **Rééquilibrage de l'IA scriptée** qui hérite désormais des compétences de ses héros sans recalibrage volontaire de la difficulté.

---

## Consigne de suivi pour Claude

Continuer à appliquer un regard critique et honnête sur les futures propositions de mécaniques — évaluer systématiquement l'impact en termes de complexité moteur, de jouabilité réelle, et de cohérence avec le scope V1 tel que délimité ci-dessus, plutôt que de valider par défaut. Toute mécanique listée en Roadmap V2+ ou en Vision cible ne doit pas être implémentée sans une décision explicite de l'utilisateur de faire passer son scope en V1.

**Leçon tirée de la rédaction de cette version** : ce document lui-même peut devenir obsolète en quelques chantiers (c'était le cas ici, resté figé depuis la session 007-008 malgré plusieurs features majeures mergées ensuite). En cas de doute sur l'état réel d'une mécanique, vérifier directement le code fourni ou le dernier résumé de session plutôt que de faire confiance par défaut à ce fichier — et signaler explicitement à l'utilisateur toute mise à jour nécessaire dès qu'un écart est détecté.