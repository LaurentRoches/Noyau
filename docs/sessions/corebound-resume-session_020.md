# Corebound — Résumé de session 020

**Branches** : `docs/combat-snapshot-framing` (cadrage, fusionnée sur `dev` avant le code), puis `feature/versioned-combat-snapshot`
**Chantier** : 2 — snapshot versionné et déterminisme

---

## Contexte et objectif

Le chantier que `04` §5.3 désigne comme **le point de l'architecture qui devient irrattrapable**. Le coût aujourd'hui est de quelques champs et d'une signature ; le coût après le premier commit PvP est le corpus entier. Rien de ce qu'il produit n'est visible par un joueur.

Le chantier 3b devait le précéder : D-20 fait de l'état de statut une liste et non un entier, et geler le format de snapshot avant ce changement aurait imposé une migration dès sa première version.

**Douze commits sur quatorze.** Il reste l'issue de combat enregistrée (commit 13) et le test de rejeu octet pour octet (commit 14), qui est celui qui franchit le critère de sortie. La branche n'est pas fusionnée.

## Le cadrage, en préalable

Sept décisions tranchées sans écrire une ligne de code, sur une branche `docs/` fusionnée **avant** l'ouverture de la branche d'implémentation : D-14 (résolution hybride par phase), D-15 (le match nul n'existe pas), D-16 (le snapshot est une photographie), D-17 *(devenue sans objet)*, D-18 (migration, schéma et contenu — quatre volets), D-19 (sérialisation canonique et côtés neutres), D-22 (dérivation de la graine de combat, créée au cadrage), plus l'embarquement inconditionnel de `goldAtCombatStart` qui sort D-12 du chemin critique.

Prendre ces décisions pendant l'écriture du code les aurait prises par défaut. Quatre d'entre elles sont irréversibles.

## Ce que les douze commits ont produit

**1 — Table `schema_version`.** Trois états à distinguer, et c'est le troisième qui porte la valeur : base neuve, base versionnée, base **antérieure au versionnement**. La détection lit `sqlite_master` **avant** toute création ; sans cela le `CREATE TABLE IF NOT EXISTS` qui suit rend les trois indiscernables et une base de développement ancienne se fait estampiller « à jour ». `initialize()` crée et estampille, `assertUpToDate()` contrôle et refuse — les fusionner imposerait les deux effets à tout appelant, dont les tests. Le refus est un **503 émis par le bootstrap**, hors du `Router` : le service ne refuse pas *cette requête*, il refuse de servir. D'où `ObsoleteSchemaException extends RuntimeException`, pour qu'elle ne soit jamais transformée en 409.

**2 — Sérialisation canonique du `CombatLog`** (D-19). Dans le Domaine, séparée du presenter. Tri `ksort`/`SORT_STRING` sur les tableaux à clés texte **et jamais sur les listes** : l'ordre des objets d'un plateau décide de qui frappe avant qui, c'est une donnée de jeu. Flottants refusés à toute profondeur.

**3 — Graine de combat explicite** (D-22). `Simulator::run()` ne reçoit plus le `Randomizer` de la run mais une graine opaque, dont il tire deux flux indépendants — `order` pour l'initiative, `effects` pour les tirages à venir — par `sha256(tag ‖ combatSeed)` tronqué à 16 octets. Le calcul appartient à l'Application, la dérivation au Domaine. C'est ce découpage qui permettra au moteur embarqué de rejouer un combat **sans jamais connaître la seed du run**.

Le commit 3 et le commit 4 du plan initial ont fusionné : changer la signature de `Simulator::run()` casse `GameRun::playRound()`, son seul appelant, donc le commit de Domaine seul ne compilait pas.

**4 — Graine de run atteignable depuis l'API** (E-13). Le corps JSON de `POST /runs` devient la source **unique**. `$params['seed']` est retiré et non conservé en second canal : `POST /runs` n'a aucun placeholder, `Request::fromGlobals()` coupe la chaîne de requête, et deux canaux pour la même valeur sont exactement ce qui a rendu l'anomalie invisible. Entier strict, rejeté sinon — `(int) 'abc'` vaut 0 et produirait une run parfaitement déterministe **sur la mauvaise graine**.

**5 — Libellés de côté neutres et `viewerSide`.** `PLAYER`/`OPPONENT` deviennent `A`/`B` pour que les deux joueurs d'un futur PvP lisent le même journal. La contrepartie obligatoire est le champ `viewerSide` dans la réponse de `resolveRound` : le journal ayant cessé de dire qui est le joueur, l'enveloppe doit le dire. La valeur vient du moteur, jamais écrite en dur côté Http — ce qui a fait qu'aucune ligne de frontend n'a bougé le jour où l'attribution est devenue canonique, trois commits plus tard.

**6 — Match nul supprimé, résolution nommée** (D-15). `winner` non nullable, champ `resolution` à trois valeurs, événement `RESOLUTION_TIEBREAK` qui consigne le motif du départage.

**7 — Résolution hybride par phase** (D-14). Statuts et enrage en simultané, morts constatées en fin de phase, double mort départagée sur PV + bouclier **d'avant la phase**. Actions d'objets en séquentiel, interrompues à la première mort, l'ordre des deux plateaux tiré au sort à chaque tick où les deux ont une action en attente.

**8 — `CanonicalJson` extrait, or de début de combat embarqué.** Les règles d'octets quittent `CombatLogSerializer` pour une classe que le snapshot partage : deux implémentations des mêmes règles, ce sont deux occasions de diverger sur un format de parité. `goldAtCombatStart` devient un paramètre **requis** de `CombatBoard`, embarqué avant même que la compétence qui le lira existe — c'est l'exception nommée au YAGNI de `06` §1.4.

**9 — La photographie et l'attribution canonique.** `BoardSnapshot` ne lit que des objets immuables. A devient le plateau à la **plus petite photographie** en ordre d'octets. Les deux ne sont pas séparables : l'attribution compare des photographies, et un test d'attribution sans photographie n'affirme rien.

**10 — L'enveloppe de provenance.** `BoardRecord` avec `SnapshotRecipe`, `FORMAT_VERSION`, `EngineVersion::CURRENT` et `contentVersion`. Elle ne sert à aucun appelant avant le commit 13 ; elle est écrite maintenant **parce que le format est irréversible**.

**11 — Retrait des accesseurs d'ordre de construction.** `getPlayerBoard()`/`getOpponentBoard()` devenaient faux dès le commit 9 et n'étaient lus par aucun code de production.

**12 — Empreinte de version de contenu** (D-18 volets 2 et 3). Trois classes sur trois couches : `ContentVersion` porte la règle dans le Domaine, `ContentCatalogReader` lit le disque en Infrastructure, `ContentVersionMismatchException` porte le refus en Persistence. `runs.content_version` épingle l'empreinte à la création, `GameRunReplayer::replay()` la compare avant de reconstruire quoi que ce soit.

## Décisions prises à l'écriture, que le cadrage n'avait pas vues

**`getBoards()` devait devenir canonique, pas seulement `getSide()`.** `StatusProcessor`, `EnrageProcessor` et `TickEngine` le parcourent, et les deux premiers écrivent un événement par plateau : l'ordre des arguments était donc **observable dans le flux d'octets du journal**. Sans ce changement, NF-01 tombait et D-19 manquait son propre objet.

**Gouvernance de `engineVersion`.** Incrémenter à chaque changement sous `Domain/Engine/` est intenable ; ne l'incrémenter que « quand le moteur change vraiment » est invérifiable. Règle retenue : **incrémenter dès qu'un changement peut modifier un `CombatLog`** — avant le chantier 11, tout changement de code sous `Domain/Engine/` hors commentaires ; à partir du chantier 11, la fixture de parité arbitre.

**Le code HTTP du rejet de contenu.** `04` §7 proposait 409 via `LogicException`. Le statut est retenu, mais la proposition manquait un point : **le 409 existait déjà** pour toute `LogicException`, donc une exception dédiée seule n'aurait rien donné au client. D'où le champ `code` facultatif dans `ApiResponse::error()`, et le `catch` dédié placé **avant** le générique. Les réponses existantes restent identiques octet pour octet.

**La double lecture des catalogues est assumée.** `GameRunReplayer` recharge les mêmes fichiers pour reconstruire l'état. Mutualiser déplacerait le chargement de toutes les fabriques dans un commit qui porte déjà une migration irréversible. Nettoyage reporté.

**Le commit 8 annoncé a éclaté en quatre.** Il contenait quatre briques indépendantes ; les garder ensemble aurait produit un message décrivant des choses sans rapport apparent, et un `git bisect` incapable d'en isoler une.

## Mesures

| Mesure | Valeur | Ce qu'elle remplace |
|---|---|---|
| Taille d'un snapshot au maximum structurel — 3 héros × 2 emplacements, 6 objets légendaires à 2 actions | **2 129 octets** | L'estimation « ~5 Ko l'unité » de `04` §5.3, 2,4 fois trop prudente |
| Corpus de 5 000 snapshots (NF-12) | **~10,2 Mo** | ~25 Mo estimés |
| Snapshot en manche 1 / en milieu de run | 349 / 1 260 octets | — |
| Empreinte des quatre catalogues réels | `2a9e8481…0f0e1aa` | Calculée hors ligne **avant** d'écrire `ContentVersion`, puis retrouvée à l'identique par le code en exécution — deux chemins indépendants, mêmes 64 caractères |
| Assertions basculées par le seul commit 9 | **19** | L'adversaire occupe désormais A dans tous les tests où les deux plateaux diffèrent |
| Suite de tests | 294 → **419** tests, 1 384 assertions | Fin de session 019 |

## Erreurs commises et rattrapées

Consignées parce qu'elles sont instructives, pas par scrupule.

- **Budget d'objets annoncé à 18 au lieu de 6.** Contredisait `04` §5.3 (« jusqu'à six objets ») et `02` §2.1, qui fixe `itemSlots` à 2. Le chiffre venait de `tests/Fixtures/heroes.json` — une **fixture**, pas la configuration de production. C'est la règle « lire la donnée » appliquée au mauvais fichier, et le document avait raison contre l'affirmation. Mesure refaite : 2 129 octets et non 5 915.
- **Trois fichiers de tests périmés livrés**, revenant à un état d'avant le renommage des côtés en A/B. La vérification faite — comptage de lignes d'ancrage et de méthodes de test — passait, parce que les ancres ne couvraient que les lignes de construction de plateau alors que le renommage touchait l'intérieur des corps. **C'est l'analyseur de l'éditeur qui l'a signalé, pas le processus.** D'où la règle de `06` §1.3 : `git diff --stat` après application, avant de lancer quoi que ce soit.
- **`SnapshotRecipe` diagnostiqué comme fichier vide.** L'indice — CS Fixer ne le corrigeant pas — avait une explication bénigne : il était déjà conforme. La vraie cause était le **cache de résultat de PHPStan**. Trois allers-retours perdus dans un fichier sain. Piège consigné en `06` §4.4, avec le discriminant `class_exists()`.
- **Ordre de résolution des classes mal prédit.** `SnapshotRecipe` était annoncé comme la première classe manquante signalée ; PHP résout la classe d'un appel statique **avant** d'évaluer ses arguments, donc c'est `BoardRecord` qui a levé.
- **Affirmation à moitié fausse sur l'ordre des arguments.** « Sans conséquence, inobservable dans le journal » : le journal est bien identique, mais il dit « A gagne » — donc l'ordre des arguments décide **quel joueur** gagne un miroir parfait. Devenue l'anomalie **E-14**.
- **Clause de départage inapplicable dans `04` §3.6.** Elle proposait de départager deux photographies égales « par un identifiant de combat », valeur unique partagée par les deux plateaux et non valeur par plateau — le même paragraphe avait rejeté les identifiants par plateau deux lignes plus haut. Elle avait survécu à deux révisions du document qui la porte, et n'est tombée qu'en l'implémentant.
- **Le saut de ligne final, attribué au hasard pendant trois runs.** CS Fixer corrigeait, à chaque passage, exactement les fichiers venant d'être livrés. La cause est le transport : un bloc de code collé dans l'éditeur perd son `\n` terminal. Elle a laissé une modification orpheline de `Simulator.php` en attente pendant deux jours. Vérifié ensuite : un fichier livré en **pièce jointe** revient identique octet pour octet. Piège consigné en `06` §4.4.
- **Contradiction de gouvernance dans `07`.** La révision 3.3 pose qu'**aucun marqueur d'avancement** n'entre dans la liste de commits du chantier 2 ; la révision 3.4, rédigée deux jours plus tard, en a ajouté trois. Retirés en 3.5.
- **Comptage d'occurrences faux** sur une vérification `Select-String` : deux annoncées, trois obtenues, `Select-String` étant insensible à la casse par défaut. Sans conséquence, mais c'est une affirmation non vérifiée.

## Corpus

`04` passe en **2.6** : §6.3 décrit désormais du code existant et nomme les trois classes ; §7 tranche le point de mapping HTTP laissé ouvert par le cadrage et documente l'ordre des `catch` comme une décision de contrat ; §10 corrige une affirmation devenue fausse — la porte de CI qu'elle annonçait « ajoutée au chantier 2 » ne l'a pas été.

`06` passe en **2.4** : le piège du saut de ligne final, et la même correction sur la porte de CI, qu'une ligne du document affirmait exister.

`07` passe en **3.5** : retrait des marqueurs d'avancement, E-05 marquée résorbée, code HTTP du rejet ajouté à la description du commit 12.

`README` : structure du projet, contrat d'API, état d'avancement et compteurs de tests, qui étaient en retard de deux sessions. **Deux exemples JSON portaient `"itemSlots": 6`** alors que le paragraphe situé vingt lignes plus haut écrit « 2 emplacements chacun » — corrigés.

## État final

**419 tests PHPUnit, 1 384 assertions. 81 tests Vitest. PHPStan 92 fichiers sans erreur.** `check-all.ps1` vert de bout en bout.

**La base de développement locale est à supprimer** : le schéma passe en version 2 et une base en version 1 est refusée, pas migrée (D-18, jetable avant J1).

## Prochain chantier

**Fin du chantier 2**, deux commits.

**13 — `feat(application): record combat outcome in RESOLVE_ROUND and apply it on replay`** (D-18 volet 1, E-11). Aujourd'hui, chaque rejeu **resimule** les combats passés avec le moteur courant : dès que le moteur change — et ce chantier l'a changé — une manche gagnée peut devenir perdue, le compteur de victoires diverge, les offres de héros des manches 3 et 5 apparaissent ou disparaissent, et une action `CHOOSE_HERO` journalisée peut lever au rejeu. Le chemin « appliquer une issue enregistrée » est réservé à `GameRunReplayer` ; `RunController::resolveRound()` simule toujours lui-même et ignore tout champ d'issue reçu, faute de quoi le vainqueur serait **déclaré par le joueur**.

**14 — `test(domain): assert byte-identical replay of a reference combat`.** Le test qui prouve NF-01 et franchit EX-J0-03.

La branche n'est pas fusionnée avant. Restent hors du chantier, et consignés comme tels : la porte de CI sur l'empreinte de contenu (`04` §10), la mutualisation du chargement des catalogues, et E-14.
