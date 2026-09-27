# Corebound — Résumé de session 022

**Branche** : `feature/electron-shell`
**Chantier** : 1b — coquille Electron et Steam, **points 1 à 4 terminés**, le point 5 attendant le chantier 1a

---

## Contexte et objectif

Le chantier 2 clos, `07` §5.2 plaçait au rang 5 le test le moins cher d'une question de niveau stack : prouver que `steamworks.js` fonctionne, qu'un succès Steam se déverrouille et que l'overlay s'accroche à une fenêtre Electron, **depuis un build packagé**. Un échec aurait rouvert la décision de packaging du 2 septembre 2026. Coût : 0 €, sur Spacewar, l'application de test de Valve (AppID 480).

**Le critère de sortie est atteint le 27/09/2026.** EX-J0-02 est vert dans sa forme testable. Des deux risques capables d'invalider la stack entière (`07` §1.3), il n'en reste qu'un : le moteur embarqué.

**Compte de la branche : 8 commits, dont 2 documentaires.** Six commits de code pour quatre briques annoncées.

## Le cadrage, et ce qu'il a défait

`07` demandait de lever deux réserves avant de planifier. **La première était mal posée** : le téléchargement du SDK Steamworks exige un compte Steam et l'acceptation d'un accord, pas une adhésion au programme partenaire — et surtout, les points 1 à 4 n'en ont pas besoin, le paquet npm `steamworks.js` publiant lui-même `steam_api64.dll`. La question se déplace vers le chantier 1c et vers EX-J0-05 : la DLL livrée viendra d'un paquet npm tiers. **La seconde s'est élargie** : `steam_appid.txt` avait une alternative, l'AppID passé à l'initialisation, à trancher par l'expérience.

Un risque que la décision du 2 septembre n'avait pas nommé a été consigné en `04` §4.1 : **`steamworks.js` est en sommeil**, dernière publication en août 2024, 52 issues ouvertes, dont plusieurs sur l'overlay.

Décisions de forme, versées en `04` §4.5 : dossier `desktop/` à la racine, fondation réutilisable et non prototype jetable, TypeScript compilé en CommonJS par `tsc` sans bundler, isolation stricte du renderer écrite explicitement, electron-builder en sortie dossier, protocole `app://` pour servir le frontend, Windows seul. **Pas de branche `docs/` de cadrage** : un écart assumé à `06` §6.1, 1b ne portant aucun point irréversible.

## Ce que les six commits ont produit

**Le squelette** (`desktop/`). Fenêtre vide, outillage aligné sur le frontend, bloc dans `check-all.ps1`, job de CI qui ne lance jamais Electron. Vérifié en développement puis sur `Corebound.exe`.

**Le protocole `app://`**. Le build Vite référence ses assets en absolu (`/assets/...`) et `runApi.ts` appelle `/runs` en relatif : chargés en `file://`, les deux se perdent. Le protocole garde ces chemins valides **sans modifier le frontend**. Sa seule logique, `resolveBundlePath`, a été écrite en TDD en commençant par le refus des traversées de répertoire ; chacune de ses quatre gardes a été retirée tour à tour pour vérifier qu'un test rougit. Le privilège `stream` suffit à la vidéo des Vestiges, lecture et saut compris.

**Le titre de la page**. `frontend/index.html` portait encore « frontend », du gabarit Vite, et Electron en fait le titre de la fenêtre.

**Steam sans jamais bloquer la fenêtre**. `initSteam` rend un statut au lieu de lever, que Steam soit fermé ou que le module natif soit introuvable. Le module est chargé à la demande, pas en tête de fichier : une DLL absente ferait sinon planter la coquille avant la première fenêtre. **L'expérience de l'AppID est tranchée : `init(480)` suffit, sans `steam_appid.txt`**, en développement comme sur le build packagé.

**Le succès**. `ACH_WIN_ONE_GAME` (« Winner ») est déverrouillé depuis un menu **Steam** ajouté au menu par défaut d'Electron, puis relu par `isActivated()`. Spacewar est passé à 1/5 dans la bibliothèque. Ce commit est celui de l'erreur de la session, plus bas.

**L'overlay**. Sans `electronEnableSteamOverlay()`, la notification de succès s'affichait — l'overlay s'était donc accroché — mais Shift+Tab n'ouvrait rien. Avec lui, l'overlay complet s'ouvre et répond. Ses options Chromium ne valent qu'avant l'événement « ready » : Steam est désormais initialisé avant, et l'overlay n'est préparé que si Steam répond.

## L'erreur de la session — des tests verts sur une fonction qui n'existe pas

La première livraison du succès appelait `achievement.names()` pour lire les succès de Spacewar au lieu de les deviner. **Les 19 tests étaient verts ; la brique a échoué à l'exécution** : « client.achievement.names is not a function ».

**La cause.** Le code de `steamworks.js` avait été lu sur la **branche principale** de son dépôt GitHub, où `names()` a été ajouté après la dernière publication. La 0.4.0 installée ne l'a pas : son `client.d.ts` ne déclare que `activate`, `isActivated` et `clear`. C'est le niveau 1 de `06` §1.2 — lire autre chose que ce qui s'exécute —, sur une dépendance plutôt que sur un fichier du dépôt.

**Pourquoi les tests ne l'ont pas vu.** Ils vérifiaient `SteamClient`, une description du module écrite à la main pour permettre l'injection. Rien ne comparait cette description au vrai module, et un `as SteamworksModule` dans `main.ts` faisait taire le compilateur.

**Ce qui l'empêche désormais.** `main.ts` importe le vrai type de `steamworks.js` (`import type`, effacé à la compilation) et le lui assigne **sans `as`**. Vérifié : remettre `names()` dans la description fait échouer le typecheck sur « Property 'names' is missing in type 'typeof achievement' ». Deux règles en sortent, en `06` §1.2 et §4.3.

## Décisions prises à l'écriture

**Un menu plutôt qu'un bouton.** Trois déclencheurs possibles pour le succès : un menu du processus principal, un déverrouillage automatique au démarrage, un bouton du frontend passant par une IPC. Le menu l'emporte : aucun frontend touché, aucune IPC ouverte pour une preuve de câblage, et un test rejouable à volonté grâce à l'entrée de réinitialisation.

**Le nom du succès écrit, pas lu.** Faute de `names()`, `ACH_WIN_ONE_GAME` est nommé dans le code, sur deux sources concordantes : le guide pas à pas des succès de la documentation Steamworks et l'exemple officiel de Steamworks.NET.

**L'overlay conditionné à Steam.** `in-process-gpu` déplace le GPU dans le processus principal : sans Steam, rien ne le justifie. Effet de bord utile : lancer le build Steam ouvert puis Steam fermé compare le coût du redessin forcé sans une ligne de code en plus.

**Les tests passent au typecheck.** Le `tsconfig.json` du squelette excluait les fichiers de test, que `tsc --noEmit` ne vérifiait donc pas. Corrigé à la brique suivante par un `tsconfig.build.json` qui les exclut de la seule compilation livrée — défaut de la brique 2, relevé avant qu'il ne coûte.

**Le test de l'AppID rendu discriminant.** Écrit avec 480, il passait même si 480 était écrit en dur dans le module. Un AppID de 4000 le fait échouer dans ce cas ; vérifié avant livraison.

## Mesures et constats

| Mesure | Valeur | Ce qu'elle établit |
|---|---|---|
| Steam fermé, build packagé | Fenêtre ouverte, « Cannot create IPC pipe to Steam client process » au journal | L'échec contrôlé tient |
| `init(480)` sans `steam_appid.txt` | « Steam disponible, AppID 480 », en développement et packagé | L'expérience du cadrage est tranchée |
| Module natif hors de l'asar | Chargé depuis `app.asar.unpacked`, **sans copie de la DLL à la racine** | Le README de `steamworks.js` recommande une copie inutile ici |
| Overlay sans `electronEnableSteamOverlay()` | Notification visible, Shift+Tab sans effet | La fonction est nécessaire, laquelle de ses trois mesures ne l'est pas isolé |
| Overlay avec | Overlay complet, cliquable, succès « Winner » affiché | Motif n° 2 de `04` §4.1 confirmé sous Windows |
| Coût du redessin forcé | 0,3 % d'écart, Steam ouvert contre fermé | Négligeable sur un écran fixe ; à reprendre sur un combat animé |
| Suite de tests desktop | 21 tests Vitest | Backend 472 et frontend 81, inchangés |

## Erreurs commises et rattrapées

Consignées parce qu'elles sont instructives. Elles ont la même forme : une affirmation produite sans être vérifiée sur ce qui s'exécute.

- **`achievement.names()` lu sur la mauvaise version** — voir plus haut. La seule des erreurs de la session qui ait atteint l'exécution.
- **Une précaution sur une fonction absente.** La même livraison expliquait longuement que `names()` faisait paniquer le code Rust et devait n'être appelé qu'au clic. La précaution portait sur une fonction que la version installée n'avait pas.
- **« Le 404 de `/runs` lèvera `RunNotFoundError` »**, écrit en `04` §4.5 au cadrage. Faux : un chemin sans fichier fait échouer la requête elle-même, seules les URL refusées par la garde reçoivent un 404. Corrigé en `04` révision 2.9.
- **Une brique « outillage seul » qui n'aurait pas passé la porte.** Le découpage initial du squelette ne tenait pas : `tsc` refuse un projet sans fichier source et Vitest échoue sans test. Relevé avant d'écrire, pas après.
- **« Spacewar » dans le magasin.** Le magasin Steam propose un autre Spacewar, payant, d'un éditeur tiers (AppID 4989790). Repéré à temps sur la capture ; l'application de test de Valve n'a pas de page de magasin et s'installe par `steam://install/480`.
- **Le transport des fichiers.** Des fichiers livrés en LF ont été reformatés par Prettier dès leur pose, l'éditeur affichant CRLF sur l'un d'eux. Cause probable, pas vérifiée ; consigné en `06` §4.4.

## Corpus

`04` passe en **2.9** : §4.5 gagne le tableau de ce que les briques ont établi — AppID, module natif, journal, succès, overlay et son coût — et corrige la phrase fausse sur `/runs` ; §4.1 dit ce qui est levé du risque `steamworks.js`, sous Windows seulement.

`06` passe en **2.7** : §1.2 gagne une cinquième facette, lire la version installée d'une dépendance et non son dépôt ; §4.3 le corollaire qui aurait arrêté l'erreur au typecheck ; §10 les pièges de `steamworks.js` 0.4.0 ; §4.4 une variante du piège de transport.

`07` passe en **3.8** : rang 5 rayé pour ses points 1 à 4, §1.3 mise à jour — un risque sur deux levé —, réserves du chantier soldées, ambiguïté du critère sans objet à ce jour, et le passage de quatre briques à six commits consigné.

`README` : Tauri retiré des deux endroits où il survivait, `desktop/` ajouté à la stack et à la structure du projet, chantier 1b à l'avancement, compteurs de tests à jour.

## État final

**Desktop : 21 tests Vitest. Backend : 472 tests PHPUnit, 1 543 assertions. Frontend : 81 tests Vitest.** `check-all.ps1` vert de bout en bout.

**La branche est prête à fusionner sur `dev` par PR.**

## Ce qui reste ouvert

- **Le relais de `/runs` vers l'API** depuis la coquille : le frontend s'y affiche, une run n'y démarre pas. C'est le « mode en ligne » de `04` §4.2, qu'aucun des quatre points du chantier ne demandait.
- **Le menu par défaut d'Electron**, utile en développement, probablement indésirable dans un jeu. Son retrait n'est pas décidé.
- **Linux** : ni l'overlay ni le packaging n'y ont été testés, et c'est là que portent les rapports d'échec d'overlay les plus récents.
- **L'AppID 480 écrit en dur** dans `main.ts`, à remplacer par l'AppID réel au chantier 1c.
- **Prettier vérifié par aucun job de CI**, et le build du frontend jamais exécuté en CI (`06` §4.2) — `chore` à part.
- **La porte de CI sur l'empreinte de contenu**, héritée du chantier 2.

## Prochain chantier

**1a — moteur embarqué** (`07` §5.2, rang 6), porte EX-J0-01. C'est lui qui exercera la parité octet pour octet entre le serveur et le binaire, contre `tests/Determinism/fixtures/reference-combat/`. Le point 5 du chantier 1b — appeler ce binaire depuis le processus principal, stdin / stdout — vient après lui, dans la coquille posée ici.
