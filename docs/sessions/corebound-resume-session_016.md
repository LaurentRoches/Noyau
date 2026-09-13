# Corebound / Projet Noyau — Résumé de session 016

## Contexte de départ

Pivot volontaire hors-code, motivé par le prototype validé en session 015 : prendre le temps de construire un socle créatif commun (lore, direction artistique, guide de génération d'assets) avant de reprendre `feature/frontend-visual-structure`. Aucune ligne de code touchée de toute la session.

## Chantier 1 — Bible de lore

Construction collaborative en page blanche, verrouillée brique par brique :
- **Le Vide** : hybride 75/25 entre cicatrice d'un effondrement cosmique ancien et perte ressentie par chaque être vivant. Nommé **la Tressure** (le tout perdu) / **l'Effilochement** (l'événement). Vécu comme un bruit de fond mélancolique banal pour tous ; vérité historique totalement perdue sous des dogmes contradictoires.
- **Le Vestige** : fragment vivant de la Tressure, littéralement fait de fil, forme instable. Le lien Vestige-porteur est directement branché sur la structure de run roguelike : le joueur incarne la conscience résiduelle du Vestige, chaque run est une tentative avec un candidat du roster (résonance directe avec le tirage de 3 héros existant), une défaite effiloche le lien, 3 défaites cumulées le rompent, 10 victoires prouvent qu'un point de fixation stable a été recréé.
- **Manifestation** : marque littérale (fils visibles sous la peau) à l'origine du vocabulaire employé pour tout le reste — le langage naît de l'observation, pas l'inverse. N'importe qui peut être choisi ; le tempérament détermine quelle affinité résonne, la neutralité étant elle-même un trait de résistance.
- **Perception sociale** : mélange religion/politique, variable par région — racine narrative actée pour le futur système de synergies d'affinité (V2+).
- **Ton** : sombre et froid, mélancolique plutôt que tragique ; crudité non déterminante (lore fonctionnel, pas narratif).
- **Déclinaison Ombre** (gabarit pour toute affinité future) : le fil dissimule plutôt qu'il ne relie ; signature mécanique révisée en session (accélération de cooldown + critiques, le poison étant une mécanique commune à toutes les affinités, pas une exclusivité Ombre).

Livrable : `corebound-lore-bible.md`.

## Chantier 2 — Guide de direction artistique (FR/EN)

Analyse de références visuelles fournies par l'utilisateur (cartes de personnages type gacha, plateau de shop d'un autre jeu) pour en extraire une grammaire réutilisable plutôt que copier une palette telle quelle.

Décisions verrouillées :
- Direction ouvragée/atmosphérique — le sobre actuel de `style.css` acté comme artefact de prototypage, à réviser séparément.
- Base froide désaturée partout + un seul accent saturé par affinité.
- **Rareté** portée uniquement par l'aura CSS (`box-shadow`, tokens `--common`/`--rare`/`--legendary` déjà existants) — jamais peinte dans l'illustration.
- **Affinité** portée uniquement par l'illustration (couleur d'accent + traitement) — jamais par le cadre.
- Hiérarchie de cadres à trois identités : Vestige (cadre fait de fils noués), héros (pierre/métal ouvragé + fils partiels si affinité non neutre), items (matière neutre, jamais de fil).
- Motif fil/couture strictement réservé au vivant (Vestige + héros), jamais sur les items — l'affinité d'un objet se lit uniquement par sa couleur d'accent.
- Formats techniques : héros en portrait 3:4/4:5 (ratio fixe, cadrage libre à l'intérieur), items et Vestige en carré 1:1.
- Gabarit Ombre complet (couleur d'accent, comportement du fil, manifestations porteur/adversaire) + un **fragment de style réutilisable** (technique/rendu seul, sans sujet/pose/tenue), affiné en pratique sur plusieurs générations réelles (retrait des éléments de tenue, correction d'une référence trop scénique/saturée en gardant uniquement sa technique d'énergie).
- Référence de mouvement/forme du Vestige verrouillée à partir d'un GIF externe (anneaux entrelacés en rotation perpétuelle), en adaptant matière (fil texturé mat, pas de rendu verre/chrome) et couleur (base neutre + accent d'affinité, pas de saturation uniforme).

Point laissé volontairement ouvert en cours de session (marques de couture permanentes vs discrètes) — tranché en fin de session : rendu IA actuel conservé tel quel, décision définitive différée à l'intervention d'un illustrateur professionnel.

Livrables : `corebound-art-style-guide-fr.md`, `corebound-art-style-guide-en.md`.

## Chantier 3 — Prompts de génération pour les items

Fragments de style réutilisables (neutre / Ombre) + description courte par objet pour les 30 items de `items.json`, sur le même principe modulaire que le fragment héros — évite de réécrire un prompt complet et redondant à chaque objet.

Méthode actée pour les familles d'objets partageant un type (ex. Dagger → Shadow Dagger → Nightfang) : générer la version commune en premier, la joindre en référence pour les variantes rare/légendaire, pour garantir une reconnaissance visuelle immédiate entre versions d'un même objet plutôt que des objets sans lien visuel apparent.

Livrable : `corebound-item-render-prompts.md`.

## Chantier 4 — Génération réelle des assets

- **Vestige** : vidéo en boucle continue (structure d'anneaux entrelacés, jamais de pose figée) générée via Dreamina/Seedance à partir du prompt du guide, + poster statique pour l'attribut `poster` de la balise `<video>`.
- **Plateau/hub** : vue du dessus, symétrie horizontale stricte, zone de bataille centrale entièrement vide (aucun asset intégré — héros/items/Vestige/coffre seront superposés séparément). Angle en légère plongée (non parfaitement orthographique) conservé tel quel pour la V1 ; positionnement des overlays acté à l'œil plutôt qu'en grille stricte.
- **Stash** : ouvert et fermé (coffre en pierre/fer ouvragé) — cohérence entre les deux états garantie en générant d'abord l'état fermé puis en le joignant en référence pour l'état ouvert.
- **Cadres** : héros (neutre + Ombre), item, Vestige.
- Tous les héros et tous les items du catalogue V1 générés.

**Incident méthodologique noté en session** : une tentative de génération du plateau avec l'image de référence jointe a produit un simple ravalement de couleur de l'UI existante (libellés, chiffres, disposition identique) plutôt qu'une nouvelle composition inspirée — l'outil (Gemini) a traité l'image jointe comme un point de départ à éditer, pas comme une inspiration de structure. Corrigé en repartant du texte seul, sans image jointe, pour ce type de génération.

**Rangement acté** : `frontend/public/assets/{heroes,items,vestige,stash,board}/`, nom de fichier = id exact de `items.json`/`heroes.json`. Choix de `public/` plutôt que `src/assets/` : contrainte Vite — les assets sont résolus dynamiquement par id (`/assets/items/${item.id}.png`), pas importés statiquement un par un.

## Chantier 5 — Sound design : SFX et musique de hub

Discussion préalable sur la pertinence du moment (chantier optionnel plutôt qu'obligatoire) et arbitrage explicite : scope volontairement minimal pour la V1 (quelques SFX clés + une seule musique de hub, pas d'identité sonore complète), cohérent avec le YAGNI déjà appliqué au reste du projet.

- **Recherche d'outils IA gratuits** (web) : trois candidats retenus — ElevenLabs Sound Effects (spécialiste SFX court ; conditions du tier gratuit contradictoires selon les sources, à vérifier directement à l'inscription), Stable Audio (musique + SFX, licence personnelle gratuite), MusicFX/Lyria 3 (musique uniquement, gratuit, intégré à l'app Gemini déjà utilisée pour les visuels).
- **Prompts rédigés** pour 6 SFX de gameplay prioritaires (dégât d'arme, gain de bouclier, soin, poison, brûlure, coffre) + 3 SFX de confort ajoutés proactivement (achat boutique, victoire/défaite de manche, survol/clic d'interface) — même registre que la DA visuelle (sourd, retenu, jamais épique/grandiloquent).
- **Ajustements techniques actés sur l'interface ElevenLabs** : durée fixée via le contrôle dédié plutôt que dans le texte du prompt, bouclage désactivé pour les SFX ponctuels, clause anti-traîne de réverbération ajoutée systématiquement (`dry mix, clean cutoff, no lingering reverb tail, mono`) — nécessaire pour éviter la superposition audible de sons qui se chevauchent en combat (statuts à tick répété, ex. plusieurs stacks de Poison).
- **Formats tranchés** : **wav** pour tous les SFX courts (pas de compression avec perte, pas de padding de silence parasite — important sur du répétitif/réactif comme les ticks de statut) ; **ogg** pour la musique de hub (boucle longue : poids nettement inférieur au wav, pas de padding de boucle contrairement au mp3, format ouvert).
- **Branche ouverte** : `feature/frontend-audio-structure`, en miroir direct de `feature/frontend-visual-structure`.
- **Arborescence actée** : `frontend/public/assets/audio/{sfx,music}/` — nom de fichier = **type d'action/statut/événement** (`weapon_hit`, `shield_gain`, `heal`, `poison_tick`, `burn_tick`, `chest_open`, `chest_close`, `shop_purchase`, `round_victory`, `round_defeat`, `ui_click`, `hover`) plutôt qu'id d'item, puisqu'un même son sert à tous les objets déclenchant la même action, indépendamment de l'arme/objet précis qui la déclenche.
- **Génération réelle terminée** : les 12 SFX + `hub_ambiance.ogg` générés et rangés dans l'arborescence actée (brouillons intermédiaires identifiés et supprimés avant commit).

## État final

- Chantier lore/DA visuel complet : bible de lore, guide bilingue de direction artistique, guide de prompts pour les 30 items, tous les assets visuels V1 générés et rangés sous `frontend/public/assets/`.
- Chantier sound design complet pour le scope V1 acté : 12 SFX + musique de hub générés et rangés sous `frontend/public/assets/audio/`, sur la branche `feature/frontend-audio-structure`.
- Aucun changement côté moteur de simulation, API, ou composants Vue — session entièrement hors-code (visuel et audio).
- `README.md` régénéré pour documenter le socle lore/DA (`docs/lore/`, `docs/art/`) et l'arborescence des assets visuels ; la documentation de l'arborescence audio reste à intégrer au README dans une prochaine passe.

## Prochaine étape — non tranchée

Intégration effective des assets (visuels **et** audio) dans les composants Vue — non démarrée cette session, aucune ligne de code touchée :
1. Visuels : résolution de chemin par id, superposition des cadres, aura CSS de rareté par-dessus, balise `<video poster>` pour le Vestige — sur `feature/frontend-visual-structure`.
2. Audio : composable de résolution (`ActionType`/`StatusType`/événement → chemin de fichier), déclenchement synchronisé avec les events du combat log, lecture en boucle de la musique de hub — sur `feature/frontend-audio-structure`.

Les deux branches sont ouvertes et alimentées en assets, mais aucune des deux intégrations n'a démarré — à ouvrir lors d'une prochaine session dédiée au code.