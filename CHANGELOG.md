# Changelog

## [1.0.0] — V1 : prototype jouable complet

Première version stable de **Corebound**, jouable de bout en bout : un run complet (Vestige fixe, héros tirés, boutique, combats contre une IA scriptée, condition de victoire/défaite) fonctionne à l'écran avec habillage visuel et sonore.

### Moteur de simulation (backend, PHP 8.3)

- Boucle de combat au tick (`TickEngine` → `EventDispatcher` → `ActionProcessor`), séparation stricte définition (readonly : `Hero`, `Item`, `Effect`, `Action`) / runtime (mutable : `CombatHero`, `CombatItem`, `CombatBoard`).
- Résolution de cibles abstraite (`Target::SELF/ENEMY/ALL_ENEMIES/ALL_ALLIES`), garde-fou "pas de coup sur un cadavre" (simultanéité de mort à double KO structurellement impossible).
- `Simulator` orchestrateur (contrat `run(playerBoard, opponentBoard, randomizer): SimulationResult`), déterminisme complet via `Randomizer` à seed fixe.
- Statuts à tick (poison, brûlure, régénération, bouclier magique/ward) et processeurs dédiés (`StatusProcessor`, `EnrageProcessor`).
- Roster de héros multiples : tirage pondéré par affinité (`HeroRosterFactory`), inventaire par héros avec suivi de budget de slots (`Inventory`, `AssignedItem`, `HeroItemAllocator`), coffre partagé (`Stash`) avec échange objet ↔ héros.
- Boutique (`ShopFactory`, `Shop`, `ShopOffer`, `Wallet`) et adversaire scripté par manche, budget croissant (`ScriptedOpponentFactory`).
- Condition de fin de run verrouillée : 10 victoires / 3 défaites.
- Persistance par journal d'actions rejouable (SQLite), sans sérialisation d'objets.

### Interface (frontend, Vue.js 3 / TypeScript / Pinia)

- Boucle de jeu complète à l'écran : Vestige, roster de héros, boutique, coffre, log de combat, en layout 3 colonnes.
- Déroulé du combat en temps réel (`combatPlayback.ts`) : révélation du log tick par tick au rythme du moteur (100ms/tick), au lieu d'un affichage instantané ; état de jeu (manche/victoires/or) différé jusqu'à la fin de l'animation pour ne jamais spoiler l'issue.
- Log de combat coloré : segments numériques isolés par couleur, teinte dépendante du statut appliqué (poison, brûlure, régénération, ward).
- Intégration visuelle complète : illustrations + cadres par rareté et affinité sur héros/objets/Vestige, vidéo en boucle pour le Vestige, état ouvert/fermé du coffre selon son contenu.
- Intégration audio : SFX synchronisés sur les événements du combat révélés pendant le playback, musique de hub avec ducking à 40% pendant les combats, activation manuelle (contrainte navigateur sur l'autoplay).
- Échange d'objets héros ↔ coffre depuis l'interface.

### Fondations créatives

- Bible de lore complète : l'univers "Les Héritiers du Vide", le Vide (la Tressure / l'Effilochement), le Vestige et son lien avec la structure de run, la déclinaison Ombre comme gabarit pour les affinités futures.
- Guide de direction artistique bilingue (FR/EN) : grammaire visuelle par rareté (aura CSS) et par affinité (illustration), hiérarchie de cadres (Vestige/héros/objets), formats techniques verrouillés.
- Génération complète des assets visuels V1 (héros, objets, Vestige, coffre, cadres) et des assets sonores (12 SFX + musique de hub).

### Qualité

- Backend : 236 tests PHPUnit / 936 assertions, PHPStan niveau 6, PHP CS Fixer — tous verts.
- Frontend : 62 tests Vitest, ESLint / Prettier / vue-tsc — tous verts.
- CI GitHub Actions bloquante sur toutes les PR vers `dev`.

### Hors scope V1 (voir Roadmap V2+)

Snapshots PvP, marché de héros, doubles phases de boutique, choix du monstre adverse, multi-marchands, compétences de héros, effets mécaniques d'affinité, ordre d'activation/initiative entre objets, renouvellement du roster aux manches 3/5, sélection interactive du Vestige.
