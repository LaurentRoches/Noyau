# Corebound — Art Direction Guide
## AI Asset Generation (v2)

> Reference document for generating any illustration (heroes, items, Vestige). Builds on `corebound-lore-bible.md` — when an intent is unclear, the lore bible is authoritative.
> **The seven affinity declensions (colors, materials, prompt fragments) live in `corebound-affinities.md`.** This guide holds only the general grammar, common to every affinity.
> This guide covers illustrated content only; UI/interface (CSS frames, layout) is a separate concern.

---

## 1. General Direction

- **Register**: ornate and atmospheric — semi-realistic painted texture, never flat vector, never cartoon.
- **Level of detail**: moderate. Rich enough to reward close inspection, readable enough to work at small thumbnail size. Strong silhouettes come first — any detail that breaks readability at small size must be simplified.
- **Base color temperature**: cold and desaturated, everywhere, no exceptions. This is the baseline for the entire world — consistent with a universe marked by a diffuse, ambient sense of loss (see lore, section 1).
- **Lighting**: strong chiaroscuro. One dramatic light source, deep shadows, minimal fill light. Never flat or uniform lighting.
- **Background**: an ambient color with a light atmospheric touch (mist, gradient, diffuse glow) — never a flat solid color, never a full narrative scene. **No ground, no floor, no cast shadow on ground.**

---

## 2. Visual Grammar: Rarity vs Affinity

Two information systems coexist on every card. The invariant that must never break:

> **Rarity and affinity never share the same support.**

| Information | Carried by |
|---|---|
| **Rarity** (Common/Rare/Legendary) | Glowing aura (`box-shadow`, tokens `--common`/`--rare`/`--legendary`) — **alone on that support**, an interface treatment, never painted |
| **Affinity** | Two supports: the **illustration** (accent color for heroes, constitutive material for items) **and the frame** (color of the knotted thread) |

**Consequence for generation**: an illustration never represents rarity. The AI only ever generates affinity, and never the frame.

---

## 3. Frame Hierarchy

The frame is a **separate image, overlaid by the application**. It is never generated together with the hero or the object: an illustration therefore never contains a frame, a border, or any surrounding ornament.

Three distinct frame identities, never interchangeable:

- **Vestige**: the frame *is* made of thread — knotted, woven, almost organic. No stone, no metal. It is the only entity whose frame is literally made of the lore's core substance (the Weave).
- **Hero**: frame in dark, ornate stone or metal (gothic spirit). Non-neutral heroes additionally carry a **knot of thread in the upper-right corner, colored by affinity**. The neutral hero has no knot — that family reads by absence.
- **Items**: frame in a neutral, more modest material (dark tarnished/burnished metal), never thread, regardless of the item's affinity.

---

## 4. Thread/Stitch Motif — Rules of Application

- **Reserved for the living**: Vestige and heroes only. **Never on items**, even affinity-bound ones.
- **Vestige**: thread makes up the entire frame.
- **Hero with non-neutral affinity**: thread appears (a) on the frame, colored by affinity, (b) within the illustration as stitch marks.
- **Stitch marks are on exposed flesh only** — never on armour, leather, straps or cloth. This rule was set after observing the gap on the first batch of generations.
- **Neutral hero**: no thread, neither on the frame nor in the illustration — neutrality is precisely the absence of visible resonance.
- **Items**: affinity is **no longer** carried by an accent color but by the **constitutive material of the object** (smoke for Shadow, water for Water, earth for Earth…). A deliberate revision of the v1 approach, whose §4 explicitly allowed for it. See `corebound-affinities.md` §3 for the full table.

---

## 5. Composition

- **Heroes**: framing varies by pose and character identity — close bust shot or full-body, decided case by case. No rigid rule; judgment takes priority.
- **Items**: always the object alone, front-facing, on the ambient background. No staged context (no hand holding the object, no surface it rests on).
- **Vestige**: a structure of interlocking rings/loops, continuously sliding through one another, never settling into a static pose. Textured matte thread (no glossy glass/chrome rendering), neutral grey base with the affinity accent applied on top.

### Dead zone imposed by the frame

Measured on the actual frame files (896 × 1200, ratio 0.747):

| | Value |
|---|---|
| Visible illustration area | 79.6 % of the card |
| Covered border | 4.6 % left/right · 3.2 % top · 3.6 % bottom |
| Thread knot footprint | x 64.6 % → 94.5 % · y 3.7 % → 28.6 % |

> **The upper-right quarter (≈ 30 % × 29 %) must stay free of any significant element.** Raised weapons, heads and hands go left or center.

Allow in addition a peripheral margin of ~5 % on the sides and ~3.5 % top/bottom.

### Accent carriers (heroes)

An accent placed only at the point of impact works solely on an active pose — a motionless hero has no impact point, therefore no accent. Two carriers are allowed:

1. **Active pose**: energy trail at the point of contact.
2. **Passive pose**: subcutaneous glow, the eyes, or the edge of a sheathed weapon.

---

## 6. Affinity Declensions

**See `corebound-affinities.md`.** That file is the single source: cycle and relations, board stats, accents and light behaviors, item materials, reusable prompt fragments.

Each declension documents, in this order: identity (id, primary/secondary, interface color) · illustration accent (hue + light behavior) · the thread (how it acts, manifestation on the bearer, manifestation on the enemy) · item material · hero style fragment · item material fragment.

Style fragments never describe the subject, pose or outfit — only the graphic grammar — so they stay combinable with any hero description.

---

## 7. Recommended Prompt Structure

1. Subject + role (hero/item/Vestige) + pose or presentation
2. Palette: cold desaturated base + affinity accent (hero) or affinity material (item)
3. Lighting: chiaroscuro, single source, strong contrast
4. Background: ambient color with light atmosphere, no narrative scene
5. Thread markers (if applicable): on exposed flesh only
6. Composition: upper-right quarter left clear
7. Exclusions: `no ground, no floor, no cast shadow on ground, no environment, no architecture, no colored sky, no frame, no border, no flat lighting, no cartoon rendering, no text, no watermark`

---

## 8. Cross-Generation Consistency

- Use an **anchor image** per affinity (one validated generation serving as the stylistic reference for later ones in the same family), to limit visual drift across successive AI calls.
- **Item families**: generate the common version first, then attach it as a reference for the variants. Since the move to constitutive material, the reference locks the **shape**, not the rendering — the kinship between `Dagger` and `Shadow Dagger` reads through silhouette, the material being deliberately different.
- A **scripted post-processing pass** (desaturation, grading, uniform contrast) is still under consideration. Design it once a real batch of assets exists — don't over-anticipate.

---

## 9. Technical Formats per Asset Type

| Type | Ratio | Notes |
|---|---|---|
| **Heroes** | Portrait 3:4 | Fixed ratio, no exceptions — confirmed by the frame files (0.747). Framing (close bust/full body) can still vary within this ratio. |
| **Items** | Square 1:1 | Object alone, front-facing. |
| **Vestige** | Square 1:1 | Centered, symmetrical structure with no fixed top/bottom or front/back orientation. |

**Stitch marks — settled.** The lore describes a thread that is "almost invisible, appearing at the instant of a strike", while generations show permanent marks. Decision taken at the end of session 016, recorded here: **the current rendering is kept as is**, the permanent mark being accepted as a lasting sign of the bearer. The final call is deferred to a professional illustrator.

---

- This guide is a starting point, not a fixed contract — correct it as soon as a gap appears between intent and generated result.
- The current UI direction (`style.css`, sober tokens) doesn't yet reflect this art direction — a separate piece of work.
