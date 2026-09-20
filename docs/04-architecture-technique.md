# 04 — Architecture technique

**Autorité sur :** l'architecture logicielle, le déterminisme, le packaging, l'infrastructure.
**Révision :** 2.1 — 20 septembre 2026.

**Note de version.** L'en-tête est resté à « 1.0 — 2 septembre 2026 » alors que le corps du document portait déjà les décisions du 13 et du 14 septembre 2026 (D-20, répartition de la brûlure, dettes résorbées). **Un document dont l'en-tête ment sur sa date est plus dangereux qu'un document daté d'hier** : il fait croire qu'il n'a pas été touché. La révision 2.0 consolide ces changements et ceux du cadrage du 19 septembre.

**Ce qui change en révision 2.0.** Le cadrage du chantier 2 tranche sept décisions qui touchent directement ce document : la signature du simulateur, la forme du résultat de combat, la dérivation du hasard, la sérialisation canonique, la forme du snapshot, la politique de migration et le contrat du journal de run. **Ce document était bloquant pour le chantier 2** (`07` §2) : il décrivait une signature et une forme de snapshot que les décisions changent.

**Ce qui change en révision 2.1.** Quatre commits du chantier 2 ont été écrits ; ce document décrit désormais, pour eux, **du code existant et non un projet**. Trois sections passent du futur au présent — la table `schema_version` (§6.3), la seed de la run (§7), la frontière Application/Domaine du hasard (§3.2). Et **une affirmation de 2.0 est retirée** : « le calcul et la dérivation sont deux commits distincts » confondait une frontière de couches avec un découpage de commits, et le découpage ne tenait pas à l'exécution. Aucune décision n'est modifiée.

**Une erreur de la révision 1.0 est corrigée** : `SimulationResult::$winner` était typé `?CombatHero` en §3.1. Le type réel est `?CombatBoard`. `02` avait relevé et corrigé la même erreur dans sa propre copie le 8 septembre 2026 ; elle a survécu ici onze jours de plus, ce qui est exactement la configuration que `00-INDEX` §5 cherche à éviter — deux documents portant la même donnée.

---

## 1. Stack

| Couche | Technologie |
|---|---|
| Moteur / backend | **PHP 8.3 natif**, architecture DDD construite à la main, aucun framework applicatif |
| Frontend | **Vue 3 / TypeScript / Pinia / Vite** |
| Persistance serveur | SQLite (dev, alpha) → **PostgreSQL** (à partir de J2) |
| Encapsulation desktop | **Electron** (décision du 02/09/2026) |
| Moteur embarqué client | Binaire PHP statique via `static-php-cli` / `phpmicro` |
| Tests | PHPUnit 12, Vitest |
| Qualité | PHPStan niveau 6, PHP CS Fixer, ESLint, Prettier, `vue-tsc` |
| CI | GitHub Actions (`ubuntu-latest`), portes bloquantes sur PR vers `dev` |
| Environnement | Windows + VSCode, Node via nvm-windows (`frontend/.nvmrc`) |

### Pourquoi PHP pour le moteur de simulation

Un combat d'auto-battler asynchrone est une **transformation de données pure**. Formule cible, après le chantier 2 :

```
CombatLog = f(snapshotA, snapshotB, combatSeed)
```

PHP y est bien adapté : cycle de vie court et stateless, aucune fuite de mémoire entre requêtes, isolation totale entre deux combats simulés en parallèle.

**La formule de la révision 1.0 était `f(playerBoard, opponentBoard, seed)`.** Trois mots changent, et chacun porte une décision : les plateaux deviennent des **snapshots** (D-16), les côtés perdent leurs noms de « joueur » et d'« adversaire » (D-19), et la graine devient **propre au combat** au lieu d'être l'état du flux de la run (D-22). `02` §7.1 en donne la lecture côté règles du jeu.

Ce choix de langage n'a jamais été un handicap sur le moteur. Il n'en devient un que sur le packaging desktop — sujet traité en §4.

---

## 2. Couches

```
Domain → Application → Infrastructure → Persistence → Presentation → Http
```

**Règle de dépendance :** une couche ne dépend jamais d'une couche située à sa droite.

| Couche | Contenu | Contrainte |
|---|---|---|
| **Domain** | `Vestige`, `Hero`, `Item`, `CombatBoard`, `CombatVestige`, `CombatHero`, moteur de combat, statuts, **sérialiseur canonique de `CombatLog`**, **dérivation des deux flux aléatoires** | Aucune I/O, aucun accès réseau ou base. Aucune source d'aléa non seedée |
| **Application** | `GameRun`, `GameRunFactory`, `HeroItemAllocator`, `ShopFactory`, `CombatBoardFactory`, `ScriptedOpponentFactory`, **calcul de la graine de combat** | Orchestration. Injection explicite des dépendances, jamais de service locator |
| **Infrastructure** | Repositories JSON, chargement de configuration, **empreinte de version de contenu** | Fail-fast sur configuration incomplète |
| **Persistence** | Journal d'actions rejouable, **enregistrements de combat**, SQLite/PostgreSQL | Aucune sérialisation d'objet domaine |
| **Presentation** | Sérialisation de l'état de run pour le client | Aucune règle métier. **Jamais la source du format canonique** |
| **Http** | `Router`, `Request`, `ApiResponse`, `RunController` | Mapping exception → HTTP centralisé dans le `Router` |

**Structure de plateau :** `CombatBoard` = 1 `CombatVestige` + 1 à 3 `CombatHero`. Le `CombatVestige` porte les PV, le bouclier et les statuts. Les héros n'ont pas d'état de combat propre.

**Pourquoi la graine se calcule dans l'Application et se dérive dans le Domaine.** Le Domaine ne doit rien savoir de la run : ni sa seed, ni son numéro de manche, ni l'identifiant d'appariement. Il reçoit une graine opaque et en tire ses deux flux. C'est ce découpage qui permet au moteur embarqué de résoudre un combat **sans jamais connaître la seed du run** (§4.2).

> **Correction en 2.1.** Cette phrase se terminait par « et c'est la raison pour laquelle le calcul et la dérivation sont **deux commits distincts** au chantier 2 ». **Ce n'est plus vrai, et l'argument était faux en soi.** La séparation des couches est une propriété de conception, pas un découpage de commits : changer la signature de `Simulator::run()` casse `GameRun::playRound()`, son seul appelant, donc le commit de Domaine seul ne compilait pas et le commit d'Application seul référençait un `CombatSeed` inexistant. `06` §1.6 exige un `check-all.ps1` vert **à chaque** commit. Les deux moitiés n'en font qu'un ; la frontière de couches, elle, est intacte. Voir `07` §6.

**Ordre d'assemblage du plateau, vérifié le 19/09/2026.** `CombatBoardFactory::createBoard()` applique `HeroSkillDecorator::decorate()` à chaque objet **avant** de construire le `CombatBoard`. Le plateau ne contient donc que des objets déjà résolus. Ce fait n'était consigné nulle part et il conditionne §5.5 : c'est lui qui rend la photographie possible.

---

## 3. Moteur de combat

### 3.1 Pipeline

```
TickEngine → EventDispatcher → PendingAction → ActionProcessor
           → StatusProcessor → EnrageProcessor → CombatEvent → CombatLog
```

**Signature actuelle :**

```php
Simulator(int $maxTicks = 500)::run(
    CombatBoard $player,
    CombatBoard $opponent,
    Randomizer $randomizer,
): SimulationResult
```

**Signature cible, chantier 2 (D-22, D-19) :**

```php
Simulator(int $maxTicks = 500)::run(
    CombatBoard $a,
    CombatBoard $b,
    string $combatSeed,
): SimulationResult
```

Trois changements : le `Randomizer` du run disparaît au profit d'une graine opaque, les paramètres perdent leurs noms de camp, et **c'est `run()` qui ordonne A et B** selon la règle d'attribution canonique de §3.6 — pas l'appelant.

- **1 tick = 100 ms.** `maxTicks = 500` par défaut, soit 50 secondes.
- Un seul endroit du code avance le temps (`TickEngine::tick()`). Ne jamais dupliquer `advanceTick()` dans `Simulator` : c'est un piège déjà rencontré et corrigé.

**Gardes de mort — état actuel.** La boucle interrompt l'exécution des `PendingAction` restantes dès qu'une entité meurt : pas de frappe sur cadavre. Cette garde couvre les actions d'objet et l'enrage, mais **pas les statuts entre eux** : `StatusProcessor` n'a aucune garde équivalente entre les deux plateaux de sa boucle, donc un double KO simultané par Poison/Burn reste possible. **La garde manquante entre statuts et enrage (E-03) est traitée au chantier 0.**

**Gardes de mort — cible, chantier 2 (D-14).** La règle devient hybride par phase : statuts et enrage en résolution **simultanée** avec constat des morts en fin de phase, actions d'objets en résolution **séquentielle** avec la garde actuelle. `02` §7.5 a autorité sur la règle ; ce document n'en porte que les conséquences de structure.

**Résultat de combat — état actuel :**

```php
SimulationResult { winner: ?CombatBoard, totalTicks: int, log: CombatLog }
```

`winner: null` couvre **le timeout et le double KO**, sans les distinguer.

> **Deux corrections à la révision 1.0.** Elle écrivait `winner: ?CombatHero` : le type réel est `?CombatBoard`. Et elle écrivait « `winner: null` couvre le timeout », ce qui laissait croire que c'était le seul cas ; le double KO produit le même `null`, et c'est précisément ce que D-15 sépare.

**Résultat de combat — cible, chantier 2 (D-15) :**

```php
SimulationResult {
    winner: CombatBoard,        // non nullable
    resolution: Resolution,     // KNOCKOUT | SIMULTANEOUS_RESOLVED | TIMEOUT_RESOLVED
    totalTicks: int,
    log: CombatLog,
}
```

### 3.2 Déterminisme — contrainte non négociable

RNG : `\Random\Randomizer` sur `\Random\Engine\PcgOneseq128XslRr64`. **Aucune autre source d'aléa dans la couche Domaine** — ni `rand()`, ni `shuffle()`, ni `array_rand()`, ni horodatage.

Le déterminisme n'est pas un confort de développement : c'est ce qui rend possibles le replay client, le PvP asynchrone, le corpus de sunset et le débogage à distance. Toute régression sur ce point casse quatre systèmes à la fois.

**Vérification :** un test CI compare, sur un jeu de graines fixes, le `CombatLog` produit par le serveur et celui produit par le binaire embarqué. Toute divergence échoue le build.

#### 3.2.1 Dérivation de la graine de combat — D-22, chantier 2

**Ce que la révision 1.0 ne voyait pas.** Elle posait « une seed », sans dire d'où elle venait. En pratique `GameRun` transmet au `Simulator` **le randomizer du run**, dont l'état dépend de tous les tirages déjà consommés : offre initiale, boutiques, offres de héros. Le combat n'est donc pas fonction d'une graine mais d'un **état de flux**. Sans conséquence tant que le combat ne consommait aucun aléa — **ce qui cesse d'être vrai avec D-14**, dont la règle tire l'ordre d'initiative à chaque tick.

**La chaîne complète.**

```php
// --- Application : calcule la graine, connaît la run ---
// PvE  : etiquette + seed de run + numero de manche
$combatSeed = hash('sha256', sprintf('combat|%d|%d', $runSeed, $round));
// PvP  : derivee de l'identifiant d'appariement, stable et enregistre avant la simulation

// --- Domain : derive deux flux, ne sait rien de la run ---
$orderSeed   = substr(hash('sha256', 'order|'   . $combatSeed, true), 0, 16);
$effectsSeed = substr(hash('sha256', 'effects|' . $combatSeed, true), 0, 16);

$order   = new Randomizer(new PcgOneseq128XslRr64($orderSeed));
$effects = new Randomizer(new PcgOneseq128XslRr64($effectsSeed));
```

| Élément | Valeur | Motif |
|---|---|---|
| **Forme de `$combatSeed`** | Digest SHA-256 en **hexadécimal, 64 caractères** | Il est stocké dans chaque snapshot, donc il traverse JSON. Une chaîne binaire brute n'y survivrait pas, et D-19 n'autorise que `int`, `string`, `bool` |
| **Encodage des entiers** | Décimal ASCII, séparateur `\|` explicite | Sans séparateur, `12‖3` et `1‖23` donneraient la même chaîne. **Vérifié le 19/09/2026** : avec le séparateur, `combat\|12\|3` et `combat\|1\|23` produisent bien deux graines distinctes |
| **Troncature à 16 octets** | `substr(..., true), 0, 16` | `PcgOneseq128XslRr64` a un état de 128 bits. 16 octets le remplissent exactement |
| **Deux flux, pas un** | `order` et `effects` | Ajouter une ligne de critique à un objet ne doit pas décaler les ordres de passage de tous les ticks suivants, ni l'inverse |
| **Extension requise** | `hash` | Cœur de PHP, non désactivable depuis 7.4. Aucune dépendance nouvelle pour `static-php-cli` (§4.3) |

**Propriété de fail-fast, vérifiée le 19/09/2026.** `PcgOneseq128XslRr64` lève une `ValueError` sur tout seed binaire qui ne fait pas exactement 16 octets — testé à 15 et 17. Une troncature erronée ne peut donc pas produire silencieusement un flux différent : elle casse à la construction.

**Changement de convention à connaître.** La construction actuelle passe un **entier** au moteur PCG (`new PcgOneseq128XslRr64($seed)`). Les deux flux dérivés passeront une **chaîne de 16 octets**. Les deux formes sont valides, mais elles ne produisent pas la même suite : tout test qui fixe une seed entière et attend une suite précise devra être relu.

> **Ce que ce schéma fige, et pourquoi il est irréversible.** Cinq éléments : l'étiquette, l'algorithme de hachage, la troncature, l'encodage des entiers et le nom des deux flux. **Changer l'un d'eux rejoue tout le corpus différemment.** `07` §8 le range en point irréversible numéro 3 — celui que la révision 2.0 de `07` manquait, en inscrivant « randomizer dérivé par combat » sans voir que la *fonction* était elle aussi un format.

**Un changement de format déjà connu et non encore fait.** La boucle cible (`02` §3.2) enchaîne **deux combats par manche**, PvE puis PvP. L'étiquette devra les distinguer. Ce point appartient au format irréversible ci-dessus et se décide au chantier 8, pas au chantier 11.

### 3.3 Règles de calcul stabilisées

- **Arrondis :** `cooldownTicks` toujours `floor()`, `value` toujours `ceil()`. Cette règle est définitive — la formulation antérieure (« réduction → floor, augmentation → ceil ») cassait sur le malus de cooldown de `SUNDERING`.

> **⚠ La règle est exacte, son implémentation ne l'est pas.** `HeroSkillDecorator` calcule ses pourcentages en **flottants**. Mesuré le 19/09/2026 sur les entiers de 0 à 1000 : `value × 1,10` diverge de l'arithmétique exacte sur **54 valeurs**, la première à 50 (`ceil` donne 56 au lieu de 55) ; `value × 1,35` diverge sur **8 valeurs**, la première à 180. Les trois `floor` sur `cooldownTicks` et le `×1,2` ne divergent nulle part sur cet intervalle.
>
> **Ce n'est pas un risque de parité** — une multiplication IEEE-754 isolée est correctement arrondie, donc identique sur les cibles 64 bits. C'est un risque de **justesse**. Voir `02` §2.3 écart 8 et `07` anomalie E-12.

- **Ordre d'activation entre les deux plateaux — TRANCHÉ (D-14, 19/09/2026).** La révision 1.0 écrivait : « déterministe par ordre de déclaration des plateaux, le joueur avant l'adversaire. Aucune statistique d'initiative. Dette connue, à revisiter quand des combats multi-objets symétriques existeront. » **Cette dette est soldée en règle.** L'ordre est désormais **tiré sur le flux `order`, à chaque tick où les deux plateaux ont une action en attente** ; le plateau tiré exécute toutes ses actions, puis l'autre. La règle complète est en `02` §7.5. Le motif du refus d'un critère d'état — le plus faible d'abord — y figure également : ce serait un rattrapage déguisé, exploitable par un build qui baisserait volontairement ses PV.
- **Ordre d'activation à l'intérieur d'un plateau — dette toujours ouverte.** Les objets sont activés héros par héros, dans l'ordre du roster puis dans l'ordre d'affectation. Mais `EventDispatcher::dispatchForItem()` parcourt un tableau associatif indexé par valeur de `Trigger` : pour un objet portant plusieurs effets, l'ordre suivrait la séquence d'enregistrement et non l'ordre de déclaration dans le JSON. Aucun objet actuel n'a deux effets. **Devient réel au chantier 3.** À ne pas confondre avec la ligne précédente.
- **Modèle de statut — instances indépendantes (D-20, 13/09/2026).** `CombatVestige` porte, **par type de statut, une liste d'instances**, chacune avec ses stacks, ses ticks restants et l'identifiant de sa source. Aucune fusion, aucun plafond écrit. `StatusProcessor` **agrège la somme des stacks vivants avant d'émettre** — un seul `CombatEvent` par statut et par tick, charge utile inchangée. Règle complète : `02` §7.4.

> **Correction de portée.** La révision 1.0 concluait cette ligne par « lecteur de rejeu du frontend non impacté ». C'est exact **pour D-20**, et faux pour le chantier 2 pris dans son ensemble : **D-19 change les libellés de côté de `PLAYER`/`OPPONENT` en `A`/`B` dans toutes les charges utiles**, ce qui impacte directement le lecteur de rejeu. Voir §8.

- **Répartition de la brûlure :** `intdiv($stacks * 3, 2)` sur le bouclier, puis `intdiv($leftover * 7, 15)` du surplus sur les PV. **Arithmétique entière exclusivement** — aucun flottant, aucun pourcentage calculé. Les deux divisions arrondissent au plancher, donc en faveur du défenseur. Un flottant ici casserait la parité serveur / moteur embarqué d'EX-J0-01.
- **Bornage des PV.** `takeDamage()`, `takeRawDamage()` et `takeBurnDamage()` passent tous trois par `min($this->currentHp, …)` : les PV ne descendent jamais sous zéro et **l'excédent de dégâts n'est conservé nulle part**. Conséquence directe sur D-14 : après une double mort, l'état final ne peut rien départager, d'où le départage sur l'état **d'avant la phase**.
- **Conséquence sur le format de snapshot (chantier 2).** L'état de statut n'est ni un entier ni un couple mais **une liste**. Le chantier 3b devait donc précéder le chantier 2, faute de quoi la première version du format aurait été à migrer immédiatement. *(Fait le 14/09/2026.)*

### 3.4 Sérialisation canonique du `CombatLog` — D-19, chantier 2

**Le besoin.** NF-01 exige un `CombatLog` identique **octet pour octet** entre le serveur et le binaire embarqué. Aujourd'hui il n'existe aucune sérialisation canonique : la seule forme écrite est `CombatEventPresenter`, en couche Présentation, qui recopie l'événement tel quel sans imposer ni ordre, ni type, ni encodage.

**Règle retenue.**

| Point | Décision |
|---|---|
| **Emplacement** | Un sérialiseur dédié dans `App\Domain\Engine`, **séparé de `CombatEventPresenter`**. Le contrat de l'API reste libre d'évoluer sans toucher au format de parité |
| **Ordre des clés** | `ksort` en `SORT_STRING`, à chaque niveau — **sur les tableaux à clés texte uniquement** |
| **Listes** | **Jamais triées.** L'ordre des héros, des objets et des événements est une donnée de jeu (§2) |
| **Types autorisés** | `int`, `string`, `bool`. Tout autre type lève une exception |
| **Enveloppe** | Une version de format, puis la liste `{tick, type, payload}` |
| **Options JSON** | Fixées une fois pour toutes, `JSON_THROW_ON_ERROR` compris, sans affichage formaté |

**Pourquoi trier plutôt que tester l'ordre d'insertion.** Le tri supprime une classe entière d'erreurs au lieu de la surveiller : un développeur qui réordonne une charge utile ne casse plus rien.

> **Correction d'un constat de `07` révision 2.0.** Elle écrivait que l'ordre des clés dépendait d'« un ordre écrit à la main, sans test ». Il est bien écrit à la main, mais il **est** testé — indirectement : `===` sur deux tableaux PHP exige le même ordre de clés, et `assertSame` repose sur `===`. Les assertions de charge utile de `StatusProcessorTest` et `EnrageProcessorTest` figent donc déjà cet ordre. La faiblesse réelle est ailleurs : ce contrôle existe **type d'événement par type d'événement, par effet de bord**, sans règle canonique. La couverture de `ActionProcessor` n'a pas été vérifiée.

**Pourquoi refuser les flottants.** Deux raisons distinctes, toutes deux suffisantes.

1. **L'écriture JSON d'un flottant dépend de `serialize_precision`**, un réglage d'exécution. Le serveur et le binaire `static-php-cli` peuvent ne pas le partager, et NF-01 tomberait sans qu'aucun calcul ne soit faux.
2. **L'enrage inflige `5 × 2^stage`.** Au-delà du stage 60 environ, le résultat dépasse `PHP_INT_MAX` et PHP le convertit **silencieusement** en flottant. Avec `maxTicks = 500` le stage plafonne à 50, mais rien n'interdira de relever `maxTicks`. Une exception au moment de la sérialisation transforme une corruption silencieuse en échec visible.

**Marge à connaître.** `5 × 2^50` ≈ 5,63 × 10¹⁵ reste sous la limite des entiers exacts en JavaScript (`Number.MAX_SAFE_INTEGER` ≈ 9,01 × 10¹⁵), donc le frontend peut lire ces valeurs sans perte. **La marge est d'un facteur 1,6, soit moins d'un tick d'enrage.** Relever `maxTicks` de quelques dizaines de ticks la consommerait.

### 3.5 Événement de départage

D-15 ajoute un type d'événement, émis chaque fois qu'un combat se conclut autrement que par un KO simple.

| Champ | Contenu |
|---|---|
| `resolution` | `SIMULTANEOUS_RESOLVED` ou `TIMEOUT_RESOLVED` |
| `criterion` | `STATE` (PV + bouclier) ou `DRAW` (tirage sur le flux `order`) |
| Les deux valeurs comparées | Entiers |
| `winnerSide` | `A` ou `B` |

**Pourquoi il entre dans le format canonique.** Il est écrit dans le `CombatLog`, donc dans la comparaison octet pour octet d'EX-J0-01. Sa charge utile suit les règles de §3.4 comme n'importe quelle autre : clés triées, entiers et chaînes seulement.

### 3.6 Attribution canonique des côtés — D-19, chantier 2

**Le problème.** Toutes les charges utiles portent aujourd'hui un côté nommé `PLAYER` ou `OPPONENT`. En PvP, le serveur simule **une seule fois** et les deux joueurs regardent le même combat : un journal écrit du point de vue de « PLAYER » est faux pour l'un des deux. Avec un seul Vestige au catalogue, un combat miroir a de surcroît deux cibles portant le même identifiant — seul le côté les distingue.

**La règle.**

> **Le journal est écrit en libellés neutres `A` et `B`. A est le plateau dont le snapshot canonique est le plus petit en comparaison d'octets. En cas d'égalité stricte, le départage se fait par un identifiant de combat enregistré avec les données d'entrée** — identifiant de run en PvE, identifiant d'appariement en PvP.

**Pourquoi l'attribution ne peut pas venir de l'appelant.** Le tirage d'ordre de D-14 désigne « A ». Si l'appelant choisissait qui est A, inverser les deux plateaux avec la même graine inverserait l'initiative et pourrait changer le vainqueur : le résultat dépendrait encore de la façon dont les plateaux ont été rangés. C'est exactement le défaut que D-14 corrige, réintroduit par une autre porte.

**Deux critères plus simples ont été écartés.** L'ordre alphabétique des identifiants de joueurs ne vaut qu'en PvP en ligne — l'adversaire scripté n'en a pas, l'adversaire d'archive non plus. Le tri des snapshots seuls fonctionne partout sauf en miroir, où il ne dit plus quel joueur est A.

**Cette règle dépend de §5.5** : elle compare des snapshots canoniques, donc elle ne peut être écrite qu'une fois leur forme fixée.

### 3.7 Dettes connues du moteur

| Dette | Description |
|---|---|
| ~~Statuts fusionnés par type~~ — **résorbé le 14/09/2026** | `CombatVestige` porte une liste d'instances indépendantes par type, `mergeWith()` a disparu, `AggregatedStatus` porte la projection exposée aux `CombatEvent`. Chantier 3b, point 1 |
| ~~Brûlure annulée par 1 point de bouclier~~ — **titre faux, corrigé et résorbé le 14/09/2026** | `takeDamage()` faisait `min(bouclier, dégâts)` : 1 point de bouclier absorbait 1 point de brûlure, pas la totalité du tick. La répartition 150 % / 70 % est implémentée par `CombatVestige::takeBurnDamage()`. Chantier 3b, point 2 |
| ~~Ordre d'activation sans initiative~~ — **tranché le 19/09/2026** | Remplacé par le tirage par tick de D-14. Voir §3.3 |
| `Trigger` non lu | `ON_ATTACK` et `EVERY_N_TICKS` sont fonctionnellement identiques ; seul `cooldownTicks` pilote la cadence. Confirmé par lecture de `TickEngine::tick()` le 14/09/2026. Renvoyé au chantier 3, retirer l'enum étant une décision de design et non un nettoyage |
| ~~Méthodes et champs morts~~ — **résorbé le 14/09/2026** | `EventDispatcher::dispatch()` et `getListenersFor()` retirés, `register()` passé en privé ; `Effect::intervalTicks` retiré de bout en bout, `EffectDTO` compris. Chantier 3b, point 5 |
| **Arithmétique flottante dans le décorateur** — *ajouté le 19/09/2026* | `HeroSkillDecorator` calcule `ceil($value * $multiplicateur)` en flottants, contrairement à la doctrine d'arithmétique entière tenue partout ailleurs dans le moteur. Divergences mesurées, ampleur réelle sur le catalogue inconnue. `07` E-12, à instruire avant le chantier 10 |
| Enrage non calibré | `triggerTick` et `baseDamage` posés par raisonnement, jamais ajustés par playtest. **Précision du 19/09/2026 :** le handicap de ×1,5 imputé à l'ordre des plateaux disparaît avec D-14. Ce qui reste à calibrer est la **granularité des paliers**, qui tient au doublement et non à l'ordre |
| Fragmentation de budget | `HeroItemAllocator` en first-fit naïf peut laisser un slot libre insuffisant pour un `TWO_HAND`. Piste : swap N-vers-1 pondéré par `slotCost()` |
| Garde anti-cascade | `EventDispatcher` n'a pas de garde-fou anti-boucle-infinie. Non urgent tant qu'aucun effet n'en re-déclenche un autre |
| IA scriptée non recalibrée | Elle hérite des compétences de ses héros sans ajustement volontaire de la difficulté. **Deux de ses trois compétences sont inertes** — `02` §2.6 |
| **Lecture de fichier non cachée** — *ajouté le 19/09/2026* | `JsonItemRepository::find()` et `JsonHeroRepository::getRawData()` relisent le fichier et refont un `json_decode` à chaque appel. **Moitié résorbée par D-18** : avec l'issue enregistrée, `show()` ne resimule plus les combats passés. Reste la relecture par `find()`, à mesurer au chantier 1a. `07` E-09 |

---

## 4. Packaging client

### 4.1 Décision

**Electron**, décidé le 2 septembre 2026 après comparaison avec Tauri (voir `corebound-packaging-tauri-vs-electron.md` pour l'analyse complète).

Motifs déterminants :

- `steamworks.js` fonctionne avec Electron et non avec Tauri, qui exigerait d'écrire du Rust pour les achievements, le cloud et l'overlay.
- L'overlay Steam ne s'accroche pas de façon fiable aux WebView, donc pas à Tauri.
- Le packaging Steam Linux est balisé sous Electron et documenté comme problématique sous Tauri.
- Chromium embarqué garantit un rendu identique sur les trois OS — un vrai gain sur une UI dense en tooltips et animations.
- L'écosystème PHP desktop (NativePHP) repose sur Electron.

**Coût accepté :** environ 85 Mo de binaire et 200 Mo de RAM supplémentaires. Sans conséquence pour un jeu affichant des cartes statiques, vendu 14,99 € sur Steam.

### 4.2 Architecture du client

```
Corebound.exe (Electron)
├── resources/
│   ├── corebound-engine.exe      ← phpmicro + PHAR du domaine (~10–15 Mo)
│   ├── snapshots-corpus.json     ← corpus d'archive (~25 Mo, à re-mesurer — §5.3)
│   └── dist/                     ← build Vue.js
└── main process (Node)
        ├── steamworks.js         ← achievements, cloud, overlay
        ├── mode en ligne  → HTTPS → API PHP distante
        └── mode hors ligne → sidecar corebound-engine.exe
                              stdin  : { snapshotA, snapshotB, combatSeed }
                              stdout : { combatLog }
```

> **Contrat de stdin corrigé en révision 2.0.** La révision 1.0 écrivait `{ playerBoard, opponentSnapshot, seed }`, ce qui portait trois problèmes : un côté nommé « joueur » que D-19 supprime, une asymétrie entre un plateau et un snapshot que D-16 supprime aussi, et une « seed » qui aurait dû être celle du run. **Le moteur embarqué n'a jamais besoin de la seed du run** (§3.2.1) : c'est ce découplage qui rend le combat rejouable isolément, donc le corpus lisible.

### 4.3 Moteur embarqué

Construit avec `static-php-cli` et `phpmicro` : un interpréteur PHP statique autonome, sans dépendance système, fusionné avec le code applicatif empaqueté en PHAR (`box-project/box`).

- Supporté sur Windows, Linux, macOS et FreeBSD.
- Builds Windows de référence : ~3 Mo (5 extensions) à ~8,5 Mo (40+ extensions).
- Compression UPX disponible sur Windows et Linux, −30 à −50 % de taille.
- Utilisé en production par Laravel Herd, FrankenPHP et NativePHP.

**Pourquoi cela fonctionne bien ici :** le moteur est du **domaine pur**. `Simulator`, `TickEngine`, `ActionProcessor`, `StatusProcessor`, `EnrageProcessor` et `CombatBoardFactory` ne font ni HTTP ni base de données — ils prennent des objets et rendent un `CombatLog`.

**Extensions requises :** `json`, `mbstring`, `random` (natif en 8.2+), **`hash`** *(ajouté en révision 2.0 — requis par la dérivation de §3.2.1 ; cœur de PHP, non désactivable depuis 7.4, donc sans effet sur l'audit de licences C-02)*, et `pdo_sqlite` si une persistance locale est retenue.

**Point d'entrée à créer :** un script CLI qui désérialise du JSON depuis stdin, appelle `Simulator::run()`, sérialise le `CombatLog` **avec le sérialiseur canonique de §3.4** sur stdout. Le contrat est celui de §4.2.

> **Réserve levée en révision 2.0.** La révision 1.0 écrivait « le contrat est déjà défini par la signature existante ». Il ne l'était pas : la signature existante prend un `Randomizer`, objet PHP qui ne traverse pas stdin. C'est D-22 qui rend le contrat réellement sérialisable.

**Voie de repli documentée.** Si `static-php-cli` posait un problème imprévu, le portage du moteur en TypeScript reste ouvert. Le déterminisme rend la parité **mécaniquement vérifiable** en CI. Coût : double implémentation à maintenir à vie. À décider sur une borne de temps fixée à l'avance, pas par épuisement.

> **Point d'attention pour ce repli, ajouté en révision 2.0.** Un portage TypeScript devrait reproduire à l'identique `PcgOneseq128XslRr64` seedé par 16 octets, `intdiv` en arithmétique entière, et l'ordre de tri `SORT_STRING` de PHP. Aucun des trois n'est natif en JavaScript. **Le repli est plus coûteux qu'il n'en avait l'air** au moment où il a été écrit, et le déterminisme n'y est pas « mécaniquement vérifiable » mais « mécaniquement vérifiable une fois ces trois briques réécrites ».

**FrankenPHP est écarté côté client** — il exige WSL sous Windows, ce qui est inenvisageable sur Steam. Il reste un bon candidat **côté serveur** : binaire unique, 50 à 70 % de RAM en moins qu'un PHP-FPM classique.

### 4.4 Points d'attention packaging

- **Signature de code** obligatoire, sinon avertissement Windows SmartScreen au premier lancement.
- **Chemins d'écriture** : répertoire de données applicatives de l'OS (`%APPDATA%`), jamais le dossier d'installation Steam. À déclarer dans la configuration Steam Cloud.
- **Audit de licences** : dépendances npm, dépendances Electron, **et extensions PHP compilées dans `micro.sfx`**. PHP est sous licence permissive, certaines extensions tierces ne le sont pas. Compiler le jeu minimal d'extensions nécessaire, jamais le build « gigantic ».
- **Steam Deck** : candidat naturel pour un jeu tour par tour. À évaluer après J4, pas avant.

---

## 5. PvP asynchrone et corpus de sunset

### 5.1 Flux

```
Joueur A → snapshot du plateau → backend (stockage, indexé par numéro de manche)
Joueur B → demande d'adversaire → backend → snapshot A + snapshot B
        → simulation déterministe → CombatLog → client B
```

Aucune session persistante, aucun WebSocket, aucun état en mémoire par partie. Chaque combat est une requête HTTP indépendante. Le `CombatLog` est cachable : mêmes snapshots + même graine de combat = même résultat.

### 5.2 Règle d'or

> **Tout combat PvP doit pouvoir être transformé en un snapshot autonome, anonymisé, versionné et rejouable localement.**

Cette règle permet un arrêt de service propre : arrêt du serveur, génération d'un corpus final, publication d'une mise à jour Steam, et le jeu continue de proposer du PvP simulé pendant des années sans coût récurrent.

### 5.3 Ce que la règle impose

| Contrainte | Détail |
|---|---|
| **Moteur exécutable côté client** | Sans lui, le corpus est illisible : le plateau du joueur varie, donc aucun `CombatLog` ne peut être pré-calculé. **C'est la précondition de la règle, pas sa conséquence** |
| **Versionnement strict** | Champ `engineVersion` dans chaque snapshot, **dès le premier commit PvP**. L'ajouter après coup invalide le corpus déjà produit |
| **Version de format de snapshot** | *(ajouté en révision 2.0)* **Distincte d'`engineVersion`.** La photographie sérialise les modèles `Item`, `Effect` et `Action` ; dès le chantier 4 ces modèles changent. Le format doit savoir relire ses versions antérieures, par exemple en donnant une valeur par défaut aux champs absents |
| **Politique de migration** | **TRANCHÉE le 19/09/2026 (D-18).** Avant J1 : une run dont la version de contenu ne correspond plus est **rejetée**, la base étant jetable. Après J1 : reporté explicitement, la question devenant « un joueur en pleine run au moment d'une mise à jour continue-t-il sur l'ancien contenu ? » |
| **Volume du corpus** | Cible ≥ 5 000 snapshots répartis par manche et par palier de puissance |
| **Anonymisation** | Le pseudonyme affiché doit être dissociable ou remplaçable dans le corpus final |
| **Testabilité continue** | Une bascule manuelle en mode hors ligne, disponible dès J2 |

> **Réserve de dimensionnement ouverte en révision 2.0.** Le budget « ~5 Ko l'unité, ~25 Mo embarqués — négligeable » a été posé quand le snapshot était supposé être une **recette** de quelques identifiants. **D-16 en fait une photographie** : les `Item` décorés au complet, effets, actions et valeurs, pour jusqu'à six objets. Le budget tient probablement, mais **il n'a pas été mesuré**. À vérifier dès que le chantier 2 produit son premier snapshot réel, pas au chantier 12.

### 5.4 Amorçage

Au lancement, la base est vide. `ScriptedOpponentFactory` fournit l'infrastructure d'adversaires de secours. Transparence recommandée envers le joueur (décision D-05, `02` §6.4).

**Point technique levé le 19/09/2026.** `ScriptedOpponentFactory` passe par `CombatBoardFactory::createBoard()`, avec un Vestige fixé en constante. Un adversaire d'archive se sérialise donc **exactement comme un plateau de joueur, sans cas particulier de format**.

### 5.5 Forme du snapshot — D-16, chantier 2

**La question tranchée.** Un fantôme enregistré avant un rééquilibrage doit-il combattre avec ses chiffres d'origine, ou avec le catalogue courant ?

> **Avec ses chiffres d'origine. Le snapshot est une photographie, pas une recette.**

**Contenu du snapshot.**

| Partie | Rôle |
|---|---|
| Les `Item` **déjà décorés**, dans l'ordre du plateau | **Font foi au rejeu.** C'est le cœur de la photographie |
| La définition du Vestige (`baseHp`, `baseShield`) | État initial du plateau |
| Les héros et leur compétence | Nécessaires aux compétences qui agissent **pendant** le combat — `OPENING`, `AURIC` (`02` §2.3.1) |
| `goldAtCombatStart` | Entier, solde au lancement. Embarqué **sans condition**, entrée de `AURIC` |
| La recette `(vestigeId, heroIds, itemIdsByHero)` | **Provenance seulement**, aucun rôle au rejeu |
| `contentVersion` | Provenance seulement, dans le snapshot |
| `engineVersion` et la version de format | §5.3 |

**Ce qui rend la photographie possible.** `CombatBoardFactory::createBoard()` décore les objets **avant** de construire le plateau (§2). Le plateau ne contient que des objets résolus, donc l'association héros ↔ objet — perdue dans la liste plate de `CombatBoard` — n'est pas nécessaire au rejeu.

> **Correction d'un argument de `07` révision 2.0.** Elle concluait que « la recette est la seule forme viable » **parce que** l'association héros ↔ objet est perdue à la construction. Le constat sur la liste plate est exact, la conclusion ne l'est pas : la décoration précède la construction. **Les deux formes étaient viables**, et D-16 s'est tranchée sur une question de design et non de faisabilité. L'argument avait survécu à une révision entière ; il se serait figé en format irréversible.

**Ce que la photographie ne règle pas — deux bornes.**

1. **Elle fige les chiffres, pas les règles.** Un fantôme rejoué après un changement de moteur combat avec ses valeurs d'origine sous des règles nouvelles. `engineVersion` reste irréversible pour cette raison exacte.
2. **Elle ne règle rien pour le journal de run.** `GameRunReplayer` reconstruit offres, boutiques et prix depuis le catalogue courant. `contentVersion` reste **indispensable sur l'enregistrement de run** ; dans le snapshot, elle n'est plus que de la provenance.

**Bénéfice de second ordre.** Le décorateur sort du chemin de rejeu. Une correction ultérieure de l'arithmétique flottante de §3.3 ne rétroagira donc pas sur les snapshots produits — **mais les chiffres faux, eux, y seront figés.** C'est un argument de plus pour instruire E-12 avant le chantier 10, pas après.

**Question de design reportée au chantier 11.** Des fantômes d'avant et d'après un rééquilibrage se retrouveront dans le même bassin d'appariement. Filtrer par `contentVersion`, borner par ancienneté ou ne pas filtrer se décide quand le PvP existe.

---

## 6. Persistance

### 6.1 Journal d'actions rejouable

Une `GameRun` est reconstruite en **rejouant, sur une seed fixe, la liste ordonnée des actions qui lui ont été appliquées**, jamais par désérialisation d'un instantané. Ce choix évite de toucher aux classes domaine `final readonly`.

**Règle d'intégrité critique :** une action n'est journalisée **qu'après validation réussie**. Une action invalide journalisée corrompt définitivement l'historique de rejeu. Vérifié tenu dans les quatre handlers mutants de `RunController` : ordre `replay()` → `apply()` → `append()`.

**Bénéfice de second ordre :** ce format rend la migration SQLite → PostgreSQL nettement moins risquée qu'une sérialisation d'objets.

> **⚠ Défaut structurel relevé le 19/09/2026 — le journal dépend du moteur.**
>
> Le journal ne stocke pas le résultat des combats, seulement l'action `RESOLVE_ROUND`. À chaque rejeu, `GameRun::playRound()` **resimule** tous les combats avec le moteur courant. **Dès que le moteur change — et le chantier 2 le change lui-même avec D-14 — une manche passée peut changer d'issue.** Le compteur de victoires diverge alors, les offres de héros des manches 3 et 5 apparaissent ou disparaissent, et une action `CHOOSE_HERO` journalisée peut lever une exception au rejeu.
>
> **Épingler `contentVersion` ne suffisait donc pas.** Une run dépend du contenu, du moteur, **et** du schéma de dérivation de la graine. `07` anomalie E-11.

### 6.2 Issue de combat enregistrée — D-18 volet 1, chantier 2

**La règle.**

> **L'issue d'un combat est écrite dans la charge utile de `RESOLVE_ROUND`. Le rejeu applique l'issue enregistrée au lieu de resimuler.**

Le journal de run devient indépendant du moteur. L'alternative — épingler `engineVersion` sur la run et refuser de rejouer une run d'une autre version — fermerait toutes les runs en cours à chaque correctif du moteur.

**Deux gardes obligatoires, sans lesquelles la règle ouvre une faille.**

1. **Le chemin « appliquer une issue enregistrée » est réservé à `GameRunReplayer`.** `RunController::resolveRound()` continue de simuler lui-même et **ignore tout champ d'issue présent dans la requête**. C'est la règle d'intégrité numéro un appliquée au nouveau champ : une issue qui viendrait du client serait une victoire déclarée par le joueur. Un test doit vérifier que, sur le chemin normal, l'issue journalisée est bien celle qu'a produite la simulation.
2. **Un enregistrement de combat par manche.** `show()` a encore besoin du `CombatLog` de la dernière manche pour l'afficher. S'il le resimule avec un moteur plus récent, l'écran peut montrer une défaite là où le journal enregistre une victoire.

**Contenu d'un enregistrement de combat :** les snapshots A et B, le `combatSeed`, l'`engineVersion`, la `resolution` et le `winnerSide`. Le `CombatLog` n'est resimulé que si l'`engineVersion` est identique ; sinon l'interface affiche l'issue enregistrée sans le détail.

**Effet de bord bénéfique.** `GameRunReplayer::replay()` s'exécute à chaque requête, `show()` compris : à la manche 8, une simple lecture d'état rejouait sept combats. **Avec l'issue enregistrée, le rejeu n'appelle plus le moteur.** C'est la moitié de E-09 réglée sans travail dédié.

**C'est aussi ce qui donne au critère de sortie du chantier 2 un endroit où vivre** : « un snapshot produit, écrit, relu, rejoué » suppose une table qui les porte.

### 6.3 Version de contenu et schéma — D-18 volets 2, 3 et 4

**`contentVersion` est une empreinte automatique.** SHA-256 des **quatre** catalogues canonicalisés — `vestiges.json`, `heroes.json`, `items.json` et `scripted_opponent.json` —, en appliquant les règles canoniques de §3.4 pour qu'un simple reformatage ne la change pas.

**Pourquoi automatique et non manuelle.** Une version manuelle est plus souple, mais un oubli d'incrémentation corrompt les rejeux **en silence**. Vu la priorité de NF-01, l'empreinte l'emporte : elle ne peut pas être oubliée. Contrepartie assumée : la moindre correction d'un libellé invalide les runs en cours, ce qui est sans conséquence avant J1.

**`scripted_opponent.json` est dans l'empreinte** parce que l'adversaire de chaque manche en dépend : le changer change le déroulé d'une run tout autant que changer un objet.

**Table `schema_version`** *(posée le 20/09/2026 — cette section décrit désormais du code existant)*. Avant elle, `Schema::initialize()` n'était qu'un `CREATE TABLE IF NOT EXISTS` sans version : ajouter une colonne à `runs` n'avait d'autre chemin que de supprimer la base, et l'erreur produite aurait été une erreur SQL brute.

- Une table à **ligne unique**, vérifiée au démarrage.
- Une base obsolète est **refusée avec un message explicite**, pas avec une erreur SQL.
- La base reste **jetable jusqu'à J1**.
- **Aucun outil de migration avant le chantier 13**, où `04` §6.4 décrit la migration comme mécanique.

**Trois états, pas deux — le point qui a coûté une décision.** Une base peut être **neuve**, **versionnée**, ou **antérieure au versionnement**. La détection lit l'existence des tables **avant toute création**, sinon le `CREATE TABLE IF NOT EXISTS` qui suit rend les trois états indiscernables et une base de développement ancienne se ferait estampiller « à jour » en silence, ce qui est exactement le défaut que la table existe pour empêcher. La version n'est donc insérée **que** si ni `runs` ni `schema_version` n'existaient à l'entrée.

**Deux méthodes distinctes, et non une.** `Schema::initialize()` crée et estampille ; `Schema::assertUpToDate()` contrôle et refuse. Les fusionner obligerait tout appelant à accepter les deux effets.

**Le refus est un 503, pas un 409, et il vit hors du `Router`.** Le service ne refuse pas *cette requête*, il refuse de servir : le contrôle est dans le bootstrap, qui envoie `ApiResponse::error(..., 503)`. `ObsoleteSchemaException` étend **`RuntimeException` et non `LogicException`** pour cette raison précise — le mapping du `Router` (§7) transforme toute `LogicException` en 409, et une exception de schéma prise dans ce filet serait annoncée au client comme un conflit d'état.

**Les runs déjà en base n'ont aucune version.** Leur sort relève du rejet d'avant J1.

### 6.4 Migration SQLite → PostgreSQL

À effectuer **avant J2**, jamais pendant. Le journal d'actions étant un format de données simple et non lié aux classes PHP, la migration est mécanique.

Le chantier 13 hérite de la table `schema_version` posée au chantier 2, et c'est là que l'outil de migration proprement dit est écrit.

---

## 7. API HTTP

`Router`, `Request`, `ApiResponse`, `RunController`.

**Mapping exception → HTTP, centralisé dans le `Router`, ordre de capture strict :**

| Exception | Code |
|---|---|
| `RunNotFoundException` | 404 |
| `InvalidArgumentException` | 400 |
| `LogicException` | 409 |

**Piège connu :** `php://input` se lit une seule fois. `Request` doit être construit une fois et transmis, jamais reconstruit par handler.

**Seed de la run** *(réécrit en 2.1 — la formulation de 2.0 était vraie et inutilisable)*. La graine se lit dans le **corps JSON de `POST /runs`**, sous la clé `seed`, et **nulle part ailleurs**. En son absence, repli sur `random_int(0, PHP_INT_MAX)`.

| Point | Règle | Motif |
|---|---|---|
| **Source** | Le corps JSON, source **unique** | C'est l'emplacement naturel d'un paramètre de création de ressource. La lecture de `$params['seed']` a été **retirée**, pas conservée en second canal : `POST /runs` n'a aucun placeholder, donc `$params` est toujours vide, et `Request::fromGlobals()` coupe la chaîne de requête sans la conserver — ni `$params` ni `?seed=42` n'ont jamais fonctionné (E-13). Deux canaux pour une même valeur sont précisément ce qui a rendu l'anomalie invisible pendant des semaines |
| **Type** | Entier strict. Tout autre type est **rejeté** : `InvalidArgumentException`, mappée en **400** | `(int) 'abc'` vaut 0 et produirait une run parfaitement déterministe **sur la mauvaise graine** ; un repli silencieux sur l'aléatoire serait pire, le client croyant sa run reproductible sans qu'elle le soit. JSON distingue `42` de `"42"`, et le client est le nôtre |
| **`null`** | `{"seed": null}` vaut **absence**, donc repli sur `random_int` | C'est l'encodage naturel de « pas de graine » chez un client typé. La distinction clé absente / clé nulle n'apporterait rien et piégerait |

**Ce que cette règle rend possible, et qui ne l'était pas.** Un test de bout en bout **à travers le routeur** sur une run de seed connue, et la reproduction d'un rapport de bug joueur à partir de sa seule graine. `01` §5 annonce la seed partageable comme différenciateur : jusqu'à ce commit, elle ne l'était pas.

**Ce que cette règle ne couvre pas.** La graine de la run **n'est pas** la graine d'un combat. `GameRun` la conserve et en dérive un `combatSeed` par manche (§3.2.1) ; le moteur, lui, ne la voit jamais.

**Garde ajoutée au chantier 2.** `RunController::resolveRound()` **ignore tout champ d'issue de combat présent dans la charge utile de la requête** (§6.2). L'issue journalisée est toujours celle que le serveur a simulée.

**Un point de mapping à trancher au chantier 2.** Une run dont la `contentVersion` ne correspond plus au catalogue est rejetée (§6.3). Quel code ? Ce n'est ni une ressource absente (404) ni une requête malformée (400). **Proposition : 409**, via `LogicException`, qui décrit déjà les conflits d'état. À confirmer au moment d'écrire le commit, avec un message qui distingue ce cas d'un conflit de séquence.

**Trois réserves de robustesse toujours ouvertes**, relevées le 8 septembre 2026 et non levées au 19 :

- `Router` ne rattrape ni `PDOException` ni `RuntimeException`. Une violation de la clé primaire `(run_id, sequence)` produirait un 500 brut au lieu d'un 409. **Nuance ajoutée en 2.1 :** ce trou est un défaut pour `PDOException`, mais il est **exploité délibérément** pour `ObsoleteSchemaException` (§6.3), qui étend `RuntimeException` afin de ne jamais être transformée en 409. Refermer la réserve en élargissant le `catch` du `Router` devra donc épargner ce cas, faute de quoi un schéma obsolète sera annoncé au client comme un conflit d'état.
- Aucun garde anti-double-soumission sur `buyItem`, `swapItem` ni `resolveRound`. Un second clic parvenu après le commit du premier rejoue un journal à jour et **joue réellement une manche de plus**.
- `GameRun::purchaseItem()` dépense l'or et marque l'offre achetée avant de tenter le rangement. Si `Stash` refuse, le joueur reçoit une erreur sur un achat qu'il croyait valide. `Stash` n'a pas été relu.

---

## 8. Frontend

- **Store Pinia** (`gameRun.ts`) avec garde fail-fast `requireRunId()`.
- **Client API typé**, proxy Vite pour le développement.
- **File d'attente d'animations** (`combatPlayback.ts`) : le `CombatLog` est **dépilé via une queue asynchrone**, jamais appliqué directement au state. Horloge à 100 ms/tick, révélation cumulative, annulation propre via `stop()`.
- **Audio** : `audioSettings` avec atténuation à 40 % pendant le combat, désactivé par défaut pour respecter les politiques d'autoplay navigateur.
- **Convention de test** : la logique métier (client API, store, composables) est testée en TDD ; les composants Vue ne le sont pas.

**Impact du chantier 2 sur le frontend — à ne pas sous-estimer.** Deux des onze commits le touchent.

| Changement | Effet |
|---|---|
| **Libellés `A`/`B`** (§3.6) | `combatPlayback.ts` ne peut plus lire `targetSide === 'PLAYER'`. Il doit traduire A et B en « vous » et « votre adversaire » **selon le spectateur**, ce qui est une information que le journal ne porte plus et que le client doit fournir |
| **Événement de départage** (§3.5) | Un type d'événement nouveau à afficher. Un combat perdu au départage doit se distinguer d'un KO, faute de quoi le joueur conclura à un bug |

> **Correction de portée.** `04` révision 1.0 écrivait, à propos de D-20, que le lecteur de rejeu n'était pas impacté. C'était exact pour D-20 et faux pour le chantier 2 : **D-19 impacte directement `combatPlayback.ts`**. Les 65 tests Vitest n'ont pas été relus au cadrage.

---

## 9. Infrastructure serveur

### 9.1 Dimensionnement

Chaque combat, PvE comme PvP, est une requête serveur. Une manche comporte 2 combats et 2 phases de marchand, soit ~30 requêtes par run.

À 1 000 joueurs actifs à 3 runs/jour : ~90 000 requêtes/jour, soit **~1 requête/seconde en moyenne**, ~15/s en pic. La simulation elle-même coûte de l'ordre de la milliseconde sur ≤ 6 entités.

**Charge par requête, révisée en 2.0.** Le chiffre ci-dessus compte les requêtes, pas leur coût. Or chaque requête déclenche un `replay()` complet, et à la manche 8 une simple lecture d'état resimulait sept combats (E-09). **D-18 supprime cette resimulation** : à partir du chantier 2, le rejeu applique les issues enregistrées. Le coût par requête baisse d'autant, et il baisse le plus là où il était le plus élevé — en fin de run.

### 9.2 Trois paliers

| Palier | Joueurs | Architecture | Coût mensuel HT |
|---|---|---|---|
| **Alpha** | 0–100 | 1 VPS, Caddy, PHP, SQLite, sauvegarde cron vers stockage objet. Hetzner CX23 (2 vCPU / 4 Go / 40 Go / 20 To) | **~6 €** |
| **Lancement** | 100–1 000 | 1 VPS + PostgreSQL, monitoring, sauvegardes off-site. Hetzner CX43 (4 vCPU / 8 Go / 80 Go) | **20–45 €** |
| **Succès** | 1 000–10 000+ | 2 instances PHP + load balancer, PostgreSQL dédié, Redis, workers, sauvegardes PITR | **80–180 €** |

**Fournisseur retenu : Hetzner**, avec OVHcloud en repli si la localisation France ou le support francophone devient un critère. AWS est écarté : complexité disproportionnée pour ce profil de charge.

**CDN : inutile.** Steam distribue le binaire. Cloudflare gratuit suffit pour la démo web.

**Stockage à prévoir, ajouté en 2.0.** Les enregistrements de combat de §6.2 portent deux snapshots par combat, donc deux par manche à partir de la boucle cible. À ~5 Ko l'unité et 12 manches, une run complète stockerait de l'ordre de 240 Ko de snapshots — chiffre à revoir avec la mesure réelle de §5.3. **Sans conséquence sur le palier alpha ; à vérifier avant le palier lancement.**

### 9.3 Ce que le coût serveur n'est pas

Le coût serveur ne dépassera jamais 3 % du chiffre d'affaires. **Aucune décision de design ne doit en dépendre.** Le vrai coût du palier « succès » n'est pas l'argent, c'est le temps d'astreinte d'un développeur solo.

---

## 10. CI/CD

- GitHub Actions sur `ubuntu-latest`, jobs `php-tests` et `frontend-tests`.
- Version de Node épinglée via `frontend/.nvmrc`, référencée par `node-version-file`.
- **Portes bloquantes sur toute PR vers `dev`** : validation JSON, PHPUnit, PHPStan niveau 6, PHP CS Fixer côté backend ; ESLint, Prettier, `vue-tsc`, Vitest côté frontend.
- **À ajouter avant J0 :** build matriciel du binaire `corebound-engine` pour Windows, Linux et macOS, et test de parité de déterminisme entre serveur et binaire embarqué.
- **Porte locale :** `check-all.ps1`, fail-fast, PHPUnit → PHPStan → CS Fixer, puis Prettier → ESLint → `vue-tsc` → Vitest.

**Porte ajoutée au chantier 2.** L'empreinte de `contentVersion` (§6.3) doit être calculée en CI et comparée à celle du dépôt : un catalogue modifié sans que les tests de rejeu soient relus doit échouer le build, pas passer.

---

## 11. Décisions d'architecture écartées

| Option | Motif du refus |
|---|---|
| **Unreal Engine** | Aucune valeur créée : pas de 3D, pas de physique, pas de rendu temps réel exigeant. 6 à 18 mois de réécriture pour un résultat fonctionnellement identique. Réutilisation du backend ≈ 0 % |
| **Tauri** | `steamworks.js` indisponible, overlay Steam non fiable sur WebView, packaging Steam Linux problématique, Rust requis sur le chemin critique |
| **FrankenPHP côté client** | Exige WSL sous Windows |
| **Epic Online Services comme backend** | Dépendance externe sur un système qui doit rester intégralement archivable |
| **Sérialisation d'instantané pour la run** | Incompatible avec les classes domaine `final readonly` ; le journal d'actions est supérieur en migration et en versionnement. *(À ne pas confondre avec le snapshot de combat, qui est bien une photographie — §5.5. Les deux choix sont opposés parce que les deux objets ont des durées de vie opposées : une run se rejoue en avançant, un combat se rejoue tel quel.)* |
| **WebSocket / Mercure** | Bonus envisageable en V2+ (notifications, signal de fin de combat), jamais une brique structurante |
| **Framework applicatif PHP** | Le moteur est du domaine pur ; un framework n'apporterait que de la surface |
| **Recette de reconstruction comme forme de snapshot** | *(écarté le 19/09/2026, D-16)* Exigerait de conserver indéfiniment chaque catalogue historique **et** le décorateur de sa version, et de les embarquer dans le binaire client. Un fantôme rejoué changerait de chiffres à chaque rééquilibrage. §5.5 |
| **Flux aléatoire unique pour le combat** | *(écarté le 19/09/2026, D-22)* Ajouter une ligne de critique à un objet décalerait les ordres d'initiative de tous les ticks suivants, et l'inverse. Deux flux dérivés coûtent un hachage de plus et isolent les deux usages |
| **Graine de combat héritée du flux de la run** | *(écarté le 19/09/2026, D-22)* C'est l'état actuel. Il rend le contenu d'une boutique dépendant du déroulé du combat précédent dès que le combat consomme de l'aléa, et empêche le moteur embarqué de rejouer un combat isolément |
| **Épingler `engineVersion` sur la run** | *(écarté le 19/09/2026, D-18)* Réglerait E-11, mais fermerait toutes les runs en cours à chaque correctif du moteur. L'issue enregistrée obtient le même découplage sans ce coût |
| **Version de contenu manuelle** | *(écarté le 19/09/2026, D-18)* Plus souple, mais un oubli d'incrémentation corrompt les rejeux en silence. L'empreinte automatique ne peut pas être oubliée |