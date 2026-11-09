# Corebound — Résumé de session 024

**Branches** : `docs/live-triggers-framing` (cadrage, fusionnée avant le code), puis `feature/live-triggers`, **ouverte**
**Chantier** : 3 — déclencheurs vivants et garde anti-cascade (`07` §6, rang 7). **Cadrage fait, brique 1a commitée, brique 1b à faire**

> **Rédigé le 09/11/2026**, sur le corpus fusionné (`02` 3.5, `04` 2.16, `07` 3.15, `00` 2.2) et sur les sorties de `check-all.ps1` envoyées en séance. **Le commit de la brique 1a est fait**, confirmé par Laurent le 09/11/2026.

---

## Contexte et objectif

Les rangs 1 à 6 de `07` sont clos (session 023). Le chantier 3 est le premier depuis le chantier 2 à rouvrir **les règles du combat**, et non leur infrastructure.

Son constat de départ est E-07 : **`Trigger` n'est lu nulle part.** Chaque objet porte un déclencheur dans `items.json`, et le moteur l'ignore. Tous s'activent à la recharge de leur cooldown, quel que soit le déclencheur écrit. Les 6 armes communes ont le même débit par tick et par slot (0,5). La seule décision au marchand est donc « quel objet a le meilleur rapport valeur sur cooldown ». Le chantier veut qu'elle devienne « lequel se branche sur ce que j'ai déjà ».

## Le cadrage (02 et 03/11/2026)

Sur `docs/live-triggers-framing`, fusionnée avant tout code (`06` §6.1). Six décisions, aucune décision ouverte.

- **D-23 — le vocabulaire.** Cinq valeurs.
  - Deux cadences. `EVERY_N_TICKS`. `ON_COMBAT_START` : l'objet entre rechargé, agit au tick 1, puis suit son cooldown. C'est une activation ordinaire.
  - Trois déclencheurs réactifs : `ON_SIBLING_TRIGGERED`, `ON_STATUS_APPLIED`, `ON_THRESHOLD`.
  - `ON_ATTACK` se fond dans `EVERY_N_TICKS`. `ON_KILL` et `ON_DEATH` sont retirés.
  - **Un objet ne porte qu'un déclencheur.**
- **D-24.** Le cooldown d'un objet réactif devient son intervalle minimal.
- **D-25 — le moment d'une réaction et la garde anti-cascade.**
  - Une réaction à un voisin agit **au même tick**. Une réaction à un statut ou à un seuil agit **au tick suivant**.
  - Aucune phase nouvelle dans le tick.
  - Profondeur 1, et une activation au plus par objet et par tick. Ce sont deux règles écrites, pas une conséquence de l'implémentation.
- **D-26 — le sens des trois déclencheurs.**
  - Voisin : un autre objet du même héros.
  - Statut : n'importe quel statut, posé par un objet de son propre plateau sur n'importe quelle cible, sans filtre, avec une fenêtre d'un tick.
  - Seuil : un **état**, `PV × 2 < PV de base`, bouclier exclu, une fois par combat.
- **D-27 — l'événement `ITEM_REACTED`.** Ses champs sont plats : `sourceSide`, `sourceItemId`, `trigger`. Selon le déclencheur s'y ajoutent `causeItemId`, `causeStatus`, `causeTargetSide`, `causeHp` et `causeBaseHp`.
- **D-28 — la photographie range chaque objet sous son héros** (`heroes[].items[]`). `BoardRecord::FORMAT_VERSION` passera à 2. **Cette décision n'était pas au programme.** Elle change un format irréversible (`07` §8, point 8), et c'est elle qui fait passer le chantier de M à L.

**Ce que le cadrage a ajouté au corpus.**

- `07` §8 gagne un **onzième point irréversible : les valeurs de `Trigger`**. L'hydrateur refuse une valeur inconnue (`tryFrom`). Ajouter une valeur ne casse donc rien, mais en retirer une rend illisible toute archive qui la porte. C'est pourquoi les trois valeurs sortent maintenant, avant qu'un corpus existe.
- `07` §4.2 gagne **E-16**. `buildParticipantResolver` indexe les objets par `side:itemId`, si bien que le même objet porté par deux héros est attribué au mauvais.
- `04` §11 consigne quatre options écartées : une phase de réaction propre, un second compteur de cadence, la recette relue au rejeu, et une cause imbriquée dans l'événement.

**Ordre des briques** (`07` §6, indicatif) :

1. Le vocabulaire.
2. La photographie au format 2.
3. Le déclencheur lu, avec `EngineVersion` passé à 3, une seule fois pour la branche.
4. `ON_SIBLING_TRIGGERED`, `ITEM_REACTED` et la garde.
5. `ON_STATUS_APPLIED`.
6. `ON_THRESHOLD`.
7. Le frontend.
8. Les objets neutres.

La brique 7 doit précéder la brique 8 : `formatCombatEvent` et `formatItemEffect` lèvent tous deux une exception sur un type ou un déclencheur inconnu.

## La brique 1 coupée en deux

La brique 1 de `07` réunit deux gestes : réduire l'enum, et refuser un objet à deux déclencheurs. Ils ont été séparés, puisqu'un commit porte une brique logique (`06` §6).

### Brique 1a — le vocabulaire (05/11/2026), **commitée**

- `Trigger` passe à cinq valeurs, avec un docblock. Il y dit qu'il est un vocabulaire fermé devenu un format, et qu'aucune valeur n'est lue avant la brique 3.
- `TriggerTest` (nouveau) fige la liste ordonnée des cinq valeurs.
- `JsonItemRepositoryTest::testTheGameCatalogueLoadsEntirely` charge le **vrai** `config/game/items.json` par `findAll()`. Jusqu'ici, tous les tests du fichier lisaient une fixture. Rien n'aurait donc nommé la panne si le catalogue contenait une valeur retirée : `ShopFactory::createShop()` charge tout le catalogue dès la première boutique.
- Dans `items.json`, **18 objets** passent d'`ON_ATTACK` à `EVERY_N_TICKS`. Aucune valeur ne change, et l'absence de saut de ligne final est conservée.
- **Neuf fichiers de test** passent d'`ON_ATTACK` à `EVERY_N_TICKS`, littéraux et constantes `PHOTOGRAPH` compris : `SimulatorTest`, `BoardHydratorTest`, `BoardSnapshotTest`, `BoardRecordTest`, `CombatRecordTest`, `CombatRecordsRepositoryTest`, `EffectPresenterTest` et `ItemPresenterTest`, plus `EventDispatcherTest`, traité à part ci-dessous.
- **Deux tests portaient un objet à deux déclencheurs**, ce que D-23 interdira : `SimulatorTest::twoTriggerItem` et le test d'`EventDispatcherTest` qui expose chaque effet d'un objet. Leur second déclencheur passe provisoirement à `ON_COMBAT_START`. Ils seront réécrits à la brique 1b.
- **La fixture de référence est régénérée** par son outil, `capture-reference-combat.php --seed=46 --round=6`. `board-a`, `board-b` et `meta.json` changent, et `contentVersion` devient `0835dbe4…f67a38`. **`combat-log.json` est identique à l'octet près** (9 826 octets ; 48 ticks ; KNOCKOUT, vainqueur B). C'est attendu, puisque rien ne lit encore le déclencheur.
- **`EngineVersion` ne bouge pas** (reste à 2) : aucun fichier sous `Domain/Engine/` n'est touché.

**Rouges vus.** `TriggerTest` a échoué sur l'ancienne enum. Le test du catalogue a été vu échouer sur le vrai catalogue (`ValueError` sur `ON_ATTACK`), après que `Trigger.php` seul a été posé.

### Brique 1b — la règle d'un déclencheur par objet, **à faire**

**Option A retenue par Laurent** : la règle vit dans le constructeur d'`Item`.

Ce qui l'accompagne :

- `BoardHydrator` réemballe le refus en `UnreadableBoardRecordException`, avec le chemin du champ en cause.
- Le test à deux déclencheurs d'`EventDispatcherTest` est réécrit avec deux effets au même déclencheur.
- `SimulatorTest::twoTriggerItem` est réécrit. Il est le vrai ancrage d'E-15 (`testTheLogDoesNotDependOnTheOrderTheBoardsArePassedIn`), et son docblock doit le dire.

**Fichiers demandés et pas encore reçus** : `Item.php`, `Effect.php`, `BoardHydrator.php`, `HeroSkillDecorator.php`, et la sortie de `git ls-files backend/tests | Select-String "ItemTest"`, pour savoir si un `ItemTest` existe.

## Mesures

| Mesure | Valeur | Ce qu'elle établit |
|---|---|---|
| Fichiers de la brique 1a | 16, dont 15 modifiés et 1 nouveau | Le diff ne touche ni `Domain/Engine/` ni le frontend |
| Objets renommés | 18 sur 30 | Aucune valeur d'objet modifiée |
| Journal de la fixture | 9 826 octets, identique avant et après | La régénération ne change que les enveloppes |
| Suite backend | **494** tests, 1 713 assertions, 2 ignorés (parité) | +2 tests depuis la session 023 : `TriggerTest` et le catalogue |
| `check-all.ps1` | Vert de bout en bout | Frontend et desktop inchangés : 81 et 36 |

## Erreurs commises et rattrapées

Même forme qu'en session 023 : une affirmation produite sans lire ce qui s'exécute.

- **Une cause imbriquée dans `ITEM_REACTED`.** Proposée au cadrage, elle est impossible : la charge utile d'un événement est plate par contrat (D-19), et `CombatLogSerializer` refuse un tableau imbriqué. Remplacée par les champs `cause…`, et retirée explicitement en `04`.
- **« Le test d'E-15 » désignait le mauvais test.** Le test à deux déclencheurs d'`EventDispatcherTest` n'est pas celui d'E-15. Le vrai ancrage est `SimulatorTest::testTheLogDoesNotDependOnTheOrderTheBoardsArePassedIn`, qui utilise `twoTriggerItem`. Corrigé en `04`.
- **Un docblock faux sur le test du catalogue.** Il disait que la boutique ne charge qu'une partie du catalogue. Or `ShopFactory::createShop()` le charge en entier par `findAll()`. Corrigé avant la livraison.
- **Une commande PHPUnit fausse, deux fois.**
  - Sous PowerShell, `vendor\bin\phpunit --filter "A|B"` passe par un `.bat`, et `cmd` lit `|` comme un tube.
  - `php vendor/bin/phpunit --filter …` sans répertoire n'affiche que l'aide.
  - **La forme qui marche** : `php vendor/bin/phpunit tests --filter "TriggerTest|JsonItemRepositoryTest"`.
  - À verser en `06` §10.
- **Un `git grep` qui ne cherchait rien.** Il a d'abord été lancé depuis la racine avec des chemins relatifs à `backend/`. Il a ensuite manqué `TriggerTest`, qui n'était pas encore suivi par Git. `git grep` ne voit que les fichiers suivis.
- **Une exécution de Laurent mal lue.** Il avait lancé la capture après avoir posé `Trigger.php` seul, ce qui était le bon ordre. Elle a été jugée prématurée, à tort.
- **PHP CS Fixer a « corrigé » deux fichiers livrés.** Les fichiers renvoyés étaient identiques à l'octet près à ceux livrés. C'est une question de fins de ligne au transport, pas une règle de style.

## Corpus

- `02` passe en **3.5** : §7.7, la règle des déclencheurs. En §7.6, l'écart d'attribution resté en arrière depuis E-14 est soldé.
- `04` passe en **2.16** : §3.8 est nouvelle, D-28 entre en §5.5, et quatre options écartées en §11.
- `07` passe en **3.15** : D-23 à D-28 en §3.1, le chantier 3 réécrit en §6, E-16, et le point irréversible 11.
- `00` passe en **2.2** : D-23 à D-28 en §4.1.

**Rien n'est encore versé pour la brique 1a.** Elle se documente au commit de clôture de branche (`06` §6).

## État final

**Backend : 494 tests PHPUnit, 1 713 assertions**, dont 2 ignorés hors du job de parité Windows. **Desktop : 36. Frontend : 81.** `EngineVersion::CURRENT = 2`, `BoardRecord::FORMAT_VERSION = 1`, tous deux inchangés à ce stade.

## Ce qui reste ouvert

**Dans la branche :**

- La brique 1b, puis les briques 2 à 8.
- **Le docblock d'`EventDispatcher`** cite à tort `04` §3.4. Il est à corriger à la brique 3 : c'est le cinquième écart de `07` §2.
- **Trois questions à trancher.**
  - Le libellé des 18 objets renommés, à la brique 7. `formatItemEffect` affiche aujourd'hui « Vitesse d'attaque » pour `ON_ATTACK` et « Toutes les Xs » pour `EVERY_N_TICKS`.
  - Refuser ou non un statut à moins d'une pile, avant la brique 5 : `ActiveStatus` ne valide pas ses piles.
  - Le son d'`ITEM_REACTED`, à la brique 7 : `combatEventSound` rend `null` par défaut.
- **Dans `07`, la question du libellé** est rangée « avant la brique 1 ». Elle appartient à la brique 7 et sera déplacée au commit de clôture.
- **`06` §10** : le piège du filtre `|` de PHPUnit sous PowerShell.

**Hérités, inchangés depuis la session 023 :**

- La porte de CI sur l'empreinte de contenu.
- Prettier vérifié par aucun job.
- Le relais de `/runs`.
- Le menu par défaut d'Electron.
- L'audit de licences C-02.
- Le retrait de `windows-2022`, les actions de CI sur Node 20, et `ubuntu-latest` qui passe à Ubuntu 26.
- Les anomalies E-14 et E-16.

## Changement d'outil à partir de la session suivante

Laurent prévoit de poursuivre dans **Claude Code**, en terminal, avec accès direct au dépôt.

**Ce qui change :** les fichiers se lisent sur place au lieu d'être envoyés.

**Ce qui ne change pas :**

- La hiérarchie d'autorité de `00` §2.
- Les règles de `06` : TDD avec le rouge vu à l'exécution, une brique par commit, `check-all.ps1` avant chaque commit.
- Un fichier qui manque se demande, au lieu d'être supposé.

**Les commits ne portent aucune ligne d'attribution.** C'est la préférence de Laurent, et elle vaut aussi dans Claude Code.

## Prochaine session

**Brique 1b**, sur `feature/live-triggers` :

1. Lire `Item.php`, `Effect.php`, `BoardHydrator.php` et `HeroSkillDecorator.php`, et chercher un `ItemTest`.
2. Écrire le test rouge du constructeur d'`Item`.