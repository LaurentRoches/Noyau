# Corebound — Résumé de session 021

**Branche** : `feature/versioned-combat-snapshot` (suite et fin)
**Chantier** : 2 — snapshot versionné et déterminisme, **terminé**

---

## Contexte et objectif

La session 020 laissait le chantier à douze commits sur quatorze : restaient l'issue de combat enregistrée et le test de rejeu octet pour octet, celui qui franchit le critère de sortie EX-J0-03.

Il en aura fallu **sept de plus que les deux annoncés**, et la raison est la seconde leçon de cette session : le test de rejeu **ne pouvait pas être écrit**. Pas parce qu'il manquait une classe, mais parce que `CombatVestige` et `CombatHero` réclamaient des entrées de catalogue complètes. Rejouer une archive aurait donc exigé d'inventer un nom, une affinité et un nombre d'emplacements que la photographie ne porte pas et n'a aucune raison de porter. Le blocage n'était pas dans le test, il était dans le domaine — et il ne s'est vu qu'en cadrant le test.

**Compte final de la branche : 26 commits, dont 5 documentaires. Des 21 commits de code, 19 appartiennent à la liste du chantier 2**, pour 14 annoncés ; les deux autres sont la caractérisation de la fureur, qui solde le chantier 0, et un commit de formatage isolé imposé par le piège de transport de `06` §4.4.

## Ce que les sept commits ont produit

**L'issue de combat enregistrée** (D-18 volet 1, E-11). L'issue d'une manche est écrite dans la charge utile de `RESOLVE_ROUND` sous la forme d'un enum `RoundOutcome`, et le rejeu **l'applique** au lieu de resimuler. Le chemin `applyRecordedRound()` est réservé à `GameRunReplayer` ; `RunController::resolveRound()` simule toujours lui-même et ignore tout champ d'issue venu de la requête — une issue acceptée du client serait une victoire déclarée par le joueur. Les deux chemins partagent **une seule** transition de fin de manche : deux copies, et un run rejoué n'aboutirait plus au même état qu'un run joué, sans qu'aucun test unitaire ne le signale.

**L'enregistrement de combat assemblé à chaque manche.** `CombatRecord` porte les deux enveloppes rangées **par côté** — jamais « joueur d'abord » —, la graine du combat, la résolution et le côté vainqueur. Il estampille lui-même `engineVersion` au lieu de la recevoir : un enregistrement n'existe qu'à l'instant où le combat est simulé, donc la version courante *est* la bonne, et la faire passer par un paramètre ouvrirait la seule façon de se tromper.

**L'archive en base** (schéma version 3, table `combat_records`). Les deux enveloppes sont stockées telles que `CanonicalJson` les écrit, dans deux colonnes `TEXT`. Les éclater en colonnes SQL rendrait le format réinventable à la relecture et lierait le schéma aux évolutions d'un snapshot — or c'est ce format-là, et lui seul, que le moteur embarqué devra reproduire. Le dépôt est en **écriture seule** : personne ne relit encore ces lignes, et une forme de retour écrite aujourd'hui serait figée avant qu'on sache ce qu'on en attend.

**Deux ports de profil de combat** (`VestigeProfile`, `HeroProfile`). `CombatVestige` et `CombatHero` ne retiennent plus une entrée de catalogue mais les cinq valeurs qu'un combat consomme réellement. Les ports vivent dans `Domain\Runtime` — **chez le consommateur** — et `Vestige`/`Hero` s'y conforment par des accesseurs qui rendent des champs déjà publics.

**L'hydrateur** (`BoardHydrator::fromCanonicalJson`). Il ne reçoit **qu'une chaîne** : pas de tableau déjà décodé, pas de chemin de configuration, pas de dépôt. L'isolement est structurel et non déclaratif — une signature qui n'accepte qu'un `string` rend *impossible*, et pas seulement déconseillée, la relecture d'un catalogue au rejeu.

**E-15 — l'ordre des effets d'un objet dépendait de l'ordre des arguments.** Voir plus bas : c'est la découverte de la session.

**Le rejeu de référence** (`tests/Determinism/`). Deux tests de nature différente, et c'est délibéré. `ReferenceCombatReplayTest` relit quatre fichiers figés, hydrate et resimule : ancre de non-régression du moteur, elle rougit si un octet du journal bouge. `ArchivedCombatReplayTest` joue une vraie run, l'archive, relit la ligne en SQL direct et compare le combat **à lui-même** : aucune constante figée, donc un rééquilibrage du chantier 10 ne peut pas le faire rougir, et il garde ce que la fixture ne peut pas garder — que le chemin d'archivage écrit aujourd'hui quelque chose de relisible.

## E-15 — la découverte de la session

En cadrant le rejeu, une vérification de routine sur le moteur réel a montré que `run($a, $b)` et `run($b, $a)` produisaient **deux journaux différents à l'octet près**, sur les mêmes plateaux et la même graine. NF-01 ne tenait pas.

**La cause.** `EventDispatcher` indexait ses écouteurs par `Trigger`, et `Simulator::run()` enregistre les deux plateaux dans l'ordre de ses arguments : les clés de cette table se créaient donc dans l'ordre des déclencheurs rencontrés. Pour un objet portant **deux `Trigger` différents**, l'ordre de ses effets dépendait de quel plateau s'était enregistré le premier — et de quels déclencheurs portait l'objet de l'autre plateau. Ce n'est pas une permutation cosmétique : bouclier avant dégâts ou l'inverse change qui meurt quand, donc le déroulé entier.

**Trois documents l'avaient vue sans la voir.** `06` §3 la rangeait depuis sa révision 2.1 parmi trois risques d'ordre d'itération, « ouvert, chantier 3 ». `04` §3.3 la décrivait comme une dette qui « devient réelle au chantier 3 ». Et `04` §3.6 affirmait que l'ordre canonique de `getBoards()`, posé cinq jours plus tôt, avait refermé la dépendance à l'ordre des arguments — il en avait refermé **une sur deux**, la seconde vivant un cran plus bas.

**Elle était latente, pas absente.** Aucun des trente objets du catalogue ne porte deux déclencheurs, et `HeroSkillDecorator` n'en crée pas : il mappe les effets un à un en conservant leur `trigger`. C'est précisément ce qui la rendait invisible, et c'est aussi ce qui a rendu la correction gratuite — `EngineVersion` passe de 1 à 2 sans qu'aucun journal produit avec le catalogue du jour ne change, ce qui ne sera plus vrai une fois un corpus produit.

**Le correctif.** Liste plate à la place de la table indexée. L'ordre des effets devient celui que l'objet porte, donc celui que la photographie archive, et il ne dépend plus de rien d'autre — pas même de l'ordre canonique des côtés. Réordonner l'enregistrement aurait refermé le cas connu ; supprimer la dépendance referme la classe entière.

**Le test épingle les octets du journal et non l'issue**, parce que dans le cas mesuré les deux journaux désignent le même vainqueur avec les mêmes chiffres de départage. Une divergence qui ne se voit nulle part ailleurs est exactement celle qui traverserait une relecture.

## Décisions prises à l'écriture

**`formatVersion` décide, `engineVersion` pas.** Le cadrage de l'hydrateur avait tranché l'inverse. Le docblock d'`EngineVersion` dit pourquoi c'était faux : l'une dit avec quelles règles le journal a été produit, l'autre comment lire l'enveloppe. Reconstruire un plateau est une question de format ; le droit de **resimuler** appartient à l'appelant, qui le lit dans la colonne `engine_version` sans décoder quoi que ce soit. Un test épingle la correction, faute de quoi la prochaine lecture du fichier la « corrigerait » à son tour.

**L'archive est écrite après le journal, et l'ordre est un choix.** Le journal est la vérité de la run, l'archive n'est que de la provenance : un échec d'archivage laisse un trou réparable. Dans l'ordre inverse, un échec du journal laisserait une archive pour une manche que le rejeu ignore, et la reprise du client se heurterait à la clé composite `(run_id, round)` — un 500 permanent.

**Les ports plutôt que les trois autres options, et le coût a été mesuré avant de trancher.** Faire entrer les valeurs nues dans les constructeurs de combat aurait réécrit **soixante-six sites de construction dans seize fichiers de test**. Garder le constructeur existant et ajouter une fabrique aurait exigé soit d'inventer un `Vestige`, soit d'en rendre le constructeur privé — c'est-à-dire le coût de la première option. Le découplage par ports n'a touché aucun site de construction.

**La fixture sort d'une vraie run, et le choix a été fait sur mesure.** Deux options étaient sur la table : une manche réelle, ou des plateaux montés à la main pour couvrir les dix types d'événement. Un balayage de soixante-dix combats a tranché — voir les mesures. La manche réelle l'emporte parce que c'est elle qui prouve que le chemin d'archivage produit des archives rejouables ; la couverture est le second critère, pas le premier.

**L'outil de capture est committé avec la fixture.** Sans lui, quatre fichiers que personne ne sait reproduire, et le message d'échec du test — « régénérer la fixture avec `capture-reference-combat.php` » — deviendrait un mensonge. Règle versée en `06` §8.

## Mesures

| Mesure | Valeur | Ce qu'elle établit |
|---|---|---|
| `run($a, $b)` contre `run($b, $a)`, objet à deux déclencheurs | **2 707 octets de chaque côté, 18 événements, divergents au 4ᵉ** | E-15 : NF-01 ne tenait pas |
| Journaux sous l'ancien et le nouveau dispatcher, à ordre d'appel identique | **Identiques sur cinq formes de combat** | La correction ne déplace aucune valeur figée, bouclier de référence de 1 253 compris |
| Combats PvE balayés — 6 graines × 12 manches | **70, tous en KO, le plus long à 192 ticks sur 500** | La fureur (tick 450) et le départage ne sont **jamais exercés** en conditions réelles. Pour le chantier 10 |
| Couverture de la fixture de référence | **6 types d'événement sur 10** | Limite structurelle, pas un défaut de choix de graine |
| Fixture retenue — graine 46, manche 6 | 48 ticks, 53 événements, 1 181 + 1 896 octets d'enveloppes, 9 826 de journal | Combat **miroir** : deux `shadow_vestige`, seul le côté les distingue |
| Aller-retour photographie → enveloppe → plateau → photographie | **Identique à l'octet près** sur 9 scénarios, dont un à 1 884 événements couvrant les 10 types | La photographie est sans perte pour le journal, statuts compris |
| Suite de tests | 419 → **472** tests, 1 543 assertions | PHPStan de 92 à 101 fichiers |

## Erreurs commises et rattrapées

Consignées parce qu'elles sont instructives, pas par scrupule. Elles se ressemblent toutes : une affirmation produite sans être vérifiée.

- **Cause inventée pour les corrections de CS Fixer.** Il corrigeait à chaque passage exactement les fichiers livrés. J'ai avancé une conversion LF → CRLF, avec un raisonnement qui tenait debout. Les sept `md5sum` l'ont démentie : les fichiers étaient **identiques à l'octet près**. L'explication restante — le saut de ligne final perdu au transport puis réparé par le fixer — n'a jamais été confirmée, et le sujet a été clos sans l'être. Une hypothèse plausible avancée comme un diagnostic.
- **`02` §9 et `07` §6 accusés de se contredire.** Affirmé deux fois dans la session, et versé dans la liste du commit documentaire. **Les deux étaient corrigés depuis des jours** — `07` le 21/09, `02` en sa révision 3.3. Je recopiais le docblock de `CombatBoard.php` sans ouvrir les documents : le niveau 1 de `06` §1.2, exactement la règle que ce document appelle « la plus souvent violée et la plus coûteuse ». Le fichier périmé était le **code**, et c'est lui qui a été corrigé.
- **Compte de commits estimé au lieu d'être compté.** « 18 commits pour 14 annoncés » a été écrit dans `07` et le README avant tout `git log`. Le chiffre réel est **19 commits de code sur 26**. Une reconstitution à partir des compteurs de tests n'est pas une mesure.
- **Mécanisme du rouge mal prédit.** Annoncé que `expectException()` recevrait un `Error` à la place de l'exception attendue, donc des échecs. PHPUnit 12 résout la classe attendue **par réflexion à l'appel** : il échoue avant d'atteindre la ligne testée, et sort en erreurs. Classification juste par accident, mécanisme faux. Piège versé en `06` §4.4.
- **Instances de `CombatItem` partagées dans un harnais de mesure.** Deux scénarios de rejeu ont été déclarés divergents à tort : le même objet servait deux combats et les deux plateaux, or un `CombatItem` porte un cooldown mutable. Le défaut était dans l'instrument, pas dans le code mesuré — et l'instrument avait produit une conclusion alarmante sur un format irréversible.
- **Chemin de fichier inventé.** `CombatSeed` demandé sous `src/Domain/Snapshot/` ; il est dans `App\Application`.
- **Commande PowerShell fausse.** `Select-String -Recurse`, qui n'existe pas. La bonne forme passe par `Get-ChildItem … | Select-String`.

## Corpus

`02` passe en **3.4** : §7.2 décrivait l'ordre des effets d'un objet comme une « dette latente, toujours ouverte » qui « devient réelle au chantier déclencheurs vivants ». Elle était réelle depuis l'origine. Le paragraphe devient une **règle nommée** — les effets d'un objet s'exécutent dans l'ordre où l'objet les déclare, et cet ordre ne dépend de rien d'autre — avec sa conséquence de conception pour le chantier 4.

`04` passe en **2.7** : §3.6 est corrigée par un encadré, §3.1 et §6.2 passent du futur au présent — elles décrivaient encore comme « cible » des signatures et une table qui existent —, §5.6 est nouvelle et porte le chemin de retour, §5.3 relève `engineVersion` à 2, §10 note que la porte de CI a désormais une valeur de référence à comparer. Et §2 consigne une **violation réelle de sa propre règle de dépendance** : `CombatBoardFactory` et `ScriptedOpponentFactory` sont en Application et prennent des dépôts d'Infrastructure. Nommée, non corrigée, avec le motif.

`06` passe en **2.5** : §3 voit se fermer la dernière de ses trois occurrences d'ordre d'itération, §1.2 gagne une quatrième facette — une copie datée n'est pas la version courante —, §1.3 un corollaire sur les ancres de non-régression, §4.4 le piège d'`expectException()`, §8 la règle sur les fixtures et leur générateur, et §4.2 constate que la porte de CI n'a pas été livrée par un chantier désormais clos.

`07` passe en **3.6** : E-15 entre et sort du registre dans la même révision, le point irréversible n° 6 est corrigé — il portait encore la clause que E-14 avait retirée cinq jours plus tôt —, un **dixième** point irréversible s'ajoute, l'éclatement des rangs 13 et 14 est consigné, le chantier 2 est rayé en §5.2, et le chantier 10 gagne un préalable de mesure chiffré.

`README` : deux erreurs factuelles corrigées, toutes deux sur les côtés — le cahier des charges et l'exemple de `CombatEvent` annonçaient encore `PLAYER`/`OPPONENT`, huit commits après le passage en `A`/`B`, et l'exemple portait un `target` qui n'existe pas au catalogue. `viewerSide` ajouté au contrat d'API, structure du projet et compteurs à jour.

## État final

**472 tests PHPUnit, 1 543 assertions. 81 tests Vitest. PHPStan 101 fichiers sans erreur.** `check-all.ps1` vert de bout en bout.

**La base de développement locale est à supprimer** : le schéma passe en version 3 et une base antérieure est refusée, pas migrée.

**La branche est prête à fusionner sur `dev` par PR.**

## Ce qui reste ouvert, et qui n'appartient à aucun chantier nommé

- **La porte de CI sur l'empreinte de version de contenu** (`04` §10, `06` §4.2). Son second motif d'exclusion est tombé : la valeur de référence existe désormais.
- **La violation de la règle de couches** par `CombatBoardFactory` et `ScriptedOpponentFactory` (`04` §2).
- **E-14**, le départage de deux photographies égales par l'ordre des arguments. Sans portée en PvE, réelle en PvP, donc chantier 11.
- **La mutualisation du chargement des catalogues**, reportée depuis la session 020.

## Prochain chantier

**1b — coquille Electron et Steam** (`07` §5.2, rang 5), sans préalable, porte EX-J0-02 partielle.

Le chantier **1a — moteur embarqué** vient ensuite et dépend de celui qu'on vient de clore : c'est lui qui exercera pour de bon la parité octet pour octet entre le serveur et le binaire. Aujourd'hui, un seul côté de cette parité est figé — et `tests/Determinism/fixtures/reference-combat/` est ce contre quoi le binaire sera comparé.
