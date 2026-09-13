# 06 — Conventions de développement

**Autorité sur :** la méthodologie, la qualité, les commits, la Definition of Done.
**Révision :** 1.0 — 2 septembre 2026.

Ces conventions ne sont pas des préférences : ce sont des règles nées d'erreurs réelles commises sur ce projet. Chacune conserve la trace de son motif.

---

## 1. Principes

### 1.1 L'architecture avant le code

Toute décision d'architecture est validée par questions/réponses explicites **avant le premier test rouge**. Les options rejetées sont documentées avec leur justification, pour ne pas les redécouvrir six mois plus tard.

### 1.2 Ne jamais coder sur hypothèse

**Le contenu réel d'un fichier est demandé avant toute modification.** Un état de fichier supposé a déjà causé de vrais bugs à plusieurs reprises — `heroes.json` et `JsonHeroRepository` ont été désynchronisés plusieurs fois dans une même session, le catalogue passant de 1 à 4 à 10 héros au fil de la conversation.

C'est la règle la plus souvent violée et la plus coûteuse.

### 1.3 TDD strict, ascendant

```
test rouge écrit → rouge CONFIRMÉ par exécution réelle
  → implémentation minimale → vert
  → CS Fixer + PHPStan + PHPUnit → commit
```

**Le rouge doit être constaté, pas supposé.** Livrer du code en bloc sans test rouge préalable est une violation de méthode, déjà survenue et corrigée.

### 1.4 YAGNI

Aucune mécanique de la Vision cible ou de la Roadmap V2+ ne passe en code sans décision explicite qui la fait basculer dans un jalon du cahier des charges.

### 1.5 Ordre par dépendance technique

```
Domain → Application → Infrastructure → Presentation → Http → Frontend
```

Une couche n'est jamais implémentée avant que ses dépendances soient vertes.

### 1.6 Une brique logique par commit

Un commit = une unité de sens qui compile, passe les tests, et peut être relue seule.

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

**À ajouter avant J0 :** build matriciel du binaire `corebound-engine` (Windows / Linux / macOS) et **test de parité de déterminisme** entre le serveur et le binaire embarqué, sur un jeu de seeds fixes.

### 4.3 Périmètre de test

| Couche | Testée |
|---|---|
| Domaine PHP | **Oui**, systématiquement |
| Application PHP | **Oui** |
| HTTP PHP | **Oui** |
| Client API, store Pinia, composables TS | **Oui** |
| Composants Vue | **Non**, par convention explicite |

**Trous de couverture :** documentés en commentaire **dans le fichier de test concerné**, jamais dans un document séparé. Un trou documenté ailleurs que là où il se trouve n'est pas documenté.

### 4.4 Pièges connus de PHPUnit

- Le suffixe `Test` est obligatoire dans le nom de fichier. Son absence **exclut le test silencieusement**.
- Le namespace doit être correct, même symptôme.
- Un espace parasite dans un nom de fichier exclut le test, également en silence.
- Les data providers utilisent les **attributs**, pas les docblocks (PHPUnit 12).

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

**Ajouté en révision 2.0.** Le préfixe `docs/` était déjà en usage dans le dépôt sans figurer dans ce tableau. Ce n'est donc pas une règle nouvelle mais la déclaration d'un usage établi.

### 6.2 Messages

Conventional Commits, sous la forme `type(scope): sujet`. **Les deux sont obligatoires.**

**Types en usage** — ce que fait le commit : `feat`, `fix`, `docs`, `test`, `ci`, `chore`.

**Scopes en usage** — ce que le commit touche :

| Nature du commit | Scope |
|---|---|
| Code | La couche touchée : `domain`, `application`, `infrastructure`, `persistence`, `presentation`, `http`, `frontend`, `config`, `backend` |
| `docs` | Le document touché : `readme`, `conventions`, `corpus`, `lore-bible`, `affinities`, `game-design-notes`… |

```
feat(domain): add engineVersion to combat snapshot
fix(frontend): prevent animation queue from leaking on unmount
test(domain): assert byte-identical replay of a reference combat
docs(conventions): déclarer le préfixe docs/ et séparer types et scopes
ci(backend): add determinism parity check between server and embedded engine
chore(config): bump stash capacity constant
```

**Défaut corrigé en révision 2.0.** La liste « scopes en usage » de la révision 1.0 contenait `ci` et `chore`, qui sont des **types** dans ses propres exemples juste au-dessus : les deux notions n'étaient pas distinguées. Elle omettait par ailleurs `docs` et `test`, tous deux en usage dans le dépôt — `docs` sur treize commits.

**Les commits à message multiligne passent par le panneau Source Control de VSCode**, jamais par le terminal — l'échappement en PowerShell a déjà causé des incidents.

---

## 7. Structure d'une session

1. **Ouverture** — confirmation de l'état Git.
2. **Cadrage** — architecture validée par questions explicites avant tout code.
3. **Travail** — sur une branche nommée, TDD strict, une brique par commit.
4. **Clôture** — texte de PR, README mis à jour, fichier de résumé de session.

**Mode de travail par défaut :** pédagogique et guidé. Bascule en mode direct sur demande explicite.

**Attendu vis-à-vis de l'assistant :** soulever les points de vérification bloquants avant de s'engager dans du code, appliquer un regard critique aux propositions plutôt que de valider par défaut, et recadrer directement les approches incorrectes sans les adoucir.

---

## 8. Règles d'intégrité spécifiques au projet

| Règle | Conséquence si violée |
|---|---|
| **Journaliser une action seulement après validation réussie** | Une action invalide journalisée **corrompt définitivement** l'historique de rejeu |
| **`engineVersion` dans chaque snapshot, dès le premier commit PvP** | L'ajouter après coup **invalide le corpus déjà produit**. Seul point irrattrapable de l'architecture |
| **Aucune source d'aléa non seedée dans le Domaine** | Casse simultanément le replay, le PvP asynchrone, le corpus de sunset et le débogage à distance |
| **Un seul endroit avance le temps** (`TickEngine::tick()`) | Double avance de tick, déjà rencontrée et corrigée |
| **`php://input` se lit une seule fois** | `Request` construit une fois et transmis, jamais reconstruit par handler |
| **Écrire les sauvegardes dans les données applicatives de l'OS** | Écrire dans le dossier d'installation Steam casse les mises à jour |

---

## 9. Gestion de la documentation

- **Hiérarchie d'autorité** : code réel > résumé de session > cahier des charges > GDD > vision produit.
- **Tout écart détecté entre un document et le code est signalé explicitement** avant d'être exploité. Un document obsolète utilisé comme référence est plus dangereux qu'un document absent.
- **Les documents externes ne font jamais autorité** sans vérification contre le code. Plusieurs brouillons externes ont contenu des affirmations factuellement fausses sur l'état implémenté — par exemple en déclarant les compétences de héros hors périmètre alors qu'elles étaient complètes, ou la distribution d'objets par héros non implémentée alors qu'elle était mergée.
- **Un document de conception peut devenir obsolète en quelques chantiers.** C'est arrivé : `game-design-notes.md` est resté figé sur la session 007-008 malgré plusieurs fonctionnalités majeures mergées ensuite.

---

## 10. Pièges d'outillage documentés

| Outil | Piège |
|---|---|
| Génération d'image (Gemini) | Joindre une image de référence fait traiter celle-ci comme une image **à éditer**, pas comme une inspiration compositionnelle. Prompts en texte seul pour toute génération originale |
| Génération audio (ElevenLabs) | Inclure `dry mix, clean cutoff, no lingering reverb tail, mono` pour éviter le chevauchement audible sur les ticks de statut rapides et répétés. WAV pour les sons courts, OGG pour les boucles |
| PowerShell | Échappement des messages multilignes — passer par le panneau Source Control |
| PHPUnit | Suffixe `Test`, namespace, espaces parasites : trois causes d'exclusion silencieuse |
