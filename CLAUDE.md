# Corebound — consignes pour Claude Code

Monorepo du jeu Corebound (Projet Noyau) : `backend/` (PHP 8.3, moteur de combat et API), `frontend/` (Vue 3, TypeScript), `desktop/` (coquille Electron), `docs/` (corpus de conception).

Réponds en **français**.

## Où en est le projet

Lis d'abord **le dernier résumé de session** dans `docs/sessions/` (`corebound-resume-session_NNN.md`, le numéro le plus haut). Il dit la branche ouverte, la brique en cours et les fichiers attendus.

Ne charge pas le corpus en entier : `docs/07-roadmap-des-chantiers.md` et `docs/04-architecture-technique.md` font plus de 150 Ko chacun. Ouvre la section qu'un résumé ou une tâche cite (par exemple `07` §6, `04` §3.8).

## Hiérarchie d'autorité (`docs/00-index.md` §2)

1. Le code réel — toujours vainqueur.
2. Le dernier résumé de session.
3. `03` cahier des charges · 4. `02` GDD · 5. `01` vision.

- **Signaler avant d'exploiter** : un écart entre un document et le code se signale avant d'être utilisé.
- **Rouvrir avant d'affirmer** : citer un document de mémoire n'est pas le lire. Lire la donnée, le code qui la consomme, **et le code qui la produit**.
- Une formule dont le résultat est stocké, haché, comparé ou rejoué est **un format**, donc une décision (`00` §2.3).

## Règles de travail (`docs/06-conventions-de-developpement.md`)

- **TDD strict.** Le test rouge est **vu échouer à l'exécution**, pour la bonne raison. Une erreur fatale ou un lint qui arrête la porte n'est pas un rouge (`06` §4.4).
- **Une brique logique par commit.**
- **`check-all.ps1` vert avant chaque commit**, depuis la racine : `powershell -ExecutionPolicy Bypass -File .\check-all.ps1`. Ordre fail-fast : backend CS Fixer → PHPStan 6 → PHPUnit, puis frontend et desktop Prettier → ESLint → typecheck → Vitest.
- **Conventional Commits** `type(scope): …`, en français.
- **Aucune ligne d'attribution** dans les commits ni les PR : ni `Co-Authored-By`, ni `Claude-Session`, ni « Generated with ».
- Un point irréversible (`07` §8) se tranche sur une branche `docs/` **avant** le code.
- Une branche se clôt par un commit `docs` et un résumé de PR.
- **Ne jamais pousser, fusionner ni commiter sans demande explicite.** Laurent relit et commite lui-même.
- **Branche de base : `dev`**, pas `main`. Les branches partent de `dev` et y reviennent par PR ; `main` ne reçoit que les versions taguées. Les commits de la branche : `git log --oneline dev..HEAD`.
- Le résumé de session et le README voyagent avec la branche de la session (`06` §6.1).
- Scopes autorisés : la liste de `06` §6.2. Un scope nouveau se déclare dans `06`.

## Ne jamais supposer un fichier

Un fichier qui manque se demande ou se lit, **configuration comprise** (`eslint.config`, `tsconfig`, `.prettierrc`, `phpstan.neon`). Ne jamais écrire un substitut « de même signature » pour faire passer une vérification : il ne vérifie que lui-même (`06` §1.2). Si une information est inconnue, dire « inconnu ».

## Invariants du moteur

- **`EngineVersion::CURRENT`** se relève pour tout changement sous `backend/src/Domain/Engine/`, **une seule fois par branche**. Code de présentation ou de CLI hors de ce chemin.
- **Journal canonique (D-19)** : clés triées, types `int`/`string`/`bool`, **charge utile plate**. Aucun flottant, aucun tableau imbriqué.
- **Fixture de référence** `backend/tests/Determinism/fixtures/reference-combat/` : régénérée **par son outil** (`capture-reference-combat.php`), jamais à la main. Vérifier ce qui change dans `combat-log.json`.
- Les valeurs de `Trigger` sont un format (`07` §8, point 11) : en retirer une rend les archives illisibles.

## Pièges connus (`06` §10)

- PHPUnit sous PowerShell : `php vendor/bin/phpunit tests --filter "A|B"`. Avec `vendor\bin\phpunit`, le `.bat` fait lire `|` comme un tube, et sans `tests` seule l'aide s'affiche.
- `git grep` ne voit que les fichiers suivis.
- Une méthode utilitaire nommée `run()` dans un `TestCase` fait tomber toute la suite (`TestCase::run()` est `final`).