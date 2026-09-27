# Corebound — Index de la documentation

**Version du corpus :** 2.1 · **Dernière révision :** 19 septembre 2026

Ce fichier est le point d'entrée de la documentation de Corebound. Il décrit **qui fait autorité sur quoi**, afin qu'aucun document ne puisse contredire silencieusement un autre.

**Ce qui change en version 2.1.** La session de cadrage du 19 septembre 2026 a tranché les six décisions qui bloquaient le chantier 2, en a créé et tranché une septième (**D-22**), et a rendu **D-17** sans objet. Le registre de la §4 est mis à jour en conséquence : **trois décisions restent ouvertes, contre neuf**. Aucun changement de structure documentaire, d'où une révision mineure et non une version 3.0.

---

## 1. Carte des documents

| # | Document | Autorité sur | Rythme de mise à jour |
|---|---|---|---|
| 00 | `00-INDEX.md` | La structure documentaire elle-même | À chaque ajout/suppression de document |
| 01 | `01-vision-produit.md` | Le pitch, le public, le positionnement, le modèle économique, les critères de succès | Rare — une révision est une décision produit |
| 02 | `02-game-design-document.md` | Les règles du jeu, les systèmes, les entités, la boucle, l'économie | À chaque chantier de gameplay |
| 03 | `03-cahier-des-charges.md` | Le périmètre par jalon, les exigences, les critères d'acceptation | À chaque décision de scope |
| 04 | `04-architecture-technique.md` | L'architecture logicielle, le déterminisme, le packaging, l'infrastructure | À chaque chantier technique structurant |
| 05 | `05-plan-production-distribution.md` | La roadmap, le budget, le prix, les plateformes, le marketing | Trimestriel, ou sur événement |
| 06 | `06-conventions-de-developpement.md` | La méthodologie, la qualité, les commits, la Definition of Done | Rare |
| 07 | `07-roadmap-des-chantiers.md` | L'ordre des chantiers, leurs préalables, leurs critères de sortie | **À chaque audit ou replanification. Jamais à chaque session** |
| — | `corebound-lore-bible.md` | L'univers, la cosmologie, les affinités narratives, le ton | À chaque nouvelle affinité |
| — | `corebound-affinities.md` | **Les stats de plateau par Vestige, les primaires et secondaires, la table de distance entre affinités** | À chaque décision d'affinité |
| — | `affinity_circle.png` | **Rien — vue dérivée, sans autorité.** Restitue en schéma les données de `corebound-affinities` §1 et §2 | Régénéré à chaque modification de `corebound-affinities` §1 ou §2 |
| — | `corebound-art-style-guide-fr.md` | La direction artistique, les formats, les cadres, la palette | À chaque chantier visuel |
| — | `corebound-art-style-guide-en.md` | Traduction de travail du précédent, pour les prompts de génération | Suit le guide français |
| — | `guide_creation_item.md` | La méthode de rédaction d'un objet | À chaque chantier de contenu |
| — | `roster-cible-40-heros.json` | **Document de conception**, pas configuration de code | À chaque décision de roster |
| — | `corebound-resume-session_NNN.md` | **L'état réel du code à une date donnée** | À chaque session |

**Trois entrées de cette carte manquaient en version 1.0** (`07`, `corebound-affinities.md`, le guide DA anglais), ce qui a directement causé une erreur : `corebound-affinities` a été cité comme autorité par `07` alors qu'il n'était référencé nulle part.

> **Piège de nommage à connaître.** `roster-cible-40-heros.json` et `config/heroes.json` sont deux fichiers différents avec deux contenus différents : 40 entrées de conception contre 10 entrées de code. Le second seul fait autorité sur l'état du jeu.

> **Second piège, de même nature.** `affinity_circle.png` affiche les mêmes chiffres que `corebound-affinities` §2 et la même règle de distance que §1. **Il n'a aucune autorité** : c'est une vue, pas une source. Deux supports portant la même donnée divergent, et la §5 interdit précisément cette configuration entre documents d'autorité. Toute divergence entre le schéma et le fichier se tranche en faveur du fichier, et le schéma est régénéré — jamais l'inverse.

---

## 2. Hiérarchie d'autorité en cas de conflit

Cette hiérarchie existe parce que la documentation de conception dérive plus vite que le code. Elle a déjà causé de vraies erreurs sur ce projet.

> **1. Le code réel** — toujours vainqueur. Ne jamais supposer l'état d'un fichier : le demander.
> **2. Le dernier résumé de session** — reflet daté du code.
> **3. Le cahier des charges (`03`)** — ce qui est engagé.
> **4. Le GDD (`02`)** — ce qui est conçu.
> **5. La vision produit (`01`)** — ce qui est visé.

### 2.1 Où se placent les documents thématiques

La version 1.0 laissait `corebound-lore-bible`, `corebound-affinities` et les guides DA hors de la hiérarchie. Règle ajoutée en 2.0 :

> **Un document thématique fait autorité sur son thème, au même rang que `02`.** Le conflit entre un document thématique et `02` se résout **par périmètre, pas par rang** : `corebound-affinities` tranche les stats et les paires d'affinité, `02` tranche les règles de combat qui les consomment.

Si un conflit tombe hors des deux périmètres, il n'est pas arbitrable par cet index et doit être tranché explicitement, puis consigné dans le document dont le périmètre a été étendu.

### 2.2 Trois règles opérationnelles

**Signaler avant d'exploiter.** Dès qu'un écart est détecté entre un document et le code, il doit être signalé explicitement avant d'être exploité. Un document obsolète utilisé comme référence est plus dangereux qu'un document absent.

**Rouvrir avant d'affirmer.** Citer un document de mémoire n'est pas le lire. La session du 8 septembre 2026 a produit trois affirmations fausses par ce seul mécanisme, dont une qui inventait un conflit déjà tranché en toutes lettres dans `corebound-affinities` §2. **Vérifier une donnée ne suffit pas non plus : il faut lire le code qui la consomme.**

**Lire aussi le code qui produit la donnée.** *(ajouté en version 2.1)* La règle précédente ne suffit pas. Le cadrage du 19 septembre 2026 a invalidé l'argument qui fondait D-16 — « l'association héros ↔ objet est perdue à la construction du plateau, donc la recette est la seule forme de snapshot viable » — parce que `CombatBoard` avait bien été lu, mais pas `CombatBoardFactory`, qui **décore les objets avant de les y ranger**. La conclusion avait survécu à une révision entière de `07` et se serait figée en format irréversible.

> Lire la donnée, lire le code qui la consomme, **et lire le code qui la produit.**

### 2.3 Une décision se reconnaît à ce qu'elle a un format

*(ajouté en version 2.1)* **D-22 n'existait dans aucun document** avant le 19 septembre 2026. `07` §8 rangeait pourtant « randomizer dérivé par combat » parmi ses points irréversibles depuis la révision 2.0 — sans jamais voir que la **fonction de dérivation elle-même** serait stockée, hachée et rejouée, donc qu'elle était un format à figer et non un détail d'implémentation.

> Toute formule dont le résultat sera stocké, haché, comparé ou rejoué est un **format**, donc une décision. La nommer tôt coûte une ligne ; la découvrir tard coûte un corpus.

---

## 3. Statut d'une mécanique — vocabulaire normalisé

Chaque mécanique décrite dans le GDD porte l'un de ces statuts. Ils sont exclusifs.

| Statut | Signification |
|---|---|
| **IMPLÉMENTÉ** | Dans le code, testé, mergé sur `dev` |
| **ENGAGÉ** | Décidé, périmètre défini, pas encore codé — apparaît dans le cahier des charges avec un jalon |
| **CIBLE** | Fait partie de la vision du jeu fini, mais ni daté ni détaillé |
| **OUVERT** | Une décision explicite est requise avant tout travail. **Bloquant** |
| **ÉCARTÉ** | Étudié puis refusé. Conserve la justification du refus |
| **⚠ ÉCART** | *(nouveau en 2.0)* La règle est décrite dans un document d'autorité, **et le code ne l'applique pas**. Vérifié par lecture directe, avec la date |

**Aucune mécanique CIBLE ou OUVERTE ne passe en code sans une décision explicite qui la fait basculer en ENGAGÉ.**

**Pourquoi ⚠ ÉCART.** Aucun des cinq statuts existants ne décrivait le cas : un écart n'est pas OUVERT (le constat lui-même n'appelle aucune décision, seule sa résolution en appelle une), ni CIBLE, ni ÉCARTÉ. Sans ce statut, sept constats de l'audit du 8 septembre auraient dû être rangés sous IMPLÉMENTÉ, c'est-à-dire présentés comme conformes. `02` §10 en tient l'index.

**Un ⚠ ÉCART n'est pas un bug.** Trois des huit écarts recensés par `02` §10 appelaient d'abord une décision de règle, pas un correctif : il fallait choisir ce que le jeu doit faire avant de changer ce qu'il fait. **Ces trois-là sont tranchés depuis le 19/09/2026** et restent pourtant marqués ⚠ ÉCART, parce qu'un écart se ferme à l'implémentation et non à la décision. Deux autres sont résorbés depuis le 14/09/2026 ; il en reste donc **six ouverts**, dont trois attendent du code et trois une décision ou un chantier de contenu.

**Le marqueur ⚠ ÉCART est validé.** `02` révision 2.0 le proposait sous réserve, cet index ayant autorité sur les statuts. **Il est adopté en version 2.1** : huit constats en dépendent, et aucun autre statut ne les décrit.

---

## 4. Décisions ouvertes en cours

Liste vivante. Toute entrée non tranchée bloque le chantier correspondant.

### 4.1 Tranchées

| ID | Décision | Résolution |
|---|---|---|
| **D-01** | Un ou deux combats par manche ? | **02/09/2026** — deux combats. Marchand → PvE → Marchand → PvP. `02` §3 |
| **D-02** | Que coûte une défaite en PvE ? | **02/09/2026** — le joueur ne gagne rien. `02` §4.3 |
| **D-06** | Nombre d'objets cible en 1.0 ? | **07/09/2026** — **180** : 40 neutres + 20 par affinité. `07` §3.1 |
| **D-07** | Pipeline d'assets | **02/09/2026** — génération IA, production étalée. `05` §4 |
| **D-08** | Conséquence d'un enchaînement de défaites PvE | **02/09/2026** — absorbée par D-02 |
| **D-09** | Taille du pool de compétences et règle d'unicité | **07/09/2026** — **20 compétences**, au plus un héros par couple (compétence, affinité). `02` §2.3.1 |
| **D-13** | Plafond du nombre de stacks d'un statut | **13/09/2026 — périmée.** Son objet disparaît avec D-20 : le modèle par instances borne la contribution par source sans qu'aucun plafond soit écrit. `07` §3.1 |
| **D-20** | Modèle de persistance des statuts | **13/09/2026** — **instances indépendantes**, sans fusion ni plafond. Remplace D-13. `02` §7.4, `04` §3.3 |
| **D-21** | Le soin retire-t-il des stacks de statut ? | **13/09/2026** — **oui**, 1 de `POISON` et 1 de `BURN` par déclenchement de `HEAL`. `02` §7.4 |
| **D-14** | Règle de départage quand les deux plateaux meurent au même tick | **19/09/2026** — **modèle hybride par phase.** Statuts et enrage en résolution **simultanée**, morts constatées en fin de phase, double mort départagée sur PV + bouclier **d'avant la phase** puis par tirage. Actions d'objets en résolution **séquentielle**, interrompue à la première mort, l'ordre des deux plateaux étant **tiré au sort à chaque tick** où les deux ont une action en attente. `02` §7.5, `07` §3.1 |
| **D-15** | Le match nul est-il distinct du timeout ? | **19/09/2026** — **le match nul n'existe pas.** `winner` devient non nullable, un timeout est départagé sur PV + bouclier **finaux** puis par tirage, et `SimulationResult` gagne un champ `resolution` à trois valeurs. Un événement `RESOLUTION_TIEBREAK` consigne le motif dans le journal. `02` §7.5, `07` §3.1 |
| **D-16** | Forme du snapshot : recette de reconstruction ou photographie ? | **19/09/2026** — **photographie.** Un fantôme enregistré avant un rééquilibrage combat avec ses chiffres d'origine. Le snapshot porte les objets **déjà décorés**, la définition du Vestige, les héros et leurs compétences ; la recette et la version de contenu sont embarquées en **provenance** seulement. `02` §6.6, `07` §3.1 |
| **D-17** | Nombre de tirages fixe dans l'offre de héros pondérée ? | **19/09/2026 — sans objet.** Figer ce compte ne découplait pas le flux du catalogue : `pickArrayKeys()` en dépend aussi, dans la boutique et dans l'offre initiale. Le découplage vient de l'épinglage de la version de contenu (D-18). `07` §3.1 |
| **D-18** | Politique de migration, schéma et contenu | **19/09/2026 — quatre volets.** L'issue du combat est **écrite dans le journal de run** et rejouée au lieu d'être resimulée ; la version de contenu est une **empreinte automatique** des quatre catalogues ; une run dont la version ne correspond plus est **rejetée** avant J1, la politique post-J1 étant reportée ; une table `schema_version` refuse une base obsolète avec un message explicite. `07` §3.1 |
| **D-19** | Sérialisation canonique de `CombatLog` dans le Domaine | **19/09/2026** — sérialiseur **séparé du présenter**, clés triées sur les tableaux à clés texte **et jamais sur les listes**, types restreints à `int`/`string`/`bool`, enveloppe versionnée, et **côtés en libellés neutres `A`/`B`** attribués par le contenu des plateaux. `02` §7.6, `07` §3.1 |
| **D-22** | Dérivation de la graine de combat | **19/09/2026 — décision nouvelle, créée au cadrage.** Le Domaine reçoit une **graine de combat explicite** et non plus le randomizer du run ; elle alimente **deux flux indépendants**, l'un pour l'ordre d'initiative, l'autre pour les effets aléatoires à venir. Le calcul appartient à l'Application. `02` §7.1, `07` §3.1 et §8 |
| **Champ « or » dans le snapshot** | Faut-il l'embarquer, et à quelle condition ? | **19/09/2026** — **embarqué sans condition.** Un champ contre une décision bloquante en moins : **D-12 sort du chemin critique du chantier 2**. `02` §4.1, `07` §3.1 |

> **Note de numérotation.** D-22 suit D-21 et n'a aucun rapport avec les « sept décisions du cadrage », qui sont D-14, D-15, D-16, D-17, D-18, D-19 et D-22 elle-même. Le rang dans le cadrage n'est pas un identifiant.

### 4.2 Ouvertes

| ID | Décision | Impact | Document |
|---|---|---|---|
| **D-03** | Durée cible d'une manche et d'une run complète | Fenêtre de remboursement Steam (2 h), rythme | `02` §3.5 |
| **D-04** | La fusion modifie-t-elle les chiffres ou le comportement ? | Design des objets, volume de contenu | `02` §5.4 |
| **D-05** | Les adversaires de secours sont-ils annoncés comme tels ? | Perception, confiance, avis Steam | `02` §6.4 |
| **D-10** | Support visuel du rang de fusion | L'aura porte la rareté, le cadre l'affinité : le rang n'a aucun support libre | `07` §3.2 |
| **D-11** | Le Rare doit-il devenir qualitativement différent ? | Sinon l'Argent domine strictement le Rare | `07` §3.2 |
| **D-12** | Forme retenue pour l'économie du Doré | **Chantier 6 uniquement.** Sortie du chemin critique du chantier 2 par l'embarquement inconditionnel du champ « or » | `07` §3.2 |

**Trois décisions ouvertes de moins qu'il n'y paraît.** La version 2.0 en comptait douze, dont neuf bloquantes pour un chantier en cours. Il en reste **six**, dont **aucune ne bloque le chantier 2**. D-10 et D-11 bloquent le chantier 9, D-12 le chantier 6 ; D-03, D-04 et D-05 sont des questions de design sans chantier immédiat.

> **Tension à lever sur D-04.** `07` §3.1 tranche l'échelle de fusion en « Bronze ×1 · Argent ×1,75 · Or ×2,25 **plus une ligne bonus** », ce qui répond de fait à D-04 : les chiffres à tous les rangs, plus un comportement au dernier. `02` §5.4 la présente encore comme non tranchée. **À confirmer ou à infirmer explicitement** — ce n'est pas à cet index de le décider. *(Tension inchangée en version 2.1 : le cadrage du 19/09 portait sur le chantier 2 et n'a pas touché à la fusion.)*

### 4.3 Où lire le détail d'une décision tranchée

La résolution courte figure ci-dessus. Le raisonnement complet, les options écartées et les points de mise en œuvre vivent ailleurs, et cet index ne les duplique pas.

| Décision | Raisonnement | Règle de jeu |
|---|---|---|
| D-14, D-15 | `07` §3.1 | `02` §7.5 |
| D-16 | `07` §3.1 | `02` §6.6 |
| D-17, D-18 | `07` §3.1 et §4.2 (E-05, E-11) | — *(persistance, pas règle de jeu)* |
| D-19 | `07` §3.1 | `02` §7.6 |
| D-22 | `07` §3.1 et §8 | `02` §7.1 |
| D-20, D-21 | `07` §3.1 | `02` §7.4 |

**`04` et `06` ont été révisés dans la même passe** et ne bloquent plus le chantier 2. `04` passe en révision 2.0 — signature du simulateur, dérivation de la graine, forme du snapshot, politique de migration, contrat du journal — et `06` en révision 2.1, où entrent les quatre règles d'intégrité nouvelles du cadrage. `07` §2 tient la liste complète des écarts documentaires, dont **plus aucun n'est bloquant**.

**Deux défauts de gouvernance relevés au passage, et corrigés.** `04` typait `SimulationResult::$winner` en `?CombatHero` alors que `02` avait corrigé la même erreur onze jours plus tôt : une donnée technique portée par deux documents avait divergé en silence, exactement ce que la §5 interdit. Et l'en-tête de `06` annonçait une révision 1.0 pendant que son corps citait une « révision 2.0 », sans qu'on puisse trancher entre la sienne et celle du corpus. **Convention désormais explicite en `06` : le numéro de révision d'un document lui est propre et n'a aucun rapport avec la version du corpus.**

---

## 5. Documents volontairement absents

Par application de YAGNI, cohérente avec la méthodologie du projet :

- **Aucun document de narration détaillée** (factions nommées, régions, personnages). Le lore est fonctionnel : il sert la DA et les mécaniques. Il s'enrichit quand un besoin concret émerge.
- **Aucun plan de test formel séparé.** Les tests sont le code (PHPUnit, Vitest), et la couverture manquante se documente en commentaire dans les fichiers de test concernés.
- **Aucun document de spécification d'API séparé.** Les contrôleurs et leurs tests font foi.
- **Aucun business plan formel.** Le projet est personnel et autofinancé ; `05` couvre le nécessaire.
- **Aucun document d'errata séparé.** *(ajouté en 2.0)* Une correction se fait **dans le document qu'elle corrige**, jamais à côté. Les documents étant numérotés dans l'ordre de lecture, un errata portant un numéro plus élevé se lirait après le texte fautif : quelqu'un qui ouvre le document seul repartirait avec l'information fausse. L'historique du changement appartient à Git et au résumé de session, pas au corpus.
- **Aucune feuille de route parallèle.** *(ajouté en 2.0)* `07` est la seule. Deux documents ayant autorité sur le même objet ne sont arbitrables par aucune règle de la §2.
- **Aucun document de décisions d'architecture séparé.** *(ajouté en 2.1)* La tentation existait au cadrage du 19 septembre, qui a produit sept décisions en une session. Elle est écartée pour le motif de la ligne précédente : un registre de décisions et `07` §3 feraient autorité sur le même objet. **Le registre reste la §4 de cet index pour la résolution courte, et `07` §3 pour le raisonnement.** La §4.3 dit lequel consulter.

---

## 6. Convention de nommage des fichiers

- Documents de référence : `NN-nom-en-minuscules.md`, numérotés dans l'ordre de lecture.
- Résumés de session : `corebound-resume-session_NNN.md`, dans `docs/sessions/`.
- Documents thématiques transverses : `corebound-<thème>.md`.
- Documents de conception non rédigés (données, tableaux) : nom explicite en minuscules, **avec l'extension qui convient**, et un nom qui ne peut pas être confondu avec un fichier de `config/`.

---

## 7. Documents d'autorité non versionnés

*(ajouté en 2.0)* Ces documents figurent dans la carte de la §1 et **ne sont pas dans le dépôt Git**. L'exclusion est délibérée. Sans cette section, une relecture future la prendrait pour un oubli et les verserait.

| Document | Motif de l'exclusion | Condition de levée |
|---|---|---|
| `roster-cible-40-heros.json` | Reflète l'état **antérieur** à la redistribution du chantier 6 (`07` §2). Le verser en l'état publierait une référence fausse | Après application de la redistribution **et** renommage levant la collision avec `config/heroes.json` |

**Un seul document dans ce cas.** Les résumés de session y figuraient jusqu'au 13/09/2026 ; ils sont désormais versionnés dans `docs/sessions/`, la §2 les classant au rang 2 de la hiérarchie d'autorité et les documents du corpus les citant comme sources.

**Le critère pour entrer ici.** Un document d'autorité ne reste hors du dépôt que si l'y verser publierait une information **fausse**, pas simplement incomplète. L'inconfort de maintenance n'est pas un motif recevable.