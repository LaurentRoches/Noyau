Voici le message que tu peux utiliser pour démarrer la nouvelle conversation :

---

## Contexte pour reprise — Création des 30 objets V1 (Corebound)

**Où on en est :** Le Walking Skeleton du moteur de simulation est terminé et vert (`Simulator`, `TickEngine`, `EventDispatcher`, `ActionProcessor`, `CombatHero`/`CombatItem`/`CombatBoard`), ainsi que la brique d'hydratation JSON (`JsonHeroRepository`, `JsonItemRepository`, `CombatBoardFactory`). Tests unitaires + E2E 100% verts, CS Fixer/PHPStan niveau 6/PHPUnit propres. Seuls 3 `ActionType` sont fonctionnellement traités par `ActionProcessor` aujourd'hui : `DEAL_DAMAGE`, `GAIN_SHIELD`, `HEAL`. Les autres (`APPLY_STATUS`, `GAIN_GOLD`, `GAIN_MANA`, `SET_AFFINITY`) existent dans l'enum `ActionType` mais tombent sur un `default => throw LogicException` si utilisés.

**Prochaine étape déclarée :** Rédiger les 30 objets réels de la V1 (thème assassin/ombre) pour peupler `config/game/items.json`, avant de passer à la boutique.

**Document apporté :** Un framework d'équilibrage générique (`guide_creation_item.md`) inspiré de *The Bazaar*/*Slay the Spire*/*Backpack Battles* — monnaie de puissance unifiée, budget par rareté, multiplicateurs de déclencheurs/conditions, importance du cooldown et des synergies. Bonne base théorique, mais pensée pour une équipe, pas un solo dev.

**Proposition de structuration perso :** Remplacer un doc monolithique par une **Game Design Bible en plusieurs fichiers Markdown** dans `docs/` (`01-CoreLoop.md`, `02-Combat.md`, `03-Items.md`, `04-StatusEffects.md`, `05-Heroes.md`, `06-Skills.md`, `07-BalanceFramework.md`, `08-Economy.md`, `09-Roadmap.md`) — plus léger, versionnable avec Git, plus facile à naviguer que remonter dans un document de centaines de pages au fil de la croissance du jeu.

**Question à trancher pour démarrer :**

Est-ce que cette structuration en plusieurs fichiers `docs/` est pertinente **maintenant** (alors qu'un seul fichier, `game-design-notes.md`, suffit encore aujourd'hui), ou est-ce une anticipation prématurée par rapport à la taille réelle du projet actuel — un peu comme on a écarté `SeededRng` ou l'interface Repository générique au nom du YAGNI ? Et si la structuration est jugée pertinente, comment migrer proprement le contenu déjà existant dans `game-design-notes.md` sans perdre les décisions déjà actées (Vestige, affinités, cahier des charges V1, etc.) ?