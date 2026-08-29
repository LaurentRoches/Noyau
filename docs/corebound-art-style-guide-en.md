# Corebound — Art Direction Guide
## AI Asset Generation (v1)

> Reference document for generating any illustration (heroes, items, Vestige). Builds on `corebound-lore-bible.md` — when an intent is unclear, the lore bible is authoritative. This guide covers illustrated content only; UI/interface (CSS frames, layout) is a separate, currently prototype-only concern.

---

## 1. General Direction

- **Register**: ornate and atmospheric — semi-realistic painted texture, never flat vector, never cartoon.
- **Level of detail**: moderate. Rich enough to reward close inspection, readable enough to work at small thumbnail size. Strong silhouettes come first — any detail that breaks readability at small size must be simplified.
- **Base color temperature**: cold and desaturated, everywhere, no exceptions. This is the baseline for the entire world — consistent with a universe marked by a diffuse, ambient sense of loss (see lore, section 1).
- **Lighting**: strong chiaroscuro. One dramatic light source, deep shadows, minimal fill light. Never flat or uniform lighting.
- **Background**: an ambient color with a light atmospheric touch (mist, gradient, diffuse glow) — never a flat solid color, never a full narrative scene (no environment, no architecture in the background for V1).

---

## 2. Visual Grammar: Rarity vs Affinity

Two information systems coexist on every card. They must never be carried by the same visual element.

| Information | Carried by | Where |
|---|---|---|
| **Rarity** (Common/Rare/Legendary) | Glowing aura (`box-shadow`) | Around the frame — **outside the illustration**, an interface treatment, not something to paint |
| **Affinity** (Shadow, future affinities) | Accent color + visual treatment | **Inside the illustration itself** — the single saturated touch on an otherwise desaturated base |

**Consequence for generation**: an illustration should never attempt to represent rarity. Rarity is a treatment applied afterward by the interface. The AI only ever generates affinity.

---

## 3. Frame Hierarchy

Three distinct frame identities, never interchangeable:

- **Vestige**: the frame *is* made of thread — knotted, woven, almost organic. No stone, no metal. It is the only entity whose frame is literally made of the lore's core substance (the Weave).
- **Hero**: frame in dark, ornate stone or metal (gothic spirit). Heroes with a non-neutral affinity have **part of the frame rendered in thread**, in addition to the base frame.
- **Items**: frame in a neutral, more modest material (dark tarnished/burnished metal), never thread, regardless of the item's affinity.

---

## 4. Thread/Stitch Motif — Rules of Application

- **Reserved for the living**: Vestige and heroes only. **Never on items**, even affinity-bound ones.
- **Vestige**: thread makes up the entire frame.
- **Hero with non-neutral affinity**: thread appears (a) partially on the frame, (b) potentially within the illustration itself (e.g. visible stitch marks on skin for Shadow — see section 6).
- **Neutral hero**: no thread, neither on the frame nor in the illustration — neutrality is precisely the absence of visible resonance.
- **Affinity-bound items**: affinity is conveyed solely through the accent color in the illustration. If this proves insufficiently readable in practice, revisiting the approach (a dedicated icon) remains possible — don't pre-empt this solution before it's proven necessary.

---

## 5. Composition

- **Heroes**: framing varies by pose and character identity — close bust shot or full-body, decided case by case. No rigid rule; judgment takes priority.
- **Items**: always the object alone, presented front-facing, on the ambient background. No staged context (no hand holding the object, no surface it rests on) in V1.
- **Vestige**: a structure of interlocking rings/loops, continuously sliding through one another, never settling into a static pose or stable state — conveys both the fragment's instability and the nature of knotted thread. Motion/shape reference validated; **material and color still need adapting**: textured, matte thread (no glossy glass/chrome rendering), neutral grey/desaturated base with the affinity accent applied on top (no saturated color across the whole structure).

---

## 6. Worked Example — Shadow Affinity (`shadow_vestige`)

This template serves as the model for documenting any future affinity.

- **Accent color**: deep, near-black violet with a cold sheen — a color that absorbs light rather than radiating it (unlike an orange or green that illuminate the scene).
- **Thread behavior**: doesn't connect, it conceals. It stitches to hide, never to heal.
- **Manifestation on the bearer**: black, faintly shifting stitch marks, almost invisible, appearing on the skin at the instant of a strike — as if a stitch were pulled tight and immediately erased.
- **Manifestation on the enemy/environment**: a thread that unravels rather than stitches — visually rendered as localized corrosion or fraying of the affected material/texture.
- **Suggested prompt keywords**: *cold desaturated palette, deep near-black violet accent glow, dramatic single-source chiaroscuro lighting, semi-realistic painted texture, subtle moving black stitch-marks on skin, fabric fraying at the edges, moody ambient colored background, no flat lighting, no full scene background*

**Reusable style fragment (rendering technique only, to combine with each hero's own description — subject, pose, outfit):**

> *Semi-realistic painted texture, cold desaturated grey-blue base palette. Single accent of deep near-black violet light, appearing only as a sharp, vibrant energy trail (thin glowing lines, bright near-white core fading to violet) concentrated at the point of contact/impact — not spread across the whole scene. Dramatic single-source chiaroscuro lighting, deep shadows. Subtle black stitch-marks visible on exposed skin. Moody ambient grey background with light atmospheric mist only — no forest, no trees, no full environmental scene, no colored sky. No flat lighting, no text, no watermark.*

This fragment never describes the subject, pose, or outfit — only the graphic grammar (palette, energy treatment, lighting, background) — so it stays combinable with any hero description.

---

## 7. Recommended Prompt Structure

1. Subject + role (hero/item/Vestige) + pose or presentation
2. Palette: cold desaturated base + explicitly named affinity accent color
3. Lighting: chiaroscuro, single source, strong contrast
4. Background: ambient color with light atmosphere, no narrative scene
5. Thread markers (if applicable): explicit description of their appearance and placement
6. Exclusions: no text, no watermark, no flat/cartoon rendering, no complex architectural background

---

## 8. Cross-Generation Consistency (to refine in practice)

- Use an **anchor image** per affinity (one validated generation serving as the stylistic reference for subsequent ones in the same family), to limit visual drift across successive AI calls.
- A **scripted post-processing pass** (desaturation, grading, uniform contrast adjustment) is being considered to unify generations that drift slightly in raw output. Design this once a first real batch of assets exists — don't over-anticipate this step before an actual need has been observed.

---

## 9. Technical Formats per Asset Type

| Type | Ratio | Notes |
|---|---|---|
| **Heroes** | Portrait 3:4 or 4:5 | Fixed ratio for all heroes, no exceptions — required for grid alignment. Framing (close bust/full body) can still vary case by case within this ratio. |
| **Items** | Square 1:1 | Object alone, front-facing (see section 5). |
| **Vestige** | Square 1:1 | Centered, symmetrical structure (see section 6, motion reference) with no fixed top/bottom or front/back orientation — a square suits it better than a portrait. |

Open point to settle during upcoming generations: the first Shadow hero attempts show permanently visible stitch marks (scars, barbed thread on the armor), while the lore describes a thread that is *"almost invisible, appearing at the instant of a strike."* To decide: a permanent, visible mark (a lasting sign of the bearer) or a toned-down, more discreet mark closer to the lore's description.

---

- This guide is a starting point, not a fixed contract — correct it as soon as a gap appears between intent and generated result.
- The current UI direction (`style.css`, sober tokens) doesn't yet reflect this art direction — a separate piece of work, to be addressed later.
