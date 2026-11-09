# Corebound — Résumé de session 025

**Branche** : `feature/live-triggers`, **ouverte**, non poussée à la rédaction
**Chantier** : 3 — déclencheurs vivants et garde anti-cascade (`07` §6, rang 7). **Briques 1b et 2 faites**, brique 3 à faire

> **Rédigé le 09/11/2026**, dans Claude Code, en terminal, avec accès direct au dépôt. Première session menée ainsi. Tous les chiffres viennent d'exécutions faites pendant la session : `check-all.ps1`, PHPUnit, PHPStan et l'outil de capture.

---

## Contexte et objectif

La session 024 avait fini la brique 1a (le vocabulaire) et laissé la brique 1b (un seul déclencheur par objet) en attente de quatre fichiers. Ici, ils se lisent sur place. La session couvre trois sujets :

- la brique 1b ;
- un incident d'outillage, PHPStan ;
- la brique 2, la photographie au format 2 (D-28).

Elle finit par la mise à jour du corpus.

## Commits de la session

| Commit | Contenu |
|---|---|
| `95c7d26` | `feat(domain)` — brique 1b, et la limite mémoire de PHPStan, gardée dans le même commit à la demande de Laurent |
| `5a64184` | `chore(tooling)` — `.claude/settings.json` : `git commit` et `git push` passent de `deny` à `ask` |
| `313a267` | `feat(domain)` — brique 2 |
| *(ce commit)* | `docs(corpus)` — `02` 3.6, `04` 2.17, `06` 2.13, `07` 3.16, README, ce résumé |

## Brique 1b — un seul déclencheur par objet

**Option A**, retenue en session 024 : la règle vit dans le constructeur d'`Item`.

- **`Item`** compte les déclencheurs **distincts** de ses effets et refuse au-delà d'un, avec une `InvalidArgumentException`. Le message nomme l'objet et les déclencheurs dans l'ordre déclaré. Un objet sans effet reste permis, tout comme plusieurs effets au même déclencheur. Les dix-sept constructions de test à `effects: []` le gardent.
- **`BoardHydrator`** rhabille le refus en `UnreadableBoardRecordException` avec le chemin de l'objet, comme il rhabille les règles de `CombatBoard`.
- **`EventDispatcherTest`** : l'objet à deux déclencheurs passe à deux effets sous `EVERY_N_TICKS`.
- **`SimulatorTest`** : `twoTriggerItem` devient `twoEffectItem`.

**Un écart avec le résumé 024, signalé avant d'écrire.** Le résumé 024 voulait que le docblock de `SimulatorTest` présente le test comme « le vrai ancrage d'E-15 ». Ce serait devenu faux. E-15 exigeait un objet à deux déclencheurs, que `Item` refuse désormais : le défaut est refermé deux fois, par la liste plate d'`EventDispatcher` et par `Item`. Le test réécrit garde NF-01 sur sa paire de plateaux. Ce point a été **vérifié par une perturbation** : quand l'attribution des côtés suit l'ordre des arguments dans `SimulationContext`, le test échoue. La perturbation a été retirée, et `git diff` est vide sous `Domain/Engine/`.

**Rouges vus.**

- `ItemTest` : un échec, et non une erreur.
- La garde d'`ItemTest` a été vue tomber sous une règle volontairement trop stricte, `count($effects) > 1`. C'est une erreur levée par `Item`, pas une erreur fatale.
- `BoardHydratorTest` : un échec sur le type d'exception.

**Relecture par l'agent `relecteur-brique`** : rien de bloquant. Deux phrases du docblock de `SimulatorTest` en disaient plus que le test, et elles ont été corrigées :

- « il échouerait pour toute autre dépendance à cet ordre » ;
- « C'est exactement le cas ici ».

Trois remarques ont aussi été appliquées :

- le test de l'hydrateur vérifie le chemin **et** la cause ;
- un commentaire ambigu de `BoardHydrator` est clarifié ;
- deux noms de variables d'`EventDispatcherTest`, restés de l'époque `ON_ATTACK`, sont renommés.

## PHPStan : la mémoire à cache froid

`check-all.ps1` est tombé sur PHPStan : « Allowed memory size of 134217728 bytes exhausted… while running parallel worker ». Ce n'était pas une erreur du code. Avec `--memory-limit=1G`, l'analyse donne `No errors`.

- **La cause du plafond.** Le `php.ini` de WAMP fixe `memory_limit=128M`. C'est le même binaire et le même fichier sous Bash et sous PowerShell.
- **`memoryLimit` dans `phpstan.neon`, proposé par Laurent, n'existe pas.** PHPStan 2.2.6 le refuse : « Unexpected item 'parameters › memoryLimit' ». L'essai a été fait dans une configuration temporaire, hors du dépôt.
- **Retenu : `"stan": "phpstan analyse --memory-limit=512M"`** dans `composer.json`. La CI (`ci.yml:44`) et `check-all.ps1` passent toutes deux par ce script. 512M suffit à cache vide, vérifié après `phpstan clear-result-cache`.
- **Ce qui n'est pas établi** : pourquoi la porte passait en session 024.
- **Ce qui n'est pas vérifié** : l'effet en CI, tant qu'aucun job n'a tourné.

## Brique 2 — la photographie au format 2 (D-28)

**Le choix de conception, par défaut, validé par Laurent : le héros porte ses objets.**

- `CombatHero(HeroProfile $profile, array $items)` : la liste est requise, sans valeur par défaut, pour qu'un oubli à l'assemblage ne produise pas en silence un héros désarmé.
- `CombatBoard(CombatVestige $vestige, array $heroes, int $goldAtCombatStart)` perd son paramètre `$items`. Il calcule sa liste plate une fois, roster d'abord, affectation ensuite. C'est l'ordre que recevait l'ancien paramètre, donc celui du dispatcher et des cooldowns. **Un objet orphelin devient impossible par construction**, et non par contrôle.
- `CombatBoardFactory` remet à chaque héros ses objets décorés.
- `BoardSnapshot` écrit `heroes[].items[]`. Un héros sans objet porte `items: []`, et la liste de premier niveau disparaît. `BoardRecord::FORMAT_VERSION` vaut 2.
- `BoardHydrator` relit l'appartenance dans la photographie seule. Il refuse la version 1 et un héros sans clé `items`. Les chemins deviennent `board.heroes.N.items.M`.

**Deux cycles TDD.**

- **Cycle 1, le runtime.**
  - Rouges : `CombatHeroTest`, avec `getItems()` absent, et `CombatBoardTest`, où `$items` est encore requis. Ce sont des erreurs d'API absente, limitées à leur test (`06` §4.4).
  - Les 15 fichiers de test qui construisent un héros ou un plateau ont été adaptés.
  - Une étape **transitoire**, jamais commitée, mettait tous les objets du format 1 sous le premier héros pour garder la suite verte.
  - Fin du cycle : 499 tests verts, rejeu de référence compris.
- **Cycle 2, le format.**
  - **Onze échecs d'assertion** au format 1, dans `BoardSnapshotTest`, `BoardRecordTest` et `BoardHydratorTest`.
  - L'un d'eux montre le défaut que D-28 corrige : en format 1, les mêmes objets répartis autrement entre les héros photographient à l'identique.
  - Une fois l'implémentation posée, il ne restait que les deux épingles de taille, mesurées ensuite, et les douze tests qui relisaient la fixture encore en format 1 : « format version 1, but this build reads version 2 ».

**Fixture régénérée par son outil** (`--seed=46 --round=6`) :

- `board-a`, `board-b` et `meta.json` changent ; dans `meta.json`, seule la date `capturedAt` bouge ;
- **`combat-log.json` est identique à l'octet près** : 48 ticks, KNOCKOUT, vainqueur B ;
- la paire n'a donc pas changé de côté ;
- la fixture a été vue rouge sur une perturbation de `baseHp` (100 → 99), puis restaurée.

**Docblocks corrigés.** `SnapshotRecipe`, `OpponentBoard`, `BoardHydrator`, `BoardHydratorTest` et `BoardRecord` disaient l'appartenance « perdue dans la liste plate ».

**`EngineVersion` ne bouge pas** (reste à 2) : aucun fichier sous `src/Domain/Engine/`. Elle passera à 3 à la brique 3, une fois pour la branche (`04` §3.8).

## Mesures

| Mesure | Valeur | Ce qu'elle établit |
|---|---|---|
| Maximum structurel de la photographie | **2 149 octets** (2 129 au format 1) | +11 par héros, −11 pour la liste de premier niveau, une virgule de moins par héros armé, moins une |
| Plateau minimal des tests, 1 héros 1 objet | 353 octets dans les deux formats | Un plateau à un seul héros garde sa taille ; les 349 octets de la manche 1 (`04` §5.3) restent valables |
| Milieu de run, 1 260 octets au format 1 | Non remesuré, le plateau n'est pas conservé | Le calcul donne 1 270 ou 1 271 |
| Sortie de la requête de référence du moteur | **9 936 octets**, dans les deux ordres | Inchangée ; les 10 282 octets d'autres graines restent à voir en CI |
| Journal de la fixture | 9 826 octets, identique | La régénération ne change que les enveloppes |
| Suite backend | **503 tests, 1 727 assertions**, 2 ignorés | +9 depuis la session 024 (494). Brique 1b : +3, soit 2 `ItemTest` et 1 test d'hydrateur. Brique 2 : +6, soit 1 `CombatHeroTest`, 1 `CombatBoardTest`, 2 `BoardSnapshotTest` et 2 `BoardHydratorTest` ; le test de version de format est renommé, pas ajouté |
| `check-all.ps1` | Vert de bout en bout, cache PHPStan vidé avant | Frontend 81, desktop 36, inchangés |

## Corpus

- `02` passe en **3.6** :
  - le tableau d'état de §7.7 distingue l'implémenté (D-23, vocabulaire et règle ; D-28) de l'engagé (lecture du déclencheur, D-24 à D-27) ;
  - §7.4 et une phrase de §7.7 passent au passé.
- `04` passe en **2.17** :
  - §3.6 : E-15 n'est plus constructible ;
  - §3.8 : ce qui est codé, et la réponse aux deux questions qu'elle laissait ouvertes ;
  - §4.3 : la limite mémoire ;
  - §5.3 : les tailles en deux colonnes ;
  - §5.5 : D-28 écrite, et les annotations « cessera d'être vrai » soldées ;
  - §5.6 : les nouveaux chemins et deux refus.
- `06` passe en **2.13** :
  - §4.2 : la limite mémoire ;
  - §6.2 : le scope `tooling` déclaré, et la règle des messages multilignes précisée (voir plus bas) ;
  - §10 : trois pièges — le filtre de PHPUnit sous PowerShell, `git grep`, la mémoire de PHPStan — et la version de PHPStan corrigée (2.2.6, et non 2.2.16).
- `07` passe en **3.16** :
  - deux points ouverts de §3.1 soldés ou déplacés : `SimulatorTest` relu, et le libellé des 18 objets renvoyé à la brique 7 ;
  - E-15 gagne une ligne ;
  - la brique 1 nomme `SimulatorTest`.
- **`00` ne change pas** : aucune décision ne bouge.
- **README** : le chantier 3 entre dans l'avancement, la prochaine étape devient la brique 3, et les compteurs de tests passent à 503 / 1 727.

**Une règle de `06` précisée, à relire par Laurent.**

- `06` §6.2 interdisait les commits multilignes « par le terminal ».
- Les commits de cette session sont passés par Git Bash avec `git commit -F <fichier>`, à la demande de Laurent.
- La règle porte maintenant sur ce qu'elle vise, l'échappement. Un message passé en `-m` sous PowerShell reste interdit.

## Erreurs commises et rattrapées

- **Deux phrases de docblock qui disaient plus que le test**, dans `SimulatorTest`. Relevées par le relecteur, corrigées avant le commit.
- **« plus bas » pour « plus haut »**, dans le commentaire de `BoardHydrator` qui situe le réemballage de `CombatBoard`. Vu en relisant, corrigé avant le commit.
- **Une cause présentée comme établie.** La première rédaction de `06` §10 attribuait l'épuisement mémoire de PHPStan à la modification d'`Item`. Rien ne le prouve. Retiré avant le commit.
- **« Les trois premières briques »**, dans la première rédaction de `04` 2.17. La formule se lisait comme les briques 1 à 3 de `07`, alors que seules 1 (en deux commits) et 2 sont faites. Corrigé.
- **Deux commits refusés par la configuration.** `.claude/settings.json` portait `git commit` en `deny`, et un refus l'emporte sur une autorisation donnée à l'invite. Laurent a passé `commit` et `push` en `ask` (`5a64184`).

## Ce qui reste ouvert

**Dans la branche :**

- Les briques 3 à 8.
- **À la brique 3**, sous `Domain/Engine/` :
  - le docblock d'`EventDispatcher`, qui cite à tort `04` §3.4 (cinquième écart de `07` §2) ;
  - `SimulationContext.php` l.86-95, qui répète l'ancien `04` §3.6 sur l'ancre d'E-15.
- **Trois questions** :
  - le libellé des 18 objets renommés, à la brique 7 ;
  - refuser ou non un statut à moins d'une pile, avant la brique 5 ;
  - le son d'`ITEM_REACTED`, à la brique 7.
- **`UnreadableBoardRecordException`** énumère six causes dans son docblock. L'objet injouable de la brique 1b en est une variante de « plateau injouable », sans y être nommée. C'est une remarque, rien n'est faux.
- **Le push.** `git push -u origin feature/live-triggers` a échoué le 09/11/2026 depuis la session : `Permission denied (publickey)`. L'alias `github-dwwm2` impose la clé `~/.ssh/dwwm2`, que GitHub n'accepte pas pour ce compte. L'état du dépôt distant à la rédaction est inconnu.
- **`58933c0` n'est pas une copie du commit de cadrage `0424c02`**, contrairement à ce que son message laissait craindre. Il part de la fusion de la PR #47 et apporte 18 ajouts et 10 retraits à `02`, `04` et `07`. Il apparaîtra donc légitimement dans la PR vers `dev`, sous un message identique à celui du cadrage.

**Hérités, inchangés depuis la session 024 :**

- la porte de CI sur l'empreinte de contenu ;
- Prettier vérifié par aucun job ;
- le relais de `/runs` ;
- le menu par défaut d'Electron ;
- l'audit de licences C-02 ;
- le retrait de `windows-2022`, les actions de CI sur Node 20, et `ubuntu-latest` vers Ubuntu 26 ;
- les anomalies E-14 et E-16.

## État final

**Backend : 503 tests PHPUnit, 1 727 assertions**, dont 2 ignorés hors du job de parité Windows. **Desktop : 36. Frontend : 81.**

`EngineVersion::CURRENT = 2`, `BoardRecord::FORMAT_VERSION = 2`.

## Prochaine session

**Brique 3**, sur `feature/live-triggers` : le déclencheur lu (`07` §6, `04` §3.8).

1. Relire `TickEngine`, `CombatItem` et `EventDispatcher`, et le test qui fige l'activation à la recharge.
2. Écrire les rouges :
   - un objet `ON_COMBAT_START` agit au tick 1 ;
   - un objet réactif rechargé n'agit pas seul ;
   - un objet `EVERY_N_TICKS` ne change pas.
3. Relever `EngineVersion` à 3, une seule fois pour la branche, puis régénérer la fixture par son outil et vérifier ce qui change dans `combat-log.json`.
4. Corriger au passage les docblocks d'`EventDispatcher` et de `SimulationContext`.
