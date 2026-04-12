<!--
  Coretsia Framework (Monorepo)
  
  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko
  
  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0
  
  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# Coretsia Branding Spec

Canonical branding guidance for the Coretsia visual identity. This document defines the approved symbol system, color palette, spacing model, safe area, favicon adaptation, light/dark variants, and canonical typography policy for the selected Coretsia mark.

---

## 1. Scope

This document defines the canonical visual rules for the approved Coretsia symbol:

- six-segment outer shell
- central red core
- geometric, symmetric, high-contrast construction
- symbol-first usage for avatar/icon surfaces
- lockup usage for README, docs, and site contexts

This document is design guidance for brand consistency. It is not a generated artifact.

---

## 2. Brand intent

The Coretsia mark MUST communicate the following qualities:

- **core / foundation / "серцевина"**
- **modular architecture**
- **deterministic-by-default**
- **strict boundaries / SSoT**
- **serious framework-level identity**

The approved symbol satisfies these requirements by combining:

- a clear central core
- a structured outer shell
- modular segmentation
- balanced geometric symmetry
- strong silhouette at small sizes

---

## 3. Canonical mark

### 3.1 Primary symbol

The canonical Coretsia symbol is **symbol-only** and consists of:

- a six-segment outer shell
- a central red core
- knockout space between outer shell segments
- knockout space around the core
- flat vector construction
- symmetric geometry
- no ornamental or decorative noise

### 3.2 Lockup

The canonical horizontal lockup for documentation and site use is:

- **symbol on the left**
- **wordmark on the right**
- wordmark text: `Coretsia`
- vertical alignment by optical center of the symbol

The symbol remains the primary identifier. The wordmark is secondary and MUST NOT overpower the symbol visually.

---

## 4. Color system

### 4.1 Primary palette

| Token                 |       Hex | Use                                 |
|-----------------------|----------:|-------------------------------------|
| Coretsia Graphite 800 | `#31353E` | deep edge / dark neutral            |
| Coretsia Graphite 700 | `#3C3F48` | primary shell color                 |
| Coretsia Graphite 600 | `#4D5159` | secondary neutral / subtle contrast |
| Core Red 500          | `#E41E29` | core highlight                      |
| Core Red 600          | `#BE1828` | primary core face                   |
| Core Red 700          | `#AD1927` | core shadow face                    |
| Canvas Light          | `#F8F8F8` | preferred light background          |
| Canvas Dark           | `#0F1115` | preferred dark background           |
| Slate Light           | `#D7DCE5` | shell color on dark backgrounds     |

### 4.2 Light version

For light surfaces, use:

- outer shell: `#3C3F48`
- core highlight: `#E41E29`
- core mid: `#BE1828`
- core shadow: `#AD1927`
- background: `#F8F8F8` or `#FFFFFF`
- separators: **background knockout**, not hardcoded white where possible

### 4.3 Dark version

For dark surfaces, use:

- outer shell: `#D7DCE5`
- core highlight: `#E41E29`
- core mid: `#BE1828`
- core shadow: `#AD1927`
- background: `#0F1115`
- separators: **background knockout**

### 4.4 Monochrome fallback

For one-color usage:

- dark mono: `#3C3F48`
- light mono: `#FFFFFF`

In monochrome mode:

- the central core becomes the same color as the outer shell
- no faceted shading is used
- no gradients are used

---

## 5. Geometry and proportions

To keep the specification resolution-independent, a base unit is defined.

### 5.1 Unit

Let:

- **x = flat-to-flat width of the central red core**

All spacing and proportional guidance is expressed relative to `x`.

### 5.2 Symbol proportions

Recommended proportions:

- outer shell thickness: **~0.42x**
- knockout ring around core: **~0.14x**
- gaps between outer shell segments: **~0.10x – 0.12x**
- total symbol width: **~2.30x – 2.45x** for the canonical symbol master

### 5.3 Geometry rules

The symbol MUST remain:

- optically monolithic as a whole
- structurally segmented at close inspection
- readable at small sizes
- balanced around the central axis

Gaps MUST NOT become so thin that they collapse at small raster sizes.

---

## 6. Safe area

### 6.1 Symbol-only safe area

Minimum safe area around the symbol:

- **0.5x** on all sides

Preferred safe area:

- **0.75x** on all sides

No text, iconography, border, or decorative element may intrude into the safe area.

### 6.2 Lockup safe area

For the horizontal lockup `symbol + Coretsia`:

- spacing between symbol and wordmark: **0.55x**
- minimum outer safe area for the full lockup: **0.5x**
- preferred outer safe area for the full lockup: **0.75x**

---

## 7. Size rules

### 7.1 Symbol-only sizes

Minimum digital sizes:

- **16 px** — favicon-adapted micro version only
- **24 px** — minimum UI usage
- **32 px** — minimum GitHub/avatar-like usage
- **64 px+** — preferred general digital usage

### 7.2 Lockup sizes

Minimum height for `symbol + wordmark`:

- **24 px** — absolute minimum
- **32 px** — recommended minimum
- **48 px+** — preferred for docs/site header use

The lockup MUST NOT be used where the wordmark becomes unreadable.

---

## 8. Favicon adaptation

Favicon and app/site icon assets MUST NOT be treated as a naive scaled-down master logo. Small-size adaptations are required.

### 8.1 64 px and above

Use the **full symbol**:

- all 6 outer segments
- faceted red core
- standard knockout gaps

### 8.2 32 px

Use a near-full version, but adjust as needed:

- slightly increase shell thickness
- slightly enlarge the core
- slightly increase red contrast
- verify the lower segment does not visually merge

### 8.3 16 px

Use a dedicated micro version:

- preserve the 6 outer segments
- remove faceted core shading
- use single-fill core color: `#E41E29`
- increase outer shell thickness by **~10–12%**
- remove micro-detail that does not survive rasterization

### 8.4 Canonical 16 px rule

At 16 px, priority order is:

1. silhouette
2. center emphasis
3. readability

Decorative detail is explicitly secondary.

---

## 9. Light / dark variants

### 9.1 Light variant

Use on:

- documentation
- README
- GitHub Pages
- light UI sections
- light presentation surfaces

Specification:

- background: `#F8F8F8` or `#FFFFFF`
- shell: `#3C3F48`
- core: red tri-tone palette
- gaps: knockout/background color

### 9.2 Dark variant

Use on:

- dark site hero sections
- IDE-like UI surfaces
- splash/promo surfaces
- dark theme documentation previews

Specification:

- background: `#0F1115`
- shell: `#D7DCE5`
- core: red tri-tone palette
- gaps: knockout/background color

### 9.3 Contrast rule

Do not use:

- `#3C3F48` shell on very dark backgrounds
- `#D7DCE5` shell on very light backgrounds

Minimum contrast MUST preserve the shell silhouette distinctly from the background.

---

## 10. GitHub organization avatar spec

For GitHub organization avatar usage, the canonical form is:

- **symbol-only**
- centered on a square artboard
- no wordmark
- no decorative background texture

### 10.1 Canonical avatar setup

- artboard: square
- background: `#F8F8F8`
- symbol centered
- visual symbol width: **64–68%** of canvas width

### 10.2 Circular mask tolerance

If the avatar is expected to be displayed inside a circular mask:

- visual symbol width: **60–64%** of canvas width

This prevents edge collisions and maintains optical balance.

### 10.3 Recommended exports

- `1024x1024 PNG`
- `512x512 PNG`
- optional transparent PNG
- SVG source-of-truth master

---

## 11. Website icon / app icon spec

### 11.1 Primary icon form

Website/app icon usage MUST use:

- square canvas
- symbol-only
- centered composition

Preferred backgrounds:

- light: `#F8F8F8`
- dark: `#0F1115`

### 11.2 Recommended export set

- `16x16`
- `32x32`
- `48x48`
- `64x64`
- `180x180` (Apple touch)
- `192x192`
- `512x512`

---

## 12. Wordmark and typography spec

### 12.1 Canonical typography policy

Coretsia uses a two-role typography system:

- **canonical wordmark font:** `Manrope`
- **canonical UI/content font:** `Inter`

These roles are distinct and MUST NOT be treated as interchangeable in canonical brand usage.

### 12.2 Wordmark font

The canonical Coretsia wordmark font is:

- **Manrope SemiBold**

The wordmark text is:

- `Coretsia`

The wordmark MUST preserve:

- strong readability
- modern technical character
- restrained geometric feel
- no futuristic distortion
- clear compatibility with the Coretsia symbol geometry

### 12.3 Wordmark delivery rule

For production brand usage, the Coretsia wordmark MUST be delivered as a **vector SVG asset** derived from the canonical wordmark master.

Canonical brand lockups for site, documentation, README, and promotional usage MUST NOT depend on runtime font loading for correct wordmark rendering.

This means:

- the approved lockup is treated as a brand asset
- the wordmark is part of the lockup asset
- `Manrope` is the canonical design source for the wordmark
- `Manrope` does not need to be loaded as a required runtime webfont for normal site rendering

### 12.4 UI and content font

The canonical UI/content font is:

- **Inter**

`Inter` MUST be used for:

- navigation
- body text
- buttons
- forms
- interface labels
- general website and documentation UI typography

### 12.5 Runtime webfont policy

For canonical website runtime typography, Coretsia MUST use:

- **one self-hosted canonical webfont only:** `Inter`

Runtime loading of additional brand font families is discouraged unless explicitly required for a non-canonical experimental surface.

The canonical production direction is:

- `Manrope` for wordmark design source
- `Inter` for runtime UI/content typography
- wordmark delivered as SVG asset
- website typography delivered with a single self-hosted runtime webfont

### 12.6 Wordmark color

- light background: `#3C3F48`
- dark background: `#D7DCE5`

### 12.7 Proportion

Recommended lockup proportion:

- cap-height of wordmark: **~72–78%** of symbol height

The wordmark MUST NOT visually outweigh the symbol.

---

## 13. Background rules

Allowed backgrounds:

- solid white
- `#F8F8F8`
- `#0F1115`
- very clean neutral surfaces

Not recommended:

- noisy textures
- photography
- loud gradients
- colored surfaces with weak contrast against the red core
- complex patterned backgrounds

The mark depends on silhouette clarity and controlled contrast.

---

## 14. Incorrect use

The following uses are not allowed:

- distorting symbol proportions
- rotating the symbol
- adding glow or neon effects
- adding drop shadows
- recoloring the shell to blue, green, or purple
- replacing knockout separators with arbitrary gray fills
- placing the symbol on low-contrast backgrounds
- using the detailed faceted core at 16 px without simplification

---

## 15. Canonical asset set

Recommended final asset inventory:

### 15.1 Symbol assets

- `coretsia-symbol-light.svg`
- `coretsia-symbol-dark.svg`
- `coretsia-symbol-mono-dark.svg`
- `coretsia-symbol-mono-light.svg`

### 15.2 Wordmark assets

- `coretsia-wordmark-light.svg`
- `coretsia-wordmark-dark.svg`

### 15.3 Lockup assets

- `coretsia-lockup-light.svg`
- `coretsia-lockup-dark.svg`

### 15.4 Preview assets

- `coretsia-github-avatar-500.png`
- `coretsia-github-avatar-1024.png`

### 15.5 Icon assets

- `coretsia-favicon-micro.svg`
- `favicon.svg`
- `favicon.ico`
- `favicon-16x16.png`
- `favicon-32x32.png`
- `favicon-48x48.png`
- `favicon-64x64.png`
- `android-chrome-192x192.png`
- `android-chrome-512x512.png`
- `apple-touch-icon.svg`
- `apple-touch-icon.png`

---

## 16. Canonical usage summary

### 16.1 Primary usage

- **GitHub org / favicon / icon:** symbol-only
- **README / website / docs header:** horizontal lockup
- **dark theme surfaces:** dark variant with light shell
- **small sizes:** simplified favicon adaptation
- **vector master:** SVG is the source of truth
- **canonical wordmark font:** Manrope
- **canonical UI/content font:** Inter
- **runtime website font policy:** one self-hosted runtime webfont only
- **wordmark delivery policy:** SVG asset, not required runtime webfont rendering

### 16.2 One-line summary

**Coretsia mark = structured outer system + central core, expressed through a deterministic, modular, high-contrast geometric emblem.**
