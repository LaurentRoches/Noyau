# Corebound — Annexe des affinités
## Source unique des sept déclinaisons (v1)

> Annexe de `corebound-art-style-guide-fr.md` / `-en.md` et de `corebound-lore-bible.md` §5.
> **Une information, un endroit** : les guides de DA ne contiennent que la grammaire générale et renvoient ici ; la bible de lore ne contient que le socle narratif commun. Les sept déclinaisons — narratif, couleur, matière, prompts — vivent dans ce fichier et nulle part ailleurs.
> Les fragments de prompt sont en anglais, comme dans le reste du projet.

---

## 1. Le cycle et les relations

Sept affinités disposées en cycle. La relation entre deux affinités se lit **uniquement par la distance sur ce cycle** — aucune case n'est décidée à la main, seul l'ordre du cycle est un choix.

Ordre : `Shadow → Fire → Gold → Metal → Water → Vegetal → Earth → (Shadow)`

| Distance | Relation | Effet |
|---|---|---|
| 0 (même affinité) | Identique | Bonus |
| 1 (voisins) | Alliés | Petit bonus |
| 2 | Neutres | Aucun effet |
| 3 (les plus éloignées) | Ennemis | Petit malus |

Sept étant impair, la répartition est identique pour chacune : 1 + 2 + 2 + 2 = 7. La table est symétrique par construction.

| Affinité | Alliés (d=1) | Neutres (d=2) | Ennemis (d=3) |
|---|---|---|---|
| Shadow | Fire, Earth | Gold, Vegetal | Metal, Water |
| Fire | Shadow, Gold | Earth, Metal | Water, Vegetal |
| Gold | Fire, Metal | Shadow, Water | Vegetal, Earth |
| Metal | Gold, Water | Fire, Vegetal | Shadow, Earth |
| Water | Metal, Vegetal | Gold, Earth | Shadow, Fire |
| Vegetal | Water, Earth | Shadow, Metal | Fire, Gold |
| Earth | Vegetal, Shadow | Fire, Water | Gold, Metal |

**Le Neutre n'est pas une affinité** — c'est l'absence d'affinité. Il ne figure pas sur le cycle et reste neutre avec les sept : aucune relation, aucun bonus, aucun malus.

**Règle d'interprétation, à ne pas perdre.** Le secondaire d'une affinité sert son identité propre, **jamais** la relation. Que `Fire → Critical` pointe vers le primaire de son voisin Shadow, et `Gold → Burn` vers celui de son voisin Fire, sont des coïncidences. Cinq autres secondaires ne pointent nulle part. Ne jamais lire le tableau des secondaires comme un encodage des relations.

**Primaire et secondaire sont des points forts, pas des exclusivités.** Toutes les caractéristiques (burn, poison, ward, critique…) sont communes à toutes les affinités. Le primaire/secondaire indique seulement où chaque Vestige penche.

---

## 2. Stats de plateau par Vestige

| Affinité | `base_hp` | `base_shield` | `starting_gold` | `income` | Primaire | Secondaire |
|---|---:|---:|---:|---:|---|---|
| Shadow | 100 | 10 | 20 | 5 | Critical | Acceleration |
| Fire | 100 | 10 | 20 | 5 | Burn | Critical |
| Gold | 90 | 0 | 25 | 7 | Economy | Burn |
| Metal | 90 | 30 | 20 | 5 | Shield | Ward |
| Water | 100 | 10 | 20 | 5 | Slow | Acceleration |
| Vegetal | 125 | 0 | 20 | 5 | Heal | Regen |
| Earth | 100 | 10 | 20 | 5 | Hp max | Poison |

Notes de lecture :
- `base_shield` est la valeur **au début d'un combat**. `gainShield()` est sans plafond ; `receiveHeal()` est plafonné à `base_hp`. Bouclier et PV ne se comparent donc pas comme une même unité.
- `Hp max` (primaire de Earth) n'est pas `base_hp` : c'est une stat à construire, qui augmente le plafond de PV, au même titre que `Critical` et `Slow` qui n'existent pas encore dans le moteur.
- Le Doré paie son économie en survie (90 PV, 0 bouclier). **À surveiller en playtest** : l'income compose sur dix manches (+2/manche ≈ 20 or cumulés, soit une légendaire) alors que le malus de survie reste fixe à −20.

---

## 3. Grammaire visuelle — deux supports, deux grammaires

L'invariant à ne jamais casser : **rareté et affinité ne partagent jamais le même support.**

| Support | Héros | Items |
|---|---|---|
| Illustration | Accent coloré + comportement de la lumière | **Matière constitutive de l'objet** |
| Cadre (image séparée) | Fil noué, coloré par affinité | Matière neutre, jamais de fil |
| Aura `box-shadow` | Rareté seule | Rareté seule |

Le cadre est une **seconde image superposée par l'application** — il n'est jamais généré avec le héros. Une illustration ne contient donc jamais de cadre.

**Le fil est réservé au vivant** : Vestige et héros uniquement, jamais sur les items. Les coutures apparaissent **exclusivement sur la chair exposée**, jamais sur une armure, un cuir ou un vêtement.

**Second porteur d'accent (poses passives).** L'accent au seul point d'impact ne fonctionne que sur une pose en action. Pour un héros immobile ou sans arme dégainée, l'accent se porte sur : une lueur sous-cutanée, le regard, ou l'arête d'une arme rangée.

### Contrainte de composition mesurée

Mesures relevées sur les fichiers de cadre réels (896 × 1200, ratio 0,747 ≈ 3:4) :

| | Valeur |
|---|---|
| Surface d'illustration visible | 79,6 % de la carte |
| Bord recouvert | 4,6 % à gauche/droite · 3,2 % en haut · 3,6 % en bas |
| Emprise du nœud de fil | x 64,6 % → 94,5 % · y 3,7 % → 28,6 % |

L'emprise du nœud est stable d'une affinité à l'autre (écart < 1,5 % entre Ombre et Feu).

> **Zone morte : le quart supérieur droit (≈ 30 % de largeur × 29 % de hauteur) doit rester libre de tout élément signifiant.** Armes levées, têtes et mains se placent à gauche ou au centre.
> Prévoir en plus une marge périphérique de ~5 % latéralement et ~3,5 % en haut/bas, mangée par la pierre du cadre.

### Matières des items (une par affinité)

L'objet **conserve toujours sa structure lisible** — garde, manche, tranchant, contour net. La matière d'affinité occupe la forme, elle ne la dissout pas. Une lame de fumée, oui ; un nuage vaguement épée, non.

| Affinité | Matière constitutive |
|---|---|
| Neutre | Matière ordinaire ternie et usée — **rivetée, assemblée, traces d'outil et joints visibles** |
| Shadow | Fumée noire dense, bords effilochés, contour tenu |
| Fire | Croûte sombre craquelée sur un noyau incandescent |
| Gold | Or massif poli, chaud |
| Metal | Métal blanc-bleu **sans joint ni rivet, comme coulé d'une seule pièce**, poli miroir |
| Water | Eau contenue en mouvement lent, réfractante |
| Vegetal | Bois vif, écorce, racines, sève |
| Earth | Terre compactée, argile fissurée, pierre brute |

**Neutre vs Metal** — c'est la seule paire réellement à risque, le métal étant la matière par défaut de la plupart des objets. La distinction n'est pas une finition mais une **fabrication** : le neutre est assemblé (rivets, joints, usure), le Metal est monolithique (aucun joint, poli miroir). La différence porte sur la densité de détail, pas sur la seule luminosité.

**Conséquence sur la méthode des familles d'objets.** La méthode actée en session 016 (générer la version commune, puis la joindre en référence pour les variantes) reste valable, mais son objet change : la référence verrouille désormais la **forme**, plus le rendu. La parenté entre `Dagger` et `Shadow Dagger` se lit à la silhouette identique, la matière étant volontairement différente.

---

## 4. Gabarit d'une déclinaison

Chaque affinité ci-dessous documente, dans cet ordre :
1. **Identité** — id, primaire/secondaire, couleur d'interface
2. **Accent d'illustration** — teinte + comportement de la lumière
3. **Le fil** — comment il agit, manifestation sur le porteur, manifestation sur l'adversaire
4. **Matière des items**
5. **Fragment de style héros** (anglais, rendu seul — jamais le sujet, la pose ou la tenue)
6. **Fragment matière item** (anglais)

---

## 5. Neutre (`neutral`)

**Identité.** Absence d'affinité. Aucun primaire, aucun secondaire. Couleur d'interface : aucune.

**Accent d'illustration.** Aucun. La base froide désaturée est laissée telle quelle.

**Le fil.** Aucun, ni sur le cadre ni dans l'illustration. La neutralité est un trait de tempérament — une résistance à la résonance, pas une absence de lien. Elle se lit **par le manque** : c'est la seule famille dont la carte ne porte aucune marque.

**Matière des items.** Matière ordinaire ternie : acier usé, cuir, bois sec. Rivets, joints et traces d'outil visibles.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette throughout. No colored accent light anywhere — the scene is entirely neutral in hue. Dramatic single-source chiaroscuro lighting, deep shadows, minimal fill. No stitch-marks on the skin. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Made of ordinary tarnished materials — worn steel, dull leather, dry wood. Visible rivets, seams and tool marks; the object is clearly assembled from parts. No colored glow. Cold desaturated grey palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 6. Ombre (`shadow`)

**Identité.** Primaire **Critical**, secondaire **Acceleration**. Couleur d'interface : violet profond.

**Accent d'illustration.** Violet presque noir à reflet froid. Comportement : **absorbe la lumière** — assombrit ce qui l'entoure au lieu de rayonner.

**Le fil.** Il ne coud pas pour relier, il coud pour dissimuler. Il se faufile sans se voir, suture les silences plutôt que les plaies : il referme une blessure en la cachant, jamais en la guérissant.
*Sur le porteur* — des points de couture noirs et mouvants, presque invisibles, qui apparaissent sur la peau à l'instant où il frappe, comme un point tiré puis aussitôt effacé.
*Sur l'adversaire* — le même fil ne coud rien : il tire un fil qui défait, un point qui détricote lentement sa propre tressure. Traduit par une corrosion ou un effilochement local de la matière touchée.

**Matière des items.** Fumée noire dense, bords effilochés, contour de l'objet toujours tenu.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of deep near-black violet, behaving as light-absorbing rather than radiant — it darkens the surrounding surfaces instead of illuminating them. On active poses the accent appears as a sharp energy trail at the point of impact; on still poses it appears instead as a faint subcutaneous violet glow, in the eyes, or along the edge of a sheathed weapon. Subtle black stitch-marks on exposed skin only — never on armour, leather or cloth. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its blade and body are made of dense black smoke — frayed at the edges yet holding a clearly readable silhouette, with guard, grip and cutting edge fully legible. The smoke absorbs light, darkening what surrounds it. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 7. Feu (`fire`)

**Identité.** Primaire **Burn**, secondaire **Critical**. Couleur d'interface : rouge sombre.

**Accent d'illustration.** Orange-rouge. Comportement : **rayonne** — halo diffus projeté sur les surfaces voisines, seule affinité dont la lumière éclaire réellement la scène.

**Le fil.** Il ne coud pas, il consume en avançant. La suture est une brûlure : elle referme, mais en détruisant les bords de la plaie.
*Sur le porteur* — des coutures qui rougeoient sous la peau comme des braises sous la cendre, visibles par transparence, plus vives à l'instant du coup puis retombant lentement.
*Sur l'adversaire* — ce qui est touché continue de se consumer après le coup. La brûlure ne s'arrête pas au contact : la matière noircit, se craquelle et s'effrite depuis le point d'impact.

**Matière des items.** Croûte sombre craquelée sur un noyau incandescent — la lumière sort des fissures, jamais de la surface.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of red-orange light, genuinely radiant — it casts a soft diffuse halo onto nearby surfaces and is the only light that illuminates the scene. On active poses the accent concentrates at the point of impact; on still poses it appears instead as ember-like stitch-marks glowing under the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks appear as banked embers seen through the skin, on exposed flesh only — never on armour, leather or cloth. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is a dark cracked crust over an incandescent core — light escapes only through the fissures, never from the surface itself. Silhouette fully readable, with guard, grip and cutting edge legible. The glow casts a faint warm halo around the object. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 8. Or (`gold`)

**Identité.** Primaire **Economy**, secondaire **Burn**. Couleur d'interface : jaune doré.

**Accent d'illustration.** Jaune doré. Comportement : **spéculaire dur avec un léger scintillement diffus** — éclats ponctuels et vifs, sans halo projeté franc.

**Le fil.** Il ne coud jamais gratuitement : il prélève. Il referme la plaie, mais prend quelque chose au passage — le porteur est toujours un peu plus pauvre de ce qui vient d'être réparé.
*Sur le porteur* — des coutures de fil doré terne, comme une suture métallique. La peau autour est tirée, amincie, exsangue : ce que la couture a pris ne revient pas.
*Sur l'adversaire* — ce qui est défait ne disparaît pas, il change de main. La matière touchée se ternit et perd son éclat, comme dépouillée.

**Matière des items.** Or massif poli, chaud.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of warm gold, behaving as hard specular light — sharp bright glints with a faint diffuse shimmer, but no projected halo. On active poses the accent concentrates at the point of impact; on still poses it appears instead as a faint gold shimmer under the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks are dull gold, like metallic sutures, on exposed flesh only — never on armour, leather or cloth; the skin around them is drawn tight, thin and bloodless. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is solid polished gold, warm and heavy, catching sharp specular glints. Silhouette fully readable, with guard, grip and cutting edge legible. No projected halo. Cold desaturated background palette against the warm metal, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 9. Métal (`metal`)

**Identité.** Primaire **Shield**, secondaire **Ward**. Couleur d'interface : blanc froid.

**Accent d'illustration.** Blanc franchement bleuté, sans aucune chaleur. Comportement : **ne luit pas** — arête blanche pure, réflexion de lame, contraste maximal sans halo ni lueur.

**Le fil.** Il ne coud pas la chair : il coud une armure par-dessus. Il ne referme pas la plaie, il interdit qu'on l'atteigne à nouveau.
*Sur le porteur* — des coutures blanches et dures, comme des agrafes posées en surface, se recouvrant jusqu'à former des plaques. Elles ne s'estompent pas entre les coups.
*Sur l'adversaire* — rien ne se défait : le coup glisse. La matière touchée ne se corrode pas, elle est simplement repoussée, comme si elle n'avait pas trouvé prise.

**Matière des items.** Métal blanc-bleu sans joint ni rivet, comme coulé d'une seule pièce, poli miroir.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of cold blue-white with no warmth at all, behaving as pure reflection rather than light — hard white edge highlights, blade-like specular, maximum contrast, no halo and no glow. On active poses the accent runs along the struck edge; on still poses it appears instead as a pale hard sheen under the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks are hard white staples on the surface of exposed flesh only — never on armour, leather or cloth — overlapping into plates. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is seamless blue-white metal, cast as a single piece — absolutely no rivets, no joints, no assembly marks, mirror-polished. Silhouette fully readable, with guard, grip and cutting edge legible. Pure reflection, no glow. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 10. Eau (`water`)

**Identité.** Primaire **Slow**, secondaire **Acceleration**. Couleur d'interface : bleu cyan.

**Accent d'illustration.** Bleu cyan. Comportement : **diffuse et réfracte** — halo doux, bords flous, comme vu à travers de l'eau.

**Le fil.** Il ne tire pas, il retient. Il s'enroule autour de ce qu'il touche et l'alourdit, sans jamais serrer.
*Sur le porteur* — des coutures qui semblent flotter juste sous la peau, se déplaçant lentement, dérivant d'un point à l'autre au lieu de rester en place.
*Sur l'adversaire* — les gestes s'alourdissent. La matière touchée se gorge, s'affaisse, réagit avec un temps de retard.

**Matière des items.** Eau contenue en mouvement lent, réfractante — l'objet a la forme d'un contenant invisible.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of cyan blue, behaving as diffused and refracted light — soft halo, blurred edges, as if seen through water. On active poses the accent gathers at the point of impact; on still poses it appears instead as a slow drifting glow under the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks appear to float just beneath the surface of exposed skin only — never on armour, leather or cloth — slowly drifting rather than fixed. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is held water in slow motion, refracting the light behind it, keeping a clearly readable silhouette with guard, grip and cutting edge legible — as if poured into an invisible mould. Soft blurred edges, no spillage, no splash. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 11. Végétal (`vegetal`)

**Identité.** Primaire **Heal**, secondaire **Regen**. Couleur d'interface : vert.

**Accent d'illustration.** Vert. Comportement : **luminescence interne** — douce, lente, émanant de l'intérieur de la matière, jamais projetée.

**Le fil.** C'est le seul fil qui répare vraiment — mais il repousse au lieu de recoudre. La suture devient racine : elle tient parce qu'elle a poussé à travers, pas parce qu'elle a été nouée.
*Sur le porteur* — des coutures vertes qui bourgeonnent, laissant sur la peau des marques en réseau, comme des veines de sève sous la surface.
*Sur l'adversaire* — presque rien. Le fil végétal agit sur soi et sur les siens ; c'est la seule affinité dont la manifestation hostile est discrète, ce qui est en soi sa signature.

**Matière des items.** Bois vif, écorce, racines, sève — matière vivante et non taillée.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of green, behaving as soft internal luminescence — glowing slowly from within the material, never projected outward. On active poses the accent gathers at the point of contact; on still poses it appears instead as a slow sap-like glow under the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks are green and budding, spreading as a network of sap-veins beneath exposed skin only — never on armour, leather or cloth. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no trees, no forest, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is living wood, bark and root, with sap visible beneath the surface — grown into shape rather than carved. Silhouette fully readable, with guard, grip and cutting edge legible. Faint green luminescence from within the material only. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 12. Terre (`earth`)

**Identité.** Primaire **Hp max**, secondaire **Poison**. Couleur d'interface : brun.

**Accent d'illustration.** **Ambre-ocre** — volontairement différent du brun d'interface (le brun est un neutre désaturé, il ne peut pas tenir le rôle d'accent). Comportement : **lueur sous une croûte** — faible, sortant de fissures dans une matière opaque.

**Le fil.** Il ne coud pas, il entasse. Il ne referme pas la plaie : il l'ensevelit sous de la matière jusqu'à ce qu'elle ne compte plus.
*Sur le porteur* — des coutures épaisses et grossières, comme des sutures d'argile séchée. La peau se craquelle autour et durcit, gagnant en épaisseur ce qu'elle perd en souplesse.
*Sur l'adversaire* — la matière touchée s'alourdit et se minéralise, se fige par plaques.

**Matière des items.** Terre compactée, argile fissurée, pierre brute.

**Fragment de style héros**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of amber-ochre, behaving as a dim glow escaping from beneath a crust — low intensity, seeping only through cracks in opaque matter, never radiant. On active poses the accent shows at the point of impact; on still poses it appears instead as a dull amber light in the fissures of the skin, in the eyes, or along the edge of a sheathed weapon. Stitch-marks are thick and coarse, like dried clay sutures on exposed flesh only — never on armour, leather or cloth — with the skin cracking and hardening around them. Dramatic single-source chiaroscuro lighting, deep shadows. Keep the upper-right quarter of the image free of any significant element. Moody ambient grey background with light atmospheric mist only — no environment, no architecture, no colored sky, no ground, no floor, no cast shadow on ground. No flat lighting, no frame, no border, no text, no watermark.*

**Fragment matière item**

> *The object alone, front-facing, centered. Its body is compacted earth, cracked clay and raw stone. Silhouette fully readable, with guard, grip and cutting edge legible. A dim amber light seeps from the fissures only, never from the surface. Cold desaturated palette, single-source chiaroscuro lighting. Ambient grey misted background, no ground, no surface, no hand, no frame, no text, no watermark.*

---

## 13. Points ouverts

- **Le nœud de fil du cadre Ombre est violet presque noir sur pierre gris foncé** : il ne se lit que par sa texture tressée, pas par sa couleur. C'est le pire cas des sept — à vérifier en vignette réelle avant de figer les six autres cadres.
- **Métal : ordre primaire/secondaire.** Le diagramme des Vestiges donne `Shield` primaire / `Ward` secondaire, une décision antérieure disait l'inverse. Sans effet sur les assets, à confirmer avant l'implémentation.
- **Végétal et Argent** inversent l'ordre instantané/durée (Vegetal : heal puis regen ; Metal : shield puis ward). À confirmer comme intentionnel — le vert réagit, l'argent anticipe.
- **Sept accents en vignette réelle** : la grille complète n'a jamais été testée côte à côte. Les paires à surveiller sont Or/Métal (spéculaires) et Feu/Terre (chauds).
- Ce fichier est un point de départ, pas un contrat figé : le corriger dès qu'un écart apparaît entre l'intention et le résultat généré.
