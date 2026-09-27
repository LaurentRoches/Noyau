# Corebound — Résumé de session 019

**Branches** : `fix/engine-death-guards`, `fix/application-play-round-guard`, `fix/application-swap-with-stash-rollback`, `feature/status-instances`
**Chantiers** : 0 (gardes de mort et caractérisation) et 3b (modèle de statut, décorateur, code mort)

---

## Contexte et objectif

Première session entièrement pilotée par `07-roadmap-des-chantiers.md`. Aucune mécanique de jeu nouvelle : il s'agit de résorber des anomalies identifiées par l'audit du corpus, dans l'ordre que la roadmap impose. Le chantier 3b devait précéder le chantier 2 parce que D-20 fait de l'état de statut une **liste** et non un entier : geler le format de snapshot avant ce changement aurait imposé une migration dès sa première version, ce que le chantier 2 existe précisément pour éviter.

Méthode tenue de bout en bout : rouge constaté par exécution réelle avant toute implémentation, valeurs prédites à la main avant de lancer les tests, et chaque affirmation sur le code vérifiée dans le fichier concerné plutôt que citée de mémoire.

## Chantier 0 — gardes de mort

**E-03, garde entre statuts et enrage.** Un poison ou une brûlure pouvait achever un Vestige sans que l'enrage s'en aperçoive. Garde ajoutée dans `Simulator::run()` entre les deux phases, précédée de sept tests de caractérisation du comportement antérieur.

**E-06, garde dans `playRound()`.** `purchaseItem()` et `openShop()` refusaient déjà quand une offre de héros est en attente ; `playRound()` ne testait que `isOver()`. Garde ajoutée, symétrique.

**Décision : pas de garde dans `swapWithStash()`.** E-06 nuit parce que `playRound()` fait avancer l'état de la run. Un échange d'objets n'avance aucun compteur et n'offre aucun avantage de timing, le héros proposé n'étant pas encore dans le roster et donc invisible pour `HeroItemAllocator`. Raison consignée dans `07` §6.

**E-10, découverte en vérifiant la précédente.** `HeroItemAllocator::canAssign()` **lève** au lieu de retourner `false` sur un héros hors roster. Le rollback de `swapWithStash()` n'étant écrit que sur le chemin `false`, il était court-circuité et l'objet retiré de l'inventaire disparaissait. Corrigé par `try`/`finally` autour de la seule fenêtre d'amputation, le `try` ne couvrant volontairement pas les trois mutations finales : une restauration après l'insertion du nouvel objet dupliquerait au lieu de réparer.

**Portée réelle de E-10 : nulle.** `RunController` respecte l'ordre `replay()` → `apply()` → `append()`, donc rien n'est journalisé quand l'exception remonte, et chaque requête reconstruit le run par rejeu. La perte était confinée à un objet en mémoire jeté avec l'exception. Défaut de correction, pas d'exploit — requalification faite après coup, la première rédaction de l'anomalie surestimait l'impact.

**`openShop()` classé sain.** Aucun endpoint ne l'expose, et ses deux appelants internes testent `isOver()` avant de l'atteindre.

## Chantier 3b — modèle de statut

### Point 1, instances indépendantes (E-02, D-20)

`ActiveStatus::mergeWith()` disparaît. `CombatVestige` porte une liste d'instances par type, chacune avec ses stacks, sa durée et son objet source. `AggregatedStatus` est la seule projection exposée aux `CombatEvent` : somme des stacks vivants, maximum des durées restantes, **calculées en un seul appel sur le même état de la liste** pour interdire l'écart entre deux lectures au moment où la liste bouge dans le tick.

Quatre décisions prises avant le premier test :

1. `sourceId` conservé bien qu'en écriture seule en V1, le chantier 2 figeant le format de snapshot de l'état de statut.
2. `remainingStacks` = somme, `remainingTicks` = maximum.
3. Un seul `STATUS_EXPIRED`, quand le type n'a plus aucune instance vivante.
4. `getStatus()` remplacé par `getStatusInstances()` et `getAggregatedStatus()`, ce qui fait disparaître le `assert()` de `ActionProcessor` au lieu de le déplacer.

Découpage imposé par la porte qualité : `ActiveStatus` et `CombatVestige` ne peuvent pas être verts séparément, supprimer `mergeWith()` cassant `applyStatus()`. Trois commits au lieu de cinq.

**Valeurs de référence, prédites à la main puis vérifiées.** `shadow_armor` produit **1 253** de bouclier sur 500 ticks sous le modèle par instances, contre **7 155** sous le modèle à fusion. Les 1 415 et 1 405 annoncés par le corpus décrivaient le **plafond à 2 stacks de D-13, périmée** : sous un plafond les stacks atteints ne redescendent jamais, alors que les instances expirent et que la moyenne vaut `durée / cooldown` = 1,67 stack par tick. D-20 est donc plus conservateur de 11 % que la solution qu'il remplace.

**Le critère de sortie était mal formulé.** `ceil(durée / cooldown)` est le **pic** atteint juste après une application, pas le régime permanent. Le compte n'est constant que si le cooldown divise la durée : trois objets sur huit seulement. `silent_death` et `panacee` ont une durée plus courte que leur cooldown et laissent leur statut s'éteindre entre deux activations, plancher à zéro. Critère reformulé en encadrement `floor`/`ceil`.

### Point 2, répartition de la brûlure

`CombatVestige::takeBurnDamage()` : 150 % de la valeur sur le bouclier, 70 % du surplus sur les PV, arithmétique entière exclusivement, les deux divisions arrondissant en faveur du défenseur.

**La justification écrite dans le GDD était fausse.** « 1 point de bouclier annule intégralement la brûlure du tick » est contredit par `takeDamage()`, qui fait `min(bouclier, dégâts)`. La règle ne débloque pas une brûlure annulée : elle la **redistribue**, et à 8 de bouclier elle inflige un PV de **plus** qu'avant. Décision inchangée, justification corrigée.

Deux conséquences du plancher à noter pour le chantier 10 : un stack isolé sur une cible nue n'inflige plus rien, et `molotov_cocktail` à 3 stacks ne fait pas mieux que `firesteel` à 2 sur une cible sans bouclier.

### Point 3, nettoyage par le soin (D-21)

`StatusType::isHostile()` porte seul la distinction, par un `match` **sans branche par défaut** : ajouter un statut ne compilera pas tant que son camp n'aura pas été tranché là.

Les quatre règles de détail sont réparties délibérément. `CombatVestige::cleanseHostileStatuses()` porte les trois qui relèvent de la liste d'instances ; la quatrième, le calcul sur le soin **tenté**, appartient à `ActionProcessor`, la méthode du Vestige ne sachant rien du soin.

`HEAL_RECEIVED` gagne `poisonCleansed` et `burnCleansed`. Sans eux la mécanique serait invisible : un Vestige à pleine vie voit `hpHealed` à 0 et n'aurait aucun moyen de savoir que son poison a reculé.

`STATUS_EXPIRED` n'est pas émis quand le nettoyage efface le dernier stack. Sa sémantique est restreinte et documentée : « ce statut a cessé **par épuisement de sa durée** ». Émettre ici aurait imposé de passer `ActionProcessor::process()` à une liste d'événements et de reprendre les gardes de mort de `Simulator::run()`.

### Point 4, pénalité sèche de `SUNDERING` (E-04)

La compétence ne s'applique plus du tout à un deux mains sans `DEAL_DAMAGE`. `scutum` et `shadow_scutum` sont à `cooldownTicks: 50`, et `floor(50 × 1,10) = 55` : le `floor` n'absorbait pas la pénalité, c'était bien une activation perdue sur 500 ticks.

Deux options écartées, avec leur motif. Majorer aussi les effets sans dégâts réaliserait la collision que `02` signale entre `SUNDERING` et `HULKING`. Conserver le malus comme contrainte de construction supposerait que le joueur le voie — la décoration a lieu au combat, l'inventaire affiché porte l'objet brut — et puisse l'éviter, l'échange libre héros ↔ héros étant au périmètre J2.

Un test sur `longsword` verrouille le fait que la pénalité doit continuer de mordre quand le bonus est accordé, ce qui interdit de « corriger » E-04 en supprimant la règle.

### Point 5, code mort (E-07)

`EventDispatcher::dispatch()` et `getListenersFor()` retirés, `register()` passé en privé. La classe tombe à deux méthodes publiques, `registerBoard()` et `dispatchForItem()`, qui sont exactement les deux appelées en production.

`CombatVestige::getStatuses()` retiré, sans appelant en production depuis que `StatusProcessor` parcourt `getStatusTypes()`.

`Effect::intervalTicks` retiré de bout en bout : cinq écritures, zéro lecture, `EffectDTO` compris. Ce retrait ferme une intention de conception — un effet `EVERY_N_TICKS` aurait pu battre à son propre rythme — qui n'a jamais existé dans le moteur.

Garde dupliquée supprimée dans `Simulator::run()` : deux blocs identiques et consécutifs après l'enrage, le second inatteignable, avec un commentaire inexact aux deux endroits.

**`Trigger` reste ouvert.** `TickEngine::tick()` active par `dispatchForItem()`, qui ignore les clés de trigger : `ON_ATTACK` et `EVERY_N_TICKS` sont fonctionnellement identiques. Retirer l'enum amputerait une intention de design, c'est une décision et non un nettoyage. Renvoyé au chantier 3.

## Corpus

Quatre affirmations fausses corrigées dans `02`, `04` et `07`, dont la valeur de référence attribuée au mauvais modèle et le point de bouclier censé annuler la brûlure. Un premier commit documentaire avait laissé trois occurrences non corrigées : l'unicité de chaque remplacement était garantie, pas l'absence d'autres formulations ailleurs dans le document.

## Erreurs commises et rattrapées

Consignées parce qu'elles sont instructives, pas par scrupule.

- **`RELENTLESS` déclaré défectueux à tort**, à partir de `applyRelentless()` sans lire `CombatBoardFactory::hasFullOneHandLoadout()`, qui conditionne son application. Violation de la règle « lire le consommateur avant de conclure ».
- **Trois diagnostics successifs sur les retouches de CS Fixer**, tous appuyés sur des mesures fausses de fins de ligne dues au quoting du shell.
- **`ItemPresenterTest` oublié** au retrait de `intervalTicks` : la recherche des lecteurs d'un champ ne voit pas les tests qui comparent un tableau le contenant trois niveaux plus bas.

## État final

**294 tests PHPUnit, 1 122 assertions. 72 tests Vitest.** `check-all.ps1` vert de bout en bout.

## Prochain chantier

**Chantier 2 — snapshot versionné et déterminisme**, précédé de son cadrage : six décisions sans code, D-14 à D-19. Le chantier 3b était son préalable.
