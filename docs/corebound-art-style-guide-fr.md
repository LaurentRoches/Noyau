# Corebound — Guide de direction artistique
## Génération d'assets IA (v1)

> Document de référence pour toute génération d'illustration (héros, objets, Vestige). S'appuie sur `corebound-lore-bible.md` — en cas de doute sur une intention, la bible de lore fait autorité. Ce guide couvre le contenu illustré uniquement ; l'UI/interface (cadres CSS, layout) reste un chantier séparé, actuellement en prototypage.

---

## 1. Direction générale

- **Registre** : ouvragé et atmosphérique — peinture semi-réaliste texturée, jamais vectoriel plat, jamais cartoon.
- **Niveau de détail** : modéré. Assez riche pour supporter l'inspection, assez lisible pour fonctionner en petit format de vignette. Silhouettes fortes avant tout — un détail qui casse la lisibilité en petit format doit être simplifié.
- **Température de couleur de base** : froide et désaturée, partout, sans exception. C'est la baseline du monde entier — cohérent avec un univers marqué par un manque diffus (cf. lore, section 1).
- **Lumière** : clair-obscur marqué. Une source dramatique, des ombres profondes, peu de remplissage. Jamais un éclairage plat ou uniforme.
- **Fond** : une couleur ambiante avec une légère atmosphère (brume, dégradé, lueur diffuse) — jamais un aplat uni, jamais un décor narratif complet (pas de scène, pas d'architecture en arrière-plan en V1).

---

## 2. Grammaire visuelle : rareté vs affinité

Deux systèmes d'information coexistent sur chaque carte. Ils ne doivent jamais se porter sur le même support.

| Information | Portée par | Où |
|---|---|---|
| **Rareté** (Commun/Rare/Légendaire) | Aura lumineuse (`box-shadow`) | Autour du cadre — **hors illustration**, c'est un traitement d'interface, pas un élément à peindre |
| **Affinité** (Ombre, futures affinités) | Couleur d'accent + traitement visuel | **Dans l'illustration elle-même** — la seule touche saturée sur une base désaturée |

**Conséquence pour la génération** : une illustration ne doit jamais essayer de représenter la rareté. La rareté est un traitement ajouté après coup par l'interface. L'IA ne génère que l'affinité.

---

## 3. Hiérarchie des cadres

Trois identités de cadre distinctes, jamais interchangeables :

- **Vestige** : le cadre *est* fait de fils — noués, tissés, presque organiques. Pas de pierre, pas de métal. C'est la seule entité dont le cadre est littéralement composé de la matière du lore (la Tressure).
- **Héros** : cadre en pierre ou métal sombre ouvragé (esprit gothique). Les héros d'affinité non neutre ont **une partie du cadre en fils**, en plus du cadre de base.
- **Items** : cadre en matière neutre et plus modeste (métal terni/bruni sombre), jamais de fil, quelle que soit l'affinité de l'objet.

---

## 4. Motif fil/couture — règles d'application

- **Réservé au vivant** : Vestige et héros uniquement. **Jamais sur les items**, même à affinité.
- **Vestige** : le fil constitue le cadre en totalité.
- **Héros à affinité non neutre** : le fil apparaît (a) en partie sur le cadre, (b) potentiellement dans l'illustration elle-même (ex. points de couture visibles sur la peau pour l'Ombre — voir section 6).
- **Héros neutre** : pas de fil, ni sur le cadre ni dans l'illustration — la neutralité est justement l'absence de résonance visible.
- **Items à affinité** : l'affinité se lit uniquement par la couleur d'accent dans l'illustration. Si l'usage prouve que ce n'est pas assez lisible, une révision (icône dédié) reste possible — ne pas anticiper cette solution avant qu'elle soit prouvée nécessaire.

---

## 5. Composition

- **Héros** : cadrage variable selon la pose et l'identité du personnage — buste resserré ou plan large, au cas par cas. Pas de règle rigide, le jugement prime.
- **Items** : toujours l'objet seul, présenté de face, sur le fond ambiant. Pas de mise en situation (pas de main qui tient l'objet, pas de surface sur laquelle il repose) en V1.
- **Vestige** : structure d'anneaux/boucles entrelacées, glissant continuellement les unes à travers les autres, jamais de pose figée ni d'état stable — traduit à la fois l'instabilité du fragment et la nature du fil noué. Référence de mouvement/forme validée ; **la matière et la couleur restent à adapter** : fil texturé et mat (pas de rendu verre/chrome brillant), base neutre grise/désaturée avec l'accent d'affinité qui vient s'y poser (pas de couleur saturée sur toute la structure).

---

## 6. Déclinaison d'exemple — Affinité Ombre (`shadow_vestige`)

Ce gabarit sert de modèle pour documenter toute future affinité.

- **Couleur d'accent** : violet profond, presque noir, à reflet froid — une couleur qui absorbe la lumière plutôt qu'elle ne rayonne (à l'inverse d'un orange ou d'un vert qui illuminent la scène).
- **Comportement du fil** : ne relie pas, dissimule. Il coud pour cacher, jamais pour guérir.
- **Manifestation sur le porteur** : points de couture noirs et mouvants, presque invisibles, qui apparaissent sur la peau à l'instant du coup — comme un point tiré puis aussitôt effacé.
- **Manifestation sur l'adversaire/l'environnement** : un fil qui détricote plutôt qu'il ne coud — traduit visuellement par une corrosion ou un effilochement local de la matière/texture touchée.
- **Mots-clés de prompt suggérés** : *cold desaturated palette, deep near-black violet accent glow, dramatic single-source chiaroscuro lighting, semi-realistic painted texture, subtle moving black stitch-marks on skin, fabric fraying at the edges, moody ambient colored background, no flat lighting, no full scene background*

**Fragment de style réutilisable (technique/rendu uniquement, à combiner avec la description propre de chaque héros — sujet, pose, tenue) :**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of deep near-black violet light, appearing only as a sharp, vibrant energy trail (thin glowing lines, bright near-white core fading to violet) concentrated at the point of contact/impact — not spread across the whole scene. Dramatic single-source chiaroscuro lighting, deep shadows. Subtle black stitch-marks visible on exposed skin. Moody ambient grey background with light atmospheric mist only — no forest, no trees, no full environmental scene, no colored sky. No flat lighting, no text, no watermark.*

Ce fragment ne décrit jamais le sujet, la pose ou la tenue — uniquement la grammaire graphique (palette, traitement de l'énergie, lumière, fond), pour rester combinable avec n'importe quelle description de héros.

---

## 7. Structure de prompt recommandée

1. Sujet + rôle (héros/item/Vestige) + pose ou présentation
2. Palette : base froide désaturée + couleur d'accent d'affinité nommée explicitement
3. Éclairage : clair-obscur, source unique, contrastes forts
4. Fond : couleur ambiante avec légère atmosphère, sans décor narratif
5. Marqueurs du fil (si applicable) : description explicite de leur apparence et de leur emplacement
6. Exclusions : pas de texte, pas de watermark, pas de rendu plat/cartoon, pas de décor architectural complexe

---

## 8. Cohérence entre générations (à affiner en pratique)

- Utiliser une **image d'ancrage** par affinité (une génération validée servant de référence stylistique pour les suivantes de la même famille), pour limiter la dérive visuelle entre plusieurs appels IA successifs.
- Une **passe de post-traitement scriptée** (désaturation, grading, ajustement de contraste uniforme) est envisagée pour unifier des générations qui divergeraient légèrement en sortie brute. À concevoir une fois qu'un premier lot d'assets réels existe — ne pas sur-anticiper cette étape avant d'avoir constaté un besoin réel.

---

## 9. Formats techniques par type d'asset

| Type | Ratio | Notes |
|---|---|---|
| **Héros** | Portrait 3:4 ou 4:5 | Ratio fixe pour tous les héros, sans exception — nécessaire pour l'alignement en grille. Le cadrage (buste serré/plan large) peut varier au cas par cas à l'intérieur de ce ratio. |
| **Items** | Carré 1:1 | Objet seul, présenté de face (voir section 5). |
| **Vestige** | Carré 1:1 | Structure centrée et symétrique (voir section 6bis, référence de mouvement), sans orientation haut/bas ou avant/arrière fixe — le carré convient mieux qu'un portrait. |

Point ouvert à trancher lors des prochaines générations : les premiers essais de héros (Ombre) montrent des marques de couture visibles en permanence (cicatrices, fil barbelé sur l'armure), alors que le lore décrit un fil *"presque invisible, qui apparaît à l'instant du coup"*. À décider : marque permanente et visible (signe distinctif du porteur) ou marque atténuée/discrète pour rester fidèle à la description du lore.

---

- Ce guide est un point de départ, pas un contrat figé : à corriger dès qu'un écart entre l'intention et le résultat généré apparaît.
- La direction UI actuelle (`style.css`, tokens sobres) ne reflète pas encore cette DA — chantier distinct, à traiter plus tard.
