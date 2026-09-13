# 01 — Vision produit

**Autorité sur :** le pitch, le public, le positionnement, le modèle économique, les critères de succès.
**Révision :** 1.0 — 2 septembre 2026.

---

## 1. Identité du projet

| | |
|---|---|
| **Nom du jeu** | Corebound |
| **Nom de code interne** | Projet Noyau |
| **Univers** | Les Héritiers du Vide |
| **Artefact central** | le Vestige |
| **Genre** | Auto-battler roguelike, dark fantasy, PvE + PvP asynchrone |
| **Plateforme** | PC (Windows d'abord, macOS et Linux ensuite) |
| **Distribution** | Steam en Early Access, puis Steam 1.0, puis Epic Games Store |
| **Modèle** | Premium, achat unique, contenu post-lancement gratuit |
| **Équipe** | Développeur solo |
| **Financement** | Autofinancé, sans investisseur ni deadline externe |

---

## 2. Pitch

> **Corebound est un auto-battler roguelike dark fantasy où vous n'incarnez pas un héros, mais l'entité brisée qui les possède — et un jeu en ligne conçu dès le départ pour survivre à son propre serveur.**

**Formulation courte pour la capsule Steam :**
> *Vous n'êtes pas le héros. Vous êtes ce qui le possède.*

**Formulation longue (description Steam, premier paragraphe) :**
> Un Vestige est un fragment vivant de ce qui reliait autrefois tout ce qui vit. Il ne se bat pas — il choisit des porteurs. Recrutez trois héros, équipez-les d'objets qui s'activent seuls, affrontez des monstres pour vous enrichir, puis mesurez-vous au plateau figé d'un autre joueur. Dix nœuds tiennent : la tentative a réussi. Trois fils cèdent : elle est finie.

---

## 3. Intention de design

Trois principes qui doivent survivre à toutes les révisions.

**Le joueur décide avant, pas pendant.** Toute la profondeur est dans la construction du plateau. Le combat se résout seul, de manière déterministe et lisible. Aucune décision n'est prise pendant le combat, aucune n'exige de la dextérité.

**Le hasard propose, le joueur dispose.** Les offres de marchand, les monstres proposés et les héros offerts sont aléatoires. Ce qu'on en fait ne l'est pas. Un joueur qui perd doit pouvoir désigner sa propre décision fautive.

**Le lore justifie la mécanique, il ne l'habille pas.** La structure 10 victoires / 3 défaites n'est pas une règle arbitraire enrobée de fiction : c'est la cosmologie qui la produit. Toute nouvelle mécanique majeure doit pouvoir être expliquée dans le vocabulaire du fil, de la Tressure et de l'Effilochement — sinon, elle est probablement étrangère au jeu.

---

## 4. Public cible

**Cœur de cible.** Joueur PC, 25–45 ans, qui possède déjà Slay the Spire et Balatro, et a joué à au moins un auto-battler à objets. Il optimise des builds plutôt qu'il n'exécute des inputs. Il lit les tooltips, consulte les wikis, regarde des runs sur YouTube. Il achète entre 10 et 20 € sans hésiter, mais lit les avis récents avant.

**Deux sous-segments à ne pas négliger.**

- **Le joueur sensible à la direction artistique**, lassé du cartoon coloré qui domine le créneau. Le dark fantasy froid et désaturé est structurellement disponible.
- **Le joueur échaudé par les jeux-services morts**, pour qui la promesse de survie hors ligne est un argument d'achat à part entière. C'est le même public qui achète sur GOG.

**Hors cible, assumé.** Le joueur compétitif temps réel, le joueur qui cherche de l'action, le joueur mobile occasionnel. Corebound ne cherchera pas à les convertir.

---

## 5. Proposition de valeur — 5 éléments différenciants

1. **Le ton.** Dark fantasy froid, désaturé, mélancolique dans un créneau saturé de couleurs vives. Seul différenciateur visible sur une capsule Steam, donc le plus important commercialement.
2. **La promesse de survie hors ligne.** Corpus de snapshots anonymisés, versionnés, rejouables localement (voir `04` §5). Aucun concurrent ne peut faire cette promesse. C'est la proposition la plus originale et la plus défendable du projet.
3. **Le renversement narratif.** Le joueur est le Vestige, pas le porteur. Cela justifie diégétiquement la structure en runs au lieu de la subir.
4. **Le plateau multi-héros.** Trois héros avec compétences et budgets de slots individuels, contre un héros ou un sac unique chez les concurrents. Différence structurelle, pas cosmétique.
5. **Le déterminisme exposé au joueur.** Seed partageable, replay, comparaison de builds à conditions identiques. Outil pour les créateurs de contenu, matière première pour les théorycrafteurs.

---

## 6. Positionnement concurrentiel

**Concurrents directs :** The Bazaar (Tempo / AVY Games), Backpack Battles (PlayWithFurcifer).
**Concurrents indirects :** Super Auto Pets, Mechabellum, Megaloot, Dungeon Clawler, Legion TD 2.
**Références de marché :** Slay the Spire, Balatro, Monster Train.

**Règle de communication.** The Bazaar est une inspiration systémique assumée en interne. **Elle n'est jamais revendiquée publiquement.** Motif : c'est un cadre de comparaison perdant — le lecteur compare deux catalogues et deux budgets, pas deux idées. On décrit le genre en termes génériques (auto-battler roguelike, combats automatiques, construction de plateau) et on laisse le rapprochement se faire par les joueurs. Venant d'eux, c'est un compliment.

**Point de vigilance PI.** L'inspiration thématique vient de *La Voie des Ombres* (Night Angel, Brent Weeks) : artefact vivant lié à un porteur unique, thématique par faction. **Seule la structure mécanique est reprise. Jamais les noms, jamais les pouvoirs exacts, jamais les personnages.**

---

## 7. Modèle économique

**Retenu : premium seul, avec contenu post-lancement gratuit.**

| Décision | Valeur |
|---|---|
| Prix Early Access | **9,99 €** |
| Prix 1.0 | **14,99 €** |
| Palier intermédiaire EA | 12,99 € à mi-parcours si le contenu le justifie |
| Promo de lancement (EA et 1.0) | -10 %, 7 jours |
| Plancher de remise | -50 %, pas avant 18–24 mois |
| DLC | Aucun avant 12 mois **et** 10 000 copies. Ensuite : Vestige complet à 4,99 € maximum |
| Free-to-play | **Écarté définitivement** |
| Micro-transactions | **Écartées définitivement** |

**Justification du refus du F2P.** Il exige du live-ops, des saisons, un équilibrage continu, de l'anti-fraude, et une population de plus de 100 000 joueurs pour que 2 % de payeurs suffisent. Hors de portée d'un développeur solo. Le cas The Bazaar montre par ailleurs que dans ce genre, une monétisation mal perçue coûte plus cher qu'un défaut de gameplay.

**Justification du refus des micro-transactions.** Un joueur qui paie un jeu en ligne accepte moins bien les achats additionnels qu'un joueur de jeu solo — il a l'impression de payer deux fois. Le premium pur est le seul modèle qui n'expose pas ce reproche.

Détail complet en `05` §3.

---

## 8. Critères de succès

Trois niveaux, à distinguer clairement pour éviter de confondre déception et échec.

**Succès personnel — le seuil réel du projet.**
Corebound existe, il est fini, il est jouable, il est publié, et son mode hors ligne fonctionne. Ce seuil est atteignable indépendamment du marché.

**Succès commercial minimal.**
Couvrir le budget cash engagé. Selon le pipeline d'assets retenu (D-07), entre **265 et 3 240 copies**.

**Succès commercial réel.**
6 000 copies sur 24 mois, ~34 500 € de revenu avant impôts, 1 000 avis Steam franchis. C'est le scénario médian d'un bon jeu indé de niche.

**Ce qui ne doit pas être un critère.** Le nombre de joueurs simultanés. Backpack Battles a perdu 97 % de son pic tout en restant un succès financier majeur : dans ce genre, l'argent se fait à la vente et sur la longue traîne des soldes, pas sur la rétention.

---

## 9. Risques produit majeurs

Par ordre de gravité. Le détail technique est en `04`, le détail commercial en `05`.

1. **Sur-dimensionnement du périmètre.** 7 Vestiges, 40 héros et ~180 objets représentent ~227 assets illustrés, contre 41 aujourd'hui. Le risque numéro un n'est plus « sortir un jeu trop léger », c'est **ne jamais sortir**. Mitigation : le séquencement par jalons du cahier des charges (`03`).
2. **Coût d'illustration.** 9 000 à 28 000 € au tarif commissionné. Le seul poste capable de rendre le projet non rentable. Décision D-07.
3. **Équilibrage impossible en solo.** 40 héros × ~180 objets × 7 Vestiges dépasse ce qu'un seul testeur peut couvrir. Mitigation : l'Early Access, dont c'est la première justification, avant même la justification commerciale.
4. **Absence de hook énonçable en une phrase.** Testable dès maintenant en publiant la page Steam et en mesurant le taux de wishlist. Ne coûte pas une ligne de code.
5. **Dépendance serveur pendant toute la vie commerciale.** Une panne rend le jeu injouable. Mitigation partielle par le mode hors ligne.
6. **Base de snapshots vide au lancement.** Mitigation par les adversaires de secours ; décision D-05 sur leur transparence.

---

## 10. Ce que Corebound n'est pas

Écrit pour pouvoir refuser vite.

- **Pas un jeu compétitif temps réel.** Pas de ladder synchrone, pas de timers, pas de saisons classées.
- **Pas un jeu-service.** Pas de battle pass, pas de saisons payantes, pas de FOMO.
- **Pas un jeu narratif.** Le lore est une ossature de sens, pas un contenu à consommer. Aucun dialogue à embranchements, aucune quête.
- **Pas un jeu multijoueur social.** Pas de guildes, pas de chat, pas d'échanges entre joueurs.
- **Pas un jeu mobile.** L'architecture ne l'interdit pas ; le périmètre l'exclut.
