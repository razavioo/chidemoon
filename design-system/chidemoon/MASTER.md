# Chidemoon Design System

> Project-approved source of truth for Chidemoon's public WordPress and WooCommerce UI.
> Derived from UI/UX Pro Max recommendations, then adapted for Persian typography,
> RTL reading, Blocksy markup, the existing brand palette, and editorial commerce.

## Product direction

Chidemoon is a Persian-first home-decoration magazine and curated affiliate catalogue. The interface is content-first, warm, trustworthy, and restrained. Editorial evidence and product context take priority over decorative effects or conversion pressure.

- **Style:** warm editorial minimalism
- **Platform:** responsive web, WordPress + WooCommerce
- **Direction:** RTL by default; logical CSS properties are required
- **Motion:** subtle state feedback only; no scroll-jacking, autoplay, or decorative choreography
- **Primary action:** one clear CTA per content block, with secondary links visibly subordinate

## Semantic tokens

The runtime variables live in `themes/chidemoon-blocksy-child/assets/css/editorial-refresh.css`.

| Role | Token | Current value | Usage |
|---|---|---:|---|
| Page | `--chidemoon-paper` | `#f3f4f1` | Main page background |
| Surface | `--chidemoon-surface` | `#fffdfa` | Cards and reading surfaces |
| Muted surface | `--chidemoon-surface-muted` | `#eee8dc` | Image fallback and quiet regions |
| Primary text | `--chidemoon-ink` | `#17241f` | Body and card text |
| Secondary text | `--chidemoon-muted` | `#607069` | Metadata and supporting copy |
| Brand primary | `--chidemoon-forest` | `#123f34` | Headings, controls, strong emphasis |
| Brand deep | `--chidemoon-forest-deep` | `#092c25` | Hover states and dark surfaces |
| Accent | `--chidemoon-clay` | `#b95d3e` | Focus, links, and editorial accents |
| Supporting accent | `--chidemoon-sage` | `#a9b69e` | Quiet emphasis and depth |
| Boundary | `--chidemoon-line` | `rgba(18, 63, 52, 0.12)` | Dividers and card outlines |

Do not introduce raw one-off brand colors inside components. New states must map to a semantic token and meet WCAG contrast in their actual composed background.

## Typography

Fonts are local and declared in `assets/css/typography.css`; do not add remote font imports.

- **Display:** Estedad variable, weights 600–800
- **Body/UI:** Vazirmatn, weights 400–800
- **Body base:** at least 16px on mobile, line-height 1.8–2.15 for Persian prose
- **Measure:** 60–75 Latin-equivalent characters for long-form desktop reading; narrower on mobile
- **Headings:** balanced wrapping is progressive enhancement; never force words together
- **Tracking:** zero for Persian headings/body; no uppercase transformation
- **Numbers:** Persian digits in public dates/prices; tabular figures where alignment matters
- **Long content:** wrap URLs, IDs, and user content without breaking ordinary prose character-by-character

## Spacing and shape

Use a 4/8px rhythm and fluid clamps between breakpoints.

- Tight: 4–8px
- Component: 12–16px
- Card/section internal: 20–32px
- Section separation: 40–76px
- Radius small/default/large: `0.35rem` / `0.5rem` / `0.7rem`; deliberately restrained for the current editorial system
- Shadows communicate elevation only; use the shared soft/default shadow tokens

## Components

### Links and buttons

- Native anchors/buttons remain semantic and keyboard-operable.
- Primary controls use forest surfaces with light text; accent clay is not used as low-contrast body text.
- Interactive targets are at least 44×44 CSS px on coarse pointers when space allows.
- Hover, focus-visible, active, and disabled states must be distinguishable without layout shift.
- Icon-only controls require an accessible name; decorative arrows/icons are hidden from assistive technology.

### Editorial cards

- Cards reuse `chidemoon_blocksy_render_post_card()` and its `lead`/`compact` variants.
- Heading level follows page context: H2 directly below a page H1, H3 beneath a labelled H2 section.
- Image, category, title, excerpt, date, and action retain this information order in the DOM.
- Missing images use the reserved-ratio fallback; fabricated content is never shown.
- Hover/focus elevation is subtle and removed under reduced motion.

### Product cards and detail

- Product data comes only from public WooCommerce fields; merchant and affiliate eligibility remain Core responsibilities.
- Product images use contained rendering and a stable aspect ratio.
- Price, availability, and merchant CTA use text—not color alone—to communicate state.
- Affiliate/source disclosures remain visually distinct but do not overpower product facts.

### Forms and feedback

- Every input has a visible label and at least 44px height on mobile.
- Errors appear beside the field, explain recovery, and are announced when dynamic.
- Loading actions disable repeat submission and show progress.
- Focus is never removed or hidden beneath persistent UI.

## Responsive rules

Test at 320, 375, 768, 1024, and 1440 CSS px.

- No page-level horizontal scrolling.
- Mobile content order matches DOM and reading order.
- Story and product cards reflow before labels truncate.
- Header/off-canvas controls stay reachable and preserve browser zoom.
- Sticky side content becomes static when the content column collapses.
- Images scale to their container and reserve space before loading.

## Motion

Use the shared motion tokens only:

- Fast state feedback: `--chidemoon-motion-fast`
- Standard elevation/state change: `--chidemoon-motion-standard`
- Image treatment: `--chidemoon-motion-image`
- Easing: `--chidemoon-ease-out`

Animate only opacity and transform where practical. Under `prefers-reduced-motion: reduce`, remove decorative translation, elevation motion, and image zoom while preserving immediate state clarity.

## Accessibility baseline

- Visible 3px focus ring with adequate contrast
- Working Persian skip link to `#primary`
- Sequential heading hierarchy and labelled sections/navigation
- Keyboard access to every action; no hover-only content
- Normal text contrast at least 4.5:1; UI boundaries and meaningful graphics at least 3:1
- Meaningful images have useful alt text; decorative fallbacks are hidden
- Information is never conveyed by color alone
- Focus remains visible around sticky headers, drawers, and overlays

## Performance baseline

- Only the true above-the-fold featured image is eager/high priority.
- Below-the-fold images are lazy-loaded and retain width/height or aspect ratio.
- Local fonts use `font-display: swap`; do not preload every weight.
- Avoid third-party animation and icon dependencies for decorative UI.
- CSS effects must not cause layout shift or main-thread-heavy scroll work.

## Anti-patterns

- Emoji as structural icons
- Remote Latin fonts replacing Persian families
- Scroll-triggered storytelling, parallax, or autoplay
- Multiple primary CTAs in one card/screen region
- Placeholder-only labels or hover-only actions
- Raw colors, arbitrary shadows/radii, or one-off transition durations
- Truncating essential Persian labels when wrapping/reflow is available
- Fabricated editorial/product content

## Pre-delivery checklist

- [ ] Keyboard-only flow and Persian skip link work
- [ ] Heading outline is sequential on every template
- [ ] Focus/hover/active/disabled states are visible
- [ ] 320/375/768/1024/1440 widths have no page overflow
- [ ] Text and UI contrast meet their thresholds
- [ ] Reduced-motion removes decorative movement
- [ ] Hero image priority and below-fold lazy loading are correct
- [ ] Empty/error/loading states explain the next action
- [ ] RTL logical spacing and directional arrows are correct
- [ ] PHP lint, route smoke tests, and visual screenshots pass
