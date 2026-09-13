# Corebound — Guide de direction artistique
## Génération d'assets IA (v2)

> Document de référence pour toute génération d'illustration (héros, objets, Vestige). S'appuie sur `corebound-lore-bible.md` — en cas de doute sur une intention, la bible de lore fait autorité.
> **Les sept déclinaisons d'affinité (couleurs, matières, fragments de prompt) vivent dans `corebound-affinities.md`.** Ce guide ne contient que la grammaire générale, commune à toutes les affinités.
> Ce guide couvre le contenu illustré uniquement ; l'UI/interface (cadres CSS, layout) reste un chantier séparé.

---

## 1. Direction générale

- **Registre** : ouvragé et atmosphérique — peinture semi-réaliste texturée, jamais vectoriel plat, jamais cartoon.
- **Niveau de détail** : modéré. Assez riche pour supporter l'inspection, assez lisible pour fonctionner en petit format de vignette. Silhouettes fortes avant tout — un détail qui casse la lisibilité en petit format doit être simplifié.
- **Température de couleur de base** : froide et désaturée, partout, sans exception. C'est la baseline du monde entier — cohérent avec un univers marqué par un manque diffus (cf. lore, section 1).
- **Lumière** : clair-obscur marqué. Une source dramatique, des ombres profondes, peu de remplissage. Jamais un éclairage plat ou uniforme.
- **Fond** : une couleur ambiante avec une légère atmosphère (brume, dégradé, lueur diffuse) — jamais un aplat uni, jamais un décor narratif complet. **Aucun sol, aucun plancher, aucune ombre portée au sol.**

---

## 2. Grammaire visuelle : rareté vs affinité

Deux systèmes d'information coexistent sur chaque carte. L'invariant à ne jamais casser :

> **Rareté et affinité ne partagent jamais le même support.**

| Information | Portée par |
|---|---|
| **Rareté** (Commun/Rare/Légendaire) | Aura lumineuse (`box-shadow`, tokens `--common`/`--rare`/`--legendary`) — **seule sur ce support**, traitement d'interface, jamais peinte |
| **Affinité** | Deux supports : l'**illustration** (accent coloré pour les héros, matière constitutive pour les items) **et le cadre** (couleur du fil noué) |

**Conséquence pour la génération** : une illustration ne représente jamais la rareté. L'IA ne génère que l'affinité, et jamais le cadre.

---

## 3. Hiérarchie des cadres

Le cadre est une **image séparée, superposée par l'application**. Il n'est jamais généré avec le héros ou l'objet : une illustration ne contient donc jamais de cadre, de bordure ni d'encadrement.

Trois identités de cadre distinctes, jamais interchangeables :

- **Vestige** : le cadre *est* fait de fils — noués, tissés, presque organiques. Pas de pierre, pas de métal. C'est la seule entité dont le cadre est littéralement composé de la matière du lore (la Tressure).
- **Héros** : cadre en pierre ou métal sombre ouvragé (esprit gothique). Les héros d'affinité non neutre portent en plus un **nœud de fil dans l'angle supérieur droit, coloré selon l'affinité**. Le héros neutre n'a pas de nœud — sa famille se lit par le manque.
- **Items** : cadre en matière neutre et plus modeste (métal terni/bruni sombre), jamais de fil, quelle que soit l'affinité de l'objet.

---

## 4. Motif fil/couture — règles d'application

- **Réservé au vivant** : Vestige et héros uniquement. **Jamais sur les items**, même à affinité.
- **Vestige** : le fil constitue le cadre en totalité.
- **Héros à affinité non neutre** : le fil apparaît (a) sur le cadre, coloré par l'affinité, (b) dans l'illustration sous forme de coutures.
- **Les coutures sont exclusivement sur la chair exposée** — jamais sur une armure, un cuir, une sangle ou un vêtement. Cette règle a été posée après constat d'écart sur les premières générations.
- **Héros neutre** : pas de fil, ni sur le cadre ni dans l'illustration — la neutralité est justement l'absence de résonance visible.
- **Items** : l'affinité n'est **plus** portée par une couleur d'accent mais par la **matière constitutive de l'objet** (fumée pour l'Ombre, eau pour l'Eau, terre pour la Terre…). Révision assumée de l'approche v1, dont la §4 prévoyait explicitement la possibilité. Voir `corebound-affinities.md` §3 pour la table complète.

---

## 5. Composition

- **Héros** : cadrage variable selon la pose et l'identité du personnage — buste resserré ou plan large, au cas par cas. Pas de règle rigide, le jugement prime.
- **Items** : toujours l'objet seul, présenté de face, sur le fond ambiant. Pas de mise en situation (pas de main qui tient l'objet, pas de surface sur laquelle il repose).
- **Vestige** : structure d'anneaux/boucles entrelacées, glissant continuellement les unes à travers les autres, jamais de pose figée ni d'état stable. Fil texturé et mat (pas de rendu verre/chrome brillant), base neutre grise avec l'accent d'affinité qui vient s'y poser.

### Zone morte imposée par le cadre

Mesures relevées sur les fichiers de cadre réels (896 × 1200, ratio 0,747) :

| | Valeur |
|---|---|
| Surface d'illustration visible | 79,6 % de la carte |
| Bord recouvert | 4,6 % à gauche/droite · 3,2 % en haut · 3,6 % en bas |
| Emprise du nœud de fil | x 64,6 % → 94,5 % · y 3,7 % → 28,6 % |

> **Le quart supérieur droit (≈ 30 % × 29 %) doit rester libre de tout élément signifiant.** Armes levées, têtes et mains se placent à gauche ou au centre.

Prévoir en plus une marge périphérique de ~5 % latéralement et ~3,5 % en haut/bas.

### Porteurs de l'accent (héros)

L'accent au seul point d'impact ne fonctionne que sur une pose en action — un héros immobile n'a pas de point d'impact, donc pas d'accent. Deux porteurs sont donc admis :

1. **Pose active** : traînée d'énergie au point de contact.
2. **Pose passive** : lueur sous-cutanée, regard, ou arête d'une arme rangée.

---

## 6. Déclinaisons d'affinité

**Voir `corebound-affinities.md`.** Ce fichier est la source unique : cycle et relations, stats de plateau, accents et comportements de lumière, matières d'items, fragments de prompt réutilisables.

Chaque déclinaison y documente, dans cet ordre : identité (id, primaire/secondaire, couleur d'interface) · accent d'illustration (teinte + comportement de la lumière) · le fil (action, manifestation sur le porteur, manifestation sur l'adversaire) · matière des items · fragment de style héros · fragment matière item.

Les fragments de style ne décrivent jamais le sujet, la pose ou la tenue — uniquement la grammaire graphique — pour rester combinables avec n'importe quelle description de héros.

---

## 7. Structure de prompt recommandée

1. Sujet + rôle (héros/item/Vestige) + pose ou présentation
2. Palette : base froide désaturée + accent d'affinité (héros) ou matière d'affinité (item)
3. Éclairage : clair-obscur, source unique, contrastes forts
4. Fond : couleur ambiante avec légère atmosphère, sans décor narratif
5. Marqueurs du fil (si applicable) : sur la chair exposée uniquement
6. Composition : quart supérieur droit laissé libre
7. Exclusions : `no ground, no floor, no cast shadow on ground, no environment, no architecture, no colored sky, no frame, no border, no flat lighting, no cartoon rendering, no text, no watermark`

---

## 8. Cohérence entre générations

- Utiliser une **image d'ancrage** par affinité (une génération validée servant de référence stylistique pour les suivantes de la même famille), pour limiter la dérive visuelle entre appels IA successifs.
- **Familles d'objets** : générer d'abord la version commune, puis la joindre en référence pour les variantes. Depuis le passage à la matière constitutive, la référence verrouille la **forme** et non le rendu — la parenté entre `Dagger` et `Shadow Dagger` se lit à la silhouette, la matière étant volontairement différente.
- Une **passe de post-traitement scriptée** (désaturation, grading, contraste uniforme) reste envisagée. À concevoir une fois qu'un lot d'assets réels existe — ne pas sur-anticiper.

---

## 9. Formats techniques par type d'asset

| Type | Ratio | Notes |
|---|---|---|
| **Héros** | Portrait 3:4 | Ratio fixe, sans exception — confirmé par les fichiers de cadre (0,747). Le cadrage (buste serré/plan large) peut varier à l'intérieur de ce ratio. |
| **Items** | Carré 1:1 | Objet seul, présenté de face. |
| **Vestige** | Carré 1:1 | Structure centrée et symétrique, sans orientation haut/bas ou avant/arrière fixe. |

**Marques de couture — point tranché.** Le lore décrit un fil « presque invisible, qui apparaît à l'instant du coup », alors que les générations montrent des marques permanentes. Décision de fin de session 016, reportée ici : **le rendu actuel est conservé tel quel**, la marque permanente étant acceptée comme signe distinctif du porteur. La décision définitive est différée à l'intervention d'un illustrateur professionnel.

---

- Ce guide est un point de départ, pas un contrat figé : à corriger dès qu'un écart entre l'intention et le résultat généré apparaît.
- La direction UI actuelle (`style.css`, tokens sobres) ne reflète pas encore cette DA — chantier distinct, à traiter plus tard.
