# Corebound / Projet Noyau — Résumé de session 006

## Contexte du projet

Auto-battler asynchrone dark fantasy (univers "Les Héritiers du Vide"), inspiré de *The Bazaar* (mécanique) et *La Voie des Ombres* (thème, sans reprendre noms/pouvoirs exacts). Voir `game-design-notes.md` dans les fichiers projet pour le cahier des charges V1 (toujours partiellement dépassé sur "1 seul héros" — voir session 005 pour le modèle Vestige + multi-héros actuel).

**Stack** : PHP 8.3, Vue.js 3 en frontend. PHPUnit 12, PHPStan niveau 6, PHP CS Fixer, CI GitHub Actions (JSON validation, PHPUnit, PHPStan, CS Fixer — tous bloquants, déclenchés sur Pull Request vers `dev`).

**Méthodologie à respecter impérativement** (rappel, cf. `SKILL.md` projet et sessions précédentes) :
- Mode pédagogique actif : questions guidées plutôt que réponses directes, sauf demande explicite de réponse directe.
- TDD strict, bottom-up : test rouge → implémentation minimale → vert → refactor.
- Regard critique systématique sur toute nouvelle idée (complexité moteur, jouabilité réelle, cohérence V1, YAGNI) plutôt que validation par défaut.
- Vérifier CS Fixer + PHPStan + PHPUnit avant chaque commit.
- Célébrer les initiatives de l'utilisateur quand il dérive un concept/solution seul.

## État Git

- **`feature/vestige-board-refactor`** : mergée vers `dev` (session 005).
- **`feature/status-effects-engine`** : chantier de cette session, **terminé, tous checks verts**. Pull Request ouverte vers `dev` ("Able to merge", pas de conflit), titre et description rédigés en fin de session, prête à être mergée.
- **Prochaine branche à créer** : `feature/shop-economy` (à créer depuis `dev` une fois la PR ci-dessus mergée).

## Point tranché cette session

**Répartition finale des 30 objets V1 (14 Common / 11 Rare / 5 Legendary)** : considérée comme close par l'utilisateur. Ne plus rouvrir cette question — la répartition sera affinée dans les futures versions plutôt que reconsidérée maintenant. La question de la présence/absence d'`elfic_bow` et du ratio 14/11/5 vs 14/10/6, ouverte depuis la session 004, est donc **résolue**.

## Chantier terminé cette session — Status Effects Engine

Objectif : rendre fonctionnels les objets du contenu V1 (session 004) qui utilisent `APPLY_STATUS` (`venomous_vial`, `firesteel`, `nightfang`, `silent_death`, `panacee`, `shadow_armor`, etc.), inertes/crashants jusqu'ici (`LogicException` sur `ActionType::APPLY_STATUS` non traité).

### Nouvelles classes

**`StatusType`** (`Domain/Enum/`) : enum simple, 4 cas — `POISON`, `BURN`, `REGEN`, `WARD`.

**`ActiveStatus`** (`Domain/Runtime/`) : classe **mutable** (cohérent avec `CombatItem`/`CombatVestige`, pas `readonly`). `type` (readonly), `stacks`, `remainingTicks` (stocke en interne la valeur passée au constructeur comme `durationTicks`, même patron que `CombatItem::$currentCooldown`).
- `decrementDuration(int $ticks = 1): void` — plafonné à 0 via `max()`, même style que `CombatItem::decrementCooldown()`.
- `isExpired(): bool` — `$this->remainingTicks === 0`, miroir de `CombatItem::isReady()`.
- `mergeWith(ActiveStatus $other): void` — stacks additionnés, `max()` des deux `remainingTicks` (testé dans les deux sens : `$other` plus long, et `$status` plus long — angle mort identifié et comblé en cours de session). Fail-fast `\InvalidArgumentException` si les `StatusType` diffèrent, cohérent avec `SimulationContext::getOppositeBoard()`/garde de `CombatBoard`.
- 5 tests, tous verts (construction, décrément, expiration, fusion succès, fusion garde d'invariant).

### Classes modifiées

**`Action`** (`Domain/Model/`) : 3 nouveaux champs nullables — `?StatusType $status`, `?int $stacks`, `?int $durationTicks` — pour supporter `APPLY_STATUS` sans casser `DEAL_DAMAGE`/`GAIN_SHIELD`/`HEAL`.

**`CombatVestige`** (`Domain/Runtime/`) :
- Stockage indexé `array<string, ActiveStatus>` (clé = `StatusType::value`), même pattern que `EventDispatcher` pour les listeners.
- `applyStatus(ActiveStatus $status): void` — fusionne via `mergeWith()` si le type existe déjà, sinon stocke.
- `getStatus(StatusType $type): ?ActiveStatus` — lookup O(1), ajouté a posteriori pour permettre à `ActionProcessor` de relire l'état résultant après application (pattern before/after déjà utilisé par `processHeal()`/`processGainShield()`).
- `getStatuses(): list<ActiveStatus>` — `array_values()` de la structure interne.
- `removeExpiredStatuses(): void` — `array_filter()` sur `isExpired()`.
- **`takeRawDamage(int $damage): void`** — nouvelle méthode, dégâts qui ignorent totalement le bouclier (nécessaire pour Poison). Décision actée : méthode dédiée plutôt qu'un flag `bool $bypassShield` sur `takeDamage()` existant (cohérent avec `dispatchForItem()` vs flag sur `dispatch()`, session 002).
- Point de refactor identifié mais non traité : `takeDamage()` et `takeRawDamage()` partagent la même logique de plafonnement HP (`min($this->currentHp, ...)`) — candidat à une méthode privée commune (`applyHpDamage()`), pas fait faute de 3e appelant (règle des 3 respectée).

**`EventType`** (`Domain/Enum/`) : 5 nouveaux cas — `STATUS_APPLIED`, `STATUS_DAMAGE_DEALT`, `STATUS_HEAL_RECEIVED`, `STATUS_SHIELD_GAINED`, `STATUS_EXPIRED`.

**`ActionProcessor`** (`Domain/Engine/`) : nouvelle branche `ActionType::APPLY_STATUS => processApplyStatus()`. Fail-fast (`\LogicException`) si `status`/`stacks`/`durationTicks` sont `null` sur l'`Action` (invariant : ces champs sont obligatoires pour ce type d'action). Construit un `ActiveStatus`, l'applique au Vestige cible, relit l'état résultant via `getStatus()`. Payload `STATUS_APPLIED` distingue l'intention (`stacksApplied`, `durationTicksApplied`) de l'état résultant (`totalStacks`, `remainingTicks`) — pattern déjà établi par `HEAL_RECEIVED` (`amount`/`hpHealed`).

**`JsonItemRepository`** (`Infrastructure/Repository/Json/`) : `mapToAction()` hydrate les 3 nouveaux champs depuis le JSON (`status`, `stacks`, `durationTicks`), nullable si absents.

### Nouvelle classe — `StatusProcessor` (`Domain/Engine/`)

**Décision architecturale actée** (question ouverte depuis la session 004) : `StatusProcessor` est un collaborateur **stateless, découplé de `TickEngine`**, orchestré directement par `Simulator` — pas injecté dans `TickEngine` comme `EventDispatcher`. Justification : SRP strict (`TickEngine` = temps + cooldowns + détection ; `StatusProcessor` = pulsation périodique ; `Simulator` = orchestration), et tests unitaires totalement isolés (pas besoin de construire un `TickEngine` complet pour tester une pulsation).

**Contrat retenu** : `processTick(SimulationContext $context): list<CombatEvent>`.

**Séquence par statut, dans `pulse()`** (identique pour les 4 types) :
1. `decrementDuration()` — **avant** la construction de l'événement (piège identifié et corrigé en session : le premier jet lisait `remainingTicks` avant le décrément, ce qui aurait produit un payload en retard d'un tick).
2. Application de l'effet spécifique au type (`match` sur `StatusType`, exhaustif, sans `default` une fois les 4 cas couverts).
3. Si `isExpired()` après décrément : émission d'un second `CombatEvent` (`STATUS_EXPIRED`) **dans le même tick** — décision actée : un statut pulse une dernière fois puis expire, il n'est jamais court-circuité (sinon un Poison de durée N perdrait sa dernière tique de dégâts).
4. `removeExpiredStatuses()` appelé une fois par vestige, après avoir traité tous ses statuts (pas dans la boucle interne).

**Les 4 branches, par ordre d'implémentation cette session** :
- `pulsePoison()` → `takeRawDamage()` (ignore le bouclier) → `STATUS_DAMAGE_DEALT`.
- `pulseBurn()` → `takeDamage()` (bouclier puis HP, classique) → `STATUS_DAMAGE_DEALT`. Volontairement dupliqué avec `pulsePoison()` (une seule ligne diffère) plutôt que factorisé — règle des 3 non atteinte.
- `pulseRegen()` → `receiveHeal()` (plafonné à `baseHp`) → `STATUS_HEAL_RECEIVED`. Test conçu pour exercer le plafond (`hpHealed < amount`), piège de setup rencontré et corrigé en session (`takeDamage()` initial était absorbé par le bouclier avant de toucher les HP — corrigé en `takeRawDamage()` dans le test).
- `pulseWard()` → `gainShield()` (sans plafond) → `STATUS_SHIELD_GAINED`. Le plus simple des 4, aucun piège de plafond.

**Total : 8 tests sur `StatusProcessorTest`** (pulsation Poison, décrément multi-appels, expiration + double-événement + purge, Burn, Regen, Ward), tous verts.

### Intégration dans `Simulator`

`Simulator::run()` reçoit une nouvelle dépendance optionnelle `?StatusProcessor $statusProcessor = null` (même pattern d'injection que `?ActionProcessor`, assignée dans le corps du constructeur — pas de valeur par défaut en signature).

**Ordonnancement actée dans la boucle de tick**, avec justification par cas limite concret (discuté et validé avant codage) :

```
tickEngine->tick($context)          // avance le temps, décrémente cooldowns,
                                      // détecte objets prêts → PendingAction NON exécutées
statusProcessor->processTick($context)  // pulse tous les statuts actifs au tick courant
   → si mort : break AVANT de traiter les PendingAction (pas de "soin sur cadavre")
foreach ($pendingActions) → actionProcessor->process(...)
   → si mort : break (règle déjà existante, inchangée)
```

**Deux invariants prouvés par raisonnement à la main avant codage** :
1. Un statut appliqué par un item au tick N ne pulse jamais avant le tick N+1 (parce que sa `PendingAction` est traitée *après* la pulsation du tick N, à l'étape suivante de la boucle).
2. Un Poison/Burn qui achève un Vestige au tick N empêche l'exécution d'objets en attente au même tick (ex. une Potion de soin prête ne sauve pas un Vestige tué par le Poison au même tick) — extension du `break` déjà existant sur mort par objet, appliqué symétriquement à la mort par statut.

### Tests E2E

Ajout d'un second test à `SimulationE2ETest` (le premier, basé sur `shadow_dagger`, reste inchangé et ne couvre pas les statuts) : combat réel depuis `config/game/items.json` avec `venomous_vial`, assertions sur la présence de `STATUS_APPLIED` et `STATUS_DAMAGE_DEALT` dans le log — preuve que la chaîne complète (déclenchement d'objet → application → pulsation) fonctionne sans mock. Pas d'assertion sur `STATUS_EXPIRED` (non garanti selon le seed/l'issue du combat dans le temps imparti).

### Résultat final de la branche

CS Fixer / PHPStan niveau 6 (0 erreur) / PHPUnit tous verts. `feature/status-effects-engine` est fonctionnellement complète : les 4 statuts (Poison, Burn, Regen, Ward) sont applicables via un objet et pulsent correctement en combat simulé. PR ouverte vers `dev`, titre et description rédigés, prête à merger.

## Commits de la session (dans l'ordre)

1. `feat(domain): implement ActiveStatus with stacking rules`
2. `test(domain): mirror tests/Domain structure with src/Domain layout` *(refactor séparé, initié par l'utilisateur en cours de session)*
3. `feat(domain): wire APPLY_STATUS action into ActionProcessor`
4. `feat(domain): implement periodic Poison damage via StatusProcessor`
5. `feat(domain): implement periodic Burn damage via StatusProcessor`
6. `feat(domain): implement periodic Regen healing via StatusProcessor`
7. `feat(domain): implement periodic Ward shield gain via StatusProcessor`
8. `feat(domain): wire StatusProcessor into Simulator's tick loop`

## Idées reportées / notées en cours de session (non implémentées)

- **Refactor `applyHpDamage()`** sur `CombatVestige` (mutualiser `takeDamage()`/`takeRawDamage()`) — mineur, en attente d'un 3e appelant ou d'une envie de nettoyage.
- **Factorisation `pulsePoison()`/`pulseBurn()`** dans `StatusProcessor` — même logique, règle des 3 non atteinte, laissé dupliqué volontairement.
- Toutes les idées différées des sessions 001-005 (multi-affinité mécanique, compétences de héros, conversion d'affinité, persistance d'état entre combats, garde-fou anti-boucle-infinie sur `EventDispatcher`, répartition des items par héros précis) restent valables et non retouchées cette session.

## Prochain chantier — Boutique / Économie

**Nom de branche retenu : `feature/shop-economy`** (à créer depuis `dev`, une fois la PR de cette session mergée).

Rappel du cahier des charges V1 (`game-design-notes.md`) pour ce chantier :
- 4 offres aléatoires par visite.
- Or de départ fixe.
- Prix croissant avec la rareté.
- Une seule monnaie.
- Boucle : choix de départ (2-3 objets communs) → boutique → combat IA → retour boutique jusqu'à mort ou victoire après N combats.

**Éléments déjà présents dans le code, pertinents pour ce chantier** :
- `Rarity::dropRateModifier()` (×1 / ×0.25 / ×0.015) — confirmé existant depuis la session 004, mentionné comme "pas encore utilisé, pertinent pour la future boutique". Probablement le premier élément à mobiliser.
- `ActionType::GAIN_GOLD` — existe dans l'enum, non traité par `ActionProcessor` (`default` aurait levé `LogicException` si atteint, mais aucun item du roster actuel ne l'utilise a priori — à vérifier).
- Aucune classe `Shop`/`Economy` n'existe encore dans `Domain/` ou ailleurs — chantier entièrement nouveau, pas d'extension d'existant.

**Rien n'a encore été discuté en détail sur ce chantier** (pas d'architecture, pas de première classe identifiée) — la session 006 s'est arrêtée avant d'ouvrir ce sujet. Prochaine session : partir du cahier des charges ci-dessus et des questions habituelles (quelle est la première brique feuille sans dépendance ? probablement une classe de définition de prix/coût par rareté).

## Rappel des conventions

- Commits Conventional Commits, scope `domain` pour le moteur (scope à définir pour la boutique — probablement `domain` toujours, ou `shop` si Laurent préfère un scope dédié, à trancher en début de prochaine session).
- Un commit par brique logique terminée et verte.
- CS Fixer + PHPStan + PHPUnit systématiquement avant commit.
- PR sur GitHub vers `dev`, CI vérifiée avant merge.
- TDD strict : test rouge → implémentation minimale → vert → refactor si besoin → nouveau test.
- Mode pédagogique par défaut : questions guidées avant le code, sauf demande explicite de réponse directe.
