# 06 — Conventions de développement

**Autorité sur :** la méthodologie, la qualité, les commits, la Definition of Done.
**Révision :** 2.3 — 22 septembre 2026.

Ces conventions ne sont pas des préférences : ce sont des règles nées d'erreurs réelles commises sur ce projet. Chacune conserve la trace de son motif.

> **Note de gouvernance, ajoutée en 2.1.** L'en-tête de ce document indiquait « Révision 1.0 » alors que §6.1 et §6.2 portaient déjà la mention « ajouté / corrigé en révision 2.0 ». Les deux lectures étaient défendables : une révision 2.0 **de ce document** dont l'en-tête n'aurait pas été bumpé, ou une référence à la **version 2.0 du corpus** (`00-INDEX`). L'ambiguïté est levée ici.
>
> **Convention retenue : le numéro de révision d'un document lui est propre et n'a aucun rapport avec la version du corpus.** Un document peut être en 1.0 dans un corpus 2.0. Quand un document cite une révision, il cite **la sienne**, sauf à écrire « version du corpus » en toutes lettres. Ce document passe donc en 2.1 : 2.0 pour les changements de §6 déjà présents, 2.1 pour ceux de la présente passe.

**Ce qui change en 2.1.** Le cadrage du chantier 2, mené le 19 septembre 2026, a produit quatre règles d'intégrité nouvelles (§8), deux règles de conception (§3), un piège d'outillage mesuré (§10) et un piège PHPUnit qui touche directement la porte de déterminisme (§4.4). Il a aussi montré qu'une règle de §1.2 était incomplète.

**Ce qui change en 2.3.** Trois réflexes, tous nés d'un échec réel de la session du 21 septembre 2026 : deux pièges d'outillage ajoutés en §4.4 — le fichier de test vide et le cache de résultat de PHPStan —, et un contrôle en §1.3, le `git diff --stat` après application d'un fichier reçu. Ce dernier est le seul qui ne dépende d'aucune affirmation extérieure, et il aurait arrêté les trois fichiers périmés appliqués ce jour-là.

**Ce qui change en 2.2.** Une seule correction, mais elle porte sur ce document et non sur le code : **l'un des deux « trous de couverture connus » de §4.3 n'existait pas.** Il avait été déclaré sans que le fichier de test soit ouvert. L'autre, vérifié à nouveau, est bien réel. La règle qui en sort est en §4.3 : on n'écrit « couverture inconnue » qu'après avoir cherché.

---

## 1. Principes

### 1.1 L'architecture avant le code

Toute décision d'architecture est validée par questions/réponses explicites **avant le premier test rouge**. Les options rejetées sont documentées avec leur justification, pour ne pas les redécouvrir six mois plus tard.

**Un cadrage est une session à part entière.** Il ne produit aucun code et se conclut par des décisions versées dans le corpus, sur une branche `docs/` (§6.1). Le cadrage du chantier 2 a tranché sept décisions en une session, dont quatre irréversibles : les prendre pendant l'écriture du code les aurait prises par défaut.

### 1.2 Ne jamais coder sur hypothèse

**Le contenu réel d'un fichier est demandé avant toute modification.** Un état de fichier supposé a déjà causé de vrais bugs à plusieurs reprises — `heroes.json` et `JsonHeroRepository` ont été désynchronisés plusieurs fois dans une même session, le catalogue passant de 1 à 4 à 10 héros au fil de la conversation.

C'est la règle la plus souvent violée et la plus coûteuse.

**Trois niveaux de lecture, et non un.** La règle ci-dessus n'a cessé d'être insuffisante :

| Niveau | Règle | Erreur qui l'a produite |
|---|---|---|
| 1 | **Lire la donnée** | `heroes.json` supposé, désynchronisé |
| 2 | **Lire le code qui consomme la donnée** | *(audit du 08/09/2026)* « `SUNDERING` modifie 14 objets » comptait correctement les objets à `DEAL_DAMAGE` sans voir que le décorateur les filtre sur `TWO_HAND` |
| 3 | **Lire le code qui produit la donnée** | *(cadrage du 19/09/2026)* « L'association héros ↔ objet est perdue à la construction du plateau » était exact sur `CombatBoard`, mais `CombatBoardFactory` **décore les objets avant de les y ranger**. La conclusion qu'on en tirait — « la recette est la seule forme de snapshot viable » — était fausse, et avait survécu à une révision entière de `07` |

Le niveau 3 est le plus coûteux à manquer, parce qu'il produit des conclusions qui **se figent en format irréversible**.

### 1.3 TDD strict, ascendant

```
test rouge écrit → rouge CONFIRMÉ par exécution réelle
  → implémentation minimale → vert
  → CS Fixer + PHPStan + PHPUnit → commit
```

**Le rouge doit être constaté, pas supposé.** Livrer du code en bloc sans test rouge préalable est une violation de méthode, déjà survenue et corrigée.

**`git diff --stat` avant de lancer quoi que ce soit** *(ajouté en 2.3)*. Après avoir appliqué un fichier reçu, comparer l'ampleur du diff à ce qui était annoncé. Une édition d'une ligne qui en montre vingt signale une copie périmée ou un transfert tronqué — et c'est le seul contrôle qui ne dépende d'aucune affirmation de celui qui a produit le fichier. **Trois fichiers de tests périmés ont été appliqués le 21/09/2026** faute de ce réflexe : ils revenaient à un état d'avant le renommage des côtés en A/B, et c'est l'analyseur de l'éditeur qui l'a signalé, pas le processus.

**Corollaire pour les tests de caractérisation.** Un test qui fige un comportement **actuel et faux**, destiné à être réécrit par un chantier ultérieur, doit le dire dans son nom ou son commentaire. Sans quoi le chantier suivant le lit comme une exigence et contourne le problème au lieu de le corriger.

### 1.4 YAGNI

Aucune mécanique de la Vision cible ou de la Roadmap V2+ ne passe en code sans décision explicite qui la fait basculer dans un jalon du cahier des charges.

**Exception nommée : les formats irréversibles.** Un champ qu'on ne peut pas ajouter après coup sans invalider un corpus n'est pas de l'anticipation, c'est une contrainte de séquence. `goldAtCombatStart` a été embarqué dans le format de snapshot **avant** que la compétence qui le lira existe, et c'est la bonne décision. La distinction tient en une question : *est-ce que l'ajouter plus tard coûterait plus qu'un refactoring ?* Si oui, ce n'est pas du YAGNI. `07` §8 tient la liste.

### 1.5 Ordre par dépendance technique

```
Domain → Application → Infrastructure → Presentation → Http → Frontend
```

Une couche n'est jamais implémentée avant que ses dépendances soient vertes.

### 1.6 Une brique logique par commit

Un commit = une unité de sens qui compile, passe les tests, et peut être relue seule.

**Un commit ne traverse pas deux couches sans raison.** Le cadrage du chantier 2 a séparé le calcul de la graine de combat (Application) de sa dérivation en deux flux (Domain), qui auraient pu tenir en un commit. Motif : le Domaine ne doit rien savoir de la run, et deux commits rendent cette frontière visible en relecture. De même, le passage aux libellés de côté neutres voyage seul parce qu'il touche le frontend.

---

## 2. Seuils de refactoring

| Contexte | Seuil d'extraction |
|---|---|
| Code de production | **3ᵉ occurrence** |
| Helpers de test | 2ᵉ ou 3ᵉ occurrence, selon le coût de duplication |

L'extraction de `GameRunFactory` (source unique de vérité pour `run.php`, le trait de test et le futur replayer) est l'application canonique de cette règle.

**Contre-exemple assumé :** 8 fichiers de tests construisent `new Vestige(...)` directement. Le seuil du Test Object Mother a été approché deux fois sans être franchi — décision débattue et tranchée, pas un oubli.

---

## 3. Règles de conception

| Règle | Motif |
|---|---|
| **Deux fixtures distinctes plutôt qu'un helper ambigu** | Un helper paramétrable qui sert deux intentions différentes finit par ne servir correctement ni l'une ni l'autre |
| **Méthodes statiques pures quand il n'y a pas d'état** | Une instance sans état est une indirection gratuite |
| **Noms de méthodes explicites plutôt que paramètres booléens** | `createOffensiveBoard()` plutôt que `createBoard(bool $offensive)` |
| **Fail-fast sur configuration incomplète** | Un `??` sur un champ obligatoire transforme une erreur de configuration en bug silencieux à l'exécution |
| **Validation intégrale avant mutation** | L'achat en boutique valide tout, puis mute. Jamais de débit partiel |
| **Arithmétique entière dans le Domaine** *(ajouté en 2.1)* | Un pourcentage se calcule en `intdiv(v * n, d)`, jamais en `ceil($v * 1.35)`. Deux motifs distincts : l'écriture JSON d'un flottant dépend de `serialize_precision`, ce qui casse la parité d'EX-J0-01 ; et `ceil()` sur un produit flottant **diverge de l'arrondi exact** sur certaines valeurs (§10). La règle est tenue dans le moteur de combat et **manquée dans `HeroSkillDecorator`** depuis l'origine |
| **Jamais l'ordre d'itération d'un tableau associatif comme règle** *(ajouté en 2.1)* | Un ordre qui dépend de la séquence d'enregistrement est une règle de jeu écrite par accident. Si un ordre compte, il est **explicite** : un tri déclaré, un index, ou un tirage seedé. Trois occurrences connues — l'ordre d'activation entre plateaux (tranché par D-14), l'ordre des effets d'un objet dans `dispatchForItem()` (ouvert, chantier 3), l'ordre des clés d'une charge utile (tranché par D-19) |
| **Un ordre de liste qui porte du sens n'est jamais trié** *(ajouté en 2.1)* | L'ordre des héros et de leurs objets détermine l'ordre d'activation en combat. Une sérialisation canonique trie les **clés**, jamais les **listes**. La règle inverse aurait changé le jeu sans que rien ne le signale |

---

## 4. Porte qualité

### 4.1 Locale — `check-all.ps1`

PowerShell, **fail-fast** :

```
Backend :  PHPUnit → PHPStan niveau 6 → PHP CS Fixer
Frontend : Prettier → ESLint → vue-tsc → Vitest
```

### 4.2 CI — GitHub Actions

Jobs `php-tests` et `frontend-tests` sur `ubuntu-latest`. **Bloquants sur toute PR vers `dev`.** Version de Node épinglée via `frontend/.nvmrc`, référencée par `node-version-file`.

**À ajouter avant J0 :** build matriciel du binaire `corebound-engine` (Windows / Linux / macOS) et **test de parité de déterminisme** entre le serveur et le binaire embarqué, sur un jeu de graines fixes.

**À ajouter au chantier 2** *(2.1)* : une porte sur l'**empreinte de version de contenu**. Les quatre catalogues sont hachés, et un catalogue modifié sans relecture des tests de rejeu doit échouer le build plutôt que passer silencieusement. `04` §6.3.

### 4.3 Périmètre de test

| Couche | Testée |
|---|---|
| Domaine PHP | **Oui**, systématiquement |
| Application PHP | **Oui** |
| HTTP PHP | **Oui** |
| Client API, store Pinia, composables TS | **Oui** |
| Composants Vue | **Non**, par convention explicite |

**Trous de couverture :** documentés en commentaire **dans le fichier de test concerné**, jamais dans un document séparé. Un trou documenté ailleurs que là où il se trouve n'est pas documenté.

**Un trou connu, et un faux trou** *(révisé le 20/09/2026)*, à consigner dans les fichiers concernés :

- **Trou réel, confirmé.** La direction du biais d'enrage sur mort simultanée n'est figée que par un test **unitaire** d'`EnrageProcessor`. Aucun test ne la vérifie **à travers `Simulator::run()`**. C'est le seul des trois comportements de résolution qui ne soit pas caractérisé de bout en bout. **Vérifié à nouveau le 20/09/2026 dans `SimulatorTest` : toujours absent**, et le commentaire de `testCharacterizesPlayerBoardPriorityOnSimultaneousActionDeath` renvoie explicitement au test unitaire, ce qui rend le trou lisible sans le combler. Voir `07` §6, chantier 0.
- ~~La couverture de l'ordre des clés de charge utile est **inconnue pour `ActionProcessor`**~~ — **faux, corrigé le 20/09/2026.** `ActionProcessorTest` fige par `assertSame` sur le tableau entier les charges utiles de `DAMAGE_DEALT`, `SHIELD_GAINED`, `HEAL_RECEIVED` **et** `STATUS_APPLIED` : la couverture est acquise là aussi, et par le même effet de bord (§4.4).

> **Pourquoi cette ligne est conservée au lieu d'être supprimée.** Le premier trou a été relevé en lisant le code ; le second a été **déclaré inconnu sans ouvrir le fichier**, puis recopié de révision en révision. Un trou de couverture inventé coûte le même temps de vérification qu'un vrai, et fait douter des autres lignes de la liste. **Règle qui en découle : on n'écrit « couverture inconnue » qu'après avoir cherché**, sans quoi on écrit « non vérifié », ce qui est une autre affirmation.

### 4.4 Pièges connus de PHPUnit et de l'outillage

- Le suffixe `Test` est obligatoire dans le nom de fichier. Son absence **exclut le test silencieusement**.
- Le namespace doit être correct, même symptôme.
- Un espace parasite dans un nom de fichier exclut le test, également en silence.
- Les data providers utilisent les **attributs**, pas les docblocks (PHPUnit 12).
- `expectExceptionMessage()` fait une correspondance **par sous-chaîne** (`str_contains`), pas une égalité stricte.
- **`assertSame` sur deux tableaux compare aussi l'ordre des clés** *(ajouté en 2.1)*. `===` sur des tableaux PHP exige les mêmes clés **dans le même ordre**, et `assertSame` repose sur `===`. Conséquence à connaître dans les deux sens : c'est ce qui fige aujourd'hui, sans l'avoir voulu, l'ordre des clés des charges utiles de statut et d'enrage — un constat de `07` révision 2.0 affirmait à tort qu'aucun test ne le couvrait. Et c'est aussi ce qui fera échouer un test le jour où une charge utile sera réordonnée sans que le format canonique ait changé.
- **Un fichier de test vide donne exactement le même symptôme que les trois premiers** *(ajouté en 2.3)*. `Class XxxTest cannot be found` ne dit pas si le fichier est mal nommé, mal rangé, ou **présent et vide** — un transfert tronqué produit le troisième cas, et on cherche alors une erreur de nommage qui n'existe pas. **Le réflexe : vérifier la taille du fichier avant d'ouvrir une hypothèse.** C'est pour cela que tout fichier livré doit l'être avec son nombre de lignes et d'octets.
- **PHPStan peut garder un `class.notFound` périmé dans son cache de résultat** *(ajouté en 2.3)*. Symptôme : une classe dont le fichier existe, dont le contenu est correct, et que PHPStan déclare introuvable — y compris après avoir corrigé le fichier. **Le critère qui sépare ce cas d'une vraie erreur :**
  ```powershell
  php -r "require 'backend/vendor/autoload.php'; var_dump(class_exists('App\\...'));"
  ```
  Si PHP charge la classe et que PHPStan ne la voit pas, c'est le cache : `vendor\bin\phpstan clear-result-cache`. Sans ce test, on cherche dans un fichier sain — ce qui a coûté trois allers-retours le 21/09/2026.

---

## 5. Definition of Done

Une brique n'est terminée que si **tous** ces points sont vrais :

- [ ] Test rouge écrit et **rouge constaté par exécution réelle**
- [ ] Implémentation minimale, vert obtenu
- [ ] `check-all.ps1` vert de bout en bout
- [ ] Trous de couverture connus documentés dans le fichier de test
- [ ] Aucune mécanique hors périmètre introduite au passage
- [ ] Documentation impactée mise à jour, ou écart signalé explicitement
- [ ] Commit unique, message conventionnel, brique logique isolée

---

## 6. Git et commits

### 6.1 Branches

| Préfixe | Usage |
|---|---|
| `feature/` | Nouvelle mécanique ou fonctionnalité |
| `fix/` | Correction de bug |
| `docs/` | Travail documentaire autonome, découplé de toute session de code |
| `ci/` | Chaîne d'intégration, outillage |
| `chore/` | Maintenance, dépendances, configuration |

Intégration sur `dev` par PR. `main` reçoit les versions taguées.

**Distinction à tenir sur `docs/`.** La mise à jour documentaire qui **clôt une session de code** — README, résumé de session — voyage avec la branche de cette session, conformément à §7.4. Elle ne justifie jamais une branche à elle seule. Le préfixe `docs/` est réservé au travail documentaire qui **ne suit aucun code** : refonte de corpus, versement d'un document thématique, correction de gouvernance. Précédent dans le dépôt : `origin/docs/lore-and-da`.

**Un cadrage prend une branche `docs/`** *(précisé en 2.1)*. Un cadrage précède le code, il ne le clôt pas : il entre donc dans « travail documentaire qui ne suit aucun code ». **Il est fusionné sur `dev` avant l'ouverture de la branche de code**, pour que les commits d'implémentation partent de décisions déjà versées et relisibles séparément. Précédent : `docs/combat-snapshot-framing`, qui porte les décisions du chantier 2 avant `feature/versioned-combat-snapshot`.

L'alternative — ouvrir directement la branche de code et en faire les premiers commits `docs(...)` — coûte une PR de moins, mais rend les décisions non relisibles indépendamment du code qui les applique. Sur un chantier qui porte des points irréversibles, c'est le mauvais échange.

**Ajouté en révision 2.0.** Le préfixe `docs/` était déjà en usage dans le dépôt sans figurer dans ce tableau. Ce n'est donc pas une règle nouvelle mais la déclaration d'un usage établi.

### 6.2 Messages

Conventional Commits, sous la forme `type(scope): sujet`. **Les deux sont obligatoires.**

**Types en usage** — ce que fait le commit : `feat`, `fix`, `docs`, `test`, `ci`, `chore`.

**Scopes en usage** — ce que le commit touche :

| Nature du commit | Scope |
|---|---|
| Code | La couche touchée : `domain`, `application`, `infrastructure`, `persistence`, `presentation`, `http`, `frontend`, `config`, `backend` |
| `docs` | Le document touché : `readme`, `conventions`, `corpus`, `lore-bible`, `affinities`, `roadmap`, `gdd`, `architecture`… |

```
feat(domain): add engineVersion to combat snapshot
fix(frontend): prevent animation queue from leaking on unmount
test(domain): assert byte-identical replay of a reference combat
docs(conventions): déclarer le préfixe docs/ et séparer types et scopes
ci(backend): add determinism parity check between server and embedded engine
chore(config): bump stash capacity constant
```

**Défaut corrigé en révision 2.0.** La liste « scopes en usage » de la révision 1.0 contenait `ci` et `chore`, qui sont des **types** dans ses propres exemples juste au-dessus : les deux notions n'étaient pas distinguées. Elle omettait par ailleurs `docs` et `test`, tous deux en usage dans le dépôt — `docs` sur treize commits.

**Trois scopes `docs` ajoutés en 2.1** : `roadmap`, `gdd` et `architecture`, tous trois en usage à partir de la branche de cadrage du chantier 2.

**Les commits à message multiligne passent par le panneau Source Control de VSCode**, jamais par le terminal — l'échappement en PowerShell a déjà causé des incidents.

---

## 7. Structure d'une session

1. **Ouverture** — confirmation de l'état Git.
2. **Cadrage** — architecture validée par questions explicites avant tout code.
3. **Travail** — sur une branche nommée, TDD strict, une brique par commit.
4. **Clôture** — texte de PR, README mis à jour, fichier de résumé de session.

**Mode de travail par défaut :** pédagogique et guidé. Bascule en mode direct sur demande explicite.

**Attendu vis-à-vis de l'assistant :** soulever les points de vérification bloquants avant de s'engager dans du code, appliquer un regard critique aux propositions plutôt que de valider par défaut, et recadrer directement les approches incorrectes sans les adoucir.

**Ce que « recadrer » veut dire, précisé en 2.1.** Cela vaut dans les deux sens. Une proposition de l'assistant contredite par le code doit être retirée explicitement, avec le motif, et non corrigée en silence dans la réponse suivante. Deux exemples du cadrage du 19/09/2026 : la nécessité d'une dérivation de graine **symétrique** entre les côtés, abandonnée une fois l'attribution canonique des côtés retenue ; et un exemple d'arrondi flottant donné de mémoire (`20 × 1,35 → 28`), **faux**, la vérification donnant 27. La dette d'arrondi existait bien, mais pas là où elle avait été annoncée, et sur un ordre de grandeur différent.

**Une session de cadrage a sa propre clôture.** Elle ne produit ni PR de code ni résumé d'implémentation, mais : les décisions versées dans les documents qui en ont l'autorité, la liste de ce qui reste ouvert, et la liste des affirmations antérieures qu'elle invalide. Cette dernière est la plus importante et la plus facile à omettre.

---

## 8. Règles d'intégrité spécifiques au projet

| Règle | Conséquence si violée |
|---|---|
| **Journaliser une action seulement après validation réussie** | Une action invalide journalisée **corrompt définitivement** l'historique de rejeu |
| **L'issue d'un combat journalisée ne vient jamais du client** *(2.1)* | Le vainqueur enregistré fait foi au rejeu. Une issue acceptée depuis la requête serait **une victoire déclarée par le joueur**. Le chemin « appliquer une issue enregistrée » est réservé au rejeu ; le handler HTTP simule toujours lui-même et ignore tout champ d'issue reçu |
| **Un journal de run ne dépend jamais du moteur** *(2.1)* | Sans l'issue enregistrée, chaque rejeu resimule les combats passés avec le moteur courant : une manche gagnée peut devenir perdue après un correctif, et une action de choix de héros journalisée peut lever au rejeu. `07` E-11 |
| **`engineVersion` dans chaque snapshot, dès le premier commit PvP** | L'ajouter après coup **invalide le corpus déjà produit** |
| **Aucune source d'aléa non seedée dans le Domaine** | Casse simultanément le replay, le PvP asynchrone, le corpus de sunset et le débogage à distance |
| **Le hasard d'un combat ne dépend que de ce combat** *(2.1)* | Une graine héritée du flux de la run rend le contenu d'une boutique dépendant du déroulé du combat précédent, et empêche le moteur embarqué de rejouer un combat isolément. `04` §3.2.1 |
| **Aucun flottant dans une charge utile de `CombatEvent`** *(2.1)* | L'écriture JSON d'un flottant dépend de `serialize_precision`, réglage d'exécution que le serveur et le binaire embarqué peuvent ne pas partager. NF-01 tomberait sans qu'aucun calcul ne soit faux. La sérialisation canonique lève une exception plutôt que d'écrire |
| **Un seul endroit avance le temps** (`TickEngine::tick()`) | Double avance de tick, déjà rencontrée et corrigée |
| **`php://input` se lit une seule fois** | `Request` construit une fois et transmis, jamais reconstruit par handler |
| **Écrire les sauvegardes dans les données applicatives de l'OS** | Écrire dans le dossier d'installation Steam casse les mises à jour |

---

## 9. Gestion de la documentation

- **Hiérarchie d'autorité** : code réel > résumé de session > cahier des charges > GDD > vision produit.
- **Tout écart détecté entre un document et le code est signalé explicitement** avant d'être exploité. Un document obsolète utilisé comme référence est plus dangereux qu'un document absent.
- **Les documents externes ne font jamais autorité** sans vérification contre le code. Plusieurs brouillons externes ont contenu des affirmations factuellement fausses sur l'état implémenté — par exemple en déclarant les compétences de héros hors périmètre alors qu'elles étaient complètes, ou la distribution d'objets par héros non implémentée alors qu'elle était mergée.
- **Un document de conception peut devenir obsolète en quelques chantiers.** C'est arrivé : `game-design-notes.md` est resté figé sur la session 007-008 malgré plusieurs fonctionnalités majeures mergées ensuite.
- **Une même donnée portée par deux documents diverge** *(2.1)*. `SimulationResult::$winner` était typé `?CombatHero` dans `02` **et** dans `04`. `02` l'a corrigé le 8 septembre 2026 ; `04` portait encore l'erreur le 19. C'est la configuration que `00-INDEX` §5 interdit entre documents d'autorité, et elle s'était installée sans que personne l'introduise volontairement. **Quand une donnée technique doit apparaître dans deux documents, l'un cite l'autre au lieu de la recopier.**
- **Le numéro de révision d'un document lui est propre** *(2.1)*, indépendant de la version du corpus. Voir la note de gouvernance en tête.
- **Une décision se reconnaît à ce qu'elle a un format** *(2.1)*. Toute formule dont le résultat sera stocké, haché, comparé ou rejoué est un format, donc une décision à nommer et à consigner. `07` §8 rangeait « randomizer dérivé par combat » parmi les points irréversibles depuis une révision entière, sans voir que la **fonction de dérivation** en était un aussi.

---

## 10. Pièges d'outillage documentés

| Outil | Piège |
|---|---|
| Génération d'image (Gemini) | Joindre une image de référence fait traiter celle-ci comme une image **à éditer**, pas comme une inspiration compositionnelle. Prompts en texte seul pour toute génération originale |
| Génération audio (ElevenLabs) | Inclure `dry mix, clean cutoff, no lingering reverb tail, mono` pour éviter le chevauchement audible sur les ticks de statut rapides et répétés. WAV pour les sons courts, OGG pour les boucles |
| PowerShell | Échappement des messages multilignes — passer par le panneau Source Control |
| PHPUnit | Suffixe `Test`, namespace, espaces parasites : trois causes d'exclusion silencieuse. Et `assertSame` compare l'ordre des clés d'un tableau (§4.4) |
| **PHP — `ceil()` sur un produit flottant** *(2.1)* | `ceil($v * $m)` **ne donne pas toujours** l'arrondi supérieur exact de `v × m`, parce que la plupart des décimaux ne sont pas représentables en binaire et que le produit peut franchir l'entier par le haut. Mesuré sur les entiers de 0 à 1000 : `× 1,10` diverge sur **54 valeurs** (première à 50 : 56 au lieu de 55), `× 1,35` sur **8 valeurs** (première à 180). `× 1,2` et les `floor` de cooldown ne divergent pas sur cet intervalle. **Ce n'est pas un problème de parité** — une multiplication IEEE-754 isolée est correctement arrondie, donc identique sur les cibles 64 bits — **c'est un problème de justesse**. Remède : `intdiv(v * n, d)`. `07` E-12 |
| **PHP — `PcgOneseq128XslRr64` et la forme du seed** *(2.1)* | Le moteur accepte un **entier** ou une **chaîne binaire de 16 octets**, et les deux formes ne produisent pas la même suite. Utile : il lève une `ValueError` sur 15 ou 17 octets, donc une troncature erronée casse à la construction au lieu de produire silencieusement un autre flux. Vérifié le 19/09/2026 |
| **PHP — dépassement d'entier silencieux** *(2.1)* | Au-delà de `PHP_INT_MAX`, un calcul entier bascule **silencieusement** en flottant. L'enrage inflige `5 × 2^stage` : le stage plafonne à 50 avec `maxTicks = 500`, mais rien n'interdira de relever `maxTicks`. C'est l'un des deux motifs du refus des flottants en charge utile (§8) |