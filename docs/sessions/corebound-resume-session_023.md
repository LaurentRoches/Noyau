# Corebound — Résumé de session 023

**Branches** : `docs/embedded-engine-framing` (cadrage, fusionnée avant le code), `feature/engine-cli`, `chore/phpstan-tests`, `chore/static-php-build`, `ci/determinism-parity`, puis `feature/engine-sidecar`
**Chantiers** : 1a — moteur embarqué, **clos sous Windows** ; 1b — point 5, appel du moteur depuis la coquille, **clos**

> **Ce résumé est rédigé a posteriori**, le 09/11/2026. Il couvre plusieurs séances, du 27/09 au 25/10/2026, et s'appuie sur le corpus tel qu'il a été fusionné — `04` révisions 2.10 à 2.15, `06` 2.8 à 2.12, `07` 3.9 à 3.14, README — et non sur un relevé pris en séance. Il ne contient aucun chiffre que ces documents ne portent pas.

---

## Contexte et objectif

Le chantier 1b avait levé le premier des deux risques capables d'invalider la stack : Steam et l'overlay fonctionnent depuis Electron. Restait le second, **le moteur embarqué** : un binaire Windows qui rejoue un combat à partir d'une requête sur stdin et rend, octet pour octet, ce que rend le serveur. C'est la porte EX-J0-01, et sans elle le mode hors ligne, le PvP asynchrone et le corpus de fantômes n'ont pas de fondation.

Le point 5 du chantier 1b — appeler ce binaire depuis le processus principal — attendait ce chantier. Il a été fait juste après.

## Le cadrage, en préalable (27/09/2026)

Sur une branche `docs/` fusionnée avant le premier code. Trois décisions de forme :

- **Le contrat stdin / stdout vit en `04` §4.3 seulement.** `07` le recopiait, et la copie contredisait `04` sur la sortie. Ni `{ combatLog }` ni le journal seul ne suffisaient : le journal ne porte ni le vainqueur, ni la résolution, ni le nombre de ticks.
- **Les clés d'entrée sont renommées** `firstSnapshot` et `secondSnapshot`. `snapshotA` et `snapshotB` laissaient croire que le premier plateau reçu est le côté A, ce que l'attribution canonique a rendu faux le 21/09/2026.
- **Une borne de temps et une échelle de repli.** Deux sessions, sur le seul barreau du binaire natif, et trois barreaux de repli dont le portage du moteur en TypeScript est le dernier.

## Ce que les branches ont produit

**La CLI** (`feature/engine-cli`, 28/09/2026). `bin/corebound-engine` lit `{ combatSeed, firstSnapshot, secondSnapshot }` et écrit le résultat complet : journal, vainqueur, résolution, ticks, et **côté du premier plateau**, que l'attribution canonique ne laisse plus deviner. Quatre codes d'erreur stables, classés **par l'étape où l'échec survient**, pas par la classe de l'exception : `\InvalidArgumentException` peut venir d'un flottant dans la requête — faute de l'appelant — comme d'un défaut du moteur.

**PHPStan sur les tests** (`chore/phpstan-tests`, 28/09/2026). `composer stan` passait `src` en argument, ce qui **remplaçait** les chemins de `phpstan.neon` : `tests/` n'était analysé par aucune porte. Onze erreurs, puis une douzième apparue en typant un utilitaire.

**Le PHAR et le binaire** (`chore/static-php-build`, 16/10/2026). Un PHAR de 299 Ko par Box 4.7.0, puis `corebound-engine.exe` de 6,0 Mo par `static-php-cli` 2.8.5, PHP 8.3.35. **EX-J0-01 vert dans sa forme manuelle** : sources, PHAR et binaire rendent les mêmes octets sur la fixture de référence. **La borne de deux sessions est tenue dans la première**, et l'échelle de repli n'a pas servi.

**La porte de CI** (`ci/determinism-parity`, 19/10/2026). `EmbeddedEngineParityTest` compare la sortie **entière** — code de sortie, stdout, stderr — du PHAR et du binaire à celle du moteur en processus, sur la fixture et 32 graines dans les deux ordres de plateaux : 66 requêtes. Le job `engine-parity-windows` construit les deux à chaque PR vers `dev` et lance le test avec `--fail-on-skipped`. **Vert au troisième essai** : les deux premiers ont montré deux défauts que le poste de développement cachait.

**L'appel depuis la coquille** (`feature/engine-sidecar`, 25/10/2026). `runEngine` lance le binaire sans shell et sans console, écrit la requête sur stdin puis le ferme, et rend stdout en octets — ou l'issue d'un échec, sans jamais rejeter. Un menu **Moteur → Lancer une requête…** l'exerce sur le build packagé : la requête de référence y donne **9 936 octets identiques à la sortie du serveur** (`fc /b`).

## Décisions prises à l'écriture

**L'encodeur de sortie vit dans `App\Presentation\Cli`, pas sous `Domain/Engine/`.** La règle d'`EngineVersion` impose un relèvement pour tout code ajouté sous ce chemin, et un relèvement prive de leur rejeu détaillé les combats archivés sous l'ancienne version.

**Box : `directories: ["src"]`** au lieu de la découverte automatique, qui embarquait les dépendances de développement — 3 060 fichiers, 58 Mo, dont 48,6 Mo pour PHPStan seul. **`check-requirements: false`** : le vérificateur de prérequis lançait `cmd.exe` et `mode CON`, qui héritaient du fichier de requête et vidaient stdin.

**`mbstring` retirée, `zlib` entrée** dans les extensions du micro. Une recherche de `mb_` dans tout le dépôt ne l'a trouvée que dans la documentation ; `zlib` vient avec `phar` dans `spc` 2.8.5.

**Linux et macOS reportés le 20/10/2026, par décision et non par échec.** Steam n'exige pas de version Linux : Proton fait tourner la version Windows, Steam Deck compris, et EX-J0-01 ne nomme que `corebound-engine.exe`. Le moteur serveur tourne déjà sous Linux en CI, où la fixture est rejouée octet pour octet. Le point 6, le cache des catalogues, sort aussi du chantier : le binaire n'en lit aucun.

**Quatre décisions pour le point 5** (`04` §4.5). Le binaire vient du poste et est copié dans `desktop/engine/`, que Git ignore, puis rangé par `electron-builder` dans `resources/engine/`. `spawn` est injecté. Un menu de test sert de déclencheur, le mode hors ligne n'existant pas encore. Et les tests se font sur un faux processus **et** sur un vrai, où Node joue le moteur.

## Mesures

| Mesure | Valeur | Ce qu'elle établit |
|---|---|---|
| PHAR | 116 fichiers, 298,89 Ko, construit en 2 s | Aucune dépendance de développement |
| `micro.sfx` | 5 714 944 octets, compilé en 217,5 s en local, 179,7 s en CI | PHP 8.3.35 NTS, `zlib` et `phar` statiques |
| `corebound-engine.exe` | **6 021 004 octets** | Dans la fourchette de `04` §4.3, 3 à 8,5 Mo |
| Parité manuelle | Trois exécutions, une même sortie de 9 936 octets | Un indice, pas une preuve : une seule requête |
| Parité en CI | **66 requêtes**, sorties de 9 936 ou 10 282 octets selon la graine | Vue rouge sur un faux artefact — `sort.exe` rejeté sur les 66 |
| Job `engine-parity-windows` | 3 min 54 s, dont 180 s de compilation | Une porte bloquante à ce prix-là est soutenable |
| Coquille, build packagé | 9 936 octets, `fc /b` sans différence | Le binaire du paquet est celui qui a tourné, d'après le journal |
| Suite de tests | Backend **492** tests, 1 711 assertions · desktop **36** · frontend **81** | Desktop : de 21 à 36 |

## Erreurs commises et rattrapées

Consignées parce qu'elles sont instructives, pas par scrupule. Elles ont presque toutes la même forme : une affirmation produite sans être vérifiée sur ce qui s'exécute.

- **Un utilitaire de test nommé `run()`.** Il heurtait `TestCase::run()`, qui est `final` : PHP refuse la classe et **toute la suite tombe en erreur fatale avant le premier test**. Deux lancements de `check-all.ps1` n'ont ainsi rien exécuté. Un rouge en erreur fatale n'est pas un rouge (`06` §4.4).
- **Cinq des douze erreurs PHPStan des tests étaient des assertions qui ne pouvaient plus échouer** — par exemple un `assertNotNull` sur un vainqueur devenu non nullable avec D-15. Quatre protégeaient pourtant quelque chose de réel, et ont été réécrites au lieu d'être retirées (`06` §4.4).
- **« L'image `windows-2025` porte Visual Studio 2022 »** : faux. L'étiquette est passée à Visual Studio 2026 sans changer de nom, et `spc` 2.8.5 ne le trouve pas. La fiche lue la première fois décrivait une image qui n'avait plus d'étiquette. Corrigé explicitement, d'où `windows-2022`.
- **« `Git\usr\bin` n'est pas dans le PATH de l'exécuteur »** : faux. L'image installe Git avec l'option `CmdTools`, contrairement au poste de développement.
- **Une vérification menée sur un fichier inventé.** Pour typer `main.ts`, un faux `bundlePath.ts` de même signature a été écrit au lieu de demander le vrai : le typecheck annoncé ne vérifiait que le substitut. Le même jour, deux fichiers TypeScript livrés sans la configuration ESLint du projet ont été arrêtés par le lint avant les tests — **un rouge qui n'en était pas un**. Règle versée en `06` §1.2 : un fichier qui manque se demande, configuration comprise.
- **Un faux positif d'éditeur** (TS7006 dans VS Code alors que `tsc` passait), **cause inconnue**. Contourné en relançant le serveur TypeScript ; non expliqué.

## Corpus

`04` passe de **2.10 à 2.15** : le contrat du moteur (§4.3), l'outillage installé et ses pièges, la porte de CI et ses mesures, le report de Linux et macOS, puis l'appel depuis la coquille (§4.5).

`06` passe de **2.8 à 2.12** : pièges de PHPUnit et de PowerShell, `tests/` dans PHPStan, Box et `spc` en §10, la CI Windows, le processus enfant lancé depuis Electron, et la facette « vérification sur un fichier inventé » de §1.2.

`07` passe de **3.9 à 3.14** : rangs 1 à 6 tous clos, le chantier 3 devient le suivant.

## État final

**Backend : 492 tests PHPUnit, 1 711 assertions**, dont 2 ignorés partout sauf dans le job de parité Windows. **Desktop : 36 tests Vitest. Frontend : 81 tests Vitest.** CI verte, `engine-parity-windows` compris.

## Ce qui reste ouvert

- La porte de CI sur l'empreinte de version de contenu (`04` §10, `06` §4.2), héritée du chantier 2.
- Prettier vérifié par aucun job, et `npm run build` du frontend jamais exécuté en CI (`06` §4.2).
- Le relais de `/runs` vers l'API depuis la coquille, et le retrait du menu par défaut d'Electron, avec les menus de test Steam et Moteur.
- L'audit de licences C-02 du binaire et sa reproductibilité : phpmicro pris sur `master`, zlib à la dernière version.
- Le retrait futur de l'image `windows-2022` ; les actions de CI qui visent Node.js 20, déclaré obsolète ; `ubuntu-latest`, annoncé vers Ubuntu 26.
- Linux et macOS, l'overlay et le packaging sous Linux, non testés.

## Prochain chantier

**Chantier 3 — déclencheurs vivants et garde anti-cascade** (`07` §5.2, rang 7), précédé de son cadrage. Session 024.