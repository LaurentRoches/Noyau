# 07 — Roadmap des chantiers

**Autorité sur :** l'ordre des chantiers, leurs préalables, leurs critères de sortie.
**Révision :** 3.1 — 20 septembre 2026.
**Rythme de mise à jour :** à chaque audit ou replanification. **Jamais à chaque session** — les états de code datés appartiennent aux résumés de session.

**Rappel d'autorité (`00-INDEX` §2) :** le code réel prime sur ce document. Les états chiffrés de la §1 ont été relevés directement dans les fichiers le 7 septembre 2026, re-vérifiés le 8 septembre 2026 sur un périmètre élargi (§1.4), et complétés le 19 septembre 2026 par la lecture de cadrage (§1.5). Toute divergence constatée ultérieurement invalide la section concernée, pas le code.

**Ce document ne remplace pas `03`.** Le cahier des charges reste l'autorité sur le périmètre par jalon. Cette roadmap dit **dans quel ordre** les chantiers s'exécutent et **ce qui bloque quoi**.

**Ce document ne remplace pas `02`.** Les écarts entre une règle décrite au GDD et la règle réellement implémentée sont reportés dans `02`, pas ici. Cette roadmap ne consigne un fait de moteur que lorsqu'il conditionne un ordre de travaux.

**Révision 2.0 — ce qui avait changé.** Un audit de 30 fichiers de code et de configuration a été mené le 8 septembre 2026. Il a invalidé sept affirmations de la révision 1.0, révélé cinq anomalies de moteur non détectées, et fait passer les points irréversibles de trois à cinq.

**Révision 3.0 — ce qui change.** La session de cadrage du 19 septembre 2026 a tranché **les six décisions du chantier 2**, plus une septième qu'aucune révision antérieure n'avait identifiée. Elle a invalidé trois affirmations de décision de la révision 2.0 — la formulation de D-12, l'argument qui fondait D-16, et le constat « sans test » de D-19 —, corrigé deux constats d'anomalie (E-01 et E-05), et révélé deux anomalies nouvelles, dont une qui conditionne la rejouabilité de tout journal de run. Les points irréversibles passent de cinq à neuf. Le chantier 2 passe de dix à **douze commits** : un retiré, un fusionné, quatre ajoutés.

**Révision 3.1 — ce qui change.** Aucune décision nouvelle : une replanification et deux constats d'état relevés dans le code. Les commits 3 et 4 du chantier 2 **fusionnent**, parce que les séparer laissait un état intermédiaire non compilable que `06` §1.6 interdit — le chantier retombe à **onze commits**, et la suite est renumérotée. Et le **chantier 0 n'est pas terminé**, contrairement à ce que son absence de rature en §5.2 laissait supposer aussi bien que l'aurait fait une rature : ses quatre points de code sont faits, sa caractérisation ne l'est pas. **E-06 est résorbée et E-03 l'est à moitié** — sa moitié `Simulator` seulement, sa moitié `StatusProcessor` restant au commit 7 du chantier 2 par décision et non par oubli ; leurs lignes en §4.2 le disent désormais.

> **Le constat sur le chantier 0 corrige une affirmation de séance, pas le document.** Cette révision avait été ouverte pour rayer le chantier 0 comme terminé. La vérification dans `SimulatorTest` a montré l'inverse : deux puces de caractérisation manquent, dont **celle que la révision 3.0 avait précisément ajoutée** parce que la direction de l'enrage n'était figée qu'en isolation. Rayer le rang 1 aurait effacé un préalable du commit 7 du chantier 2. Voir §6, chantier 0.

> **Ce que cette révision n'ajoute pas, délibérément : aucun marqueur d'avancement sur la liste de commits du chantier 2.** L'en-tête pose que ce document ne se met pas à jour à chaque session, et cocher des commits au fil de l'eau est exactement cela. L'état d'avancement appartient au résumé de session ; la liste de commits dit l'ordre et le contenu, pas l'avancement.
>
> **Le registre d'anomalies de la §4.2 obéit à une autre règle, et c'est voulu.** Une anomalie n'est pas une étape de plan : c'est un état du code, et une ligne qui décrit un défaut corrigé est fausse. E-02, E-04 et E-10 portent déjà leur date de résorption. E-06 et E-13 la portent à leur tour, E-03 pour sa moitié. La mention « commit 4 — fait » qui suit E-13 nomme **où** la correction a eu lieu, pas où en est le chantier.

> **Le cadrage s'est fait sur pièces.** Vingt-huit fichiers de code et de test ont été lus pendant la session : moteur de combat, plateau, fabriques, décorateur, persistance et journal. Deux conclusions de la révision 2.0 sont tombées parce que **le code qui consomme la donnée avait enfin été lu** — la même cause que les deux erreurs de la révision 1.0 relevées en §10.

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

**Ce qui n'avait pas été vérifié**, et qui conditionnait une partie des décisions ouvertes :

| Point | Conséquence si l'hypothèse est fausse | Statut au 19/09/2026 |
|---|---|---|
| `Stash::add()` à capacité pleine | `GameRun::purchaseItem()` dépense l'or et marque l'offre achetée **avant** de tenter le rangement. Si `Stash` lève, l'action entière est écartée du journal — cohérente au rejeu, mais le joueur reçoit une erreur sur un achat qu'il croyait valide | **Toujours non vérifié.** `Stash` n'a pas été lu |
| Mode d'erreur PDO au bootstrap | `Router` ne rattrape ni `PDOException` ni `RuntimeException`. Une violation de la clé primaire `(run_id, sequence)` produirait un 500 brut au lieu d'un 409 | **Toujours non vérifié** |
| Double clic sur `resolveRound` après commit du premier | Le second rejoue un journal à jour, applique une seconde `RESOLVE_ROUND` valide et **joue réellement deux manches**. Journal cohérent, joueur lésé | **Toujours non vérifié.** Aggravé par E-11 : au rejeu, la manche rejouée peut de surcroît changer d'issue |
| Faisabilité économique du chantier 9 | `ShopFactory` tire sans remise et exclut les identifiants déjà offerts : **quatre offres toujours distinctes**. Accumuler trois exemplaires d'un même objet pour fusionner en Or ne peut venir que de visites successives, sur 10 manches | **Confirmé sur le code** le 19/09/2026 : `ShopFactory::drawItems()` passe par `Randomizer::pickArrayKeys()`, sans remise, et le dernier slot exclut les identifiants déjà tirés. La simulation reste à faire |
| Suite de tests existante | La révision 2.0 l'estimait à « ~248 tests PHPUnit et 65 Vitest non relus ». **Chiffres relevés le 19/09/2026 : 294 tests PHPUnit / 1 122 assertions, et 72 tests Vitest sur 10 fichiers**, tous verts. L'estimation était donc basse de 19 % côté backend et de 11 % côté frontend | **Partiellement levé** — une partie a été lue au cadrage (§1.5), le reste des tests de moteur pendant le chantier 2. **Reste les 72 tests Vitest**, non relus |

### 1.5 Base de vérification de la révision 3.0

*(ajouté le 19/09/2026)* La session de cadrage a lu intégralement, code **et** tests :

**Moteur et résolution.** `Simulator`, `TickEngine`, `StatusProcessor`, `EnrageProcessor`, `SimulationResult`, `CombatBoard`, `CombatVestige`, `CombatLog`, `CombatEvent`, `EventType`, `CombatEventPresenter`, et les tests correspondants (`SimulatorTest`, `TickEngineTest`, `StatusProcessorTest`, `EnrageProcessorTest`, `CombatBoardTest`, `CombatVestigeTest`, `CombatLogTest`, `CombatEventTest`, `CombatEventPresenterTest`).

**Assemblage du plateau.** `CombatBoardFactory`, `ScriptedOpponentFactory`, `HeroSkillDecorator`, et leurs tests.

**Run et flux aléatoire.** `GameRun`, `GameRunFactory`, `HeroOfferGenerator`, `ShopFactory`, et leurs tests.

**Persistance.** `Schema`, `GameRunRepository`, `GameRunActionsRepository`, `GameRunReplayer`, et leurs tests.

**Ce qui reste non lu, et qui pèse sur des décisions encore ouvertes :**

| Fichier | Ce qu'il conditionne |
|---|---|
| `items.json` | L'ampleur réelle de E-12 : quelles valeurs du catalogue tombent sur une divergence d'arrondi |
| ~~`ActionProcessor` et son test~~ — **lu le 20/09/2026** | ~~La couverture de l'ordre des clés pour `DAMAGE_DEALT`, `HEAL_RECEIVED`, `SHIELD_GAINED`~~ **Couverture acquise** : `ActionProcessorTest` fige par `assertSame` sur le tableau entier les charges utiles de `DAMAGE_DEALT`, `SHIELD_GAINED`, `HEAL_RECEIVED` **et** `STATUS_APPLIED`. D-19 n'avait donc aucun angle mort de ce côté |
| `Stash`, `Wallet`, `ShopOffer`, `AssignedItem` | Les trois réserves non levées de la §1.4 |
| ~~`RunController`, `Router`, bootstrap HTTP~~ — **lus les 19 et 20/09/2026** | ~~La forme exacte de la garde anti-issue-cliente de D-18~~ **Garde acquise par construction** : `RunController::resolveRound()` journalise une charge utile `[]` **en dur** et ne lit jamais son `Request`. La lecture a par ailleurs produit E-13 |
| Frontend, **72** tests Vitest sur 10 fichiers | Le lecteur de rejeu, touché par le passage aux libellés `A`/`B` (commit 5). **Seule entrée de cette table encore entière**, et elle est le préalable direct du prochain commit de code |

---

## 2. Écarts documentaires à solder

À traiter avant que ces documents ne soient réutilisés comme référence.

| Document | Écart | Action |
|---|---|---|
| `Corebound_architecture_deploiement.md` | Conclut en §15 et §17 « Tauri en priorité », « décision définitive de stack ». Contredit `04` §4.1 (Electron, 02/09/2026) et `04` §11 (Tauri écarté) | **Marquer obsolète en tête de fichier**, ou fusionner ce qui reste valable dans `04` |
| `corebound-packaging-tauri-vs-electron.md` | La synthèse §8 dit encore « Tauri par défaut, à prouver en deux semaines » | Ajouter une note de tête : décision postérieure tranchée en faveur d'Electron |
| `00-INDEX` §1 | **Partiellement soldé.** `corebound-affinities.md` et le guide DA anglais y figurent désormais. `affinity_circle.png` y a été ajouté en vue dérivée non normative. Reste hors carte : `Corebound_architecture_deploiement.md`, ce qui est cohérent s'il n'est pas versé au dépôt | Ne rien faire si ce document reste hors dépôt. L'y verser exigerait de l'inscrire à la carte **et** de lui poser l'en-tête d'obsolescence ci-dessus |
| `00-INDEX` §4 | **À resolder en révision 3.0.** Les six décisions du chantier 2 passent en §4.1, et D-22 y est créée. Restent ouvertes : D-03, D-04, D-05, D-10, D-11, D-12 | Appliqué dans `00` révision 2.1, même branche |
| `04` §5.3 et §3.3 | **Écart ouvert et soldé le 19/09/2026.** `04` décrivait le snapshot sans trancher sa forme, une signature de `Simulator::run()` que D-22 change, et une politique de migration « à définir avant J2 » que D-18 tranche | **Soldé.** `04` passe en révision 2.0, même branche. Deux défauts distincts relevés au passage : son en-tête datait du 2 septembre alors que son corps portait des décisions du 14, et il typait `SimulationResult::$winner` en `?CombatHero` — erreur que `02` avait corrigée onze jours plus tôt dans sa propre copie |
| `06` §3, §4.4, §8 et §10 | **Écart ouvert et soldé le 19/09/2026.** Quatre règles d'intégrité, deux règles de conception et trois pièges d'outillage issus du cadrage n'étaient consignés nulle part | **Soldé.** `06` passe en révision 2.1. Ambiguïté de numérotation levée au passage : son en-tête disait 1.0 pendant que son corps citait une « révision 2.0 », sans qu'on puisse savoir s'il s'agissait de la sienne ou de celle du corpus |
| `04` §7 et §6.3 | **Écart ouvert et soldé le 20/09/2026.** Quatre commits du chantier 2 étaient écrits, et `04` les décrivait encore au futur : « seed : paramètre optionnel de `RunController::create()` » était devenu vrai sans dire que la source est le corps JSON ni que le rejet est strict, et la table `schema_version` était décrite comme un projet | **Soldé.** `04` passe en révision 2.1, avec le détail des trois états de base, le choix du 503 hors `Router` et l'extension de `RuntimeException`. Une affirmation de 2.0 y est **retirée** : « le calcul et la dérivation sont deux commits distincts » |
| `06` §4.3 | **Faux constat, corrigé le 20/09/2026.** L'un des deux « trous de couverture connus » — l'ordre des clés de `ActionProcessor` — **n'existait pas** : il avait été déclaré inconnu sans que le fichier de test soit ouvert, puis recopié de révision en révision. Le même faux constat vivait en `07` §1.4 et §1.5 | **Soldé.** `06` passe en révision 2.2, `07` corrige ses deux occurrences. Règle ajoutée en `06` §4.3 : on n'écrit « couverture inconnue » qu'après avoir cherché. **L'autre trou, lui, est confirmé** — la direction de l'enrage à travers `Simulator::run()` |
| `06` §6.1 et §6.2 | **Soldé.** `06` §6.1 déclare le préfixe `docs/` et distingue clôture de session et travail documentaire autonome ; §6.2 sépare types et scopes | Ne rien faire |
| `02` §10 | **Index des écarts passé de sept à huit.** L'écart 8 (arithmétique flottante du décorateur) existait depuis l'écriture du code et n'avait jamais été détecté | **Soldé** en `02` révision 3.0 |
| `roster-cible-40-heros.json` | Reflète l'état **antérieur** à la redistribution du chantier 6 : ni `AURIC` ni `MENDING`, `LINGERING` à 5 et `TITANIC` à 4 | Appliquer la redistribution avant de le verser au dépôt. Le renommer pour éviter la collision avec `config/heroes.json` |
| `02` §5.4 et §9 | Fusion à 3 rangs et Diamant écarté, cohérents avec la décision de session | Aucune, à jour |
| `02` §7.2 et §7.3 | Les écarts 1, 2 et 3 sont désormais **tranchés en règle** par D-14 et D-15, et non encore implémentés | Reporté dans `02` révision 3.0, même branche. Ne pas dupliquer ici |

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
| **Modèle de persistance des statuts (D-20)** | **13/09/2026 — instances indépendantes.** Chaque application crée une instance portant ses stacks, ses ticks et sa source ; aucune fusion. Le régime permanent se stabilise seul entre `floor` et `ceil(durée / cooldown)` par source, sans qu'aucun plafond soit écrit — `ceil` étant le pic juste après une application et la moyenne valant `durée / cooldown` (précision mesurée le 14/09/2026). **Périme D-13.** `02` §7.4, `04` §3.3 |
| **Répartition de la brûlure** | **13/09/2026** — 150 % sur le bouclier, 70 % du surplus sur les PV, en arithmétique entière, arrondi au plancher en faveur du défenseur. `02` §7.4 |
| **Le soin retire des stacks (D-21)** | **13/09/2026 — oui.** Un déclenchement de `HEAL` retire 1 stack de `POISON` et 1 de `BURN`, sur l'instance à la plus longue durée restante, calculé sur le soin **tenté**. `REGEN` ne nettoie pas. `02` §7.4 |
| **Dépendance du chantier 2 au chantier 1a** | **Retirée.** `03` rédige EX-J0-03 comme « tout snapshot porte un champ `engineVersion` », sans mention de moteur embarqué. La révision 1.0 avait ajouté « rejoué localement » et durci un critère hors mandat |

#### Décisions du cadrage du 19 septembre 2026

| ID | Décision |
|---|---|
| **D-14** | **Résolution des morts — modèle hybride par phase.** **Statuts et enrage : résolution simultanée.** Les deux plateaux subissent l'intégralité de la phase, les morts sont constatées **en fin de phase**. Aucun « coup de fantôme » n'est possible : dans ces deux phases, aucun plateau ne frappe l'autre — chaque Vestige subit son propre poison, sa propre brûlure, le même enrage. **Actions d'objets : résolution séquentielle**, avec interruption à la première mort — la règle « pas de frappe sur cadavre » est conservée là où elle a un sens. **L'ordre de passage des deux plateaux est tiré au sort à chaque tick**, sur le flux `order` de D-22, et **uniquement lorsque les deux plateaux ont au moins une action en attente** : sans cela l'ordre n'a aucun effet et le tirage consommerait de l'aléa pour rien. Le plateau tiré exécute **toutes** ses actions, puis l'autre les siennes. **Départage d'une double mort** : PV + bouclier **relevés avant la phase**, puis tirage sur le flux `order` en cas d'égalité stricte. `02` §7.5 |
| **D-15** | **Pas de match nul.** `SimulationResult::$winner` devient **non nullable**. Un timeout est départagé par PV + bouclier **finaux**, puis par tirage en cas d'égalité. `SimulationResult` gagne un champ `resolution`, énumération à trois valeurs : `KNOCKOUT`, `SIMULTANEOUS_RESOLVED`, `TIMEOUT_RESOLVED`. Un événement `RESOLUTION_TIEBREAK` est émis dans `CombatLog` chaque fois qu'un départage a lieu, pour que le rejeu montre **pourquoi** ce vainqueur a été retenu. `02` §7.5, §6.5 |
| **D-16** | **Forme du snapshot — photographie.** Un fantôme PvP enregistré avant un rééquilibrage combat **avec ses chiffres d'origine**. Le snapshot porte donc les `Item` **déjà décorés**, dans l'ordre du plateau, et ce sont eux qui font foi au rejeu. Il porte également la définition du Vestige (`baseHp`, `baseShield`) et les héros avec leur compétence, nécessaires aux compétences d'exécution à venir (`OPENING`, `AURIC`). La **recette** `(vestigeId, heroIds, itemIdsByHero)` et la `contentVersion` sont embarquées en **provenance**, sans aucun rôle dans le rejeu, et dès la première version du format. `02` §6.6 |
| **D-17** | **Sans objet.** La question « figer le nombre de tirages dans `buildWeightedOffer()` » ne réglait pas le problème qu'elle visait : `ShopFactory` et `buildInitialOffer()` passent par `Randomizer::pickArrayKeys()`, dont la consommation dépend elle aussi de la taille du pool. Le découplage vient de l'épinglage de `contentVersion` (D-18), pas d'un compte de tirages |
| **D-18** | **Politique de migration, schéma et contenu — quatre volets.** **(1) Le journal de run est découplé du moteur** : l'issue du combat est écrite dans la charge utile de `RESOLVE_ROUND`, et le rejeu **applique l'issue enregistrée** au lieu de resimuler. **(2) `contentVersion` est une empreinte automatique** : SHA-256 des quatre catalogues canonicalisés selon les règles de D-19, `scripted_opponent.json` compris. **(3) Une run dont la version ne correspond plus est rejetée** avant J1 ; la politique post-J1 est explicitement reportée. **(4) Une table `schema_version` à ligne unique** est vérifiée au démarrage et refuse une base obsolète avec un message clair ; la base reste jetable jusqu'à J1, l'outil de migration attend le chantier 13 |
| **D-19** | **Sérialisation canonique de `CombatLog`.** Un sérialiseur dédié dans `App\Domain\Engine`, **séparé de `CombatEventPresenter`** : le contrat de l'API reste libre d'évoluer sans toucher au format de parité. **Tri des clés** par `ksort` en `SORT_STRING`, à chaque niveau — **sur les tableaux à clés texte uniquement, jamais sur les listes**. **Types autorisés : `int`, `string`, `bool`**, tout autre type levant une exception, les flottants en premier. **Enveloppe** : une version de format, puis la liste `{tick, type, payload}`, options JSON fixées. **Côtés en libellés neutres `A`/`B`**. `02` §7.6 |
| **D-22** | **Seed de combat — dérivation et flux.** *(décision nouvelle, identifiée au cadrage)* Le Domaine reçoit un **`combatSeed` explicite** ; `Simulator::run()` ne reçoit plus de `Randomizer`. Le `combatSeed` est un digest **SHA-256 encodé en hexadécimal, 64 caractères**. Il est calculé par l'**Application** : `sha256("combat\|runSeed\|round")` en PvE, dérivé de l'identifiant d'appariement en PvP. **Deux flux dérivés** dans le Domaine, en un seul endroit : `order` et `effects`, chacun seedé par `sha256(tag ‖ combatSeed)` tronqué à 16 octets. `02` §7.1, `04` §3.3 |
| **Champ « or » dans le snapshot** | **Embarqué sans condition**, sous la forme `goldAtCombatStart` — entier, solde du portefeuille au lancement du combat. Un champ contre une décision bloquante en moins : **D-12 sort du chemin critique du chantier 2** et reste au chantier 6 |

**Trois affirmations de la révision 2.0 invalidées par ce cadrage.**

1. **La formulation de D-12 était inversée.** La révision 2.0 écrivait « D-12 bloque le chantier 2 **si** le champ or y est embarqué inconditionnellement », puis justifiait par « une décision bloquante en moins ». Les deux moitiés se contredisaient. `07` §8.4 disait déjà le contraire : embarquer le champ sans condition sert précisément à **sortir** D-12 du chemin critique. La vraie question du cadrage n'était donc pas D-12, mais « embarque-t-on l'or sans condition ? ». Réponse : oui.

2. **L'argument qui fondait D-16 était faux.** La révision 2.0 concluait que « la recette est la seule forme viable » parce que `CombatBoard` range les objets en liste plate et perd l'association héros ↔ objet. Le constat sur la liste plate est exact, la conclusion ne l'est pas : **`CombatBoardFactory::createBoard()` applique `HeroSkillDecorator::decorate()` avant de construire le plateau**. Le `CombatBoard` ne contient donc que des objets déjà décorés, et une photographie n'a plus besoin de l'association. Les deux formes étaient viables ; D-16 s'est tranchée sur une question de design, pas de faisabilité.

3. **Le constat « sans test » de D-19 était inexact.** L'ordre des clés est bien écrit à la main, mais il **est** testé, indirectement : `===` sur deux tableaux PHP exige le même ordre de clés, et `assertSame` repose sur `===`. Les assertions de charge utile de `StatusProcessorTest` et `EnrageProcessorTest` figent donc déjà cet ordre. La faiblesse réelle est ailleurs : ce contrôle existe **type d'événement par type d'événement, par effet de bord**, sans aucune règle canonique. *(Corrigé le 20/09/2026 : la phrase disait « la couverture de `ActionProcessor` reste inconnue ». Elle est connue, et elle est bonne — `ActionProcessorTest` fige par `assertSame` sur le tableau entier les charges utiles de `DAMAGE_DEALT`, `SHIELD_GAINED`, `HEAL_RECEIVED` et `STATUS_APPLIED`. Affirmer une ignorance sans avoir ouvert le fichier est la même faute que d'affirmer un fait sans l'avoir vérifié.)*

#### Points de mise en œuvre attachés aux décisions ci-dessus

Ces précisions ne sont pas des décisions séparées. Elles sont consignées ici parce que les omettre rouvrirait la décision qu'elles servent.

**Attribution canonique de A et B (D-19).** Le tirage d'ordre de D-14 désigne « A ». Si l'appelant choisit lui-même qui est A, inverser les deux plateaux avec le même seed inverse l'initiative et peut changer le vainqueur : le résultat dépendrait encore de la façon dont les plateaux ont été rangés. L'attribution doit donc découler des **données du combat**.

> **A est le plateau dont le snapshot canonique est le plus petit en comparaison d'octets. En cas d'égalité, le départage se fait par un identifiant de combat enregistré avec les données d'entrée** — identifiant de run en PvE, identifiant d'appariement en PvP.

Les deux critères envisagés en séance ne suffisaient pas seuls. L'**ordre alphabétique des identifiants de joueurs** ne vaut qu'en PvP en ligne : l'adversaire scripté n'a pas d'identifiant de joueur, l'adversaire d'archive du mode hors ligne non plus. Le **tri des snapshots** fonctionne partout, sauf quand les deux snapshots sont identiques — le combat miroir, parfaitement plausible tant qu'il n'existe qu'un seul Vestige. Le combat reste équitable, les deux plateaux étant interchangeables, mais on ne saurait plus quel joueur est A, donc lequel a gagné.

**Cette règle rend D-19 dépendante de D-16**, puisque c'est D-16 qui définit le snapshot canonique.

**Le seed de combat n'a pas besoin d'être symétrique.** Une position intermédiaire du cadrage exigeait une dérivation symétrique entre les côtés. Elle a été abandonnée : l'équité vient de l'attribution canonique de A et B, après laquelle **aucune règle du moteur ne favorise plus un côté**. Un seed dépendant de l'initiateur du match ne favorise personne de façon systématique. Deux propriétés suffisent : le seed doit être **indépendant du flux de la run**, et **enregistré comme donnée d'entrée du combat**.

**Encodage des entiers dans le hachage (D-22).** `runs.seed` est un `INTEGER` et le numéro de manche un entier : le hachage exige un encodage fixé, sans quoi la concaténation est ambiguë — `12‖3` et `1‖23` donneraient la même chaîne. **Écriture décimale en ASCII, séparateur `|` explicite.**

**Changement de convention de seed du moteur PCG (D-22).** La construction actuelle passe un entier à `PcgOneseq128XslRr64`. Les deux flux dérivés passeront une **chaîne de 16 octets**, qui remplit l'état 128 bits de l'engine. L'extension `hash` appartient au cœur de PHP : aucune dépendance supplémentaire pour `static-php-cli`.

**Pourquoi deux flux et non un (D-22).** Les critiques du chantier 4 tireront dans le flux `effects`. Avec un flux unique, ajouter une ligne de critique à un objet décalerait les **ordres de passage** de tous les ticks suivants. Deux flux isolent les deux usages.

**Le commit du randomizer dérivé n'est plus « gratuit ».** La révision 2.0 le qualifiait de neutre, au motif que le combat ne consomme aucun aléa. C'était exact **avant** D-14. Le tirage d'ordre par tick en consomme désormais : sans dérivation, toutes les boutiques et offres de héros d'une run dépendraient du déroulé des combats précédents. Le commit devient un **prérequis** du commit de départage, et non plus une avance sans risque. L'ordre de la §6 le respectait déjà ; c'est la justification qui change.

**Le chemin « appliquer une issue enregistrée » est réservé au rejeu (D-18).** Avec le volet (1), le vainqueur stocké dans le journal devient une donnée qui fait foi. Ce chemin doit être **réservé à `GameRunReplayer`**. `RunController::resolveRound()` continue de simuler lui-même et **ignore tout champ d'issue présent dans la requête**. C'est la règle d'intégrité numéro un de `06` §8 appliquée au nouveau champ. Un test doit vérifier que, sur le chemin normal, l'issue journalisée est bien celle qu'a produite la simulation.

**L'enregistrement de combat par manche (D-18).** `show()` a encore besoin du `CombatLog` de la dernière manche pour l'afficher. S'il le resimule avec un moteur plus récent, l'écran peut montrer une défaite là où le journal enregistre une victoire. Chaque manche enregistre donc : les **snapshots A et B**, le **`combatSeed`**, l'**`engineVersion`**, la **`resolution`** et le **`winnerSide`**. Le `CombatLog` n'est resimulé que si l'`engineVersion` est identique ; sinon l'interface affiche l'issue enregistrée sans le détail. C'est aussi ce qui donne au critère de sortie du chantier 2 — un snapshot produit, écrit, relu, rejoué — un endroit où vivre.

**Le format de snapshot porte sa propre version (D-18).** Une photographie sérialise les modèles `Item`, `Effect` et `Action`. Dès le chantier 4, ces modèles changent : ligne de critique, nouveaux types d'action. Le snapshot porte donc une **version de format distincte d'`engineVersion`**, et sait relire les formats antérieurs — par exemple en donnant une valeur par défaut aux champs absents.

**L'empreinte de contenu couvre les quatre catalogues (D-18).** `scripted_opponent.json` compris : l'adversaire de chaque manche en dépend. Elle reprend les règles canoniques de D-19 — tables triées, listes dans leur ordre — pour qu'un simple reformatage ne la change pas.

**Ce que la photographie ne règle pas.** Deux bornes à ne pas perdre de vue.

- **Le rejeu d'un combat ne dépend plus du catalogue, mais il dépend toujours du moteur.** La photographie fige les *chiffres*, pas les *règles*. Le chantier 2 modifie lui-même la résolution, le chantier 3 fait lire les `Trigger`, le chantier 4 ajoute le critique. Un fantôme enregistré aujourd'hui combattra demain avec ses chiffres d'origine, mais sous des règles nouvelles. La capsule temporelle est **partielle**, et `engineVersion` reste irréversible pour cette raison exacte.
- **La photographie ne résout rien pour le journal de run.** `GameRunReplayer` reconstruit une run en rejouant ses actions contre le catalogue : offres de héros, boutiques, prix. `contentVersion` reste donc indispensable **sur l'enregistrement de run**. Dans le snapshot, elle n'est plus que de la provenance.

**Une question de design reportée au chantier 11.** En PvP, des fantômes d'avant et d'après un rééquilibrage se retrouveront dans le même bassin d'appariement. Filtrer par `contentVersion`, par ancienneté, ou ne pas filtrer, est une décision à prendre à ce moment-là. Rien à trancher aujourd'hui.

### 3.2 Ouvertes

| ID | Décision requise | Bloque |
|---|---|---|
| **D-10** | Support visuel du rang de fusion. L'aura porte la rareté, l'illustration et le cadre portent l'affinité : le rang n'a aucun support libre | Chantier 9 |
| **D-11** | Le Rare doit-il devenir qualitativement différent (seconde action ou condition) ? Sinon l'Argent à 20 or et ×1,75 domine strictement le Rare à 25 or et ×1,5 | Chantier 9 |
| **D-12** | Forme retenue pour l'économie du Doré : valeur indexée sur l'or non dépensé (recommandée), intérêt plafonné en fin de manche, ou objets qui consomment de l'or | **Chantier 6 seulement.** Sortie du chemin critique du chantier 2 par l'embarquement inconditionnel de `goldAtCombatStart` |

**Trois décisions ouvertes, contre neuf en révision 2.0.** Les six du chantier 2 sont tranchées, D-17 est devenue sans objet, et D-12 a changé de chantier.

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
| **Asymétrie soin / dégâts** | **Reformulée.** L'écart de 25 % sur les débits nominaux existe, mais il est secondaire devant une asymétrie **voulue et déjà consignée** dans `corebound-affinities` §2 : `receiveHeal()` est plafonné à `baseHp`, `gainShield()` ne l'est pas, et bouclier et PV ne se comparent pas comme une même unité | Chantier 10 |
| **Compétences sans objets** | `QUICKENING`, `GLACIAL`, `TITANIC`, `KEEN`, `BRUTAL` n'ont aujourd'hui **aucun objet** à modifier | Chantier 4 |
| **Densité inégale** | **Corrigé.** `SUNDERING` ne modifie pas 14 objets mais **10** : `applySundering()` est fermée aux `TWO_HAND`. `WARDEN` en modifie 1 (`shadow_armor`), `RESURGENT` 1 (`panacee`) | Chantier 10 |
| **Adversaire scripté partiellement inerte** | Sur ses trois héros, **une seule compétence agit** : `FRANTIC` sur `shadow_bearer` (cd 20 → 16). `STALWART` sur `the_bulwark` ne trouve aucun `GAIN_SHIELD` dans un `longsword`. `WARDEN` sur `shadow_bastion` ne trouve aucun statut dans un `shield`. Tout chiffre d'équilibrage relevé jusqu'ici porte sur un adversaire aux deux tiers inerte | Chantier 10 |
| **Rampe adverse non monotone** | Le remplissage glouton de `createOpponent()` fait `continue 2` vers le héros suivant dès qu'un objet ne rentre pas. Résultat : manches 5–6 → 2× dagger + **shield** ; manches 7–8 → 2× dagger + longsword, **sans bouclier**. Le budget monte, la défense descend | Chantier 10 |

### 4.2 Anomalies de moteur

| ID | Anomalie | Constat | Traitée par |
|---|---|---|---|
| **E-01** | **Biais d'ordre sur les morts simultanées** | Trois boucles itèrent `getBoards()`, joueur en premier, et cassent à la première mort. Quand la boucle inflige à l'ennemi (`TickEngine` → actions d'objets), passer en premier est un **avantage**. Quand elle inflige à soi-même (`EnrageProcessor`), c'est un **désavantage**. **Précision du 19/09/2026 : il y a trois directions, pas deux.** `StatusProcessor` n'a aucune garde, donc les deux plateaux meurent ensemble et `winner` vaut `null` ; `EnrageProcessor` favorise l'adversaire ; la phase d'actions favorise le joueur. Autrement dit, ce que D-14 devait trancher n'était pas « qui passe en premier » mais **« la phase se résout-elle en simultané ou en séquentiel »**. Second point relevé : la direction de l'enrage n'est figée qu'au niveau unitaire, aucun test ne la vérifie à travers `Simulator::run()` | Chantier 2, décision D-14 — **tranchée** |
| **E-02** | ~~**Statuts fusionnés par type, stacks non bornés**~~ — **résorbée le 14/09/2026** | `shadow_armor` produisait **7 155** de bouclier sur 500 ticks sous le modèle à fusion, contre **1 253** sous le modèle par instances. Mesuré par `SimulatorTest::testShadowArmorProducesABoundedReferenceShieldOverFiveHundredTicks`, qui consigne 1 253 comme valeur de référence. **Tranché par D-20**, implémenté : `CombatVestige` porte une liste d'instances par type, `mergeWith()` a disparu, `AggregatedStatus` porte la projection exposée aux `CombatEvent` | Chantier 3b, points 1 et 2 — fait |
| **E-03** | **Gardes de mort incomplètes** — **moitié chantier 0 résorbée, vérifiée le 20/09/2026** | ~~`Simulator::run()` n'a **aucun contrôle entre statuts et enrage** : si l'adversaire meurt d'un poison, l'enrage s'exécute quand même, frappe le joueur en premier et peut le tuer — **une victoire devient une défaite**~~ **Corrigé** : la boucle rompt entre les deux phases, et `SimulatorTest::testPlayerWinsWhenPoisonKillsOpponentEvenIfEnrageWouldTriggerSameTick` l'exerce avec un enrage à 1000 de dégâts dès le tick 1. ~~Le commentaire d'`EnrageProcessor` affirme rétablir un double KO « structurellement impossible »~~ **réécrit** : il décrit l'invariant réel et nomme le trou restant. **Reste** : `StatusProcessor::processTick()` n'a **aucune garde de vie** et pulse les deux plateaux — écarté du chantier 0 délibérément, un `break` naïf y implémenterait D-14 à l'envers | Reste : chantier 2, commit 7 |
| **E-04** | ~~**`SUNDERING` pénalise deux objets**~~ — **résorbée le 14/09/2026** | La compétence ne s'applique plus du tout à un deux mains sans `DEAL_DAMAGE` | Chantier 3b, point 4 — fait |
| **E-05** | **Flux RNG couplé au catalogue** | `buildWeightedOffer()` tire un `nextFloat()` par héros du pool : 9 à la manche 3, 8 à la manche 5 avec 10 héros. Passer à 16 héros au chantier 6 en consommera 15 et 14. **Correction du 19/09/2026 : figer ce compte ne suffisait pas.** `ShopFactory::drawItems()` et `HeroOfferGenerator::buildInitialOffer()` passent par `Randomizer::pickArrayKeys()`, dont la consommation dépend elle aussi de la taille du pool. **Toute modification de catalogue décale le flux, quoi qu'on fasse dans `buildWeightedOffer()`.** Le découplage vient donc de l'épinglage de `contentVersion` sur la run, qui garantit qu'une run se rejoue toujours avec son propre catalogue | Chantier 2, décision **D-18** *(et non plus D-17, devenue sans objet)* |
| **E-06** | ~~**Garde manquante dans `playRound()`**~~ — **résorbée, vérifiée le 20/09/2026** | `playRound()` teste désormais `isOver()` **et** `pendingHeroOffer !== null`, et lève une `LogicException` explicite dans le second cas. Constat d'origine : `purchaseItem()` et `openShop()` refusaient tant qu'une offre de héros était en attente, `playRound()` ne testait que `isOver()`. Portée réelle limitée : au tour 1, `CombatBoard::__construct()` refuse un roster vide (400) **avant toute mutation**. Le trou se limitait aux tours 3 et 5, roster à 1 ou 2 héros, et n'était pas atteignable par l'interface | Chantier 0 — fait |
| **E-07** | **Champs et méthodes morts** — **deux tiers résorbés le 14/09/2026** | ~~`Effect::intervalTicks`~~ retiré, ~~`EventDispatcher::dispatch()` et `getListenersFor()`~~ retirés. **Reste `Trigger`**, non lu nulle part : retirer l'enum amputerait une intention de design, c'est une décision et non un nettoyage | `Trigger` : chantier 3 |
| **E-08** | **Pas de garde anti-cascade** | `EventDispatcher` n'a aucun garde-fou. `dispatchForItem()` n'est appelé que par `TickEngine`, jamais en réentrance, donc inoffensif tant qu'aucun objet n'est conditionnel. Le devient au chantier 3 | Chantier 3 |
| **E-09** | **Lecture de fichier non cachée** | `JsonItemRepository::find()` et `JsonHeroRepository::getRawData()` relisent le fichier et refont un `json_decode` **à chaque appel**. `CombatBoardFactory` les appelle une fois par héros et par objet, pour les deux plateaux. Et `GameRunReplayer::replay()` s'exécute à chaque requête, y compris `show()` : à la manche 8, une simple lecture d'état rejoue sept combats. **Moitié traitée par D-18** : avec l'issue enregistrée, le rejeu n'appelle plus le moteur, donc `show()` ne resimule plus rien. Reste la relecture de fichier à chaque `find()` | Chantier 2 pour la moitié `show()`, chantier 1a pour le cache |
| **E-10** | ~~**Rollback court-circuité dans `swapWithStash()`**~~ — **résorbée le 14/09/2026** | Corrigée par un `try`/`finally` autour de la seule fenêtre d'amputation | Chantier 0 — fait |
| **E-11** | **Le journal de run dépend du moteur** | *(relevée le 19/09/2026)* **Ni la révision 2.0 ni la note de passation du chantier 2 ne la signalaient.** Le journal ne stocke pas le résultat des combats, seulement l'action `RESOLVE_ROUND` : à chaque rejeu, `GameRun::playRound()` **resimule** tous les combats avec le moteur courant. Dès que le moteur change — et le chantier 2 le change lui-même avec D-14 — une manche passée peut changer d'issue. Le compteur de victoires diverge alors, les offres de héros des manches 3 et 5 apparaissent ou disparaissent, et une action `CHOOSE_HERO` journalisée peut lever une exception au rejeu. **Épingler `contentVersion` ne suffisait donc pas** : une run dépend du contenu, du moteur, **et** du schéma de dérivation du seed | Chantier 2, décision D-18 volet (1) |
| **E-13** | ~~**La seed n'est pas atteignable depuis l'API HTTP**~~ — **résorbée le 20/09/2026** | *(relevée le 19/09/2026)* `RunController::create()` lit `$params['seed']`, mais la route `POST /runs` n'a **aucun placeholder** : `$params` est toujours vide. `Request::fromGlobals()` **coupe la chaîne de requête sans la conserver**, donc `?seed=42` est perdu. Et la closure de route accepte `$request` mais **ne le transmet pas** à `create()`, donc un corps JSON ne passerait pas davantage. **En production, la seed est donc toujours `random_int()`.** Seul `RunControllerTest` l'exerce, en appelant le contrôleur directement. Défaut de contrat externe : `04` §7 décrivait « seed : paramètre optionnel de `RunController::create()` » sans signaler qu'aucun client ne peut l'exercer, et `01` §5 annonce la seed partageable comme différenciateur. **Portée réelle : testabilité, pas correction.** Le commit de rejeu octet pour octet et le test de parité du chantier 1a travaillent en Domaine et en CLI, sans HTTP ; ce qui est impossible aujourd'hui, c'est un test de bout en bout **à travers le routeur** sur une run de seed connue, et la reproduction d'un rapport de bug joueur. **Tranchée le 19/09/2026** : la seed devient un **champ du corps JSON de `POST /runs`** — emplacement naturel d'un paramètre de création de ressource, contrairement à une chaîne de requête ou à un corps ignoré. `$request` transite du routeur jusqu'à `create()`. **Résorbée le 20/09/2026** : le corps JSON est la source **unique** — la lecture de `$params['seed']` a été retirée plutôt que conservée en second canal, et un `seed` non entier est **rejeté** (`InvalidArgumentException` → 400) au lieu d'être casté, `{"seed": null}` valant absence | Chantier 2, commit 4 — fait |
| **E-12** | **Arithmétique flottante dans `HeroSkillDecorator`** | *(relevée le 19/09/2026)* Le décorateur calcule en flottants — `ceil($value * 1.2)`, `floor($cd * 1.10)` — alors que `CombatVestige::takeBurnDamage()` déclare qu'un flottant « casserait la parité ». **Ce n'est pas un risque de parité** : une multiplication IEEE-754 isolée est correctement arrondie, donc identique sur les cibles 64 bits. **C'est un risque de justesse.** Mesuré le 19/09/2026 sur les valeurs entières de 0 à 1000 : `value × 1,10` (RELENTLESS) diverge de l'arithmétique exacte sur **54 valeurs**, la première à 50 — `ceil` donne 56 là où le calcul exact donne 55 ; `value × 1,35` (SUNDERING) diverge sur **8 valeurs**, la première à 180. `value × 1,2` et les trois `floor` sur cooldown ne divergent nulle part sur cet intervalle. **Quelles valeurs du catalogue sont réellement touchées reste inconnu** : `items.json` n'a pas été lu. **Ampleur accrue par D-16** : avec la photographie, le décorateur sort du chemin de rejeu, donc une correction ultérieure ne rétroagit pas sur les snapshots déjà produits — mais les chiffres faux, eux, y sont figés | **Ouvert.** À instruire au chantier 10, où les valeurs montent. Ni chantier 2 ni chantier 3b. Consigné en `02` §2.3, écart 8 |

**Ce qui a été vérifié et se révèle sain**, et qu'il faut cesser de soupçonner :

- `run_actions` porte `PRIMARY KEY (run_id, sequence)`. Le journal est protégé contre les insertions concurrentes par verrou optimiste, sans transaction explicite.
- `RunController` respecte l'ordre `replay()` → `apply()` → `append()` dans ses quatre handlers mutants. **La règle d'intégrité numéro un est tenue.**
- `HeroSkillDecorator` préserve l'`id` du catalogue dans `withCooldownTicks()` et `withEffects()`.
- **La décoration par compétence précède la construction du plateau.** `CombatBoardFactory::createBoard()` décore chaque `Item` avant de l'envelopper dans un `CombatItem`. *(vérifié le 19/09/2026 — c'est ce qui rend D-16 possible en photographie)*
- **L'ordre des listes du plateau a un sens et doit être préservé.** Les objets sont rangés héros par héros, dans l'ordre de `$heroIds` puis dans l'ordre d'affectation, et `TickEngine` les active dans cet ordre. *(vérifié le 19/09/2026 — c'est ce qui interdit à D-19 de trier les listes)*
- **`ScriptedOpponentFactory` passe par `CombatBoardFactory::createBoard()`**, avec un Vestige fixé en constante. Un adversaire scripté se représente donc dans l'une ou l'autre forme de snapshot, sans cas particulier. *(vérifié le 19/09/2026)*
- **Les PV sont bornés à zéro.** `takeDamage()`, `takeRawDamage()` et `takeBurnDamage()` passent tous trois par `min($this->currentHp, …)`, et `CombatVestigeTest::testTakeBurnDamageNeverPushesHpBelowZero` le fige. **Après une double mort, les deux Vestiges sont à 0 PV et l'excédent de dégâts n'est enregistré nulle part** : c'est pourquoi D-14 départage sur l'état **avant** la phase. *(vérifié le 19/09/2026)*
- Le `match` de `decorate()` n'a pas de branche `default` et couvre exactement les 10 compétences implémentées. **Filet de sécurité pour le chantier 6, à ne pas casser en ajoutant un `default`.**
- Les objets à `cooldownTicks == durationTicks` (`firesteel`, `molotov_cocktail`) sont **bornés et déterministes**.
- `Shop::purchase()` valide entièrement avant de muter.
- `openShop()` n'a pas de garde `isOver()`, et n'en a pas besoin. Vérifié le 14/09/2026.

---

## 5. Séquence d'exécution

### 5.1 Trois règles d'ordonnancement

**R1 — Ce qui devient irréversible passe avant ce qui est seulement gros.**
Une décision qui coûte un champ aujourd'hui et un corpus demain passe avant un chantier long mais réversible.

**R2 — On ne modifie pas un comportement qu'on n'a pas d'abord figé par un test.**
Le moteur contient plusieurs biais non documentés (E-01). Les corriger sans test préalable rend le changement invisible dans le diff.

**R3 — Aucun rééquilibrage de contenu tant que le moteur ment.**
Recalibrer 30 objets sur un moteur dont l'enrage handicape le joueur (E-01) et dont l'adversaire est aux deux tiers inerte produit des chiffres à jeter.

### 5.2 Ordre retenu

| Rang | Chantier | Préalable | Taille | Porte |
|---:|---|---|---|---|
| 1 | **0** — Gardes de mort et caractérisation — **code fait, caractérisation incomplète** *(constaté le 20/09/2026)* | — | reste XS | — |
| 2 | ~~**3b** — Modèle de statut, décorateur, code mort~~ **terminé le 14/09/2026** | — | M | — |
| 3 | ~~**Cadrage du chantier 2** — 6 décisions, aucun code~~ **terminé le 19/09/2026 — 7 décisions** | 0, 3b | — | — |
| 4 | **2** — Snapshot versionné et déterminisme | cadrage | **L** | EX-J0-03 |
| 5 | **1b** points 1 à 4 — Coquille Electron et Steam | — | M | EX-J0-02 partiel |
| 6 | **1a** — Moteur embarqué | 2 | L | EX-J0-01 |
| 7 | **3** — Déclencheurs vivants | 3b | M | — |
| 8 | suite selon §5.3 | | | |

**Le rang 1 reste ouvert, et il ne bloque pas le chantier 2 en bloc.** Les quatre points de code du chantier 0 sont faits — garde de vie entre statuts et enrage, commentaire d'`EnrageProcessor`, garde de `playRound()`, rollback de `swapWithStash()` — mais **deux puces de caractérisation manquent** (détail en §6). Ce reliquat ne bloque que le **commit 7** du chantier 2, celui qui applique la règle de résolution D-14 et qui est documenté comme « réécrit les tests de caractérisation du chantier 0 » : on ne réécrit pas un test qui n'existe pas, et R2 tombe exactement là où elle a été écrite. Les commits 1 à 6 ne le touchent pas. **À solder avant le commit 7, coût estimé deux tests, aucun code de production.**

**Le cadrage a produit sept décisions, pas six.** D-22 — la dérivation du seed de combat — n'apparaissait dans aucune révision antérieure. Elle était pourtant déjà rangée parmi les points irréversibles en §8.3, sous la forme « randomizer dérivé par combat », sans que la **fonction de dérivation** elle-même soit jamais identifiée comme un format à figer.

**Le chantier 2 passe de M à L.** Trois causes, toutes issues du cadrage : D-16 en photographie sérialise les modèles complets et non trois listes d'identifiants ; D-18 volet (1) ouvre un second chemin d'application dans `GameRun` ; et E-11, découverte en séance, ajoute l'enregistrement de combat par manche.

**Changement d'ordre du 13/09/2026, rappelé.** Le chantier 3b était au rang 6. Il est passé au rang 2, avant le chantier 2, parce que D-20 fait de l'état de statut une **liste** et non un entier. Geler le format de snapshot avant ce changement aurait imposé une migration dès sa première version.

En parallèle et sans blocage : la **piste sans code** de la §7, et la mise à niveau documentaire de la §2 — dont `04`, qui devient bloquante avant le premier commit du chantier 2.

### 5.3 Vue d'ensemble des chantiers

| # | Chantier | Préalable | Jalon | Taille |
|---|---|---|---|---|
| **0** | Gardes de mort et caractérisation du moteur | — | J0 | S |
| **1a** | Moteur embarqué `corebound-engine` | 2 | J0 | L |
| **1b** | Coquille Electron + `steamworks.js` (AppID 480) | — *(point 5 : 1a)* | J0 | M |
| **1c** | Compte partenaire Steam et page produit | statut juridique | J0 différé | S |
| **2** | Format de snapshot versionné et déterminisme | 0, **3b**, cadrage | J0 | **L** |
| **3b** | Modèle de statut, décorateur, code mort | — | J0 | M |
| **3** | Déclencheurs vivants et garde anti-cascade | 3b | J0 | M |
| **4** | Actions manquantes du moteur | 3, **2** | J1 | M |
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

**Quatre changements de dépendance par rapport à la révision 1.0.**
1. Le chantier 2 ne dépend plus de 1a — c'est 1a qui dépend de 2, parce que le test de parité d'EX-J0-01 exige que le combat ne pioche pas dans le flux RNG du run.
2. Le chantier 1b n'est indépendant que sur ses points 1 à 4. Son point 5 exige le binaire produit par 1a.
3. Le chantier 6 dépend du chantier 2 : passer de 10 à 16 héros décale le flux RNG (E-05).
4. *(ajouté en 3.0)* **Le chantier 4 dépend explicitement du chantier 2.** Le critique tire dans le flux `effects` de D-22, qui n'existe qu'après. La révision 2.0 le disait dans le corps du chantier 4 sans l'inscrire à la table.

### Graphe de dépendances

```
0 ── 3b ── cadrage ── 2 ──┬── 1a ──┬─────────┐
                          │        │         │
                          │   1b(5)┘         │
                          │                  │
1b(1-4) ──────────────────┘                  │
                                             │
3b ──┬── 3 ── 4 ── 5 ── 6 ──┐                │
     │  │     │             │                │
     │  │     └── (2) ──────┤                │
     │  └── 9               ├── 10 ──────────┤
     └──────────────────────┘                │
                                             │
7 (indépendant)                              │
                                             │
8 ──┬─────────────────────────────────────── 11 ── 12
    │                                              │
    └── 14                                  13 ────┘
```

---

## 6. Détail des chantiers

### Chantier 0 — Gardes de mort et caractérisation du moteur

**Objectif.** Corriger ce qui ne dépend d'aucune décision, et figer par des tests ce que le chantier 2 va délibérément changer.

**Pourquoi en premier.** Application de R2. Sans tests de caractérisation, la modification de la règle de résolution au chantier 2 est un diff illisible : on ne verra pas quel comportement a changé ni dans quel sens.

**Contenu.**

1. **Tests de caractérisation.** Ils affirment le comportement **actuel**, y compris ce qui est faux. Ils seront réécrits quand la règle qu'ils caractérisent changera, pas tous au même chantier.
   - Double KO par statut simultané → `winner: null` → comptabilisé en défaite par `GameRun::playRound()`
   - Priorité `playerBoard` sur mort simultanée par action d'objet
   - Priorité `opponentBoard` sur mort simultanée par enrage
   - **Ajouté en 3.0 :** la direction de l'enrage **à travers `Simulator::run()`**, et non seulement au niveau unitaire d'`EnrageProcessor`. C'est la seule des trois directions qui ne soit aujourd'hui figée qu'en isolation
   - Accumulation non bornée : **fait le 14/09/2026** pour `venomous_vial`, `firesteel`, `molotov_cocktail` et `shadow_armor`
   - Ordre des phases dans un tick : cooldowns → statuts → enrage → actions

2. **Contrôle de vie entre statuts et enrage** dans `Simulator::run()` (E-03). **Ce n'est pas cosmétique** : sans lui, la mort de l'adversaire par poison n'empêche pas l'enrage de tuer le joueur dans le même tick, transformant une victoire en défaite.

3. **Commentaire d'`EnrageProcessor` réécrit** pour décrire l'invariant réel.

4. **Garde dans `playRound()`** (E-06). **Pas de garde dans `swapWithStash()`, décision assumée.** La vérification a en revanche révélé un défaut distinct dans cette méthode (E-10), corrigé dans ce chantier.

**État au 20/09/2026, relevé dans le code et non déclaré.** Les quatre points de code sont faits ; la caractérisation ne l'est pas.

| Point | État | Preuve |
|---|---|---|
| 1 — double KO par statut → `winner: null` → défaite | ✅ | `SimulatorTest::testCharacterizesSimultaneousStatusDeathAsNullWinnerRecordedAsDefeat` |
| 1 — priorité `playerBoard` sur mort simultanée par action | ✅ | `SimulatorTest::testCharacterizesPlayerBoardPriorityOnSimultaneousActionDeath` |
| 1 — priorité `opponentBoard` sur mort simultanée par enrage | ⚠️ **en isolation seulement** | `EnrageProcessorTest::testProcessTickStopsBeforeSecondBoardWhenFirstDies` — unitaire, jamais à travers `Simulator::run()` |
| 1 — **ajouté en 3.0** : direction de l'enrage **à travers `Simulator::run()`** | ❌ **absent** | Aucun test de `SimulatorTest` ne l'exerce. Le commentaire de `testCharacterizesPlayerBoardPriorityOnSimultaneousActionDeath` renvoie le lecteur au test unitaire d'`EnrageProcessorTest` — c'est exactement le trou que 3.0 demandait de combler |
| 1 — accumulation non bornée | ✅ | Fait le 14/09/2026 sur quatre objets |
| 1 — ordre des phases : cooldowns → statuts → enrage → actions | ⚠️ **partiel** | `testCharacterizesPhaseOrderWithinATickAsStatusesBeforeActions` **neutralise l'enrage** (`triggerTick: 1_000_000`) et ne pince que « statuts avant actions ». La position de l'enrage dans le tick n'est figée par aucun test |
| 2 — contrôle de vie entre statuts et enrage (E-03) | ✅ | `Simulator::run()` rompt la boucle entre `statusProcessor` et `enrageProcessor` ; `testPlayerWinsWhenPoisonKillsOpponentEvenIfEnrageWouldTriggerSameTick` l'exerce avec un enrage à 1000 de dégâts dès le tick 1 |
| 3 — commentaire d'`EnrageProcessor` réécrit | ✅ | Il décrit l'invariant réel et **nomme** le trou restant de `StatusProcessor` |
| 4 — garde dans `playRound()` (E-06) | ✅ | `playRound()` teste `isOver()` **et** `pendingHeroOffer !== null` |
| 4 — rollback de `swapWithStash()` (E-10) | ✅ | Résorbée le 14/09/2026 |

**Ce qui reste, et quand.** Deux tests dans `SimulatorTest`, aucun code de production : la direction de l'enrage sur mort simultanée **à travers `Simulator::run()`**, et la position de l'enrage dans l'ordre des phases. **À écrire avant le commit 7 du chantier 2**, qui réécrit ces caractérisations. Les écrire après serait les écrire sur le comportement d'arrivée, ce qui ne caractérise rien.

**Ce qui n'est explicitement pas dans ce chantier.** La garde de vie dans `StatusProcessor`. Un `break` naïf y ferait mourir le joueur en premier sur toute mort simultanée par statut, c'est-à-dire implémenterait D-14 par accident, **et dans le sens exactement contraire à celui retenu** : D-14 résout les statuts en simultané, pas en séquentiel. Elle appartient au chantier qui applique la règle.

**Critère de sortie.** Suite verte, `check-all.ps1` vert, comportement de résolution documenté par des tests.

**Branches.** `fix/engine-death-guards`, `fix/application-play-round-guard`, `fix/application-swap-with-stash-rollback`.

---

### Chantier 2 — Snapshot versionné et déterminisme

**Pourquoi maintenant alors que le PvP est loin.** `04` §5.3 : **le point de l'architecture qui devient irrattrapable.** Le coût aujourd'hui est de quelques champs et d'une signature. Le coût après le premier commit PvP est le corpus entier.

**Périmètre.** La révision 1.0 le dimensionnait en S sur trois points. L'audit du 8 septembre l'a porté à M sur neuf décisions. Le cadrage du 19 septembre le porte à **L**, pour les trois raisons données en §5.2.

**Étape préalable : le cadrage — faite le 19/09/2026.** Sept décisions tranchées sans écrire de code : D-14, D-15, D-16, D-17 *(devenue sans objet)*, D-18, D-19, D-22, plus l'embarquement inconditionnel de `goldAtCombatStart` qui sort D-12 du chemin critique. Voir §3.1.

**Préalable documentaire, bloquant.** `04` §3.3 et §5.3 décrivent une signature de `Simulator::run()` et une forme de snapshot que D-16 et D-22 changent. **Mettre `04` à jour avant le premier commit**, pas après (§2).

**Contenu, dans l'ordre TDD.**

1. `feat(persistence): add schema version table` — table `schema_version` à ligne unique, vérifiée au démarrage, refusant une base obsolète avec un message explicite plutôt qu'une erreur SQL brute (D-18 volet 4).
2. `feat(domain): add canonical CombatLog serialization` — dans `App\Domain\Engine`, séparé de `CombatEventPresenter`. Tri `ksort`/`SORT_STRING` **sur les tableaux à clés texte uniquement**, types restreints à `int`/`string`/`bool`, enveloppe versionnée, options JSON fixées (D-19).
3. `feat(domain): derive combat randomizer from an explicit combat seed` — `combatSeed` en hexadécimal 64 caractères, deux flux `order` et `effects` dérivés par `sha256(tag ‖ combatSeed)` tronqué à 16 octets ; `Simulator::run()` ne reçoit plus de `Randomizer` (D-22). **Porte aussi le calcul du seed côté Application** — `CombatSeed::forRound()`, encodage décimal ASCII, séparateur `|` — parce que les deux moitiés ne sont pas séparables : voir la fusion ci-dessous.
4. `feat(http): accept an optional seed in the POST /runs body` — `$request` transite du routeur jusqu'à `create()`, qui lit `seed` dans le corps JSON et retombe sur `random_int()` en son absence (E-13). **Seul commit de couche Http du chantier, et le seul indépendant des dix autres** : il peut être écrit en premier si un test de bout en bout sur seed connue est nécessaire plus tôt.
5. `feat(domain): adopt neutral side labels and canonical A/B assignment` — `SimulationContext::getSide()` passe de `PLAYER`/`OPPONENT` à `A`/`B`, tous les payloads portant `targetSide` ou `sourceSide` suivent ; A est le plateau au snapshot canonique le plus petit, égalité départagée par l'identifiant de combat (D-19). **Touche le lecteur de rejeu du frontend.**
6. `feat(domain): make the winner non-nullable and add a resolution field` — `KNOCKOUT`, `SIMULTANEOUS_RESOLVED`, `TIMEOUT_RESOLVED`, plus l'événement `RESOLUTION_TIEBREAK` (D-15).
7. `feat(domain): apply the hybrid resolution rule to all three phases` — statuts et enrage en simultané avec constat des morts en fin de phase et départage sur l'état d'avant la phase ; actions en séquentiel avec `break` et ordre tiré par tick lorsque les deux plateaux ont une action en attente (D-14). **Réécrit les tests de caractérisation du chantier 0 — donc exige que les deux puces manquantes de ce chantier soient écrites d'abord** (§5.2 et chantier 0 ci-dessus).
8. `feat(domain): add snapshot format, engineVersion and contentVersion` — photographie des `Item` décorés dans l'ordre du plateau, définition du Vestige, héros et compétences, recette et `contentVersion` en provenance, `goldAtCombatStart` embarqué sans condition, version de format distincte d'`engineVersion` (D-16, D-18).
9. `feat(persistence): pin a computed contentVersion on run records` — empreinte SHA-256 des quatre catalogues canonicalisés, `scripted_opponent.json` compris ; une run dont la version ne correspond plus est rejetée (D-18 volets 2 et 3).
10. `feat(application): record combat outcome in RESOLVE_ROUND and apply it on replay` — l'issue est écrite dans la charge utile, le rejeu l'applique au lieu de resimuler, le chemin est réservé à `GameRunReplayer`, `RunController::resolveRound()` ignore tout champ d'issue venu du client (D-18 volet 1, E-11). Enregistrement de combat par manche : snapshots A et B, `combatSeed`, `engineVersion`, `resolution`, `winnerSide`.
11. `test(domain): assert byte-identical replay of a reference combat`.

**Le passage de dix à onze commits, en détail.**

| Mouvement | Commit | Motif |
|---|---|---|
| **Retiré** | `fix draw count in weighted hero offer` | D-17 devenue sans objet : figer le nombre de tirages ne découplait pas le flux du catalogue, puisque `pickArrayKeys()` en dépend aussi. E-05 est traitée par l'épinglage de `contentVersion` |
| **Fusionné** | `embed gold field in snapshot` | Absorbé par le commit 8. `goldAtCombatStart` est un champ du format de snapshot, pas un format à part |
| **Ajouté en 3.0, fusionné en 3.1** | `compute the combat seed from run seed and round` | 3.0 : D-22 sépare ce que le Domaine consomme de ce que l'Application calcule — deux couches, deux commits. **3.1 : le découpage ne tient pas à l'exécution.** Changer la signature de `Simulator::run()` casse `GameRun::playRound()`, seul appelant ; le commit de Domaine seul laissait un état non compilable, et le commit d'Application seul aurait référencé un `CombatSeed` inexistant. `06` §1.6 exige un `check-all.ps1` vert à chaque commit : les deux moitiés n'en font qu'un |
| **Ajouté** | `adopt neutral side labels and canonical A/B assignment` | D-19 : ce commit touche le frontend, il ne peut pas voyager avec un commit de Domaine |
| **Ajouté** | `record combat outcome in RESOLVE_ROUND and apply it on replay` | E-11, découverte au cadrage. Ni la révision 2.0 ni la note de passation ne la signalaient |
| **Ajouté** | `accept an optional seed in the POST /runs body` | E-13, relevée à l'ouverture du chantier en lisant `index.php` et `Request.php` ensemble. La seed était inatteignable depuis l'API |

**Le compte.** Dix au départ, moins un retiré, moins deux fusionnés, plus quatre ajoutés : **onze**. La révision 3.0 en annonçait douze ; la seconde fusion est ce qui les ramène à onze.

**Critère de sortie.** EX-J0-03 vert. Un snapshot produit, écrit, relu, rejoué, `CombatLog` identique octet pour octet. **Complété en 3.0 :** une run rejouée après un changement de moteur conserve ses issues de manche, et une run dont la `contentVersion` ne correspond plus est rejetée avec un message explicite.

**Branche.** `feature/versioned-combat-snapshot`.

**Branche de cadrage.** `docs/combat-snapshot-framing`, qui porte les révisions de `00`, `02`, `04`, `06` et de ce document. Fusionnée sur `dev` **avant** l'ouverture de la branche de code, pour que les commits d'implémentation partent de décisions déjà versées. `06` §6.1 réserve le préfixe `docs/` au travail documentaire qui ne suit aucun code : un cadrage précède le code, il ne clôt pas une session de code.

---

### Chantier 1b — Coquille Electron et Steam

**Objectif.** Prouver que `steamworks.js` fonctionne, que l'overlay Steam s'accroche à une fenêtre Electron, et que le sidecar `corebound-engine` est appelable depuis le processus principal.

**Coût monétaire : 0 €.** Valve fournit **Spacewar, AppID 480**, application de test publique permettant d'exercer les API Steamworks sans adhésion au programme partenaire. Le mécanisme est un fichier `steam_appid.txt` contenant `480`, placé à côté de l'exécutable.

**Contenu.**
1. Coquille Electron minimale chargeant le build Vue.
2. `steamworks.js` initialisé dans le processus principal, avec `steam_appid.txt` à 480.
3. Déverrouillage d'un achievement Spacewar.
4. Overlay Steam vérifié sur la fenêtre. **C'est le motif numéro deux du rejet de Tauri en `04` §4.1.**
5. Appel du sidecar `corebound-engine.exe` depuis le processus principal, stdin / stdout. **Ce point exige le binaire du chantier 1a et se fait après lui.**

**Pourquoi les points 1 à 4 passent avant 1a.** M contre L, 0 € des deux côtés, et c'est le test le moins cher d'une question de niveau stack : un échec rouvre la décision de packaging prise le 2 septembre.

**Critère de sortie.** EX-J0-02 vert dans sa forme testable : un achievement se déverrouille et l'overlay s'affiche **depuis un build packagé**, pas seulement en développement.

**Réserves à connaître.**
- Les achievements de Spacewar sont ceux de Valve. Vous prouvez le câblage, pas votre configuration.
- Des écarts entre build de développement et build shipping sont rapportés sur ce chemin. D'où l'exigence de tester sur build packagé.
- La licence du SDK impose d'être développeur enregistré pour un usage en production. 480 couvre le développement, pas la sortie.
- Valve rappelle de **retirer `steam_appid.txt`** avant tout envoi vers un dépôt Steam.
- À vérifier avant de planifier : si le téléchargement du SDK Steamworks est aujourd'hui derrière un compte partenaire.

**Branche.** `feature/electron-shell`.

---

### Chantier 1a — Moteur embarqué

**Objectif.** Prouver que le moteur PHP tourne sur la machine du joueur et produit un `CombatLog` identique à celui du serveur.

**Préalable : le chantier 2.** Le test de parité exige que le combat ne pioche pas dans le flux RNG du run. Avec D-22, l'entrée du binaire est exactement le contrat voulu : `{ snapshotA, snapshotB, combatSeed }`, et **le moteur embarqué n'a jamais besoin du seed de run**.

**Coût monétaire : 0 €.** Aucun lien avec Steam.

**Contenu.**
1. Point d'entrée CLI : lit `{ snapshotA, snapshotB, combatSeed }` sur stdin, appelle `Simulator::run()`, écrit le `CombatLog` canonique sur stdout.
2. Empaquetage du domaine en PHAR via `box-project/box`.
3. Binaire via `static-php-cli` et `phpmicro`. Extensions minimales : `json`, `mbstring`, `random`, **`hash`** *(requise par D-22 ; elle appartient au cœur de PHP, donc sans coût de licence supplémentaire)*. **Jamais le build `gigantic`**, pour l'audit de licences C-02.
4. Build matriciel Windows / Linux / macOS en CI.
5. Test de parité de déterminisme serveur contre binaire, sur un jeu de seeds fixes, bloquant.
6. Cache mémoire des repositories JSON (E-09), si la mesure le justifie. **Portée réduite par D-16** : le rejeu d'un combat en photographie ne touche plus les catalogues.

**Critère de sortie.** EX-J0-01 vert : `echo '{...}' | corebound-engine.exe` produit un `CombatLog` strictement identique à celui du serveur pour le même `combatSeed`.

**Branches.** `feature/engine-cli`, `chore/static-php-build`, `ci/determinism-parity`.

**Repli documenté.** Si le chantier coince, `04` §11 liste le portage du moteur en TypeScript, avec test de parité en CI rendu possible par le déterminisme. Coût : double maintenance permanente. À décider sur une borne de temps fixée à l'avance, pas par épuisement.

---

### Chantier 1c — Compte partenaire et page produit

**Différé, sans être abandonné.** Exige 90 à 100 € et un statut juridique permettant de percevoir les revenus.

**Ce que le report coûte réellement.** `05` §7.2 classe la publication de la page Steam comme l'action de marketing au meilleur rendement. `03` §5 conditionne le franchissement de J1 à 2 000 wishlists. **Les wishlists s'accumulent dans le temps : chaque mois sans page est un mois de collecte perdu, pas un mois neutre.**

**Conséquence de planification.** L'ouverture du statut juridique devient une **tâche datée de cette roadmap**, pas un prérequis flottant.

**Rappel bloquant.** `03` C-01 : formulaire **W-8BEN** complété avant toute mise en vente.

**Hors périmètre de ce document.** Le choix du statut, le régime fiscal et la forme du formulaire relèvent d'un expert-comptable ou du guichet des formalités des entreprises.

---

### Chantier 3b — Modèle de statut, décorateur, code mort

**Objectif.** Rendre le moteur honnête avant d'y verser du contenu, et **avant de figer le format de snapshot**. Application de R3.

**Contenu.** *(Les cinq points sont faits au 14/09/2026 ; le chantier est clos.)*

1. **Instances indépendantes** (E-02, décision D-20).
2. **Répartition de la brûlure** : `intdiv(stacks * 3, 2)` sur le bouclier, `intdiv(leftover * 7, 15)` du surplus sur les PV.
3. **Nettoyage par le soin** (décision D-21).
4. **Pénalité sèche de `SUNDERING` corrigée** (E-04).
5. **Code mort retiré** (E-07) : `EventDispatcher::dispatch()`, `getListenersFor()`, `Effect::intervalTicks`.

**Critères de sortie.** Tous verts au 14/09/2026 : bouclier de `shadow_armor` borné et consigné à 1 253 sur 500 ticks, encadrement du nombre d'instances par objet à statut, répartition de brûlure sur trois cas, aucun flottant introduit.

**Note ajoutée en 3.0.** Le critère « aucun flottant introduit » portait sur le code de ce chantier, et il est tenu. Il ne couvrait pas le flottant **préexistant** de `HeroSkillDecorator`, découvert au cadrage du 19/09/2026 et consigné en E-12.

**Branche.** `feature/status-instances`, fusionnée le 14/09/2026.

---

### Chantier 3 — Déclencheurs vivants et garde anti-cascade

**Objectif.** Faire exister un axe de conception d'objet autre que la valeur et le cooldown.

**Justification chiffrée.** Les 6 armes communes ont exactement le même débit par tick et par slot (0,5). Les 3 boucliers aussi (0,5), les 3 soins aussi (0,625). **Mais le pool commun n'est pas plat pour autant** : `venomous_vial` tue en 80 ticks là où `dagger` en met 220. Et **rien dans le moteur ne lit la cadence** : `Trigger` n'est lu nulle part (E-07).

**Référence de genre.** Dans The Bazaar, la profondeur vient du fait que les objets se déclenchent les uns sur les autres. Dans Backpack Battles, du placement et de l'adjacence. Dans Corebound, aucun objet ne parle d'un autre.

**Contenu.**
1. `Trigger` réellement lu par `TickEngine`. **Vocabulaire fermé à cinq entrées** : `EVERY_N_TICKS`, `ON_COMBAT_START`, `ON_SIBLING_TRIGGERED`, `ON_STATUS_APPLIED`, `ON_THRESHOLD`.
2. Garde anti-cascade : un objet se déclenche au plus une fois par tick, profondeur de chaîne bornée, résolution dans le tick.
3. Départage explicite de l'ordre d'activation **à l'intérieur d'un plateau**. `dispatchForItem()` itère un tableau associatif indexé par valeur de trigger : l'ordre suit aujourd'hui la séquence d'enregistrement, pas l'ordre de déclaration dans le JSON. **Jamais l'ordre d'itération d'un tableau associatif comme règle.** *(À ne pas confondre avec l'ordre **entre** plateaux, tranché par D-14.)*
4. Six à huit objets conditionnels ajoutés au pool Ombre. **Pas de réécriture des 30.**

**Maille d'adjacence retenue : le héros, pas une grille.** Le budget de slots étant individuel et jamais mutualisé, le couple d'objets d'un héros est déjà la maille combinatoire naturelle.

**Écarté explicitement.** Copier la grille spatiale de Backpack Battles.

**Contrainte renforcée par D-19.** Un objet conditionnel produira des événements nouveaux, donc des charges utiles nouvelles. Elles passent par le sérialiseur canonique : **clés triées, types restreints à `int`/`string`/`bool`**. Aucune ligne de flottant n'entre dans un payload.

**Critère de sortie.** La décision au marchand cesse d'être « quel objet a le meilleur rapport valeur sur cooldown » et devient « lequel se branche sur ce que j'ai déjà ». Si ce n'est pas le cas, c'est **le vocabulaire de déclencheurs qui est faux, pas le principe**.

**Branches.** `feature/live-triggers`, `fix/dispatcher-cascade-guard`.

---

### Chantier 4 — Actions manquantes du moteur

**Justification.** Quatre des sept primaires de Vestige n'existent pas dans le moteur : `Critical` (Ombre), `Slow` (Eau), `Hp max` (Terre), plus `Acceleration` en secondaire de trois affinités.

**Préalable explicite : le chantier 2.** Le critique tire dans le flux `effects` de D-22, qui n'existe qu'après.

**Contenu.** `HASTE`, `SLOW`, `MAX_HP`, et le critique comme ligne portée par chaque objet.

**Trois points à trancher avant le premier test rouge.**

1. **Plafond de PV mutable.** `CombatVestige::receiveHeal()` lit aujourd'hui `$this->definition->baseHp`, sur la définition immuable : le plafond doit migrer dans l'état de combat. Ordre imposé : **relever le plafond avant de soigner**. **Conséquence sur D-16 :** la photographie porte `baseHp` et `baseShield` ; un plafond mutable devra y figurer aussi, ce qui **fait évoluer la version de format du snapshot** — le mécanisme est prévu, l'usage est ici.
2. **Critique et déterminisme.** Le tirage vient exclusivement du flux **`effects`** dérivé au chantier 2. **Jamais du flux `order`** : une ligne de critique ajoutée à un objet ne doit pas décaler les ordres de passage.
3. **Départage total de la ligne de vitesse.** À valeur égale, un critère de repli déterministe explicite (identifiant d'objet, puis index de slot), **jamais l'ordre d'itération d'un tableau associatif**.

**Note d'équilibrage, révisée en 3.0.** L'enrage de `02` §7.3 inflige `5 × 2^n`, cumul `5 × (2^(k+1) − 1)`. Passer de 100 à 150 PV ne change souvent **aucun palier**. Le handicap de ×1,5 que la révision 2.0 imputait à l'ordre des plateaux **disparaît avec D-14**, l'enrage devenant simultané : deux Vestiges du même palier meurent ensemble et sont départagés sur leur état d'avant la phase, ce qui est symétrique. **Le problème de granularité des paliers, lui, subsiste** : il tient au doublement, pas à l'ordre. Les objets de PV max resteront forts en combat normal et faibles en stalemate.

**Contrainte de typage à respecter (D-19).** Un multiplicateur de critique exprimé en flottant ne peut pas entrer dans un payload. Suivre la doctrine de `takeBurnDamage()` : arithmétique entière, `intdiv`, arrondi au plancher en faveur du défenseur. **Voir aussi E-12** avant d'écrire une nouvelle ligne de calcul en flottants.

**Branches.** `feature/haste-slow-actions`, `feature/max-hp-stat`, `feature/critical-strikes`.

---

### Chantier 5 — Affinité à effet mécanique

**Préalables : chantiers 3b et 4.** Sans `Critical`, `Slow` et `Hp max`, quatre affinités sur sept n'ont pas de quoi exprimer leur primaire.

**Contenu.** Système à quatre relations de `02` §5.2, appliqué à la compétence du héros et à ses objets, selon la table de distance de `corebound-affinities` §1.

**Quel triplet pour le prototype.** `02` §5.2 recommande deux affinités, jamais sept. Avec trois Vestiges à J2, un triplet expose les quatre relations d'un coup :

> **Ombre, Feu, Métal.**

| Paire | Distance | Relation |
|---|---:|---|
| Ombre ↔ Ombre | 0 | Identique |
| Ombre ↔ Feu | 1 | Alliés |
| Feu ↔ Métal | 2 | Neutres |
| Ombre ↔ Métal | 3 | Ennemis |

**Vérifié le 8 septembre 2026** sur la table de `corebound-affinities` §1.

**Porte de sortie, non négociable.** `03` §2.4 : si le prototype démontre que l'affinité n'est qu'un multiplicateur de puissance sans effet sur les builds, **le système est repensé, pas étendu**.

**Point de vigilance ajouté en 3.0.** L'affinité s'appliquera par décoration, comme les compétences. Avec D-16 en photographie, **le résultat de cette décoration est figé dans chaque snapshot**. Un rééquilibrage de la table de relations ne rétroagira donc pas sur les fantômes déjà enregistrés — c'est l'intention, mais il faut le savoir avant de calibrer.

**Branche.** `feature/affinity-relations`.

---

### Chantier 6 — Compétences restantes

**Préalable : le chantier 2.** Passer de 10 à 16 héros change le nombre de tirages consommés et décale toutes les boutiques en aval (E-05). Sans `contentVersion` épinglée, tout journal de run existant devient irrejouable.

**Pool cible : 20 compétences.** Dix existent, dix restent à créer.

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
| **`AURIC`** | +% de valeur par tranche d'or non dépensé au début du combat | à créer |
| **`MENDING`** | +% valeur et −% cooldown sur les objets `HEAL` | à créer |

**`AURIC` et `OPENING` sont les deux compétences qui ont dicté le contenu du snapshot.** Toutes les autres agissent par décoration, donc leur résultat est déjà figé dans la photographie. Ces deux-là agissent **pendant le combat** : `OPENING` doit savoir qu'une activation est la première, `AURIC` doit lire l'or. C'est pour elles que D-16 embarque les héros avec leur compétence et le champ `goldAtCombatStart` — décidé au cadrage, avant que la compétence existe.

**Pourquoi `AURIC`.** Aucune des 18 compétences initiales ne touchait à l'économie, laissant le Doré sans signature de combat. `AURIC` reste un filtre appliqué à l'assemblage du plateau, donc `GAIN_GOLD` demeure écartée : rien n'est gagné en combat, l'or est un paramètre d'entrée.

**Pourquoi `MENDING`.** Le pool initial comptait environ dix compétences offensives contre cinq défensives.

**Point de vigilance.** `HULKING` et `SUNDERING` sont toutes deux réservées aux `TWO_HAND`, sur un pool qui n'en compte que 10 sur 30. Vérifier qu'elles ne deviennent pas la même compétence.

**Redondance à résoudre dans ce chantier.** `SAVAGE` (+20 % dégâts), `SUNDERING` (≈ +22,7 % DPS) et `RELENTLESS` (≈ +22,2 % DPS) sont trois compétences de DPS quasi équivalentes. En garder deux au maximum.

**Redistribution actée sur le roster cible.**

| Héros | Affinité | Avant | Après | Motif |
|---|---|---|---|---|
| Vardun | gold | `LINGERING` | `AURIC` | donne son primaire au Doré |
| Vessarin | water | `TITANIC` | `QUICKENING` | couple libre, secondaire de l'Eau |
| Anhe | vegetal | `TITANIC` | `MENDING` | la case soin manquante existe |
| Palo | vegetal | `OPENING` | inchangé | héros hors thème assumé |

**Cette redistribution n'est pas appliquée au fichier de roster** (voir §2).

**Point ouvert restant.** L'Ombre a 5 héros sur 5 alignés sur son primaire ou son secondaire et **aucun héros hors thème**.

**Test d'invariant.** NF-17 : au plus un héros par couple (compétence, affinité), vérifié automatiquement sur `heroes.json`.

**Filet existant à préserver.** Le `match` de `HeroSkillDecorator::decorate()` n'a pas de branche `default`. **Ne pas ajouter de `default`.**

**À traiter au passage.** E-12 : les nouvelles compétences à pourcentage ne doivent pas reconduire le calcul en flottants du décorateur.

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
3. Récompenses PvE. **Défaite PvE : rien** (D-02 tranchée).
4. Seconde phase de marchand.
5. Choix parmi trois marchands spécialisés.
6. Choix du Vestige parmi trois au démarrage.

**Décision à instruire pendant ce chantier.** **D-03**, durée d'une manche et d'une run.

**Point de surveillance en playtest.** Si le monstre facile est choisi dans plus de 70 % des manches, l'écart de récompense entre difficultés est trop faible.

**Protection à ajouter au passage.** Aucun garde anti-double-soumission n'existe sur `buyItem`, `swapItem` ni `resolveRound`. Un second clic parvenu après le commit du premier rejoue un journal à jour et **joue réellement une manche de plus** (§1.4).

**Deux conséquences du chantier 2 sur ce chantier.** Deux combats par manche signifient **deux `combatSeed` par manche** : l'encodage de D-22 devra distinguer le PvE du PvP dans son étiquette, et **c'est un changement de format, donc à décider ici et pas plus tard**. Et chaque manche enregistrera **deux** enregistrements de combat, pas un.

**Branches.** `feature/monster-entity`, `feature/monster-choice`, `feature/pve-rewards`, `feature/second-shop-phase`, `feature/vestige-choice`.

---

### Chantier 9 — Fusion d'objets

**Préalable : le chantier 3.** La ligne bonus du dernier rang est par définition conditionnelle. Elle n'est pas descriptible sans vocabulaire de déclencheurs.

**Préalables de décision : D-10 et D-11.**

**Vérification faite le 19/09/2026.** `ShopFactory::drawItems()` passe par `pickArrayKeys()`, sans remise, et le dernier slot exclut les identifiants déjà tirés : **les quatre offres d'une visite sont toujours distinctes**. Accumuler trois exemplaires d'un même objet ne peut donc venir que de visites successives, sur 10 manches et 4 offres. **La faisabilité économique de la fusion reste à simuler.**

**Note.** `MAX_LEGENDARY_OFFERS = 1` est un nom trompeur : le quatrième slot tire dans le catalogue complet privé des trois premiers, sans filtre de rareté. Il peut produire un commun.

**Contenu.**
1. Trois rangs : Bronze ×1, Argent ×1,75, Or ×2,25 plus ligne bonus.
2. Multiplicateur sur `value` uniquement.
3. Plafond par rareté : communs jusqu'à Or, rares jusqu'à Argent, légendaires non fusionnables.
4. Lignes bonus rédigées pour les 81 objets communs de la cible.

**Pourquoi le plafond par rareté.** Le modificateur de drop des légendaires est de ×0,015.

**Pourquoi D-11 est bloquante.** Un Argent coûte 20 or pour ×1,75, un Rare coûte 25 or pour ×1,5, à slot égal. **L'Argent domine strictement le Rare.**

**Point de méthode ajouté en 3.0.** Les multiplicateurs de fusion sont des décimaux — 1,75 et 2,25. **Ne pas les implémenter en flottants** : E-12 montre que `ceil(v × m)` diverge de l'arithmétique exacte dès que le multiplicateur n'est pas représentable en binaire. `intdiv(v * 7, 4)` et `intdiv(v * 9, 4)` sont exacts et ne coûtent rien. *(1,75 = 7/4 et 2,25 = 9/4 sont d'ailleurs tous deux exactement représentables, contrairement à 1,1 et 1,35 — mais la règle de doctrine vaut mieux que l'exception.)*

**Branche.** `feature/item-fusion`.

---

### Chantier 10 — Contenu du palier

**Préalable : le chantier 3b.** Application de R3.

**Ce chantier ne peut pas partir de l'analyse de la révision 1.0.** Trois de ses prémisses sont fausses ou incomplètes. Voir §4.

**Deux préalables de mesure.**
1. **Remettre à plat l'adversaire scripté.** Deux de ses trois compétences sont inertes et sa défense recule à la manche 7.
2. **Rejouer un combat de référence après le chantier 3b** et mesurer l'écart de durée.

**Préalable de mesure ajouté en 3.0.** **Instruire E-12 avant d'écrire une seule valeur.** Les divergences d'arrondi du décorateur apparaissent sur des valeurs que le catalogue actuel n'atteint peut-être pas, mais que ce chantier fera monter : la première divergence de `RELENTLESS` tombe à 50, celle de `SUNDERING` à 180. Calibrer sur un décorateur dont l'arrondi est faux à ces échelles, c'est produire des chiffres à corriger deux fois.

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

**Rôle des deux pools.** Le neutre est vu dans 7 runs sur 7 : il couvre les dix familles d'effet et reste volontairement plat. Les 20 objets d'affinité portent l'identité.

**Corrections à appliquer au pool existant avant tout ajout.**
1. Chaque affinité descend à 9 communs.
2. **Les 5 objets `shadow` rares** sont réécrits pour exprimer `Critical` et `Acceleration`. Les deux doublons stricts disparaissent.
3. `nightfang` est recalibré à ×2,5 comme ses pairs.
4. Le calibrage du poison est repris.

**Composition d'effets à viser.** Environ 50 à 55 % d'objets à composante offensive, 30 % défensive, 15 % de tempo.

**Séquencement des héros.** Le code passe de 10 à 16, **pas à 40**.

> **Alerte de séquencement pour la démo.** Le Vestige de J1 est l'Ombre. Trois de ses cinq héros cibles (Zasuk `BRUTAL`, Ysenn `KEEN`, Hakkeb `QUICKENING`) portent des compétences non implémentées, et le roster Ombre cible ne recouvre celui du code qu'à 2 sur 5. Soit les 8 héros de J1 sont complétés hors Ombre, soit le chantier 4 passe avant le contenu.

**Branches.** `feature/items-batch-NN`, `feature/heroes-batch-NN`, `feature/vestiges-fire-metal`.

---

### Chantier 11 — PvP asynchrone et backend

**Préalables : 1a, 2 et 8.** Le format de snapshot doit exister avant le premier commit PvP.

**Contenu.**
1. Publication du snapshot de plateau en fin de manche.
2. Endpoint d'appariement, strictement par numéro de manche.
3. Simulation serveur, retour du `CombatLog`.
4. Amorçage par adversaires d'archive via `ScriptedOpponentFactory`.
5. VPS Hetzner, palier alpha à ~6 € par mois (`04` §9.2).

**Décision à trancher avant la mise en ligne.** **D-05** : les adversaires de secours sont-ils annoncés comme tels ?

**Ce que le chantier 2 a déjà réglé ici.** D-14 et D-15 suppriment le résultat de match non spécifié : plus de `winner` nul, une règle de résolution identique des deux côtés, et l'attribution canonique de A et B qui garantit que le **même appariement produit le même vainqueur quel que soit l'ordre d'arrivée des deux plateaux**. Ces trois points n'ont plus à être instruits ici.

**Deux points à instruire ici, issus du cadrage.**
1. **Le `combatSeed` du PvP** est dérivé de l'identifiant d'appariement (D-22). Cet identifiant doit donc être **stable et enregistré** avant la simulation, pas généré à la volée.
2. **Le bassin d'appariement mêlera des `contentVersion` différentes.** Filtrer, ne pas filtrer, ou borner par ancienneté est la question de design reportée en §3.1.

**Règle d'intégrité rappelée, et étendue.** Une action n'est journalisée qu'**après validation réussie**. Depuis D-18, s'y ajoute : **l'issue d'un combat journalisée ne vient jamais du client.**

**Branches.** `feature/board-snapshot-publish`, `feature/pvp-matchmaking`, `feature/pvp-combat`.

---

### Chantier 12 — Mode hors ligne et bascule

`03` NF-10 exige une bascule manuelle testable **dès J2**, pas au moment du sunset. C'est aussi le différenciateur numéro deux de `01` §5.

**Contenu.**
1. Bascule dans le client : appel du sidecar `corebound-engine` à la place de l'API.
2. Corpus embarqué comme ressource du build, **jamais comme téléchargement externe** (NF-11).
3. Test de bout en bout sur poste vierge.

**Cible de corpus.** ≥ 5 000 snapshots répartis par manche et par palier de puissance, soit ~25 Mo embarqués.

> **Réserve de dimensionnement ouverte par D-16.** Ce budget d'environ 5 Ko par snapshot a été posé quand le snapshot était supposé être une recette de quelques identifiants. **Une photographie sérialise les `Item` décorés au complet** : effets, actions, valeurs, pour six objets. Le budget tient probablement, mais **il n'a pas été mesuré**. À vérifier dès que le format du chantier 2 produit son premier snapshot réel — c'est-à-dire bien avant ce chantier, et sans attendre.

**Branche.** `feature/offline-mode`.

---

### Chantier 13 — Migration PostgreSQL

`04` §6.2 : **avant J2, jamais pendant.** Le journal d'actions étant un format de données simple et non lié aux classes PHP, la migration est mécanique.

Sauvegardes quotidiennes hors site et **restauration testée au moins une fois** (NF-22).

**Ce chantier hérite de la table `schema_version`** posée au chantier 2 (D-18 volet 4), et c'est ici que l'outil de migration proprement dit est écrit.

**Branche.** `chore/postgresql-migration`.

---

### Chantier 14 — Passifs de plateau

**Ce chantier commence par une décision de design, pas par du code.** `02` §2.5 : aucune mécanique n'est spécifiée.

Dépend du chantier 8, puisque les passifs arrivent par récompense de monstre ou par marchand rare. Cible J3 : 10 à 15 passifs. Cible J4 : 30 à 40.

**Contrainte héritée de D-16.** Un passif modifie le plateau. S'il agit par décoration, son résultat est figé dans la photographie. S'il agit pendant le combat, **le snapshot doit le porter**, comme il porte déjà les compétences de héros. À trancher au moment de la conception, pas de l'implémentation.

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
| Solder les écarts documentaires de la §2 | §2 | **Plus rien.** `04` et `06` étaient les deux seuls bloquants pour le chantier 2 ; tous deux sont soldés le 19/09/2026. Ce qui reste — les deux documents de packaging obsolètes et le fichier de roster — ne bloque aucun chantier |

---

## 8. Points irréversibles

**Neuf, contre cinq en révision 2.0.** Tout le reste se corrige.

| # | Point | Pourquoi irréversible |
|---:|---|---|
| 1 | **`engineVersion` dans chaque snapshot**, dès le premier commit PvP | L'ajouter après coup invalide le corpus déjà produit. **Non supprimé par D-16** : la photographie fige les chiffres, pas les règles — un fantôme rejoué sous un moteur plus récent combat sous des règles nouvelles |
| 2 | **`contentVersion` dans le snapshot et dans l'enregistrement de run** | `engineVersion` seul ne suffit pas : le moteur peut être identique et le catalogue différent. **Indispensable sur la run** — `GameRunReplayer` reconstruit offres, boutiques et prix depuis le catalogue, ce que la photographie ne change pas. Dans le snapshot, elle n'est plus que de la provenance |
| 3 | **Le schéma de dérivation du seed de combat** (D-22) | Deux flux `order` et `effects`, `sha256(tag ‖ combatSeed)` tronqué à 16 octets, `combatSeed` en hexadécimal 64 caractères, entiers encodés en décimal ASCII avec séparateur `\|`. **Changer l'un de ces cinq éléments rejoue tout le corpus différemment.** C'est le point que la révision 2.0 manquait : elle inscrivait « randomizer dérivé » sans voir que la **fonction** était elle aussi un format |
| 4 | **`goldAtCombatStart` dans le snapshot**, sans condition | Tranché au cadrage. Un champ contre une décision bloquante en moins ; D-12 sort du chemin critique |
| 5 | **Journaliser une action seulement après validation réussie** | Vérifié tenu au 8 septembre 2026 |
| 6 | **L'attribution canonique de A et B** (D-19) | Snapshot canonique le plus petit en octets, égalité départagée par l'identifiant de combat enregistré. Si l'attribution change, l'initiative de chaque tick change, donc le vainqueur peut changer. **Dépend de la forme du snapshot** : la changer après coup exige de réattribuer tout le corpus |
| 7 | **Le format canonique de sérialisation du `CombatLog`** (D-19) | Tri des clés, types autorisés, enveloppe, options JSON. C'est l'objet même de la comparaison octet pour octet d'EX-J0-01 |
| 8 | **La forme photographie du snapshot et sa version de format propre** (D-16) | Le choix photographie contre recette ne se révise pas après production du corpus. La **version de format** est ce qui rend les évolutions ultérieures survivables — l'omettre est ce qui serait irréversible |
| 9 | **L'issue de combat enregistrée dans le journal de run** (D-18, E-11) | Une run journalisée sans son issue est irrejouable dès que le moteur change. Les runs déjà en base sont dans ce cas : leur sort relève du rejet d'avant J1 |

**Quasi-irréversible.** La règle de résolution de D-14. La changer après production du corpus rejoue tous les snapshots avec des `CombatLog` différents, ce qui viole NF-01 et invalide le corpus. C'est la raison pour laquelle elle a été tranchée au cadrage, avant tout code.

**Un changement de format encore devant nous, et connu.** Le chantier 8 introduit deux combats par manche, donc deux `combatSeed`. L'étiquette de D-22 devra les distinguer. **Ce point appartient au format irréversible numéro 3 et doit être décidé au chantier 8**, pas découvert au chantier 11.

---

## 9. Ce qui n'est pas dans cette roadmap, et pourquoi

| Élément | Motif |
|---|---|
| **Rang Diamant de fusion** | Écarté au GDD §9, confirmé par l'échelle à trois rangs |
| **Les quatre affinités restantes** | Arrivent à J3, après la porte de sortie du chantier 5 |
| **Recalibrage de l'enrage** | Ne peut se faire que par playtest sur la boucle cible. Pertinent après le chantier 8. **Précision de la révision 3.0 :** le handicap de ×1,5 disparaît avec D-14, l'enrage devenant simultané. Ce qui reste à recalibrer, c'est la **granularité des paliers**, qui tient au doublement et non à l'ordre |
| **Fragmentation de budget de `HeroItemAllocator`** | Dette sans conséquence tant que les héros ont 2 slots et que le catalogue est petit |
| **Grille spatiale à la Backpack Battles** | Écartée : refonte du plateau, snapshot plus lourd, terrain de comparaison à éviter |
| **Politique de rétention des snapshots après un patch** | Reportée à J1 par D-18 volet 3. Avant J1 les runs sont des runs de développement, et le rejet suffit |
| **Filtrage du bassin d'appariement par `contentVersion`** | Question de design du chantier 11. Rien à trancher avant que le PvP existe |
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

**Leçon de l'audit du 8 septembre 2026.** Deux affirmations de la révision 1.0 étaient fausses non pas parce que les données avaient été mal lues, mais parce que **le code qui consomme ces données n'avait pas été lu**. « `SUNDERING` modifie 14 objets » comptait correctement les objets à `DEAL_DAMAGE` sans voir que le décorateur les filtre sur `TWO_HAND`. « Les 8 objets `shadow` sont des copies ×1,5 » généralisait un motif vrai sur 4 entrées.

**Leçon du cadrage du 19 septembre 2026 — la même, une révision plus tard.** L'argument qui fondait D-16, « l'association héros ↔ objet est perdue, donc la recette est la seule forme viable », était faux pour exactement la même raison : `CombatBoard` avait été lu, `CombatBoardFactory` ne l'avait pas été. La conclusion avait survécu à une révision entière et se serait transformée en format irréversible.

> Vérifier une donnée ne suffit pas. Il faut lire le code qui la consomme — **et le code qui la produit.**

**Trois leçons de méthode ajoutées par ce cadrage.**

1. **Une décision se reconnaît à ce qu'elle a un format.** D-22 n'a été identifiée que parce qu'on a demandé « quelle fonction, exactement ? » à une ligne qui disait « randomizer dérivé ». Toute formule qui sera stockée ou hachée est un format, donc une décision.

2. **Une contradiction interne dans un document est un signal, pas une coquille.** La formulation de D-12 disait une chose et sa justification l'inverse. Ce n'était pas une faute de frappe : c'était une décision jamais tranchée qui s'était figée en deux moitiés incompatibles.

3. **Une anomalie non signalée par deux révisions successives peut être la plus lourde.** E-11 ne figurait ni dans la révision 2.0 ni dans la note de passation. Elle conditionne pourtant la rejouabilité de tout journal de run, et c'est elle qui a fait passer le chantier 2 de M à L.