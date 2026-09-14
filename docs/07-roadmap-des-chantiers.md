# 07 — Roadmap des chantiers

**Autorité sur :** l'ordre des chantiers, leurs préalables, leurs critères de sortie.
**Révision :** 2.0 — 8 septembre 2026.
**Rythme de mise à jour :** à chaque audit ou replanification. **Jamais à chaque session** — les états de code datés appartiennent aux résumés de session.

**Rappel d'autorité (`00-INDEX` §2) :** le code réel prime sur ce document. Les états chiffrés de la §1 ont été relevés directement dans les fichiers le 7 septembre 2026 et re-vérifiés le 8 septembre 2026 sur un périmètre élargi (§1.4). Toute divergence constatée ultérieurement invalide la section concernée, pas le code.

**Ce document ne remplace pas `03`.** Le cahier des charges reste l'autorité sur le périmètre par jalon. Cette roadmap dit **dans quel ordre** les chantiers s'exécutent et **ce qui bloque quoi**.

**Ce document ne remplace pas `02`.** Les écarts entre une règle décrite au GDD et la règle réellement implémentée sont reportés dans `02`, pas ici. Cette roadmap ne consigne un fait de moteur que lorsqu'il conditionne un ordre de travaux.

**Révision 2.0 — ce qui a changé.** Un audit de 30 fichiers de code et de configuration a été mené le 8 septembre 2026. Il a invalidé sept affirmations de la révision 1.0, révélé cinq anomalies de moteur non détectées, et fait passer les points irréversibles de trois à cinq. L'ordre des chantiers en est modifié.

---

## 1. État réel vérifié

### 1.1 Configuration

| Fichier | Contenu réel |
|---|---|
| `vestiges.json` | **1 Vestige** : `shadow_vestige`, affinité `shadow`, `baseHp` 100, `baseShield` 10, `startingGold` 20, `startingIncome` 5 |
| `heroes.json` | **10 héros** : 5 `shadow`, 5 `neutral`. Tous à `itemSlots: 2`. Compétences : FRANTIC, WARDEN, VIRULENT, RELENTLESS, SAVAGE (shadow) · SUNDERING, SAVAGE, VITALIC, SEARING, STALWART (neutral) |
| `items.json` | **30 objets** : 14 Common / 11 Rare / 5 Legendary · 22 `neutral` / 8 `shadow` |
| `scripted_opponent.json` | **3 héros** : `shadow_bearer` (2× `dagger`), `the_bulwark` (`longsword`), `shadow_bastion` (2× `shield`) |

Ces quatre lignes ont été recalculées le 8 septembre 2026 sur les fichiers réels. Elles sont exactes.

**Doublon confirmé.** `SAVAGE` est porté par `shadow_arrow` et `the_farshot`. L'affinité n'ayant aucun effet mécanique, ces deux héros sont strictement identiques en jeu. Conforme au constat du GDD §2.2.

### 1.2 Distinction essentielle : conception faite, code non fait

Ce qui est **produit** : 40 descriptions de héros, 47 assets illustrés (7 Vestiges + 40 héros), une attribution de compétences validée sur 18 compétences, sans violation de l'invariant d'unicité.

Ce qui est **implémenté** : 1 Vestige, 10 héros, 30 objets.

> **Le roster de 40 héros est un document de conception, pas l'état du code.** Toute planification qui traite les 40 héros comme existants est fausse. La roadmap ci-dessous part de 10.

**Précision chiffrée ajoutée en révision 2.0.** Sur les 40 héros du roster cible, **18 seulement portent une compétence déjà implémentée**. 15 attendent une compétence à créer, 7 une compétence exigeant du travail moteur. Moins de la moitié du roster cible est codable aujourd'hui.

Par ailleurs, le roster Ombre cible et le roster Ombre du code ne se recouvrent qu'à **2 sur 5** (`Bildat` FRANTIC et `Ssovak` RELENTLESS). Adopter le roster cible pour la démo J1 retirerait trois héros Ombre jouables pour en ajouter trois injouables. Voir l'alerte de séquencement du chantier 10.

### 1.3 Portes J0 non franchies

`03` §2.1 pose que « aucun travail d'illustration ni de contenu ne démarre avant que EX-J0-01 et EX-J0-02 soient verts ». 47 assets existent, le moteur embarqué et le prototype Electron n'existent pas.

Ce n'est pas rattrapable. La conséquence à retenir est factuelle : **les deux seuls risques capables d'invalider la stack entière sont toujours non levés, alors que l'investissement en assets est déjà consenti.**

### 1.4 Base de vérification de la révision 2.0

L'audit du 8 septembre 2026 a porté sur les fichiers suivants, lus intégralement :

**Moteur.** `Simulator`, `TickEngine`, `EventDispatcher`, `ActionProcessor`, `StatusProcessor`, `EnrageProcessor`, `SimulationContext`, `SimulationResult`, `PendingAction`, `CombatLog`, `CombatEvent`, `CombatBoard`, `CombatVestige`, `CombatHero`, `CombatItem`, `ActiveStatus`, `StatusType`.

**Application et domaine.** `GameRun`, `GameRunFactory`, `CombatBoardFactory`, `ShopFactory`, `HeroOfferGenerator`, `ScriptedOpponentFactory`, `HeroSkillDecorator`, `HeroItemAllocator`, `Inventory`, `Shop`, `WeightedDraw`, `Item`, `Effect`.

**Persistance et HTTP.** `Schema`, `GameRunRepository`, `GameRunActionsRepository`, `GameRunReplayer`, `GameRunActionApplier`, `GameRunActionType`, `GameRunRecord`, `GameRunActionRecord`, `RunController`, `Router`, `Request`, `Response`, `ApiResponse`.

**Configuration et infrastructure.** `vestiges.json`, `heroes.json`, `items.json`, `scripted_opponent.json`, les quatre `Json*Repository`, `EffectPresenter`, `RunStatePresenter`.

**Ce qui n'a pas été vérifié**, et qui conditionne une partie des décisions ouvertes :

| Point | Conséquence si l'hypothèse est fausse |
|---|---|
| `Stash::add()` à capacité pleine | `GameRun::purchaseItem()` dépense l'or et marque l'offre achetée **avant** de tenter le rangement. Si `Stash` lève, l'action entière est écartée du journal — cohérente au rejeu, mais le joueur reçoit une erreur sur un achat qu'il croyait valide |
| Mode d'erreur PDO au bootstrap | `Router` ne rattrape ni `PDOException` ni `RuntimeException`. Une violation de la clé primaire `(run_id, sequence)` produirait un 500 brut au lieu d'un 409 |
| Double clic sur `resolveRound` après commit du premier | Le second rejoue un journal à jour, applique une seconde `RESOLVE_ROUND` valide et **joue réellement deux manches**. Journal cohérent, joueur lésé. Aucune protection anti-double-clic sur `buyItem`, `swapItem`, `resolveRound` — la session 018 n'en a posé une que sur `HeroOfferPanel` |
| Faisabilité économique du chantier 9 | `ShopFactory` tire sans remise et exclut les identifiants déjà offerts : **quatre offres toujours distinctes**. Accumuler trois exemplaires d'un même objet pour fusionner en Or ne peut venir que de visites successives, sur 10 manches. À simuler avant d'écrire le chantier 9 |
| Suite de tests existante | ~248 tests PHPUnit et 65 Vitest non relus. La couverture réelle des comportements décrits en §4.2 est inconnue |

Fichiers non lus : `Stash`, `Wallet`, `ShopOffer`, `AssignedItem`, les énumérations, le bootstrap HTTP, l'ensemble des tests, le frontend.

---

## 2. Écarts documentaires à solder

À traiter avant que ces documents ne soient réutilisés comme référence.

| Document | Écart | Action |
|---|---|---|
| `Corebound_architecture_deploiement.md` | Conclut en §15 et §17 « Tauri en priorité », « décision définitive de stack ». Contredit `04` §4.1 (Electron, 02/09/2026) et `04` §11 (Tauri écarté) | **Marquer obsolète en tête de fichier**, ou fusionner ce qui reste valable dans `04` |
| `corebound-packaging-tauri-vs-electron.md` | La synthèse §8 dit encore « Tauri par défaut, à prouver en deux semaines » | Ajouter une note de tête : décision postérieure tranchée en faveur d'Electron |
| `00-INDEX` §1 | **Partiellement soldé.** `corebound-affinities.md` et le guide DA anglais y figurent désormais. `affinity_circle.png` y a été ajouté en vue dérivée non normative. Reste hors carte : `Corebound_architecture_deploiement.md`, ce qui est cohérent s'il n'est pas versé au dépôt | Ne rien faire si ce document reste hors dépôt. L'y verser exigerait de l'inscrire à la carte **et** de lui poser l'en-tête d'obsolescence ci-dessus |
| `00-INDEX` §4 | **Soldé.** D-06 et D-09 sont passées en §4.1, D-10 à D-19 figurent en §4.2. D-13 a été **périmée** le 13/09/2026 par la décision D-20, et remplacée par D-20 et D-21, toutes deux tranchées | Ne rien faire |
| `06` §6.1 et §6.2 | **Constat de la révision 2.0 faux, corrigé.** Le préfixe `docs/` et le type `docs` existent tous deux dans le dépôt — branche `origin/docs/lore-and-da`, et treize commits `docs(...)` sur `dev`. Ce qui manquait était leur **déclaration** dans `06`, pas leur existence. Défaut réel et distinct, révélé au passage : la liste « scopes en usage » de `06` §6.2 contenait `ci` et `chore`, qui sont des types | **Soldé.** `06` §6.1 déclare le préfixe et distingue clôture de session et travail documentaire autonome ; §6.2 sépare types et scopes |
| `roster-cible-40-heros.json` | Reflète l'état **antérieur** à la redistribution du chantier 6 : ni `AURIC` ni `MENDING`, `LINGERING` à 5 et `TITANIC` à 4 | Appliquer la redistribution avant de le verser au dépôt. Le renommer pour éviter la collision avec `config/heroes.json` |
| `02` §5.4 et §9 | Fusion à 3 rangs et Diamant écarté, cohérents avec la décision de session | Aucune, à jour |
| `02` §7.3 | Décrit une règle de départage que le code n'applique pas (voir §4.2, anomalie E-01) | Reporté dans `02` par la révision 2.0. Ne pas dupliquer ici |

---

## 3. Registre des décisions

### 3.1 Tranchées

| Sujet | Décision |
|---|---|
| **Échelle de fusion** | Bronze ×1 · Argent ×1,75 · Or ×2,25 **plus une ligne bonus**. Trois rangs, Diamant reste écarté |
| **Portée du multiplicateur** | Sur `value` uniquement, **jamais** sur `cooldownTicks`. Sinon Or vaut ×5 réels |
| **Plafond de fusion par rareté** | Communs jusqu'à Or · Rares jusqu'à Argent · Légendaires non fusionnables. La ligne bonus n'existe que sur les communs |
| **PV max mutable** | Des objets augmentant le plafond de PV sont au programme. Le plafond devient une valeur mutable de l'état de combat |
| **Volume d'objets (réponse à D-06)** | **180** : 40 neutres + 20 par affinité |
| **Pool de compétences (réponse à D-09)** | **20 compétences**, invariant « au plus un héros par couple (compétence, affinité) » |
| **Poison contre bouclier** | Le poison ignore le bouclier (`takeRawDamage`), la brûlure ne l'ignore pas (`takeDamage`). **Décision de design confirmée**, c'est l'identité mécanique qui sépare les deux statuts |
| **Modèle de persistance des statuts (D-20)** | **13/09/2026 — instances indépendantes.** Chaque application crée une instance portant ses stacks, ses ticks et sa source ; aucune fusion. Le régime permanent se stabilise seul à `ceil(durée / cooldown)` par source, sans qu'aucun plafond soit écrit. Écarte le monolithe actuel (discontinuité dès `cd < durée`) et le pool à décroissance (discontinuité déplacée sur `stacks > cd`, plus un rééchelonnage ×7 à ×12 des huit objets à statut). **Périme D-13.** `02` §7.4, `04` §3.3 |
| **Répartition de la brûlure** | **13/09/2026** — 150 % sur le bouclier, 70 % du surplus sur les PV, en arithmétique entière, arrondi au plancher en faveur du défenseur. Tranche la question qu'« ignore / n'ignore pas » laissait ouverte : la brûlure **atténue**, elle n'est plus annulée par 1 point de bouclier. `02` §7.4 |
| **Le soin retire des stacks (D-21)** | **13/09/2026 — oui.** Un déclenchement de `HEAL` retire 1 stack de `POISON` et 1 de `BURN`, sur l'instance à la plus longue durée restante, calculé sur le soin **tenté**. `REGEN` ne nettoie pas. Donne au soin le second rôle qui manquait face au bouclier (§4.1). Dissonance Végétal/Terre assumée et consignée. `02` §7.4 |
| **Dépendance du chantier 2 au chantier 1a** | **Retirée.** `03` rédige EX-J0-03 comme « tout snapshot porte un champ `engineVersion` », sans mention de moteur embarqué. La révision 1.0 avait ajouté « rejoué localement » et durci un critère hors mandat. Rien dans le format de snapshot ne dépend d'un binaire |

### 3.2 Ouvertes

| ID | Décision requise | Bloque |
|---|---|---|
| **D-10** | Support visuel du rang de fusion. L'aura porte la rareté, l'illustration et le cadre portent l'affinité : le rang n'a aucun support libre | Chantier 9 |
| **D-11** | Le Rare doit-il devenir qualitativement différent (seconde action ou condition) ? Sinon l'Argent à 20 or et ×1,75 domine strictement le Rare à 25 or et ×1,5 | Chantier 9 |
| **D-12** | Forme retenue pour l'économie du Doré : valeur indexée sur l'or non dépensé (recommandée), intérêt plafonné en fin de manche, ou objets qui consomment de l'or | Chantier 6 — **plus le chantier 2**, si le champ « or » y est embarqué inconditionnellement (recommandé) |
| **D-14** | **Règle de départage quand les deux plateaux meurent au même tick.** Aujourd'hui l'ordre de `getBoards()` décide, dans deux directions opposées et non documentées | Chantier 2 |
| **D-15** | **Le match nul est-il distinct du timeout ?** `SimulationResult::$winner` à `null` confond les deux. En PvE c'est résolu en défaite ; en PvP c'est un résultat de match non spécifié | Chantier 2 |
| **D-16** | **Forme du snapshot** : recette de reconstruction depuis `GameRun` (`vestigeId` + `heroIds` + `itemIdsByHero`), ou photographie d'état. `CombatBoard` stocke les objets en **liste plate** : l'association héros ↔ objet est perdue à la construction, or c'est elle qui détermine la décoration par compétence. La recette est la seule forme viable | Chantier 2 |
| **D-17** | **Nombre de tirages fixe dans `HeroOfferGenerator::buildWeightedOffer()` ?** Il tire aujourd'hui un `nextFloat()` par héros du pool disponible. Le flux RNG dépend donc de la taille de `heroes.json` | Chantier 2, puis chantier 6 |
| **D-18** | **Politique de migration**, schéma **et** contenu. `Schema::initialize()` est un `CREATE TABLE IF NOT EXISTS` sans table de version : ajouter une colonne à `runs` n'a d'autre chemin que supprimer la base. Et que fait-on d'un journal dont le `contentVersion` ne correspond plus au catalogue ? Rejet, migration, ou archivage | Chantier 2 |
| **D-19** | **Sérialisation canonique de `CombatLog` dans le Domaine.** La seule existante est `CombatEventPresenter`, en couche Présentation. Le test de parité d'EX-J0-01 en dépendrait, contre l'ordre des couches de `06`. Et `CombatEvent::$payload` étant `array<string, mixed>`, l'identité octet pour octet dépend d'un ordre de clés écrit à la main, sans test | Chantiers 2 et 1a |

---

## 4. Anomalies relevées dans le code

La révision 1.0 affirmait qu'aucune anomalie ne justifiait de chantier propre. **C'est faux depuis l'audit du 8 septembre** : les anomalies de moteur de la §4.2 conditionnent la validité de tout playtest et fondent deux chantiers nouveaux (0 et 3b).

### 4.1 Anomalies de contenu

| Anomalie | Constat | Traitée par |
|---|---|---|
| **Objets interchangeables** | Les 6 armes communes ont toutes un débit de 0,5 par tick et par slot. Idem les 3 boucliers (0,5) et les 3 soins (0,625). **Vérifié exact.** Mais la conclusion « environ 6 profils économiques » est fausse dès qu'un statut est en jeu : à slot, prix et cooldown égaux, `dagger` tue un Vestige de référence en 220 ticks et `venomous_vial` en 80, même entièrement borné | Chantier 3, puis 10 |
| **`nightfang` hors budget** | ×1,8 sur les dégâts **et** ×2,0 sur le poison, soit environ ×3,8, contre ×2,47 pour `silent_death` et ×2,5 pour `excalibur`. **Vérifié exact par recalcul** | Chantier 10 |
| **Clones d'affinité** | **Corrigé.** L'affirmation « les 8 objets `shadow` sont des copies ×1,5 » est fausse sur 4 des 8. Quatre sont des copies ×1,5 exactes (`shadow_dagger`, `shadow_longsword`, `shadow_scutum`, `shadow_cataplasm`), `shadow_venomous_vial` est à ×2 (les stacks sont entiers), et les 3 légendaires sont des objets à deux actions sans contrepartie neutre. **Le lot à réécrire fait 5 objets, pas 8.** En revanche aucun n'exprime `Critical` ni `Acceleration`, pour une raison plus simple que le clonage : ces mécaniques n'existent nulle part dans le moteur | Chantier 10 |
| **Doublons stricts** | `katana` = `shadow_longsword` (75 dégâts, cd 50, 2 mains) · `healing_potion` = `shadow_cataplasm` (38 soins, cd 40). **Vérifié exact** | Chantier 10 |
| **Aucun commun d'affinité** | Les 8 `shadow` sont 5 rares et 3 légendaires. La fusion étant réservée aux communs, elle serait morte sur tout le pool d'affinité | Chantier 10 |
| **Asymétrie soin / dégâts** | **Reformulée.** L'écart de 25 % sur les débits nominaux existe, mais il est secondaire devant une asymétrie **voulue et déjà consignée** dans `corebound-affinities` §2 : `receiveHeal()` est plafonné à `baseHp`, `gainShield()` ne l'est pas, et bouclier et PV ne se comparent pas comme une même unité. Conséquence de barème, pas défaut : à cadence et valeur égales, un objet de bouclier vaut plus qu'un objet de soin sur combat long | Chantier 10 |
| **Compétences sans objets** | `QUICKENING`, `GLACIAL`, `TITANIC`, `KEEN`, `BRUTAL` n'ont aujourd'hui **aucun objet** à modifier | Chantier 4 |
| **Densité inégale** | **Corrigé.** `SUNDERING` ne modifie pas 14 objets mais **10** : `applySundering()` est fermé aux `TWO_HAND`. `WARDEN` en modifie 1 (`shadow_armor`), `RESURGENT` 1 (`panacee`) | Chantier 10 |
| **Adversaire scripté partiellement inerte** | Sur ses trois héros, **une seule compétence agit** : `FRANTIC` sur `shadow_bearer` (cd 20 → 16). `STALWART` sur `the_bulwark` ne trouve aucun `GAIN_SHIELD` dans un `longsword`. `WARDEN` sur `shadow_bastion` ne trouve aucun statut dans un `shield`. Tout chiffre d'équilibrage relevé jusqu'ici porte sur un adversaire aux deux tiers inerte | Chantier 10 |
| **Rampe adverse non monotone** | Le remplissage glouton de `createOpponent()` fait `continue 2` vers le héros suivant dès qu'un objet ne rentre pas. Résultat : manches 5–6 → 2× dagger + **shield** ; manches 7–8 → 2× dagger + longsword, **sans bouclier**. Le budget monte, la défense descend | Chantier 10 |

### 4.2 Anomalies de moteur

| ID | Anomalie | Constat | Traitée par |
|---|---|---|---|
| **E-01** | **Biais d'ordre sur les morts simultanées** | Trois boucles itèrent `getBoards()`, joueur en premier, et cassent à la première mort. Quand la boucle inflige à l'ennemi (`TickEngine` → actions d'objets), passer en premier est un **avantage**. Quand elle inflige à soi-même (`EnrageProcessor`), c'est un **désavantage**. Aucun des deux n'est documenté. Conséquence chiffrée en `02` | Chantier 2, décision D-14 |
| **E-02** | **Statuts fusionnés par type, stacks non bornés** | `ActiveStatus::mergeWith()` fait `stacks +=` et `remainingTicks = max(...)`. `CombatVestige::applyStatus()` indexant sur le seul type, une réapplication avant expiration remet le compteur à plein et empile sans limite. **Le critère est `cooldownTicks < durationTicks`, et quatre des huit objets à statut le franchissent** : `nightfang` (10/30), `shadow_armor` (18/30), `venomous_vial` (20/30), `shadow_venomous_vial` (20/30). `firesteel` et `molotov_cocktail` ne sont bornés qu'à un tick près, et par l'ordre des phases de `Simulator::run()` — vérifié sur le code le 13/09/2026. **L'impact mesuré reste concentré sur un objet** : sur les offensifs l'emballement ne coûte que 6 ticks, `REGEN` bute sur `baseHp`, seul le `WARD` n'est saturé par rien, le bouclier étant sans plafond **par conception**. `shadow_armor` produit ≈ **7 165** de bouclier sur 500 ticks contre ≈ 1 415 borné — dont 459 de `GAIN_SHIELD` direct, donc pas du `WARD`. Un recalcul indépendant donne 7 155 / 1 405, l'écart de 10 tenant à la convention de cooldown au premier tick. **Second défaut, distinct** : deux objets appliquant le même statut fusionnent, le plus long écrasant la durée du plus court et détruisant l'intention de design des deux. **Tranché par D-20** — modèle par instances indépendantes | Chantier 3b |
| **E-03** | **Gardes de mort incomplètes** | Le commentaire d'`EnrageProcessor` affirme rétablir un double KO « structurellement impossible ». C'est faux : `StatusProcessor::processTick()` n'a **aucune garde de vie** et pulse les deux plateaux. Et `Simulator::run()` n'a **aucun contrôle entre statuts et enrage** : si l'adversaire meurt d'un poison, l'enrage s'exécute quand même, frappe le joueur en premier et peut le tuer — **une victoire devient une défaite** | Chantier 0, puis 2 |
| **E-04** | **`SUNDERING` pénalise deux objets** | `applySundering()` applique le bonus de dégâts *puis* `withCooldownTicks(×1.10)` sans vérifier qu'un bonus a été appliqué. Sur `scutum` et `shadow_scutum`, deux mains sans `DEAL_DAMAGE`, le bonus ne trouve rien mais la pénalité s'applique : **+10 % de cooldown pour zéro bénéfice** | Chantier 3b |
| **E-05** | **Flux RNG couplé au catalogue** | `buildWeightedOffer()` tire un `nextFloat()` par héros du pool : 9 à la manche 3, 8 à la manche 5 avec 10 héros. Passer à 16 héros au chantier 6 en consommera 15 et 14. **Toutes les boutiques en aval décalent, et tout journal de run existant rejoue une partie différente.** `GameRunReplayer` résout le catalogue au moment du rejeu, sans l'épingler | Chantier 2, décisions D-17 et D-18 |
| **E-06** | **Garde manquante dans `playRound()`** | `purchaseItem()` et `openShop()` refusent tant qu'une offre de héros est en attente. `playRound()` ne teste que `isOver()`. Portée réelle limitée : au tour 1, `CombatBoard::__construct()` refuse un roster vide (400) **avant toute mutation**. Le trou se limite aux tours 3 et 5, roster à 1 ou 2 héros : combat en sous-effectif, compteur avancé, puis 409. Le journal reste propre grâce à l'ordre `apply()` puis `append()`. Non atteignable par l'interface | Chantier 0 |
| **E-07** | **Champs et méthodes morts** | Trois, pas un. `Trigger` n'est lu nulle part — `dispatchForItem()` balaie tous les listeners en ignorant les clés. `Effect::intervalTicks` est sérialisé vers le frontend et n'est renseigné par aucun des 30 objets. `EventDispatcher::dispatch()` et `getListenersFor()` n'ont aucun appelant en production | Chantiers 3 et 3b |
| **E-08** | **Pas de garde anti-cascade** | `EventDispatcher` n'a aucun garde-fou. **Constat maintenu et précisé** : `dispatchForItem()` n'est appelé que par `TickEngine`, jamais en réentrance, donc inoffensif tant qu'aucun objet n'est conditionnel. Le devient au chantier 3 | Chantier 3 |
| **E-09** | **Lecture de fichier non cachée** | `JsonItemRepository::find()` et `JsonHeroRepository::getRawData()` relisent le fichier et refont un `json_decode` **à chaque appel**. `CombatBoardFactory` les appelle une fois par héros et par objet, pour les deux plateaux. Et `GameRunReplayer::replay()` s'exécute à chaque requête, y compris `show()` : à la manche 8, une simple lecture d'état rejoue sept combats. Invisible à 30 objets ; à mesurer avant de conclure que PHP est trop lent | Chantier 1a |
| **E-10** | **Rollback court-circuité dans `swapWithStash()`** | Le retrait temporaire de l'inventaire précède l'appel à `HeroItemAllocator::canAssign()`, et la restauration n'est écrite que sur le chemin `false`. Or `canAssign()` ne retourne pas `false` sur un héros hors roster : `findHero()` **lève**. Le `if` n'est jamais évalué, la restauration est sautée, et l'objet équipé disparaît de l'inventaire comme du stash. Reproduit le 14/09/2026 par test dédié : `venomous_vial` affecté à `shadow_arrow` quitte l'index 0, les suivants glissent d'un cran. **Atteignable par l'API** : `RunController::swapItem()` transmet `heroId` depuis le payload sans vérifier l'appartenance au roster. **Portée réelle nulle en revanche** : `apply()` précède `append()`, donc rien n'est journalisé, et chaque requête reconstruit le run par rejeu. La perte est confinée à un objet en mémoire jeté avec l'exception. Défaut de correction, pas d'exploit | Chantier 0 |

**Ce qui a été vérifié et se révèle sain**, et qu'il faut cesser de soupçonner :

- `run_actions` porte `PRIMARY KEY (run_id, sequence)`. Le journal est protégé contre les insertions concurrentes par verrou optimiste, sans transaction explicite.
- `RunController` respecte l'ordre `replay()` → `apply()` → `append()` dans ses quatre handlers mutants. **La règle d'intégrité numéro un est tenue.**
- `HeroSkillDecorator` préserve l'`id` du catalogue dans `withCooldownTicks()` et `withEffects()`. Un snapshot par identifiants reconstruit fidèlement — c'est ce qui rend D-16 réalisable.
- Le `match` de `decorate()` n'a pas de branche `default` et couvre exactement les 10 compétences implémentées. Ajouter un cas à `HeroSkillType` sans toucher au décorateur produit une `UnhandledMatchError`. **Filet de sécurité pour le chantier 6, à ne pas casser en ajoutant un `default`.**
- Les objets à `cooldownTicks == durationTicks` (`firesteel`, `molotov_cocktail`) sont **bornés et déterministes** : l'ordre des phases place l'expiration avant l'application, le statut est retiré puis recréé neuf. Ils restent à leurs stacks de base.
- `Shop::purchase()` valide entièrement avant de muter.
- `openShop()` n'a pas de garde `isOver()`, et n'en a pas besoin. Aucun endpoint ne l'expose : `RunController` n'appelle que `GameRunActionApplier` sur quatre types d'action, et `runApi.ts` n'offre pas d'autre route. Ses deux appelants internes testent `isOver()` avant de l'atteindre : `chooseHero()` en première ligne, `playRound()` en entrée puis par `return` anticipé avec `currentShop = null` quand la manche termine le run. Vérifié le 14/09/2026.

---

## 5. Séquence d'exécution

### 5.1 Trois règles d'ordonnancement

**R1 — Ce qui devient irréversible passe avant ce qui est seulement gros.**
Une décision qui coûte un champ aujourd'hui et un corpus demain passe avant un chantier long mais réversible.

**R2 — On ne modifie pas un comportement qu'on n'a pas d'abord figé par un test.**
Le moteur contient plusieurs biais non documentés (E-01). Les corriger sans test préalable rend le changement invisible dans le diff.

**R3 — Aucun rééquilibrage de contenu tant que le moteur ment.**
Recalibrer 30 objets sur un moteur dont les statuts fusionnent et ne sont pas bornés (E-02), dont l'enrage handicape le joueur (E-01) et dont l'adversaire est aux deux tiers inerte, produit des chiffres à jeter.

### 5.2 Ordre retenu

| Rang | Chantier | Préalable | Taille | Porte |
|---:|---|---|---|---|
| 1 | **0** — Gardes de mort et caractérisation | — | S | — |
| 2 | **3b** — Modèle de statut, décorateur, code mort | — | M | — |
| 3 | **Cadrage du chantier 2** — 6 décisions, aucun code | 0, 3b | — | — |
| 4 | **2** — Snapshot versionné et déterminisme | cadrage | M | EX-J0-03 |
| 5 | **1b** points 1 à 4 — Coquille Electron et Steam | — | M | EX-J0-02 partiel |
| 6 | **1a** — Moteur embarqué | 2 | L | EX-J0-01 |
| 7 | **3** — Déclencheurs vivants | 3b | M | — |
| 8 | suite selon §5.3 | | | |

**Changement d'ordre du 13/09/2026.** Le chantier 3b était au rang 6. Il passe au rang 2, **avant le chantier 2**, parce que D-20 fait de l'état de statut une **liste** et non un entier. Geler le format de snapshot avant ce changement imposerait une migration dès sa première version — exactement ce que le chantier 2 existe pour éviter. Sa taille passe de S à M : il ne s'agit plus d'ajouter un plafond mais de remplacer le modèle.

En parallèle et sans blocage : la **piste sans code** de la §7, et la mise à niveau documentaire de la §2.

### 5.3 Vue d'ensemble des chantiers

| # | Chantier | Préalable | Jalon | Taille |
|---|---|---|---|---|
| **0** | Gardes de mort et caractérisation du moteur | — | J0 | S |
| **1a** | Moteur embarqué `corebound-engine` | 2 | J0 | L |
| **1b** | Coquille Electron + `steamworks.js` (AppID 480) | — *(point 5 : 1a)* | J0 | M |
| **1c** | Compte partenaire Steam et page produit | statut juridique | J0 différé | S |
| **2** | Format de snapshot versionné et déterminisme | 0, **3b** | J0 | M |
| **3b** | Modèle de statut, décorateur, code mort | — | J0 | M |
| **3** | Déclencheurs vivants et garde anti-cascade | 3b | J0 | M |
| **4** | Actions manquantes du moteur | 3 | J1 | M |
| **5** | Affinité à effet mécanique | 3b, 4 | J1 | M |
| **6** | Compétences restantes | 2, 4, 5 | J2 | M |
| **7** | Échange libre héros ↔ héros | — | J1 | S |
| **8** | Boucle cible complète | — | J2 | L |
| **9** | Fusion d'objets | 3, D-10, D-11 | J2 | M |
| **10** | Contenu du palier | 3b, 3, 4, 5, 6 | J1 puis J2 | L |
| **11** | PvP asynchrone et backend | 1a, 2, 8 | J2 | L |
| **12** | Mode hors ligne et bascule | 1a, 1b, 2, 11 | J2 | M |
| **13** | Migration PostgreSQL | 11 | avant J2 | S |
| **14** | Passifs de plateau | 8 | J3 | M |

Les tailles sont **relatives entre elles**, pas des durées. Aucune conversion en semaines n'est proposée : la vélocité réelle du projet n'est pas mesurée.

**Trois changements de dépendance par rapport à la révision 1.0.**
1. Le chantier 2 ne dépend plus de 1a — c'est 1a qui dépend de 2, parce que le test de parité d'EX-J0-01 exige que le combat ne pioche pas dans le flux RNG du run.
2. Le chantier 1b n'est indépendant que sur ses points 1 à 4. Son point 5 exige le binaire produit par 1a. La révision 1.0 lui donnait un préalable « — » sans distinguer.
3. Le chantier 6 dépend désormais du chantier 2 : passer de 10 à 16 héros décale le flux RNG (E-05).

### Graphe de dépendances

```
0 ── 3b ── 2 ──┬── 1a ──┬─────────┐
               │        │         │
               │   1b(5)┘         │
               │                  │
1b(1-4) ───────┘                  │
                                  │
3b ──┬── 3 ── 4 ── 5 ── 6 ──┐     │
     │  │                   │     │
     │  └── 9               ├── 10│
     └──────────────────────┘     │
                                  │
7 (indépendant)                   │
                                  │
8 ──┬──────────────────────────── 11 ── 12
    │                                   │
    └── 14                        13 ───┘
```

---

## 6. Détail des chantiers

### Chantier 0 — Gardes de mort et caractérisation du moteur

**Objectif.** Corriger ce qui ne dépend d'aucune décision, et figer par des tests ce que le chantier 2 va délibérément changer.

**Pourquoi en premier.** Application de R2. Sans tests de caractérisation, la modification de la règle de départage au chantier 2 est un diff illisible : on ne verra pas quel comportement a changé ni dans quel sens.

**Contenu.**

1. **Tests de caractérisation.** Ils affirment le comportement **actuel**, y compris ce qui est faux. Ils seront réécrits au chantier 2.
   - Double KO par statut simultané → `winner: null` → comptabilisé en défaite par `GameRun::playRound()`
   - Priorité `playerBoard` sur mort simultanée par action d'objet
   - Priorité `opponentBoard` sur mort simultanée par enrage
   - Accumulation non bornée : `venomous_vial` et `shadow_armor`, valeurs de stacks aux ticks de référence
   - Bornage exact de `firesteel` et `molotov_cocktail` à leurs stacks de base
   - Ordre des phases dans un tick : cooldowns → statuts → enrage → actions

2. **Contrôle de vie entre statuts et enrage** dans `Simulator::run()` (E-03). **Ce n'est pas cosmétique** : sans lui, la mort de l'adversaire par poison n'empêche pas l'enrage de tuer le joueur dans le même tick, transformant une victoire en défaite.

3. **Commentaire d'`EnrageProcessor` réécrit** pour décrire l'invariant réel : garde présente dans `Simulator` (actions) et `EnrageProcessor`, absente dans `StatusProcessor`.

4. **Garde dans `playRound()`** (E-06). **Pas de garde dans `swapWithStash()`, décision assumée** : E-06 nuit parce que `playRound()` fait avancer l'état de la run (manche, compteurs, dernier résultat de combat écrasé). Un échange d'objets n'avance aucun compteur et n'offre aucun avantage de timing : le héros proposé n'est pas encore dans le roster, donc invisible pour `HeroItemAllocator`, et tout échange légal pendant l'offre l'est tout autant juste après `chooseHero()`. Dans la seule autre fenêtre où une offre est en attente, la construction, l'appel échoue à la lecture des index, avant toute mutation. **La vérification a en revanche révélé un défaut distinct dans cette méthode** (E-10), corrigé dans ce chantier.

**Ce qui n'est explicitement pas dans ce chantier.** La garde de vie dans `StatusProcessor`. Un `break` naïf y ferait mourir le joueur en premier sur toute mort simultanée par statut, c'est-à-dire implémenterait D-14 par accident, dans le sens défavorable. Elle appartient au chantier qui tranche la règle.

**Critère de sortie.** Suite verte, `check-all.ps1` vert, comportement de résolution documenté par des tests.

**Branches.** `fix/engine-death-guards`, `fix/application-play-round-guard`, `fix/application-swap-with-stash-rollback`.

---

### Chantier 2 — Snapshot versionné et déterminisme

**Pourquoi maintenant alors que le PvP est loin.** `04` §5.3 : **le point de l'architecture qui devient irrattrapable.** Le coût aujourd'hui est de quelques champs et d'une signature. Le coût après le premier commit PvP est le corpus entier.

**Périmètre élargi en révision 2.0.** La révision 1.0 le dimensionnait en S sur trois points. L'audit en a révélé six décisions supplémentaires de même nature — gratuites maintenant, coûteuses après. Le chantier passe en M.

**Étape préalable obligatoire : le cadrage.** Six décisions se tranchent **sans écrire de code**, en une session : D-14, D-15, D-16, D-17, D-18, D-19. Plus D-12 si le champ « or » est embarqué inconditionnellement, ce qui est recommandé : un champ de plus contre une décision bloquante en moins.

**Contenu, dans l'ordre TDD.**

1. `feat(persistence): add schema version table` — ou la mention écrite si D-18 retient « base jetable jusqu'à J1 ».
2. `feat(domain): add canonical CombatLog serialization` — dans `App\Domain\Engine`, ordre de clés testé (D-19).
3. `feat(domain): derive per-combat randomizer` — seedé de `seed(run)` et du numéro de manche. **Vérifié neutre** : aucun composant du moteur n'appelle `getRandomizer()`, le combat ne consomme aujourd'hui aucun aléa. Gratuit maintenant, refonte après le chantier 4.
4. `feat(domain): distinguish draw from timeout in SimulationResult` (D-15).
5. `feat(domain): apply tie-break rule to all three resolution loops` (D-14) — **réécrit les tests de caractérisation du chantier 0**. Inclut la garde de vie dans `StatusProcessor`.
6. `feat(domain): add engineVersion and contentVersion to snapshot` (D-16).
7. `feat(persistence): pin contentVersion on run records`.
8. `feat(domain): embed gold field in snapshot`.
9. `feat(application): fix draw count in weighted hero offer` (D-17).
10. `test(domain): assert byte-identical replay of a reference combat`.

**Critère de sortie.** EX-J0-03 vert. Un snapshot produit, écrit, relu, rejoué, `CombatLog` identique octet pour octet.

**Branche.** `feature/versioned-combat-snapshot`.

---

### Chantier 1b — Coquille Electron et Steam

**Objectif.** Prouver que `steamworks.js` fonctionne, que l'overlay Steam s'accroche à une fenêtre Electron, et que le sidecar `corebound-engine` est appelable depuis le processus principal.

**Coût monétaire : 0 €.** Valve fournit **Spacewar, AppID 480**, application de test publique permettant d'exercer les API Steamworks sans adhésion au programme partenaire. Le mécanisme est un fichier `steam_appid.txt` contenant `480`, placé à côté de l'exécutable. Spacewar dispose d'achievements prédéfinis utilisables pour le test.

**Contenu.**
1. Coquille Electron minimale chargeant le build Vue.
2. `steamworks.js` initialisé dans le processus principal, avec `steam_appid.txt` à 480.
3. Déverrouillage d'un achievement Spacewar.
4. Overlay Steam vérifié sur la fenêtre. **C'est le motif numéro deux du rejet de Tauri en `04` §4.1** : le valider est le cœur du chantier.
5. Appel du sidecar `corebound-engine.exe` depuis le processus principal, stdin / stdout. **Ce point exige le binaire du chantier 1a et se fait après lui.**

**Pourquoi les points 1 à 4 passent avant 1a.** M contre L, 0 € des deux côtés, et c'est le test le moins cher d'une question de niveau stack : un échec rouvre la décision de packaging prise le 2 septembre. 1a est plus gros mais plus prévisible, et dispose d'un repli documenté.

**Critère de sortie.** EX-J0-02 vert dans sa forme testable : un achievement se déverrouille et l'overlay s'affiche **depuis un build packagé**, pas seulement en développement.

**Réserves à connaître.**
- Les achievements de Spacewar sont ceux de Valve. Vous prouvez le câblage, pas votre configuration.
- Des écarts entre build de développement et build shipping sont rapportés sur ce chemin, notamment un overlay fonctionnel en standalone et absent en shipping. D'où l'exigence de tester sur build packagé.
- La licence du SDK impose d'être développeur enregistré pour un usage en production. 480 couvre le développement, pas la sortie.
- Valve rappelle de **retirer `steam_appid.txt`** avant tout envoi vers un dépôt Steam.
- À vérifier avant de planifier : si le téléchargement du SDK Steamworks est aujourd'hui derrière un compte partenaire. `steamworks.js` livre en principe des binaires précompilés, ce qui rendrait la question sans objet.

**Branche.** `feature/electron-shell`.

---

### Chantier 1a — Moteur embarqué

**Objectif.** Prouver que le moteur PHP tourne sur la machine du joueur et produit un `CombatLog` identique à celui du serveur.

**Préalable : le chantier 2.** Le test de parité exige que le combat ne pioche pas dans le flux RNG du run. Sans le randomizer dérivé, le moteur embarqué ne peut pas reproduire un combat isolé, puisqu'il ignore les tirages consommés avant lui. La révision 1.0 posait la dépendance inverse.

**Coût monétaire : 0 €.** Aucun lien avec Steam.

**Contenu.**
1. Point d'entrée CLI : lit `{ playerBoard, opponentSnapshot, seed }` sur stdin, appelle `Simulator::run()`, écrit le `CombatLog` sur stdout.
2. Empaquetage du domaine en PHAR via `box-project/box`.
3. Binaire via `static-php-cli` et `phpmicro`. Extensions minimales : `json`, `mbstring`, `random`. **Jamais le build `gigantic`**, pour l'audit de licences C-02.
4. Build matriciel Windows / Linux / macOS en CI.
5. Test de parité de déterminisme serveur contre binaire, sur un jeu de seeds fixes, bloquant.
6. Cache mémoire des repositories JSON (E-09), si la mesure le justifie.

**Critère de sortie.** EX-J0-01 vert : `echo '{...}' | corebound-engine.exe` produit un `CombatLog` strictement identique à celui du serveur pour la même seed.

**Branches.** `feature/engine-cli`, `chore/static-php-build`, `ci/determinism-parity`.

**Repli documenté.** Si le chantier coince, `04` §11 et le document de packaging listent le portage du moteur en TypeScript, avec test de parité en CI rendu possible par le déterminisme. Coût : double maintenance permanente. À décider sur une borne de temps fixée à l'avance, pas par épuisement.

---

### Chantier 1c — Compte partenaire et page produit

**Différé, sans être abandonné.** Exige 90 à 100 € et un statut juridique permettant de percevoir les revenus.

**Ce que le report coûte réellement.** `05` §7.2 classe la publication de la page Steam comme l'action de marketing au meilleur rendement, parce qu'elle teste le risque numéro quatre de `01` §9 (absence de hook énonçable en une phrase) sans écrire une ligne de code. `03` §5 conditionne le franchissement de J1 à 2 000 wishlists. **Les wishlists s'accumulent dans le temps : chaque mois sans page est un mois de collecte perdu, pas un mois neutre.**

**Conséquence de planification.** L'ouverture du statut juridique devient une **tâche datée de cette roadmap**, pas un prérequis flottant. Les 90 € représentent moins de 9 % du budget cash de `05` §4.1 (~1 010 €) et sont récupérables dès 1 000 $ de revenu brut ajusté.

**Rappel bloquant.** `03` C-01 : formulaire **W-8BEN** complété avant toute mise en vente. Son absence coûte 30 % de retenue à la source supplémentaire.

**Hors périmètre de ce document.** Le choix du statut, le régime fiscal et la forme du formulaire relèvent d'un expert-comptable ou du guichet des formalités des entreprises.

---

### Chantier 3b — Modèle de statut, décorateur, code mort

**Objectif.** Rendre le moteur honnête avant d'y verser du contenu, et **avant de figer le format de snapshot**. Application de R3.

**Préalable : aucun.** Préalable **de** 2, 3, 5 et 10.

**Contenu.**

1. **Remplacer le modèle de statut par des instances indépendantes** (E-02, décision D-20). `CombatVestige` porte une liste d'instances par type ; `ActiveStatus::mergeWith()` disparaît. Chaque instance porte ses stacks, ses ticks restants et l'identifiant de sa source. `StatusProcessor` somme les stacks vivants avant d'appliquer l'effet et **agrège avant d'émettre** : un seul `CombatEvent` par statut et par tick, charge utile inchangée.
2. **Implémenter la répartition de la brûlure** : `intdiv(stacks * 3, 2)` sur le bouclier, `intdiv(leftover * 7, 15)` du surplus sur les PV. Arithmétique entière, arrondi au plancher. Corrige le fait qu'1 point de bouclier annule aujourd'hui toute la brûlure du tick.
3. **Implémenter le nettoyage par le soin** (décision D-21) : l'action `HEAL` retire 1 stack de `POISON` et 1 de `BURN`, sur l'instance à la plus longue durée restante, sur le soin **tenté**. Exige de distinguer statuts hostiles et bénéfiques — méthode sur `StatusType`, pas de liste dispersée dans le moteur.
4. **Corriger la pénalité sèche de `SUNDERING`** (E-04) sur `scutum` et `shadow_scutum`.
5. **Retirer le code mort** (E-07) : `EventDispatcher::dispatch()`, `getListenersFor()`, `Effect::intervalTicks`.

**Critères de sortie.**

- Un test de non-régression sur `shadow_armor` : le bouclier produit sur 500 ticks est **borné et stable**, et sa valeur est consignée comme référence.
- Un test par objet à statut vérifiant que le nombre d'instances vivantes en régime permanent vaut `ceil(durationTicks / cooldownTicks)`.
- Un test de la répartition de brûlure sur les trois cas : bouclier nul, bouclier partiel, bouclier plein.
- Aucun flottant introduit. La porte de déterminisme reste verte.

**Hors périmètre, vérifié le 8 septembre 2026.** Le plafond de `gainShield()` n'est pas à trancher : son absence est une décision consignée dans `corebound-affinities` §2. Et `Shield` primaire contre `Ward` secondaire du Métal n'est pas un doublon : c'est la paire instantané / étalé, exactement comme `Heal` et `Regen` du Végétal.

**Hors périmètre, ajouté le 13/09/2026.** Le calibrage des valeurs — combien de stacks, quelles durées, quelle force de nettoyage — reste au chantier 10. Ce chantier change le modèle, pas les chiffres. Les 30 objets actuels ne sont **pas** rééchelonnés : le modèle par instances a été retenu en partie pour cette raison.

**Pourquoi il a été remonté en tête de roadmap.** La révision 2.0 le plaçait au rang 6, au motif que l'emballement ne vaut aujourd'hui qu'un objet sur trente. Ce raisonnement portait sur l'impact de jeu et reste juste. Il rate la contrainte d'ordre : D-20 fait de l'état de statut une **liste**, et le chantier 2 fige le format de snapshot. Le faire après imposerait une migration dès la version 1 du format.

**Branche.** `feature/status-instances`. *(Renommée le 13/09/2026 — `fix/status-stack-cap` décrivait un plafond qui n'est plus la solution retenue, et `fix/` ne convient pas à un changement de modèle.)*

---

### Chantier 3 — Déclencheurs vivants et garde anti-cascade

**Objectif.** Faire exister un axe de conception d'objet autre que la valeur et le cooldown.

**Justification chiffrée, corrigée.** Les 6 armes communes ont exactement le même débit par tick et par slot (0,5). Les 3 boucliers aussi (0,5), les 3 soins aussi (0,625). **Mais le pool commun n'est pas plat pour autant** : `venomous_vial` tue en 80 ticks là où `dagger` en met 220, à slot, prix et cooldown égaux, parce que le poison ignore le bouclier. La platitude est réelle sur les objets de valeur brute, pas sur ceux à statut. Et **rien dans le moteur ne lit la cadence** : `Trigger` n'est lu nulle part (E-07).

**Référence de genre.** Dans The Bazaar, la profondeur vient du fait que les objets se déclenchent les uns sur les autres. Dans Backpack Battles, du placement et de l'adjacence. Dans les deux cas, un objet parle des autres. Dans Corebound, aucun objet ne parle d'un autre : la seule couche d'interaction est la compétence de héros.

**Contenu.**
1. `Trigger` réellement lu par `TickEngine`. **Vocabulaire fermé à cinq entrées** : `EVERY_N_TICKS`, `ON_COMBAT_START`, `ON_SIBLING_TRIGGERED`, `ON_STATUS_APPLIED`, `ON_THRESHOLD`.
2. Garde anti-cascade : un objet se déclenche au plus une fois par tick, profondeur de chaîne bornée, résolution dans le tick. **Contrainte plus forte ici que chez les références**, parce que NF-01 exige un log identique octet pour octet : une récursion qui s'arrête par chance n'est pas déterministe.
3. Départage explicite de l'ordre d'activation. `dispatchForItem()` itère `$this->listeners`, tableau associatif indexé par valeur de trigger : l'ordre suit aujourd'hui la séquence d'enregistrement, pas l'ordre de déclaration dans le JSON. Latent tant qu'aucun objet n'a deux effets. **Jamais l'ordre d'itération d'un tableau associatif comme règle.**
4. Six à huit objets conditionnels ajoutés au pool Ombre. **Pas de réécriture des 30.**

**Maille d'adjacence retenue : le héros, pas une grille.** Le plateau de Corebound est déjà trois blocs de deux slots, et `02` §2.2 précise que le budget de slots est individuel et jamais mutualisé. **Le couple d'objets d'un héros est donc déjà la maille combinatoire naturelle**, sans grille et sans toucher au schéma d'interface de `02` §8.

**Écarté explicitement.** Copier la grille spatiale de Backpack Battles. Elle exigerait de refaire le plateau, alourdirait la lecture d'un snapshot PvP, et placerait le jeu sur le terrain de comparaison que `01` §6 recommande d'éviter.

**Critère de sortie.** La décision au marchand cesse d'être « quel objet a le meilleur rapport valeur sur cooldown pour mon or » et devient « lequel se branche sur ce que j'ai déjà ». Si ce n'est pas le cas, c'est **le vocabulaire de déclencheurs qui est faux, pas le principe**, et le constat aura coûté huit objets.

**Branches.** `feature/live-triggers`, `fix/dispatcher-cascade-guard`.

---

### Chantier 4 — Actions manquantes du moteur

**Justification.** Quatre des sept primaires de Vestige n'existent pas dans le moteur : `Critical` (Ombre), `Slow` (Eau), `Hp max` (Terre), plus `Acceleration` en secondaire de trois affinités. Cinq compétences du roster cible n'ont **aucun objet** à modifier.

**Contenu.** `HASTE`, `SLOW`, `MAX_HP`, et le critique comme ligne portée par chaque objet.

**Trois points à trancher avant le premier test rouge.**

1. **Plafond de PV mutable.** `HEAL` et `REGEN` se plafonnent sur le maximum courant, plus sur `base_hp`. `CombatVestige::receiveHeal()` lit aujourd'hui `$this->definition->baseHp`, sur la définition immuable : le plafond doit migrer dans l'état de combat. Ordre imposé : **relever le plafond avant de soigner**, sinon le soin est écrêté par l'ancienne valeur.
2. **Critique et déterminisme.** Le tirage vient exclusivement du `Randomizer` **dérivé par combat** au chantier 2. Toute autre source casse simultanément le replay, le PvP asynchrone, le corpus de sunset et le débogage à distance.
3. **Départage total de la ligne de vitesse.** À valeur égale, un critère de repli déterministe explicite (identifiant d'objet, puis index de slot), **jamais l'ordre d'itération d'un tableau associatif**.

**Note d'équilibrage, à consigner.** L'enrage de `02` §7.3 inflige `5 × 2^n`, cumul `5 × (2^(k+1) − 1)`. Passer de 100 à 150 PV ne change souvent **aucun palier** : les objets de PV max seront forts en combat normal et nuls en stalemate, ce qui rend le Vestige Terre structurellement faible face aux plateaux défensifs. Voir `02` pour le calcul complet du handicap.

**Branches.** `feature/haste-slow-actions`, `feature/max-hp-stat`, `feature/critical-strikes`.

---

### Chantier 5 — Affinité à effet mécanique

**Préalables : chantiers 3b et 4.** Sans `Critical`, `Slow` et `Hp max`, quatre affinités sur sept n'ont pas de quoi exprimer leur primaire. Et sans le bornage des statuts ni l'arbitrage `Shield` / `Ward`, le Métal ne peut pas être écrit.

**Contenu.** Système à quatre relations de `02` §5.2, appliqué à la compétence du héros et à ses objets, selon la table de distance de `corebound-affinities` §1.

**Quel triplet pour le prototype.** `02` §5.2 recommande deux affinités, jamais sept. Avec trois Vestiges à J2, un triplet expose les quatre relations d'un coup :

> **Ombre, Feu, Métal.**

| Paire | Distance | Relation |
|---|---:|---|
| Ombre ↔ Ombre | 0 | Identique |
| Ombre ↔ Feu | 1 | Alliés |
| Feu ↔ Métal | 2 | Neutres |
| Ombre ↔ Métal | 3 | Ennemis |

**Vérifié le 8 septembre 2026** sur la table de `corebound-affinities` §1 : les alliés de l'Ombre sont Feu et Terre, ses ennemis Métal et Eau ; les neutres du Feu sont Terre et Métal. Bonus : les trois profils de jeu sont distincts (tempo, dégâts sur la durée, défense).

**Porte de sortie, non négociable.** `03` §2.4 : si le prototype démontre que l'affinité n'est qu'un multiplicateur de puissance sans effet sur les builds, **le système est repensé, pas étendu**.

**Ce que ce chantier débloque.** La contrainte des 20 héros de `03` §2.3, et la légitimité du doublon `SAVAGE`.

**Branche.** `feature/affinity-relations`.

---

### Chantier 6 — Compétences restantes

**Préalable ajouté : le chantier 2.** Passer de 10 à 16 héros change le nombre de tirages consommés par `buildWeightedOffer` et décale toutes les boutiques en aval (E-05). Sans `contentVersion` ni tirage fixe, tout journal de run existant devient irrejouable.

**Pool cible : 20 compétences.** Dix existent, dix restent à créer, plus deux ajouts issus de la session.

| Compétence | Effet | Statut |
|---|---|---|
| `SAVAGE` | +% valeur `DEAL_DAMAGE` | existe |
| `SEARING` | +1 stack `BURN` | existe |
| `VIRULENT` | +1 stack `POISON` | existe |
| `VITALIC` | +% valeur `HEAL` | existe |
| `RESURGENT` | +1 stack `REGEN` | existe |
| `STALWART` | +% valeur `GAIN_SHIELD` | existe |
| `WARDEN` | +1 stack `WARD` | existe |
| `FRANTIC` | −20 % cooldown, `ONE_HAND` | existe |
| `SUNDERING` | hybride dégâts / cadence, **`TWO_HAND` uniquement** | existe |
| `RELENTLESS` | hybride, chargement complet `ONE_HAND` | existe |
| `TITANIC` | +% sur les gains de PV max | à créer |
| `QUICKENING` | +% sur les effets d'accélération | à créer |
| `GLACIAL` | +% sur les effets de ralentissement | à créer |
| `HULKING` | bonus réservé aux `TWO_HAND` | à créer |
| `LINGERING` | +% durée sur tous les statuts appliqués | à créer |
| `KEEN` | +% de chance de critique | à créer, moteur |
| `BRUTAL` | +% de dégâts critiques | à créer, moteur |
| `OPENING` | première activation de chaque objet doublée | à créer, moteur |
| **`AURIC`** | +% de valeur par tranche d'or non dépensé au début du combat | à créer, **nouveau** |
| **`MENDING`** | +% valeur et −% cooldown sur les objets `HEAL` | à créer, **nouveau** |

**Point de vigilance ajouté.** `HULKING` et `SUNDERING` sont toutes deux réservées aux `TWO_HAND`, sur un pool qui n'en compte que 10 sur 30. Vérifier qu'elles ne deviennent pas la même compétence, comme `Shield` et `Ward` le sont devenus.

**Pourquoi `AURIC`.** Aucune des 18 compétences initiales ne touchait à l'économie, laissant le Doré sans signature de combat, avec 1 héros aligné sur 5 contre 5 sur 5 pour l'Ombre. `AURIC` reste un filtre appliqué à l'assemblage du plateau, donc `GAIN_GOLD` demeure écartée : rien n'est gagné en combat, l'or est un paramètre d'entrée.

**Pourquoi `MENDING`.** Le pool initial comptait environ dix compétences offensives contre cinq défensives. Les affinités défensives puisaient dans un vivier deux fois plus petit et tombaient sur les compétences joker.

**Redondance à résoudre dans ce chantier.** `SAVAGE` (+20 % dégâts), `SUNDERING` (≈ +22,7 % DPS) et `RELENTLESS` (≈ +22,2 % DPS) sont trois compétences de DPS quasi équivalentes. En garder deux au maximum, différenciées par l'endroit où le gain se pose.

**Redistribution actée sur le roster cible.**

| Héros | Affinité | Avant | Après | Motif |
|---|---|---|---|---|
| Vardun | gold | `LINGERING` | `AURIC` | donne son primaire au Doré |
| Vessarin | water | `TITANIC` | `QUICKENING` | couple libre, secondaire de l'Eau |
| Anhe | vegetal | `TITANIC` | `MENDING` | la case soin manquante existe |
| Palo | vegetal | `OPENING` | inchangé | héros hors thème assumé |

Après quoi : `LINGERING` retombe de 5 à 4 occurrences, `TITANIC` de 4 à 2, amplitude 1 à 4 au lieu de 1 à 5. **Cette redistribution n'est pas appliquée au fichier de roster** (voir §2).

**Point ouvert restant.** L'Ombre a 5 héros sur 5 alignés sur son primaire ou son secondaire et **aucun héros hors thème**. Chaque affinité en a besoin d'un ou deux, sinon les sept Vestiges deviennent sept archétypes fermés.

**Test d'invariant.** NF-17 : au plus un héros par couple (compétence, affinité), vérifié automatiquement sur `heroes.json`. Le roster cible le respecte, vérifié le 8 septembre 2026.

**Filet existant à préserver.** Le `match` de `HeroSkillDecorator::decorate()` n'a pas de branche `default`. Ajouter une compétence à `HeroSkillType` sans la décorer produit une erreur immédiate. **Ne pas ajouter de `default`.**

**Branche.** `feature/hero-skills-batch-2`.

---

### Chantier 7 — Échange libre héros ↔ héros

Petit, indépendant, à placer entre deux gros chantiers. `02` §4.2 le qualifie explicitement : « ce n'est pas une restriction de design, c'est un manque ». Seul `swapWithStash` existe.

**Branche.** `feature/free-item-swap`.

---

### Chantier 8 — Boucle cible complète

**Objectif.** Passer de la boucle actuelle (une boutique, un combat PvE scripté) à celle de `02` §3.2.

**Contenu, dans l'ordre des dépendances.**
1. Entité Monstre : thématique, difficulté, récompense en or, récompense secondaire.
2. Choix parmi trois monstres, faible / moyenne / difficile.
3. Récompenses PvE. **Défaite PvE : rien** (D-02 tranchée). `startingIncome` reste le seul filet anti-spirale.
4. Seconde phase de marchand.
5. Choix parmi trois marchands spécialisés.
6. Choix du Vestige parmi trois au démarrage.

**Décision à instruire pendant ce chantier.** **D-03**, durée d'une manche et d'une run. `02` §3.5 vise 2 à 3 minutes par manche et 25 à 35 minutes par run, contre la fenêtre de remboursement Steam de 2 heures.

**Point de surveillance en playtest.** Si le monstre facile est choisi dans plus de 70 % des manches, l'écart de récompense entre difficultés est trop faible. Si le difficile est choisi presque toujours, c'est l'inverse.

**Protection à ajouter au passage.** Aucun garde anti-double-soumission n'existe sur `buyItem`, `swapItem` ni `resolveRound`. Un second clic parvenu après le commit du premier rejoue un journal à jour et **joue réellement une manche de plus** (§1.4).

**Branches.** `feature/monster-entity`, `feature/monster-choice`, `feature/pve-rewards`, `feature/second-shop-phase`, `feature/vestige-choice`.

---

### Chantier 9 — Fusion d'objets

**Préalable : le chantier 3.** La ligne bonus du dernier rang est par définition conditionnelle. Elle n'est pas descriptible sans vocabulaire de déclencheurs.

**Préalables de décision : D-10 et D-11.**

**Vérification à faire avant d'écrire quoi que ce soit.** `ShopFactory` tire sans remise et exclut du dernier slot les identifiants déjà offerts : **les quatre offres d'une visite sont toujours distinctes**. Accumuler trois exemplaires d'un même objet ne peut donc venir que de visites successives, sur 10 manches et 4 offres. La faisabilité économique de la fusion n'est pas acquise et doit être simulée.

**Note.** `MAX_LEGENDARY_OFFERS = 1` est un nom trompeur : le quatrième slot tire dans le catalogue complet privé des trois premiers, sans filtre de rareté. Il peut produire un commun.

**Contenu.**
1. Trois rangs : Bronze ×1, Argent ×1,75, Or ×2,25 plus ligne bonus.
2. Multiplicateur sur `value` uniquement.
3. Plafond par rareté : communs jusqu'à Or, rares jusqu'à Argent, légendaires non fusionnables.
4. Lignes bonus rédigées pour les 81 objets communs de la cible, et pour eux seuls.

**Pourquoi le plafond par rareté.** Le modificateur de drop des légendaires est de ×0,015 : trouver deux légendaires identiques dans une run est négligeable, quatre est hors d'atteinte.

**Pourquoi D-11 est bloquante.** Un Argent coûte 20 or pour ×1,75, un Rare coûte 25 or pour ×1,5, à slot égal. **L'Argent domine strictement le Rare.** Piste : le Rare gagne une seconde action ou une condition, comme le sont déjà les légendaires (les 5 légendaires ont deux actions, les 11 rares une seule).

**Branche.** `feature/item-fusion`.

---

### Chantier 10 — Contenu du palier

**Préalable ajouté : le chantier 3b.** Application de R3.

**Ce chantier ne peut pas partir de l'analyse de la révision 1.0.** Trois de ses prémisses sont fausses ou incomplètes : le nombre d'objets Ombre à réécrire (5 et non 8), la portée de `SUNDERING` (10 et non 14), et l'origine de l'enrage. Voir §4.

**Deux préalables de mesure.**
1. **Remettre à plat l'adversaire scripté.** Deux de ses trois compétences sont inertes et sa défense recule à la manche 7. Recalibrer 30 objets contre lui produirait des chiffres à jeter.
2. **Rejouer un combat de référence après le chantier 3b** et mesurer l'écart de durée. C'est la mesure qui dira si l'enrage reste nécessaire sous sa forme actuelle.

**Règle de volume retenue.** 40 objets neutres + 20 par affinité.

| | Vérifié 08/09 | J1 | J2 | J3 | J4 |
|---|---:|---:|---:|---:|---:|
| Vestiges | 1 | 1 | 3 | 5 | 7 |
| Héros au code | 10 | 8 min. | 16 | 26 | 40 |
| Objets neutres | 22 | 30 | 40 | 40 | 40 |
| Objets par affinité | 8 | 20 | 20 × 3 | 20 × 5 | 20 × 7 |
| **Total objets** | **30** | **50** | **100** | **140** | **180** |

7 + 40 + 180 = **227 assets**, exactement le chiffre budgété en `01` §9 et `05` §4.

**Répartition par rareté cible**, en conservant le ratio actuel (14/11/5) :

| | Communs | Rares | Légendaires | Total |
|---|---:|---:|---:|---:|
| Pool neutre | 18 | 15 | 7 | 40 |
| Chaque affinité | 9 | 7 | 4 | 20 |
| **Total 1.0** | **81** | **64** | **35** | **180** |

**Rôle des deux pools.** Le neutre est vu dans 7 runs sur 7 : il couvre les dix familles d'effet, 3 à 4 objets chacune, et reste volontairement plat. C'est lui qui garantit qu'un Vestige Végétal peut construire un plateau offensif. Les 20 objets d'affinité portent l'identité. Environ 8 sur le primaire et le secondaire, 12 ailleurs, conformément à `corebound-affinities` §1.

**Corrections à appliquer au pool existant avant tout ajout.**
1. Chaque affinité descend à 9 communs. Aujourd'hui aucun objet d'affinité n'est commun, ce qui rendrait la fusion morte sur la moitié du pool visible.
2. **Les 5 objets `shadow` rares** sont réécrits pour exprimer `Critical` et `Acceleration`, et cessent d'être des copies numériques. Les deux doublons stricts disparaissent. Les 3 légendaires Ombre relèvent d'un recalibrage, pas d'une réécriture.
3. `nightfang` est recalibré à ×2,5 comme ses pairs.
4. Le calibrage du poison est repris : un commun à statut ne peut pas tuer 2,75 fois plus vite qu'un commun de dégâts à slot et prix égaux.

**Composition d'effets à viser.** Environ 50 à 55 % d'objets à composante offensive, 30 % défensive, 15 % de tempo.

**Séquencement des héros.** Le code passe de 10 à 16, **pas à 40**. Le roster de 40 reste un document de conception et les assets attendent.

> **Alerte de séquencement pour la démo, aggravée en révision 2.0.** Le Vestige de J1 est l'Ombre. Trois de ses cinq héros cibles (Zasuk `BRUTAL`, Ysenn `KEEN`, Hakkeb `QUICKENING`) portent des compétences non implémentées, et le roster Ombre cible ne recouvre celui du code qu'à 2 sur 5. Adopter le roster cible pour la démo retirerait trois héros jouables pour en ajouter trois injouables. Soit les 8 héros de J1 sont complétés hors Ombre, soit le chantier 4 passe avant le contenu.

**Branches.** `feature/items-batch-NN`, `feature/heroes-batch-NN`, `feature/vestiges-fire-metal`.

---

### Chantier 11 — PvP asynchrone et backend

**Préalables : 1a, 2 et 8.** Le format de snapshot doit exister avant le premier commit PvP. L'appariement se faisant par numéro de manche, la boucle cible doit être stable.

**Contenu.**
1. Publication du snapshot de plateau en fin de manche.
2. Endpoint d'appariement, strictement par numéro de manche.
3. Simulation serveur, retour du `CombatLog`.
4. Amorçage par adversaires d'archive via `ScriptedOpponentFactory`.
5. VPS Hetzner, palier alpha à ~6 € par mois (`04` §9.2).

**Décision à trancher avant la mise en ligne.** **D-05** : les adversaires de secours sont-ils annoncés comme tels ? `02` §6.4 recommande la transparence, avec un libellé neutre du type « adversaire d'archive ».

**Ce que D-14 et D-15 conditionnent ici.** Sans règle de départage explicite, le même appariement produit deux vainqueurs différents selon lequel des deux plateaux est passé en `playerBoard`. Sans distinction `draw` / `timeout`, un double KO est un résultat de match non spécifié. Ces deux points sont tranchés au chantier 2, pas ici.

**Règle d'intégrité rappelée.** Une action n'est journalisée qu'**après validation réussie**. Vérifié tenu dans `RunController` le 8 septembre 2026.

**Branches.** `feature/board-snapshot-publish`, `feature/pvp-matchmaking`, `feature/pvp-combat`.

---

### Chantier 12 — Mode hors ligne et bascule

`03` NF-10 exige une bascule manuelle testable **dès J2**, pas au moment du sunset. C'est aussi le différenciateur numéro deux de `01` §5.

**Contenu.**
1. Bascule dans le client : appel du sidecar `corebound-engine` à la place de l'API.
2. Corpus embarqué comme ressource du build, **jamais comme téléchargement externe** (NF-11).
3. Test de bout en bout sur poste vierge.

**Cible de corpus.** ≥ 5 000 snapshots répartis par manche et par palier de puissance, soit ~25 Mo embarqués. **La génération de ce corpus est le moment où E-09 devient mesurable.**

**Branche.** `feature/offline-mode`.

---

### Chantier 13 — Migration PostgreSQL

`04` §6.2 : **avant J2, jamais pendant.** Le journal d'actions étant un format de données simple et non lié aux classes PHP, la migration est mécanique.

Sauvegardes quotidiennes hors site et **restauration testée au moins une fois** (NF-22).

**Branche.** `chore/postgresql-migration`.

---

### Chantier 14 — Passifs de plateau

**Ce chantier commence par une décision de design, pas par du code.** `02` §2.5 : aucune mécanique n'est spécifiée.

Dépend du chantier 8, puisque les passifs arrivent par récompense de monstre ou par marchand rare. Cible J3 : 10 à 15 passifs. Cible J4 : 30 à 40.

---

## 7. Piste parallèle sans code

Aucune de ces tâches ne dépend d'un chantier.

| Tâche | Référence | Bloque |
|---|---|---|
| Audit de licences (npm, Electron, extensions PHP de `micro.sfx`) | EX-J0-05, C-02 | La distribution Steam |
| Prompts d'assets figés et versionnés par catégorie | EX-J0-06 | La production de volume |
| Ouverture du statut juridique | — | Chantier 1c |
| Formulaire W-8BEN | C-01 | Toute mise en vente |
| Certificat de signature de code (~300 €) | C-03 | Évite l'avertissement SmartScreen au premier lancement |
| Politique de confidentialité | C-04 | Exigée par Valve |
| Solder les écarts documentaires de la §2 | §2 | Rien, mais évite une erreur future |

---

## 8. Points irréversibles

Cinq, contre trois en révision 1.0. Tout le reste se corrige.

1. **`engineVersion` dans chaque snapshot, dès le premier commit PvP.** L'ajouter après coup invalide le corpus déjà produit.
2. **`contentVersion` dans le snapshot et dans l'enregistrement de run.** `engineVersion` seul ne suffit pas : le moteur peut être identique et le catalogue différent. `GameRunReplayer` résout le catalogue au moment du rejeu, et le chantier **6** suffit à casser tout journal existant, avant même le chantier 10.
3. **Randomizer dérivé par combat**, seedé de `seed(run)` et du numéro de manche. Vérifié neutre aujourd'hui, puisque le combat ne consomme aucun aléa. Refonte complète après le chantier 4, corpus perdu après le chantier 11.
4. **Le champ « or » dans le snapshot**, si D-12 retient la piste économique indexée. Recommandation : l'embarquer inconditionnellement pour sortir D-12 du chemin critique.
5. **Journaliser une action seulement après validation réussie.** Vérifié tenu au 8 septembre 2026.

**Quasi-irréversible.** La règle de départage sur mort simultanée (D-14). La changer après production du corpus rejoue tous les snapshots avec des `CombatLog` différents, ce qui viole NF-01 et invalide le corpus.

---

## 9. Ce qui n'est pas dans cette roadmap, et pourquoi

| Élément | Motif |
|---|---|
| **Rang Diamant de fusion** | Écarté au GDD §9, confirmé par l'échelle à trois rangs |
| **Les quatre affinités restantes** | Arrivent à J3, après la porte de sortie du chantier 5. Les livrer avant reviendrait à généraliser un système non validé, ce que `03` §2.4 interdit |
| **Recalibrage de l'enrage** | Ne peut se faire que par playtest sur la boucle cible. Pertinent après le chantier 8. **Mais l'écart entre la règle du GDD et la règle implémentée est consigné dans `02` dès maintenant**, parce que ce n'est pas un problème d'équilibrage mais de conformité |
| **Fragmentation de budget de `HeroItemAllocator`** | Dette sans conséquence tant que les héros ont 2 slots et que le catalogue est petit. À revisiter si le chantier 10 la rend visible |
| **Grille spatiale à la Backpack Battles** | Écartée : refonte du plateau, snapshot plus lourd, terrain de comparaison à éviter selon `01` §6 |
| **Steam Deck** | À évaluer après J4 (`04` §4.4) |
| **Portage mobile, console, multijoueur synchrone, classement, social, modding** | Hors périmètre jusqu'à décision explicite (`03` §6) |

---

## 10. Rappel de méthode

Chaque chantier respecte `06` :

- Architecture validée par questions explicites **avant le premier test rouge**.
- Rouge **constaté par exécution réelle**, jamais supposé.
- Ordre par dépendance technique : Domain → Application → Infrastructure → Persistence → Presentation → Http → Frontend.
- Une brique logique par commit, message conventionnel avec scope.
- `check-all.ps1` vert de bout en bout avant commit.
- Contenu réel des fichiers demandé avant toute modification. **C'est la règle la plus souvent violée et la plus coûteuse.**
- Commits multilignes par le panneau Source Control de VSCode, jamais par le terminal.

**Leçon spécifique de l'audit du 8 septembre 2026.** Deux affirmations de la révision 1.0 étaient fausses non pas parce que les données avaient été mal lues, mais parce que **le code qui consomme ces données n'avait pas été lu**. « `SUNDERING` modifie 14 objets » comptait correctement les objets à `DEAL_DAMAGE` sans voir que le décorateur les filtre sur `TWO_HAND`. « Les 8 objets `shadow` sont des copies ×1,5 » généralisait un motif vrai sur 4 entrées.

> Vérifier une donnée ne suffit pas. Il faut lire le code qui la consomme.