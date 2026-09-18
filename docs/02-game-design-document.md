# 02 — Game Design Document

**Autorité sur :** les règles du jeu, les systèmes, les entités, la boucle, l'économie.
**Révision :** 2.0 — 8 septembre 2026.

**Statuts employés :** IMPLÉMENTÉ · ENGAGÉ · CIBLE · OUVERT · ÉCARTÉ (voir `00-INDEX.md` §3).
**Rappel d'autorité :** en cas de doute sur l'état réel d'une mécanique, le code et le dernier résumé de session priment sur ce document.

**Marqueur introduit en révision 2.0 : ⚠ ÉCART.** Il signale une règle décrite ici que **le code n'applique pas**, vérifiée par lecture directe. Ce n'est ni une décision ouverte, ni une cible : c'est une divergence entre la règle voulue et la règle exécutée. Ce vocabulaire n'existe pas dans `00-INDEX` §3, qui a autorité sur les statuts ; **son ajout est une proposition, à valider.**

**Ce qui a changé en révision 2.0.** L'audit de code du 8 septembre 2026 a invalidé sept affirmations de la révision 1.0, dont deux dans la description du pipeline de combat (§7.2) et une dans la table des compétences (§2.3). Trois écarts entre règle décrite et règle exécutée sont désormais consignés : §7.2 (double KO et priorité d'ordre), §7.3 (handicap d'enrage), §7.4 (accumulation de stacks). Les sections de conception pure sont inchangées.

---

## 1. Concept en une page

Le joueur est la conscience résiduelle d'un **Vestige**, un fragment vivant de la Tressure brisée. Il ne se bat pas lui-même : il recrute jusqu'à **trois héros porteurs**, les équipe d'**objets** qui s'activent automatiquement selon leur cadence propre, et regarde le combat se résoudre.

Une partie se joue en **manches**. Chaque manche enchaîne deux phases d'achat et deux combats : un combat **PvE contre un Monstre choisi**, qui rapporte des ressources, puis un combat **PvP asynchrone contre le plateau figé d'un autre joueur**, qui décide seul si la manche est gagnée ou perdue.

**Dix manches gagnées** terminent la run en victoire. **Trois manches perdues** la terminent en défaite.

---

## 2. Entités du jeu

### 2.1 Vestige — IMPLÉMENTÉ (1 exemplaire), CIBLE (7)

Le Vestige porte **l'intégralité de l'état vivant du plateau** : points de vie, bouclier, statuts actifs. Les héros n'ont pas d'état de combat propre. Il définit également l'**affinité** de la run et son économie de départ.

| Champ | Rôle | Obligatoire |
|---|---|---|
| `id` | Identifiant technique | Oui |
| `name` | Nom affiché | Oui |
| `affinity` | Affinité de la run | Oui |
| `baseHp` | Points de vie du plateau | Oui |
| `baseShield` | Bouclier de départ | Oui |
| `startingGold` | Or initial, crédité une seule fois | Oui |
| `startingIncome` | Or crédité **à chaque fin de manche**, gagnée ou perdue | Oui |

Tous les champs sont **obligatoires, fail-fast** : l'absence d'un champ dans le fichier de configuration doit provoquer une erreur au chargement, jamais une valeur par défaut silencieuse.

> **Réserve de vérification (08/09/2026).** `JsonVestigeRepository` applique aujourd'hui `$vestigeData['baseShield'] ?? 0` : `baseShield` a une valeur par défaut silencieuse, contrairement à la règle ci-dessus. Les six autres champs sont bien fail-fast. À corriger ou à assumer explicitement.

`startingIncome` existe pour une raison de design précise : **un joueur qui perd ne doit jamais être bloqué économiquement.** Une spirale de la mort où perdre rend le rattrapage impossible est le pire défaut possible dans une structure à trois défaites.

**État actuel :** un seul Vestige, `shadow_vestige`, affinité `shadow`, 100 PV, 10 de bouclier, 20 d'or, 5 de revenu, fixé en dur dans `RunController`.
**Cible :** 7 Vestiges, choix parmi 3 tirés aléatoirement au démarrage de la run.

**Point à connaître pour l'équilibrage.** `ScriptedOpponentFactory` utilise **le même `shadow_vestige` que le joueur**. Les deux camps ont donc aujourd'hui exactement les mêmes 100 PV et 10 de bouclier. C'est ce qui rend le biais d'enrage de §7.3 opérant à chaque manche.

### 2.2 Héros — IMPLÉMENTÉ (10), CIBLE (40)

Un héros est un **porteur d'objets doté d'une compétence passive**. Il n'a ni PV ni bouclier propres.

| Champ | Valeur |
|---|---|
| `affinity` | `shadow` ou `neutral` aujourd'hui ; une par affinité en cible |
| `itemSlots` | **2**, contrainte individuelle et non budget partagé |
| `skill` | `?HeroSkillType`, nullable |

Le budget de slots est **individuel par héros**, jamais mutualisé sur les trois. C'est une contrainte de design volontaire : sans elle, une compétence pourrait modifier plus d'objets que prévu.

**Contrainte structurelle vérifiée.** `CombatBoard::__construct()` refuse tout plateau hors de la fourchette **1 à 3 héros**. Un roster vide ne peut pas entrer en combat.

**Contrainte de lisibilité (cible).** Avec 40 héros et 3 recrutés par run, un joueur voit 9 cartes de héros par run, soit ~22 % du roster. Chaque héros dispose donc de très peu de temps d'écran.

La contrainte porte sur **la carte au moment de la décision**, pas sur l'unicité absolue du héros. Ce qui doit être lisible en une ligne, c'est la réponse à « pourquoi celui-ci plutôt que celui-là ? ». Deux héros peuvent partager une compétence sans être redondants, à condition qu'un autre axe les sépare de façon **mécaniquement effective**.

**Précision sur l'état actuel :** le catalogue de 10 héros contient déjà un doublon — `SAVAGE` est porté à la fois par *Shadow's Arrow* (`shadow`) et *The Farshot* (`neutral`). Comme l'affinité n'a aujourd'hui **aucun effet mécanique**, ces deux héros sont strictement identiques en jeu. Ce doublon devient légitime dès que le système d'affinité de §5.2 est actif, et pas avant.

### 2.3 Compétences de héros — IMPLÉMENTÉ (10), CIBLE (20)

Une compétence est un **filtre passif appliqué aux objets du héros au moment de l'assemblage du plateau** (`CombatBoardFactory` + `HeroSkillDecorator`), jamais une action autonome. Le moteur de combat n'en a aucune connaissance. Vérifié le 08/09/2026.

| Compétence | Effet | Portée |
|---|---|---|
| `FRANTIC` | −20 % `cooldownTicks` | objets `ONE_HAND` uniquement |
| `VIRULENT` | +1 stack sur `APPLY_STATUS(POISON)` | actions concernées |
| `SEARING` | +1 stack sur `APPLY_STATUS(BURN)` | actions concernées |
| `WARDEN` | +1 stack sur `APPLY_STATUS(WARD)` | actions concernées |
| `RESURGENT` | +1 stack sur `APPLY_STATUS(REGEN)` | actions concernées |
| `STALWART` | +20 % sur la valeur de `GAIN_SHIELD` | actions concernées |
| `VITALIC` | +20 % sur la valeur de `HEAL` | actions concernées |
| `SAVAGE` | +20 % sur la valeur de `DEAL_DAMAGE` | actions concernées |
| `SUNDERING` | **+35 % dégâts et +10 % cooldown** (≈ +22,7 % DPS réel) | objets `TWO_HAND` uniquement |
| `RELENTLESS` | +10 % dégâts et −10 % cooldown (≈ +22,2 % DPS) | tous les objets du héros, sous condition |

**Deux corrections apportées en révision 2.0.**

La révision 1.0 décrivait `SUNDERING` comme « −10 % cooldown ». C'est l'inverse : `applySundering()` fait `floor(cooldownTicks × 1.10)`, soit une **pénalité** de cadence compensée par un gros bonus de dégâts. Le chiffre de DPS (+22,7 %) était juste, la description du signe était fausse.

`SUNDERING` est par ailleurs **fermée aux objets `TWO_HAND`**. Sur les 30 objets actuels, elle en touche 10, pas 14.

> **~~⚠ ÉCART~~ — IMPLÉMENTÉ, résorbé le 14/09/2026.**
> `applySundering()` appliquait le bonus de dégâts, puis la pénalité de cooldown, **sans vérifier qu'un bonus avait été appliqué**. Sur `scutum` et `shadow_scutum`, deux mains sans `DEAL_DAMAGE`, le bonus ne trouvait aucune action à modifier mais la pénalité s'appliquait quand même : `floor(50 × 1,10) = 55`, soit une activation perdue sur 500 ticks, 50 de bouclier pour `scutum` et 75 pour `shadow_scutum`.
>
> **La compétence ne s'applique désormais plus du tout** à un deux mains sans `DEAL_DAMAGE`. Deux autres options ont été examinées et écartées. Majorer aussi les effets sans dégâts réaliserait la collision signalée plus bas dans cette section : `SUNDERING` deviendrait `HULKING`. Conserver le malus comme contrainte de construction supposerait que le joueur le voie — la décoration a lieu au combat, l'inventaire affiché porte l'objet brut — et puisse l'éviter, l'échange libre héros ↔ héros étant au périmètre J2. Un malus invisible et non évitable contredit `01` §3.
>
> Restaurer l'inertie est par ailleurs le comportement cohérent : `SUNDERING` est la seule des dix compétences à avoir un coût, et ce coût est le prix de son bonus. Chantier 3b, point 4.

**Condition de `RELENTLESS`.** La compétence ne s'applique que si le héros porte **exactement `itemSlots` objets, tous `ONE_HAND`** (`CombatBoardFactory::hasFullOneHandLoadout()`). Un héros sans objet ne la déclenche jamais. Quand la condition passe, la compétence s'applique à **tous** les objets du héros, y compris les boucliers et les soins — qui n'y gagnent que la réduction de cooldown. C'est une compétence défensive utilisable, pas seulement offensive.

**Règle d'arrondi, stable et définitive :** `cooldownTicks` toujours `floor()`, `value` toujours `ceil()`. Vérifiée appliquée dans `HeroSkillDecorator`.

**Filet de sécurité à préserver.** Le `match` de `decorate()` n'a **pas de branche `default`** et couvre exactement les 10 compétences implémentées. Ajouter un cas à `HeroSkillType` sans écrire sa décoration produit une erreur immédiate plutôt qu'une compétence silencieusement inerte. Ne pas ajouter de `default`.

### 2.3.1 Unicité des compétences — TRANCHÉE (ex-D-09)

**Décision retenue : pool restreint de 20 compétences.**

**Motif.** Le joueur construit un vocabulaire mental. S'il doit mémoriser 40 effets, il ne maîtrise jamais le système et évalue chaque carte à la lecture, lentement. Avec un pool de 20, il apprend `SEARING = +1 brûlure` une fois, puis évalue les héros sur les autres axes. S'y ajoute la charge de conception : 40 compétences distinctes, c'est 40 effets à concevoir, équilibrer et tester, sur un projet où l'équilibrage est déjà identifié comme un risque majeur.

**Ce qui rend un doublon légitime.** Un axe de différenciation **mécaniquement effectif**, pas cosmétique. Une illustration et un nom différents ne changent rien à la valeur du choix. En revanche, dès que l'affinité produit un effet (§5.2), deux héros partageant `SEARING` mais d'affinités différentes deviennent deux choix distincts.

**Règle d'unicité retenue :**

> Deux héros ne peuvent pas partager **à la fois** la même compétence et la même affinité.

Traduite en invariant testable (NF-17) :

```
test : pour tout couple (skill, affinity), il existe au plus un héros dans heroes.json
```

Avec 7 affinités et 20 compétences, cela ouvre 140 combinaisons pour 40 héros. Le roster cible respecte l'invariant, vérifié le 8 septembre 2026.

**Prérequis bloquant, maintenu.** Cette règle n'a de sens qu'une fois l'affinité mécaniquement effective. Tant que l'affinité est décorative, toute compétence dupliquée produit deux héros strictement identiques, c'est-à-dire du remplissage. **Ne pas dépasser 20 héros avant que le système d'affinité soit actif.**

**Piste complémentaire, non tranchée :** un second axe de différenciation en plus de l'affinité — magnitude de la compétence, `itemSlots` variable, ou préférence de taille d'objet. À étudier seulement si 20 × 7 se révèle insuffisant, ce qui est peu probable.

**Point de vigilance de conception.** `HULKING` (bonus réservé aux `TWO_HAND`) et `SUNDERING` (fermée aux `TWO_HAND`) risquent de devenir la même compétence sur un pool qui ne compte que 10 objets à deux mains sur 30. À vérifier au moment de les écrire.

### 2.4 Objets — IMPLÉMENTÉ (30), CIBLE (180)

| Rareté | Multiplicateur | Prix | Modificateur de drop |
|---|---|---|---|
| Commune | ×1 | 10 or | ×1 |
| Rare | ×1,5 | 25 or | ×0,25 |
| Légendaire | ×2,5 | 50 or | ×0,015 |

**Taille.** `ONE_HAND` (coût 1 slot) ou `TWO_HAND` (coût 2 slots). Un `TWO_HAND` vaut **×2 en valeur brute** mais seulement **×1,75 en prix** — un vrai rabais compensant la perte de flexibilité.

**Attribution.** Un objet acheté est attribué à un héros précis **dès l'achat** (`AssignedItem` = `Item` + `heroId`), jamais au moment du combat. `HeroItemAllocator` valide la faisabilité en sommant les `slotCost()` déjà assignés contre le budget du héros, et attribue au **premier héros du roster ayant de la place**.

**Cadence de départ.** Un objet entre en combat à **cooldown plein** : sa première activation intervient au tick `cooldownTicks`, pas au tick 1.

**Répartition actuelle :** 14 Common / 11 Rare / 5 Legendary · 22 `neutral` / 8 `shadow`. Aucun objet d'affinité n'est commun.

**Sur les 12 objets défensifs — affirmation révisée.** La révision 1.0 écrivait que ce chiffre avait motivé le système d'enrage. Le décompte est exact, mais l'audit du 8 septembre montre que ce n'est probablement pas la cause principale des stalemates. Voir §7.3 et §7.4 : un seul objet, `shadow_armor`, produisait à lui seul **7 155 points de bouclier sur 500 ticks**, contre 500 pour un `scutum` commun à deux mains. Le modèle par instances de D-20, implémenté le 14/09/2026, ramène ce chiffre à **1 253**. La cause est mécanique avant d'être une question de composition de catalogue.

**Asymétrie voulue entre soin et bouclier.** `CombatVestige::receiveHeal()` est plafonné à `baseHp`. `CombatVestige::gainShield()` n'a aucun plafond. **C'est une décision de conception, consignée dans `corebound-affinities` §2** : bouclier et PV ne se comparent pas comme une même unité. Le bouclier est un tampon consommable qui s'accumule, les PV un plafond fixe.

Conséquence de calibrage à ne pas perdre de vue : à valeur nominale et cadence égales, un objet de bouclier vaut plus qu'un objet de soin sur un combat long, le surplus de soin étant perdu et le surplus de bouclier conservé. L'écart réel entre les deux familles n'est donc pas les 25 % de débit nominal (0,625 contre 0,5). Ce n'est pas un défaut à corriger, c'est un facteur à intégrer au barème du chantier 10.

**Calibrage relatif des communs : le poison n'est pas au barème.** À rareté, prix, taille de slot et cooldown identiques, contre un Vestige de référence à 100 PV et 10 de bouclier :

| Commun, 1 main, cd 20 | Temps pour tuer |
|---|---:|
| `dagger` (10 dégâts) | 220 ticks |
| `venomous_vial` (POISON 1 stack / 30 ticks) | **80 ticks** |

Facteur 2,75, mesuré **avec** un plafond de stacks appliqué. Aucune part de cet écart n'est imputable au défaut d'accumulation décrit en §7.4. En régime stable, `venomous_vial` produit 2 dégâts bruts par tick là où `dagger` en produit 0,5 mitigés. Le poison ignorant le bouclier est une décision d'identité confirmée (§7.4) ; sa valorisation au barème ne l'est pas. Correction au chantier 10 de `07`.

### 2.5 Passifs de plateau — CIBLE

Modificateurs s'appliquant à **l'ensemble du plateau du joueur**, distincts des compétences de héros. Deux sources : la récompense d'un Monstre (rare) et un marchand spécialisé (rare).

Le schéma de plateau prévoit un panneau **« Liste passifs »** dédié. Aucune mécanique n'est encore spécifiée. **À documenter avant tout chantier.**

### 2.6 Monstre — CIBLE

Adversaire PvE, choisi parmi 3 propositions à chaque manche.

| Attribut | Rôle |
|---|---|
| Thématique propre | Identité visuelle et mécanique |
| Difficulté | faible / moyenne / difficile |
| Récompense en or | Montant fixe indexé sur la difficulté |
| Récompense secondaire | **Un** de : un de ses objets · un de ses passifs (rare) · un supplément d'or |

**État actuel.** `ScriptedOpponentFactory` produit un adversaire scripté unique, sans choix, sans thématique et sans récompense. Trois héros fixes : `shadow_bearer` (2× `dagger`), `the_bulwark` (`longsword`), `shadow_bastion` (2× `shield`). Difficulté croissante par le seul budget de slots, `min(ceil(round / 2), 6)`. Aucun aléa n'est consommé : l'adversaire de la manche N est **rigoureusement identique d'une run à l'autre**.

> **⚠ ÉCART — l'adversaire scripté est aux deux tiers inerte et sa rampe n'est pas monotone.**
>
> Sur ses trois héros, **une seule compétence agit** : `FRANTIC` sur `shadow_bearer` (cooldown des dagues 20 → 16). `STALWART` sur `the_bulwark` ne trouve aucun `GAIN_SHIELD` dans un `longsword`. `WARDEN` sur `shadow_bastion` ne trouve aucun statut dans un `shield`.
>
> Et le remplissage glouton de `createOpponent()` passe au héros suivant dès qu'un objet ne rentre pas dans le budget. Résultat :
>
> | Manches | Budget | Objets |
> |---:|---:|---|
> | 5–6 | 3 | 2× dagger + **shield** |
> | 7–8 | 4 | 2× dagger + longsword |
> | 9–10 | 5 | 2× dagger + longsword + shield |
>
> L'adversaire **perd son bouclier entre la manche 6 et la manche 7** : le budget monte, la défense descend.
>
> Conséquence : toute observation d'équilibrage relevée jusqu'ici porte sur une séquence unique, déterministe et partiellement inerte. À remettre à plat avant toute recalibration de catalogue.

### 2.7 Marchand — IMPLÉMENTÉ (générique), CIBLE (spécialisé, parmi 3)

**Actuel.** Un marchand générique, une visite par manche, 4 offres. Tirage partitionné : 3 slots dans le pool Commun + Rare, 1 slot dans le catalogue complet privé des trois déjà tirés. Probabilité qu'une visite contienne une légendaire : **5 / 27 ≈ 18,5 %**. Achat en deux phases — validation intégrale puis mutation, **jamais de débit partiel**. Vérifié dans `Shop::purchase()`.

**Deux précisions ajoutées en révision 2.0.**

Le tirage se fait **sans remise**, et le dernier slot exclut explicitement les identifiants déjà offerts : **les quatre offres d'une visite sont toujours quatre objets distincts.** Conséquence directe sur la fusion, voir §5.4.

La constante `MAX_LEGENDARY_OFFERS = 1` est un nom trompeur. Le quatrième slot ne filtre pas sur la rareté : il peut parfaitement produire un objet commun. La constante décrit un **plafond structurel** (un seul slot peut produire une légendaire), pas une garantie d'en offrir une.

**Cible.** Choix parmi 3 marchands par phase, chacun spécialisé. Un marchand peut influencer le pool d'objets, les prix ou le rachat. Un marchand rare vend des passifs. Le catalogue cible couvre : armes, objets, **héros**, compétences, passifs.

---

## 3. Boucle de jeu

### 3.1 Boucle actuelle — IMPLÉMENTÉ

```
Vestige fixe (shadow_vestige)
  ↓
Manche 1 : choix d'un héros parmi 3 (≥ 1 de l'affinité du Vestige)
  ↓
┌─── Nouvelle manche ────────────────────────────────┐
│  Boutique (1 visite, 4 offres distinctes)          │
│  Construction du plateau                            │
│  Combat PvE contre IA scriptée                      │
│  Victoire : +1 victoire, +10 or, +startingIncome    │
│  Défaite/nul/timeout : +1 défaite, +startingIncome  │
└────────────────────────────────────────────────────┘
  ↓ (manches 3 et 5 : nouveau choix de héros parmi 3, pondéré ×2, sans doublon)
Fin : 10 victoires ou 3 défaites
```

**Résolution du résultat.** `GameRun::playRound()` teste `$result->winner === $playerBoard`. Toute autre issue — défaite, timeout, match nul — est comptabilisée en défaite. Voir §7.2.

### 3.2 Boucle cible — ENGAGÉ (décision D-01, tranchée le 02/09/2026)

```
Choix du Vestige (parmi 3 tirés aléatoirement)
  ↓
┌─── MANCHE ─────────────────────────────────────────────────────┐
│                                                                 │
│  1. MARCHAND — phase d'ouverture                                │
│     choix parmi 3 marchands · 4 offres                          │
│         ↓                                                        │
│  2. COMBAT PvE — choix du Monstre parmi 3                       │
│     (faible / moyenne / difficile)                              │
│     → récompense : or fixe selon difficulté                     │
│     → + 1 récompense : objet OU passif OU or supplémentaire     │
│     → n'affecte PAS le compteur victoires/défaites               │
│         ↓                                                        │
│  3. MARCHAND — phase de préparation au combat                   │
│     choix parmi 3 marchands · 4 offres                          │
│     (dépense les gains du PvE)                                  │
│         ↓                                                        │
│  4. COMBAT PvP ASYNCHRONE                                       │
│     plateau adverse = snapshot figé d'un autre joueur,          │
│     indexé par numéro de manche                                 │
│     → récompense : or uniquement                                │
│     → DÉCIDE si la manche est gagnée ou perdue                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
  ↓ Victoire → +1 victoire · Défaite → +1 défaite
  ↓ (+ startingIncome à chaque fin de manche, quel que soit le résultat)
  ↓ (aux manches définies : choix d'un héros parmi 3, pondéré, sans doublon)
Fin de run : 10 victoires (gagné) ou 3 défaites (perdu)
```

### 3.3 Répartition des rôles — principe directeur

| | PvE (Monstre) | PvP (snapshot) |
|---|---|---|
| **Rôle** | Moteur d'économie | Juge de la manche |
| **Récompense** | Or fixe selon difficulté **+** une récompense au choix du monstre (objet / passif / or) | Or uniquement |
| **Effet sur le score** | **Aucun** | +1 victoire ou +1 défaite |
| **Adversaire** | Monstre choisi parmi 3 | Snapshot d'un autre joueur, même numéro de manche |
| **Décision du joueur** | Arbitrage risque/récompense | Aucune — le plateau est déjà construit |

**Ce que cette répartition produit [conception] :** le joueur choisit délibérément son niveau de risque économique au milieu de la manche, puis subit la sanction sur un terrain qu'il ne choisit pas. Cela sépare proprement l'agentivité (le PvE) de l'épreuve (le PvP), et évite le défaut classique du PvP asynchrone pur, où un joueur en retard sur la courbe de puissance n'a plus aucun levier.

### 3.4 Points d'ancrage narratif

- **Une victoire** = le fragment (Vestige + porteur) fait tenir un nœud, un point de fixation stable dans ce qui reste de la Tressure.
- **Une défaite** = le fil cède un peu plus.
- **Trois défaites** = le fil casse pour de bon ; le porteur est rejeté, le Vestige se retire pour cette tentative.
- **Dix victoires** = assez de points de fixation recréés pour prouver que la tentative a fonctionné.

### 3.5 Durée — OUVERT (D-03)

Quatre phases de décision par manche, jusqu'à 12 manches. **Contrainte externe forte :** la fenêtre de remboursement Steam est de 2 heures. Si une run dépasse 45 minutes, un joueur n'en fait pas trois avant la fin de la fenêtre.

**Cible proposée, à valider par playtest :** manche en 2 à 3 minutes, run complète en 25 à 35 minutes. Cela impose que les deux phases de marchand soient rapides et que le combat soit rejouable en accéléré ou sautable.

**Borne technique.** Un combat est plafonné à 500 ticks, soit 50 secondes à 10 ticks par seconde. Deux combats par manche donnent un plancher de 100 secondes de combat non compressible, avant toute phase de décision. C'est déjà la moitié basse de la cible de 2 à 3 minutes.

---

## 4. Économie

### 4.1 Sources d'or

| Source | Montant | Statut |
|---|---|---|
| `Vestige::startingGold` | Une fois, au début de la run | IMPLÉMENTÉ (20) |
| `Vestige::startingIncome` | À chaque fin de manche, gagnée ou perdue | IMPLÉMENTÉ (5) |
| Victoire de manche | +10 or fixe | IMPLÉMENTÉ |
| Récompense PvE — or de base | Selon la difficulté du Monstre | ENGAGÉ |
| Récompense PvE — récompense secondaire | Objet, passif rare, **ou** supplément d'or | ENGAGÉ |
| Récompense PvP | Or uniquement | ENGAGÉ |
| Revente d'objets | — | ÉCARTÉ en V1, CIBLE via marchands spécialisés |

`startingGold = 20` est calibré pour permettre l'achat de **deux objets Communs au premier tour** (prix Common = 10).

### 4.2 Inventaire

- **Plateau de combat :** 6 slots au total, répartis entre les 3 héros selon leur budget individuel de 2.
- **Coffre :** capacité 6.
- **Seul mouvement implémenté :** `swapWithStash`, échange direct héros ↔ coffre.
- **CIBLE explicite :** échange libre héros ↔ héros, sans réordonnancement artificiel, sans pénalité, sans limitation. Ce n'est pas une restriction de design, c'est un manque.

**Point à vérifier.** `GameRun::purchaseItem()` dépense l'or et marque l'offre achetée **avant** de tenter le rangement en inventaire ou en coffre. Si le coffre est plein et que `Stash` refuse, le joueur reçoit une erreur sur un achat qu'il croyait valide. L'action n'étant pas journalisée en cas d'exception, l'état persistant reste cohérent, mais l'expérience ne l'est pas.

### 4.3 Conséquence d'une défaite PvE — ENGAGÉ (D-02, tranchée le 02/09/2026)

> **Une défaite en PvE ne rapporte rien. Ni or de base, ni récompense secondaire.**

Le compteur victoires/défaites n'est pas affecté : seul le PvP le fait bouger.

**Ce que cette règle produit.** Le choix du Monstre devient un arbitrage réel entre espérance de gain et probabilité de réussite. Un joueur en avance sur la courbe de puissance vise le monstre difficile pour creuser l'écart ; un joueur en retard doit choisir entre sécuriser un petit gain et tenter un rattrapage risqué. C'est la seule décision de la manche où le joueur module lui-même son exposition au risque, et elle intervient au bon moment — avant la seconde phase de marchand, donc avec des conséquences immédiatement visibles sur ce qu'il pourra acheter.

**Filet anti-spirale.** `Vestige::startingIncome` est crédité à chaque fin de manche, quel que soit le résultat des deux combats. Un joueur qui enchaîne les échecs PvE progresse donc économiquement, plus lentement. C'est le seul garde-fou, et il est volontairement le seul.

**Point à surveiller en playtest.** Si les joueurs choisissent le monstre facile dans plus de ~70 % des manches, l'écart de récompense entre les trois difficultés est trop faible. Si le monstre difficile est choisi presque toujours, c'est l'inverse.

---

## 5. Systèmes cibles non encore spécifiés

### 5.1 Choix du Vestige — CIBLE

Trois Vestiges tirés aléatoirement au démarrage. Chaque Vestige apporte : une affinité, une économie de départ propre, des stats de plateau, et un pool d'objets thématiquement dédié.

**Contrainte de design :** sept Vestiges doivent produire sept manières de jouer, pas sept jeux de statistiques. Si deux Vestiges ne diffèrent que par des chiffres, l'un des deux est du remplissage.

### 5.2 Système d'affinité étendu — CIBLE

Aujourd'hui : l'affinité existe sur les héros, les objets et le Vestige, mais **n'a strictement aucun effet mécanique**. C'est une donnée décorative.

Cible : un système à quatre relations.

| Relation entre l'affinité du porteur et celle du Vestige | Effet |
|---|---|
| Identique | Bonus |
| Liée positivement | Petit bonus |
| Neutre | Aucun effet |
| Liée négativement | Petit malus |

S'applique à la compétence du héros et aux objets selon l'affinité du héros qui les porte.

**Racine narrative :** la perception d'un porteur est un mélange de croyance religieuse et de politique locale. Deux affinités bien vues dans les mêmes cultures synergisent ; deux affinités issues de cultes rivaux entrent en tension. Voir `corebound-lore-bible.md` §3.

**Recommandation de séquencement :** prototyper avec **deux affinités seulement**, jamais sept d'un coup. L'objectif du prototype est de répondre à une seule question — l'affinité crée-t-elle des builds différents, ou n'est-elle qu'un multiplicateur de puissance ? Si c'est la seconde réponse, le système doit être repensé et non étendu.

**Structure des primaires et secondaires.** `corebound-affinities` §2 organise plusieurs affinités autour d'une paire **instantané / étalé dans le temps** : Végétal porte `Heal` en primaire et `Regen` en secondaire, Métal porte `Shield` en primaire et `Ward` en secondaire. Ce ne sont pas des doublons : `GAIN_SHIELD` accorde un montant immédiat, `WARD` accorde un gain périodique, exactement comme `HEAL` face à `REGEN`. Le moteur implémente déjà les quatre.

### 5.3 Choix du marchand — CIBLE

Trois marchands par phase, chacun spécialisé dans la vente et/ou le rachat d'un type d'objet, plus un marchand rare vendant des passifs.

**Recommandation de séquencement :** à traiter **après** l'échange libre d'objets et après le prototype d'affinité. Motif : on ne peut pas construire une offre pertinente avant de savoir ce que le joueur cherche réellement à acheter.

### 5.4 Fusion d'objets — CIBLE, avec un point OUVERT (D-04)

Les objets démarrent en rang Bronze. Deux objets identiques de même rang possédés simultanément fusionnent en un rang supérieur : Bronze → Argent → Or → (Diamant, écarté).

**Échelle tranchée.** Bronze ×1 · Argent ×1,75 · Or ×2,25 **plus une ligne bonus**. Multiplicateur sur `value` uniquement, **jamais** sur `cooldownTicks` — sinon l'Or vaut ×5 réels. Plafond par rareté : communs jusqu'à Or, rares jusqu'à Argent, légendaires non fusionnables.

**Décision non tranchée :** la fusion doit-elle changer uniquement les chiffres, ou le comportement de l'objet ?

**Analyse.** Une fusion purement numérique devient un réflexe automatique : le joueur fusionne toujours, il n'y a pas de décision. Une fusion qui change le comportement crée un vrai arbitrage. La seconde option est nettement supérieure en design, mais elle **multiplie le volume de contenu par le nombre de rangs**.

> **Contrainte d'alimentation à vérifier avant tout développement.** `ShopFactory` tire sans remise et exclut du dernier slot les identifiants déjà offerts : **les quatre offres d'une visite sont toujours distinctes** (§2.7). Accumuler trois exemplaires d'un même objet ne peut donc venir que de visites successives, sur 10 à 12 manches et une visite par manche. **La faisabilité économique de la fusion n'est pas acquise** et doit être simulée avant d'écrire les lignes bonus des 81 objets communs de la cible.

**Point de tension avec la rareté.** Un Argent coûte 20 or pour ×1,75 ; un Rare coûte 25 or pour ×1,5, à slot égal. **L'Argent domine strictement le Rare.** Tant que le Rare n'est qu'un bâton de statistiques, fusion et rareté sont le même axe de puissance exprimé deux fois. Piste : le Rare gagne une seconde action ou une condition — les 5 légendaires actuelles ont deux actions, les 11 rares une seule.

### 5.5 Choix de difficulté PvE — ENGAGÉ

Trois monstres proposés : faible / moyenne / difficile, avec des récompenses croissantes.

---

## 6. PvP asynchrone

### 6.1 Principe

```
Joueur A → snapshot du plateau → backend (stockage)
Joueur B → demande d'adversaire → backend → snapshot A + plateau B
        → simulation déterministe → CombatLog → client B
```

Aucune session persistante, aucune contrainte de latence, aucune simultanéité. Les deux joueurs ne sont jamais en ligne en même temps.

### 6.2 Règles d'appariement

- **Appariement strictement par numéro de manche.** Un joueur en manche 4 n'affronte que des snapshots de manche 4. C'est ce qui garantit une courbe de puissance cohérente sans système d'ELO.
- Le snapshot est **figé** : il ne réagit pas, ne s'adapte pas, et ne connaît pas son adversaire.
- Un snapshot ne périme jamais. Un jeu à 200 joueurs actifs peut puiser dans des dizaines de milliers de runs historiques.

### 6.3 Règle d'or — contrainte non négociable

> **Tout combat PvP doit pouvoir être transformé en un snapshot autonome, anonymisé, versionné et rejouable localement.**

Conséquences détaillées en `04` §5. La conséquence la plus importante pour le design : **cette règle impose que le moteur de combat soit exécutable sur la machine du joueur**, puisque le plateau du joueur varie et qu'aucun `CombatLog` ne peut être pré-calculé pour un adversaire donné.

### 6.4 Amorçage de la base — OUVERT (D-05)

Au lancement, la base de snapshots est vide. Des adversaires de secours doivent être générés (l'infrastructure existe : `ScriptedOpponentFactory`).

**Décision requise :** ces adversaires sont-ils annoncés comme tels au joueur ?

**Position recommandée : oui, transparence.** L'opacité sur ce point est exactement le genre de chose qui finit sur Reddit et qui coûte plus cher qu'elle ne rapporte. Un libellé neutre du type « adversaire d'archive » suffit.

### 6.5 Deux règles manquantes, bloquantes avant la mise en ligne

Les écarts de §7.2 ont une conséquence directe et non résolue sur l'équité du PvP.

1. **Départage sur mort simultanée.** Tant que l'ordre du tableau de plateaux décide, le même appariement produit **deux vainqueurs différents** selon lequel des deux plateaux est désigné « joueur ». Une règle explicite est requise, et elle doit être fixée **avant** la production du corpus : la changer après rejoue tous les snapshots avec des `CombatLog` différents, ce qui viole la règle d'or.
2. **Statut du match nul.** Un double KO n'est aujourd'hui pas distinguable d'un timeout. En PvE les deux sont résolus en défaite ; en PvP, « les deux meurent » et « personne n'a gagné en 500 ticks » ne peuvent pas donner le même résultat de match des deux côtés.

Décisions D-14 et D-15 de `07`, tranchées au chantier 2.

---

## 7. Moteur de combat — IMPLÉMENTÉ

### 7.1 Déterminisme

`CombatLog = f(playerBoard, opponentBoard, randomizer)`.

RNG : `\Random\Randomizer` sur moteur `\Random\Engine\PcgOneseq128XslRr64($seed)`. Aucune autre source d'aléa n'est autorisée dans le domaine.

**Précision apportée en révision 2.0.** La formule de la révision 1.0 écrivait `f(..., seed)`. C'est inexact : `GameRun::playRound()` transmet au `Simulator` **le randomizer du run**, dont l'état dépend de tous les tirages déjà consommés — offre initiale, boutiques, offres pondérées. Le combat n'est donc pas fonction d'une seed mais d'un **état de flux**.

Sans conséquence aujourd'hui, puisque **aucun composant du moteur n'appelle `getRandomizer()`** : le combat ne consomme actuellement aucun nombre aléatoire. La conséquence apparaît dès que `Critical` existe, et elle est double : le contenu d'une boutique dépendrait du nombre de tirages consommés par le combat précédent, et un combat rejoué isolément par le moteur embarqué ne pourrait pas reproduire le flux.

**Règle retenue, à implémenter avant le chantier « actions manquantes » :** le combat reçoit un randomizer **dérivé**, seedé de façon déterministe à partir de la seed du run et du numéro de manche. Jamais le randomizer du run.

### 7.2 Pipeline

Ordre réel d'un tick, vérifié dans `Simulator::run()` le 8 septembre 2026 :

```
1. TickEngine        avance le tick, décrémente les cooldowns,
                     consomme la charge des objets prêts et collecte
                     leurs intentions SANS les exécuter
                     (via EventDispatcher::dispatchForItem)
        ↓
2. StatusProcessor   pulsation des statuts actifs des deux plateaux,
                     puis purge des statuts expirés
        ↓
3. EnrageProcessor   au-delà de triggerTick uniquement
        ↓
4. ActionProcessor   exécution des intentions collectées en 1
        ↓
   CombatEvent → CombatLog
```

**Correction majeure.** Le schéma de la révision 1.0 plaçait `ActionProcessor` **avant** `StatusProcessor` et `EnrageProcessor`. C'est l'inverse. Les statuts pulsent avant que les objets n'agissent, ce qui a une conséquence observable : un statut appliqué au tick T reçoit sa première pulsation au tick T+1, et un statut dont la durée égale le cooldown de son objet source expire **avant** d'être réappliqué, donc ne s'accumule pas.

- **1 tick = 100 ms** (10 ticks/seconde). `maxTicks` par défaut : 500, soit 50 secondes de combat.
- `SimulationResult { winner: ?CombatBoard, totalTicks: int, log: CombatLog }`. La révision 1.0 écrivait `?CombatHero` — le type réel est `CombatBoard`.
- `winner: null` couvre **le timeout et le double KO**, sans les distinguer.

> **⚠ ÉCART 1 — le double KO n'est pas impossible.**
> La révision 1.0 affirmait que le `break` immédiat rendait le double KO « structurellement impossible ». C'est faux à deux endroits.
> **`StatusProcessor::processTick()` n'a aucun contrôle de vie** : il pulse les statuts des deux plateaux dans la même boucle. Si le poison de chacun achève l'autre au même tick, les deux meurent.
> **`Simulator::run()` n'a aucun contrôle entre les phases 2 et 3.** Si l'adversaire meurt d'un poison, l'enrage s'exécute quand même, frappe le joueur en premier, et peut le tuer — **transformant une victoire en double KO, donc en défaite**.
> Le commentaire d'`EnrageProcessor` documente lui-même l'invariant comme acquis. Il ne l'est que dans deux boucles sur trois.

> **⚠ ÉCART 2 — l'ordre des plateaux décide des morts simultanées, dans deux directions opposées.**
> Trois boucles itèrent les plateaux dans l'ordre `[joueur, adversaire]` et s'interrompent à la première mort.
>
> | Boucle | Cible des dégâts | Camp favorisé |
> |---|---|---|
> | `TickEngine` → actions d'objets | l'ennemi | **le joueur** — il frappe en premier |
> | `EnrageProcessor` | soi-même | **l'adversaire** — le joueur encaisse en premier |
> | `StatusProcessor` | soi-même | aucun aujourd'hui, faute de garde |
>
> La règle sous-jacente : quand une boucle inflige à l'ennemi, passer en premier est un avantage ; quand elle inflige à soi-même, c'est un désavantage. Aucun des deux biais n'est documenté ni voulu. Ajouter naïvement la garde manquante à `StatusProcessor` alignerait les statuts sur le biais **défavorable** au joueur : la garde et la règle de départage doivent être décidées ensemble.

**Dette d'ordre latente.** `EventDispatcher::dispatchForItem()` parcourt un tableau indexé par valeur de `Trigger`. Pour un objet portant plusieurs effets de déclencheurs différents, l'ordre d'exécution suivrait la séquence d'enregistrement, pas l'ordre de déclaration dans le JSON. Aucun objet actuel n'a deux effets ; la dette devient réelle au chantier « déclencheurs vivants ».

### 7.3 Système d'enrage

À partir de `triggerTick = max(1, maxTicks − 50)`, des dégâts exponentiels s'appliquent aux deux Vestiges au même tick : `damage = baseDamage × 2^(tick − triggerTick)`, `baseDamage = 5`, passant par le bouclier normalement.

**Raison d'être.** Sans résolution forcée, un combat atteignant `maxTicks` produit `winner: null`, compté comme défaite — ce qui rendait les builds défensifs structurellement perdants dans tout matchup de stalemate. Les dégâts passent **par le bouclier** et non en brut : appliquer des dégâts bruts punirait une seconde fois les builds que le système est censé protéger.

**Paramètres non calibrés par playtest** — posés par raisonnement.

> **⚠ ÉCART 3 — l'enrage handicape structurellement le joueur d'environ 1,5×.**
>
> `EnrageProcessor::processTick()` itère les plateaux dans l'ordre `[joueur, adversaire]` et interrompt la boucle dès qu'un Vestige meurt. Le dégât doublant à chaque tick, les seuils cumulés doublent également :
>
> | Tick | Dégât du tick | Cumul |
> |---:|---:|---:|
> | 450 | 5 | 5 |
> | 454 | 80 | 155 |
> | 457 | 640 | 1 275 |
> | 460 | 5 120 | 10 235 |
>
> Deux Vestiges dont les PV effectifs restants tombent dans le même palier meurent donc au même tick. **Le joueur, traité en premier, meurt en premier, et l'adversaire ne reçoit jamais son coup.**
>
> | PV effectifs de l'adversaire | PV effectifs requis côté joueur | Handicap |
> |---:|---:|---:|
> | 100 | 156 | ×1,56 |
> | 125 | 156 | ×1,25 |
> | 300 | 316 | ×1,05 |
> | 800 | 1 276 | ×1,59 |
> | 1 415 | 2 556 | ×1,81 |
>
> **Le joueur a besoin d'environ 1,5 fois les PV effectifs de l'adversaire pour gagner un combat résolu par l'enrage.** Comme l'enrage n'intervient que sur des plateaux de force voisine — c'est sa définition — ce n'est pas un cas limite, c'est son cas nominal. Le biais est aggravé par le fait que les deux camps partagent le même Vestige (§2.1).
>
> Toute observation de playtest portant sur un build défensif est à considérer comme faussée tant que ce point n'est pas tranché.

**Deux notes de précision.**

`ENRAGE_WINDOW_TICKS = 50` décrit une fenêtre qui se résout en pratique en 10 ou 11 ticks. Même les ≈ 7 165 points de bouclier que `shadow_armor` peut produire tombent au tick 460. La constante suggère une phase de fin cinq fois plus longue qu'elle ne l'est.

**Conséquence sur les objets de PV max (cible).** Passer de 100 à 150 PV ne fait souvent franchir **aucun palier**, les paliers doublant. Les objets de PV max seront donc forts en combat normal et sans effet en stalemate, ce qui rendra le futur Vestige Terre structurellement faible face aux plateaux défensifs. À prendre en compte lors de sa conception, pas après.

### 7.4 Effets et statuts

**Modèle de persistance — TRANCHÉ (D-20, 13/09/2026).** Chaque application de statut crée une **instance indépendante**, portant ses propres stacks, son propre compteur de ticks et l'identifiant de sa source. **Les instances ne fusionnent jamais.** À chaque tick : chaque instance décrémente son compteur, l'effet s'applique pour la **somme des stacks de toutes les instances vivantes du même type**, puis les instances à zéro tick sont retirées.

| Statut | Effet par tick | Bouclier | Nettoyé par le soin |
|---|---|---|---|
| `POISON` | Dégâts sur PV (`takeRawDamage`) | **ignoré** | oui |
| `BURN` | Dégâts, répartis selon la règle ci-dessous | traversé à taux majoré | oui |
| `REGEN` | Soin, plafonné à `baseHp` | — | non |
| `WARD` | Gain de bouclier, sans plafond | — | non |

**Pourquoi ce modèle plutôt que les deux autres.** Trois étaient candidats.

- *Monolithe à durée unique* — l'implémentation actuelle. Produit une discontinuité : dès que `cooldownTicks < durationTicks`, la pile ne redescend plus jamais. Laisse en outre un objet rapide écraser la durée d'un objet lent, détruisant l'intention de design de chacun.
- *Pool à décroissance* — supprime `durationTicks`, mais **déplace** la discontinuité sur `stacks > cooldownTicks` au lieu de la supprimer, et impose de rééchelonner les huit objets à statut d'un facteur 7 à 12, une application de *n* stacks totalisant `n(n+1)/2`.
- *Instances séparées* — **aucune discontinuité, quel que soit le rapport des deux horloges.** Le nombre d'instances simultanées se stabilise de lui-même à `ceil(durationTicks / cooldownTicks)` par source. Aucun plafond n'est écrit, aucun objet n'est rééchelonné.

Le plafond `ceil(durée / cooldown)` que la révision 2.0 proposait comme correctif est exactement le régime permanent de ce modèle. Les deux convergent : l'un l'obtient par une règle à maintenir, l'autre par la structure de données.

**Référence externe.** C'est le modèle de Path of Exile pour le poison — chaque application vit sa course, sans limite de nombre. The Bazaar et Backpack Battles ont retenu le pool ; leur poison ne décroît pas et croît quadratiquement, ce que les deux communautés signalent comme incontrable en fin de partie.

**Empilement non borné, et c'est voulu.** Aucun plafond de stacks. Une construction à cinq sources de poison empile cinq fois plus qu'à une source. Ce qui est borné, c'est la contribution **par source**, et elle l'est par construction et non par règle.

**Conséquence sur le journal de combat : aucune.** `StatusProcessor` agrège la somme des stacks vivants **avant** d'émettre. Un seul `CombatEvent` par statut et par tick, charge utile inchangée. La liste d'instances est un état interne ; le lecteur de rejeu du frontend n'est pas touché.

#### Brûlure — répartition bouclier / PV, TRANCHÉE (13/09/2026)

La brûlure est un **brise-défense** : forte sur le bouclier, faible sur les PV. Le poison tue, la brûlure ouvre la brèche.

```
boosted  = intdiv(stacks * 3, 2)      // 150 % — valeur majorée
absorbed = min(shield, boosted)        // ce que le bouclier encaisse
leftover = boosted - absorbed          // surplus, en unités majorées
hpDamage = intdiv(leftover * 7, 15)    // retour à 100 % (×2/3) puis 70 % → ×7/15
```

Exemple : 10 stacks contre 8 de bouclier → 15 majorés, 8 absorbés, 7 de surplus, `intdiv(49, 15) = 3` PV. Total 8 bouclier + 3 PV. Contre 0 bouclier : 7 PV, soit 70 % exactement. Contre un bouclier plein : 15, soit 150 %.

**Arithmétique entière exclusivement.** Aucun flottant, aucun pourcentage calculé. Les deux divisions arrondissent **au plancher, donc en faveur du défenseur**. Cette contrainte n'est pas stylistique : un flottant ici casserait la parité serveur / moteur embarqué exigée par EX-J0-01.

**Ce que cette règle change.** `takeDamage()` vidait le bouclier avant les PV sans atténuation, par un `min(bouclier, dégâts)` : **1 point de bouclier absorbait 1 point de brûlure, pas davantage.** La règle ne débloque donc pas une brûlure annulée, elle la **redistribue** : davantage sur le bouclier, jusqu'à 150 %, moins sur les PV nus, 70 %. Sur 10 stacks contre 8 de bouclier, elle inflige même **un PV de plus** qu'avant. La décision « le poison ignore, la brûlure n'ignore pas » (§3.1 de `07`) ne tranchait pas *atténue ou annule* ; le code avait répondu sans que la question soit posée. **Affirmation corrigée le 14/09/2026** : la rédaction antérieure écrivait que 1 point de bouclier annulait intégralement la brûlure du tick, ce que le `min()` contredit.

#### Le soin nettoie — TRANCHÉ (D-21, 13/09/2026)

Un déclenchement de l'action `HEAL` retire **1 stack de `POISON` et 1 stack de `BURN`**. `REGEN` et `WARD` ne sont jamais touchés : le nettoyage ne vise que les statuts hostiles.

Quatre règles de détail, qui n'existent que parce que le modèle est par instances :

1. **Le retrait porte sur l'instance à la plus longue durée restante**, égalité départagée par l'ordre d'insertion. Retirer d'abord une instance sur le point d'expirer seule n'aurait aucun effet mesurable.
2. **Le nettoyage se calcule sur le soin tenté, pas sur le soin réalisé.** `receiveHeal()` plafonne à `baseHp` : sur le soin réalisé, un Vestige à pleine vie ne pourrait jamais se nettoyer.
3. **`REGEN` ne nettoie pas.** Le nettoyage est attaché à l'action `HEAL`, non à `receiveHeal()` — sinon `panacee` nettoierait à chaque tick de sa régénération.
4. **Une instance vidée de ses stacks est retirée**, même si son compteur de ticks n'est pas à zéro.

**Effet recherché.** `07` §4.1 relève qu'à cadence et valeur égales, un objet de bouclier vaut plus qu'un objet de soin sur combat long. Le nettoyage donne au soin un second rôle et **inverse le classement par cadence** : `mercurocroum` (cd 16) devient le meilleur nettoyeur, `panacee` (cd 40) le meilleur restaurateur. Deux axes au lieu d'un. C'est le rôle que The Bazaar donne à son soin, et ce qui y justifie son débit brut inférieur.

**Pourquoi un retrait fixe et non un pourcentage.** À l'échelle des valeurs du jeu, `intdiv(10 * 5, 100) = 0` : `mercurocroum` ne nettoierait jamais rien. Le retrait fixe est lisible, échelonne correctement, et reste en arithmétique entière.

**Dissonance assumée.** Végétal est primaire `Heal`, Terre est secondaire `Poison`, et le cycle des affinités les donne **alliés** à distance 1 (`corebound-affinities` §1). Le nettoyage crée donc un contre mécanique entre deux affinités que le cycle déclare amies. Bonus de stats et contre-jeu sont deux axes distincts et n'ont pas à coïncider — la dissonance est **acceptée**, et consignée ici pour ne pas être redécouverte en playtest comme un défaut.

#### État du code

> **~~⚠ ÉCART 4~~ — IMPLÉMENTÉ, résorbé le 14/09/2026. Le texte ci-dessous décrit l'état antérieur.**
>
> L'implémentation actuelle est le monolithe. `CombatVestige` indexe ses statuts **sur le seul type**, et `ActiveStatus::mergeWith()` fait `stacks +=` et `remainingTicks = max(...)`. Une réapplication avant expiration remet le compteur à plein et empile sans limite.
>
> **Quatre des huit objets à statut sont dans ce cas** : `nightfang` (cd 10 / durée 30), `shadow_armor` (18 / 30), `venomous_vial` (20 / 30), `shadow_venomous_vial` (20 / 30). Les quatre autres sont bornés parce que leur cooldown atteint ou dépasse la durée — ce qui tient à **un tick près** pour `firesteel` et `molotov_cocktail`, et dépend de l'ordre des phases de `Simulator::run()` : `removeExpiredStatuses()` s'y exécute avant que `ActionProcessor` ne recrée le statut. Vérifié le 13/09/2026 sur le code. Leur sécurité est un effet de bord de cet ordre, pas une propriété du design.
>
> **L'impact mesuré était concentré sur `WARD`.** Sur les objets offensifs l'emballement ne coûtait que 6 ticks, les combats se terminant avant. Sur `REGEN` il saturait contre le plafond de `baseHp`. Sur `WARD`, rien ne le saturait, le bouclier étant sans plafond **par conception** (§2.4).
>
> *Valeurs définitives, mesurées le 14/09/2026 sur le moteur :* `shadow_armor` produisait **7 155** de bouclier sur 500 ticks sous le modèle à fusion, et en produit **1 253** sous le modèle par instances. La composante `GAIN_SHIELD` directe pèse **459** dans les deux cas — ce n'est pas le `WARD` seul. Les estimations antérieures de 1 415 et 1 405 décrivaient le **plafond à 2 stacks de D-13, périmée**, et non D-20 : sous un plafond les stacks atteints ne redescendent jamais, alors que les instances expirent et que la moyenne vaut `durée / cooldown` = 1,67 stack par tick. D-20 est donc plus conservateur de 11 % que la solution qu'il remplace. Valeur de référence consignée par `SimulatorTest::testShadowArmorProducesABoundedReferenceShieldOverFiveHundredTicks`.
>
> Traité par `07` chantier 3b, points 1 et 2. `CombatVestige` porte une liste d'instances indépendantes par type, `ActiveStatus::mergeWith()` a disparu, et la projection exposée aux `CombatEvent` est portée par `AggregatedStatus`.

**Dette connue, étendue en révision 2.0, deux tiers résorbée le 14/09/2026.** Trois éléments morts, pas un.

- `Trigger` n'est lu nulle part : `dispatchForItem()` balaie tous les listeners en ignorant les clés. `ON_ATTACK` et `EVERY_N_TICKS` sont donc fonctionnellement identiques, seul `cooldownTicks` pilote la cadence. **Toujours ouvert** : retirer l'enum amputerait une intention de design, c'est une décision et non un nettoyage. Renvoyé au chantier 3.
- ~~`Effect::intervalTicks` est sérialisé vers le frontend et n'est renseigné par **aucun** des 30 objets.~~ **Retiré** : cinq écritures, zéro lecture. `EffectDTO` perd le champ.
- ~~`EventDispatcher::dispatch()` et `getListenersFor()` n'ont aucun appelant en production.~~ **Retirés**, et `register()` passe en privé. La classe tombe à deux méthodes publiques, `registerBoard()` et `dispatchForItem()`, qui sont exactement les deux appelées en production.
---

## 8. Interface — plateau de jeu

D'après le croquis de référence (`board_idea.pdf`) :

```
┌────────────────────────────────────────────────────────────┐
│  ┌──────────────┐              ┌──────────────────────┐    │
│  │ Liste        │              │  Bloc héros 2        │    │
│  │ passifs      │              │  [objets] Compétence │    │
│  │              │              └──────────────────────┘    │
│  └──────────────┘                                          │
│  ┌──────────┐   ┌──────┐       ┌──────────────────────┐    │
│  │ VESTIGE  │   │ HP / │       │  Bloc héros 1        │    │
│  │ affinité │   │shield│       │  [objets] Compétence │    │
│  └──────────┘   │status│       └──────────────────────┘    │
│  ┌──────────────┐│      │      ┌──────────────────────┐    │
│  │ Avancement   ││      │      │  Bloc héros 3        │    │
│  │ Or / income  │└──────┘      │  [objets] Compétence │    │
│  └──────────────┘              └──────────────────────┘    │
└────────────────────────────────────────────────────────────┘
```

**Règles de lecture :**
- Un héros peut tenir **1 objet à 2 mains ou 2 objets à 1 main**.
- Un objet est une **arme, un objet ou un bouclier**.
- La barre centrale HP / bouclier / statuts appartient au **Vestige**, pas aux héros.
- Le Vestige est choisi au début de la run et définit l'affinité.
- Le panneau « Avancement » affiche l'or total et l'income.

**Conséquence de §7.4 sur l'affichage du bouclier.** Le bouclier n'ayant aucun plafond, sa valeur peut atteindre plusieurs milliers en fin de combat long. Une jauge proportionnelle aux PV maximum devient illisible dans ce cas. À traiter au moment de la mise en œuvre : valeur numérique, ou jauge à échelle adaptative.

**Direction artistique :** voir `corebound-art-style-guide-fr.md`. Points structurants : base froide désaturée avec un seul accent saturé par affinité, rareté portée uniquement par une aura CSS, hiérarchie de cadres à trois identités (Vestige = fils tressés ; héros = pierre/métal ornés avec fil partiel si affinité non neutre ; objets = matériau neutre, le motif de fil étant strictement réservé au vivant). Formats fixes : héros en 3:4 ou 4:5, objets et Vestige en 1:1.

---

## 9. Mécaniques écartées

Conservées avec leur justification, pour ne pas les redécouvrir.

| Mécanique | Motif du refus |
|---|---|
| Conversion d'affinité adverse (sabotage) | Écartée par analyse de fun. `SetAffinity` reste un placeholder pour la conversion de sa propre affinité uniquement |
| `GAIN_GOLD` / `GAIN_MANA` comme actions de combat | Aucun des 30 objets ne les utilisait — erreur du cahier des charges initial, pas une mécanique V2+. Confirmé : la compétence économique retenue (`AURIC`) agit à l'assemblage du plateau, pas en combat |
| Revente d'objets en V1 | Hors périmètre ; revient en cible via les marchands spécialisés |
| Typage d'objets par tags (`weapon`, `melee`…) | Besoin exercé une seule fois — YAGNI |
| Pondération de rareté de l'adversaire scripté | Faute de données de playtesting |
| Rang Diamant en fusion | Écarté — prototyper 3 rangs avant d'en ajouter un quatrième |
| Grille spatiale d'adjacence | Écartée : le couple d'objets d'un héros est déjà la maille combinatoire naturelle, le budget de slots étant individuel (§2.2) |

---

## 10. Index des écarts entre règle décrite et règle exécutée

Consolidé pour lecture rapide. Chaque entrée est développée dans sa section.

| # | Écart | Section | Traité par |
|---|---|---|---|
| 1 | Le double KO est atteignable ; deux gardes de vie manquent | §7.2 | `07` chantiers 0 et 2 |
| 2 | L'ordre des plateaux décide des morts simultanées, dans deux sens opposés | §7.2 | `07` chantier 2, décision D-14 |
| 3 | L'enrage handicape le joueur d'environ ×1,5 | §7.3 | `07` chantier 2, décision D-14 |
| 4 | ~~Le moteur fusionne les statuts par type et n'en borne pas les stacks~~ — **résorbé le 14/09/2026**, modèle par instances (D-20) implémenté | §7.4 | `07` chantier 3b, points 1 et 2 |
| 5 | ~~`SUNDERING` pénalise `scutum` et `shadow_scutum` sans contrepartie~~ — **résorbé le 14/09/2026** | §2.3 | `07` chantier 3b, point 4 |
| 6 | L'adversaire scripté est aux deux tiers inerte, sa rampe n'est pas monotone | §2.6 | `07` chantier 10 |
| 7 | `baseShield` a une valeur par défaut silencieuse malgré la règle fail-fast | §2.1 | à arbitrer |

**Ce que cet index n'est pas.** Une liste de bugs à corriger dans l'ordre. Trois de ces écarts (1, 2, 3) appellent d'abord une **décision de règle**, pas un correctif : il faut choisir ce que le jeu doit faire avant de changer ce qu'il fait. L'ordre d'exécution est fixé par `07`, pas ici.