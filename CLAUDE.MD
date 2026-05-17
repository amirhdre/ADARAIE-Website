# Amir Daraie WordPress Site — Project Handoff

**Site:** https://adaraie.com
**Owner:** Amir Hossein Daraie, PhD candidate at Johns Hopkins University (Sarma Lab — intracranial EEG / ML research). Starting Apple internship Summer 2026 as "Sensing Data & Interaction SW Intern" in Cupertino.
**Stack:** WordPress 6.9.4 on EasyWP hosting, Certy theme, WPCode snippets for all custom code.

---

## High-level architecture

Custom code lives entirely in **WPCode snippets** (no theme file edits, no functions.php hacking). Six active snippets:

| Snippet name | Location | Purpose |
|---|---|---|
| `liquid css` | Site Wide Header | Main Liquid Glass theme CSS (~1000 lines) |
| `Liquid Glass SVG Filter` | Site Wide Header | SVG filters for backdrop distortion |
| `Custom Cursor` | Site Wide Header | Dot+ring cursor with GPU acceleration |
| `Moments Page CSS Snippet` | Site Wide Header | Polaroid timeline gallery CSS (~1500 lines) |
| `Moments Timeline Gallery` | Site Wide Footer | JS that renders /moments page |
| `Moments Render Function` | Run Everywhere (PHP) | Injects window.MOMENTS_DATA from WordPress |

**Active WordPress plugins** (all confirmed stable): Contact Form 7, One Click Demo Importer, Akismet, WPCode (Insert Headers and Footers), Custom Post Type UI, Appointment Calendar.

**Permanently abandoned:** ACF (Advanced Custom Fields) — caused two "critical error" crashes. Recovery via wp-admin Plugins page or by renaming `/wp-content/plugins/advanced-custom-fields/` folder in EasyWP File Manager. DO NOT reinstall ACF.

---

## Moments CMS — fully functional, all WordPress-core

**No custom field plugins** used. Everything maps to built-in WordPress fields:

| Moment property | Where it lives |
|---|---|
| Event title | Post title |
| Subtitle | Post excerpt |
| Year + month | Post published date (PHP derives season: Mar-May=Spring, Jun-Aug=Summer, Sep-Nov=Fall, Dec-Feb=Winter) |
| Photos | WordPress Gallery block inside post body |
| Photo captions | Each image's Caption field in Media Library (NOT inline in editor — captions read from `post_excerpt` of the attachment) |
| Categories | `moment_category` taxonomy: Lab, Conferences, Internships, Teaching, Travel, Community, Milestones, Personal |
| Cities | `moment_city` taxonomy: Baltimore, San Diego, San Francisco, Cupertino, Tehran, Boston, Vancouver |

**Currently published moments:** 1 (Apple AI/ML Internship + Day Trip to San Francisco — actually looks like SF day trip is the one rendered in source).

**Workflow for adding new moments:**
1. WordPress admin → Moments → Add New
2. Title = event name
3. Body editor → + → Gallery block → upload photos
4. For each photo: set caption in Media Library (NOT inline)
5. Right sidebar: set Published date for year/month
6. Excerpt panel: subtitle text
7. Categories: check Moment Categories
8. Cities: check appropriate city
9. Publish

PHP snippet parses `core/gallery` blocks via `parse_blocks()`, with extensive safety guards (`function_exists`, `taxonomy_exists`, `is_wp_error`, type checks, default fallbacks). Bails silently on any error rather than crashing the page. Only fires on `is_page('moments')`.

---

## Critical user-facing decisions made

[DECISION] **Mobile layout: KEEP horizontal flex-row on <600px** instead of stacking column. Year nav becomes thin 38px sticky sidebar on left. Original column-stacked design caused iOS scroll capture bugs — three nested horizontal scrollers stacked vertically confused iOS's gesture system.

[DECISION] **Use `body.moments-active` class** (added by JS based on `/moments` URL match) instead of `page-id-150` for CSS targeting. Survives WordPress ID changes.

[DECISION] **PhotoSwipe v5** over alternatives (Fancybox, GLightbox, Lightgallery) — MIT license, no jQuery, ES modules, best iOS touch support. Loaded dynamically from `https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/` only on /moments page.

[DECISION] **Polaroids as `<a>` tags** (not `<div>`) for PhotoSwipe compatibility. PhotoSwipe requires `href` + `data-pswp-width`/`data-pswp-height` attributes.

[DECISION] **Photos stored in WordPress core Gallery block**. Captions in Media Library attachment Caption field. Avoids ALL custom field plugin dependencies.

[DECISION] **`overflow-x: clip` instead of `hidden`** on mobile wrappers. `clip` prevents visual overflow but does NOT trap touch events the way `hidden` does — critical iOS Safari fix.

[DECISION] **Manual JS touch swipe handler** in the polaroid scroller. Listens to raw `touchstart`/`touchmove`/`touchend` and manually drives `scrollLeft`. Bypasses iOS scroll quirks entirely. Includes momentum/inertia with velocity tracking and decay animation.

[DECISION] **Tap-to-scroll arrow buttons** (left/right glass pill buttons) on every polaroid row. Always visible on mobile right button. Primary navigation fallback in case swipe is finicky.

---

## Pending issues (latest unresolved item)

### CRITICAL — `.crt-paper` background still showing on mobile /moments

User has been complaining: a translucent white gradient/backdrop is still visible behind the Moments content on mobile, even after multiple iterations adding `background: transparent !important` to every wrapper.

**Root cause finally diagnosed** from the page source: The `liquid css` snippet (main one) has this rule in Part 8 "Content Sections":

```css
body .crt-paper {
  position: relative;
  isolation: isolate;
  background:
    linear-gradient(135deg,
      rgba(255, 255, 255, 0.45) 0%,
      rgba(255, 255, 255, 0.30) 100%) !important;
  backdrop-filter:
    url(#liquid-glass-distortion-strong)
    blur(20px)
    saturate(200%)
    contrast(108%);
  ...
}
```

This rule has specificity **0,0,1,2** and `!important`. It's WINNING the cascade battle on mobile.

**Fix provided to user (last message):** Paste at bottom of Moments Page CSS Snippet:

```css
@media (max-width: 991px) {
  body.moments-active .crt-paper,
  html body.moments-active .crt-paper {
    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
  }

  body.moments-active .crt-paper::before,
  html body.moments-active .crt-paper::before {
    display: none !important;
    content: none !important;
    background: none !important;
  }
}
```

Specificity `0,0,1,3` beats `0,0,1,2`. PENDING USER CONFIRMATION whether this finally fixes it.

### Also pending — empty Moments menu icon class

In the source: `<span class="crt-icon crt-icon-">` — there's an empty Certy icon class (trailing dash). User should fix in wp-admin → Appearance → Menus → Moments menu item → set CSS class to `crt-icon-camera` or similar Certy icon class.

A workaround SVG icon for the Moments menu item is already in `liquid css`:

```css
#crtNav a[href*="/moments"] .crt-icon,
#crtNavSm a[href*="/moments"] .crt-icon {
  -webkit-mask-image: url("data:image/svg+xml;utf8,<svg ... rect+circle camera shape...");
  background-color: currentColor;
  ...
}
```

---

## Major problems solved during this session

[COMPLETED] **iOS portrait swipe broken at <600px** — narrowed down to phone width. Layout was switching to column-stacked on <600px breakpoint which created three nested horizontal scrollers (year nav strip, filter pills, polaroid row) stacked vertically. iOS confused which one to give horizontal pan to. Fix: keep horizontal flex layout on mobile, year nav becomes 38px sticky sidebar.

[COMPLETED] **Top header had less padding on /moments vs homepage** — Part F of Moments CSS was stripping `padding-top` and `margin-top` from `.crt-wrapper`, which also affected the top logo+icon header. Fix: removed those overrides; added explicit `padding-top: 1.5rem-2rem` on body.moments-active across breakpoints; added `margin-bottom` between logo and icon nav row.

[COMPLETED] **Spring 2026 sticky year text bleeding through page title** — fixed via z-index layering (header z-index 5, content 2, nav 1) and solid background on `.moments-header`.

[COMPLETED] **Polaroid sizing iterations** — went from 140px (too small) → 175px → 200px → final: 240px desktop, 210px tablet, 200px phones, 175px tiny phones. Captions at 1.15rem Caveat font, mobile bumped to 1.1rem.

[COMPLETED] **PhotoSwipe v5 lightbox integration** — fully working. Tap polaroid → opens dark elegant fullscreen gallery with swipe/arrow-key/Esc navigation. Custom CSS makes it match the site aesthetic (radial dark gradient backdrop, Caveat caption font, glass arrow buttons). Re-initializes on filter change.

[COMPLETED] **Active Moments icon in right floating nav** — added comprehensive selectors targeting every possible "active" class Certy might use (`.current-menu-item`, `.current_page_item`, `[aria-current="page"]`, etc.) plus `body.moments-active` URL-based fallback. Dark navy color (`#1f2937`) with drop shadow and scale(1.12).

[COMPLETED] **Engineering · Science · Design logo overflowing viewport on mobile** — fixed via:
```css
@media (max-width: 600px) {
  #crtLogo { font-size: 1.1rem !important; white-space: normal !important; }
  .crt-head-inner, .crt-head-row, #crtHeadCol1 { overflow: hidden !important; max-width: 100% !important; }
}
```

[COMPLETED] **Edge-to-edge mobile layout** — used the CSS trick `margin-left: calc(-50vw + 50%); width: 100vw` to break out of nested theme containers and reach full viewport width. Combined with transparency overrides on all wrappers.

---

## Site URLs and critical paths

- Site: https://adaraie.com
- /moments page: https://adaraie.com/moments
- WordPress admin: https://adaraie.com/wp-admin
- Homepage edit: https://adaraie.com/wp-admin/post.php?post=5&action=edit (page-id-5)
- File Manager emergency path: `/wp-content/plugins/advanced-custom-fields/` (rename if accidentally reinstalls)
- PhotoSwipe CDN: https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/

---

## Homepage structure (page-id-5)

Single Custom HTML block containing 4 sections:
1. **About** — leads with Apple internship announcement (Summer 2026)
2. **Skills** — 3-column grid: Machine Learning, Programming, Neural Data
3. **Experience** — timeline with 4 entries, each with logo tile in frosted glass:
   - Apple (Summer 2026) — Sensing Data & Interaction SW Intern
   - JHU (2022-Present) — PhD Candidate, Biomedical Engineering, Sarma Lab
   - Dassault Systèmes (Summer 2025) — AI Research Intern, San Diego
   - RoboCup & Sharif University (2015-2022) — BSc in EE, competitive robotics
4. **Contacts** — email: adaraie1@jhu.edu, plus LinkedIn, X, GitHub, Instagram

References and Blog sections were removed per user request.

**Email of record:** `adaraie1@jhu.edu`

---

## Key code locations

### Moments Render Function (PHP) — `wp_footer` hook

Reads Moment posts via `WP_Query`, derives year/season/period from post date, extracts photos from Gallery blocks via `parse_blocks()`, builds `window.MOMENTS_DATA` and `window.MOMENTS_CATEGORIES` JS globals. Heavy safety guards. Only runs on `is_page('moments')`.

### Moments Timeline Gallery (JS) — Site Wide Footer

`DOMContentLoaded` handler that:
1. Bails if not on `/moments` (regex match `/^\/moments\/?$/`)
2. Adds `body.moments-active` class
3. Reads `window.MOMENTS_DATA` and `window.MOMENTS_CATEGORIES`
4. Calls `renderTimeline('all')` which:
   - Builds year/period nav HTML
   - Builds filter pill HTML
   - Renders each event with polaroid row
   - Injects everything into `.crt-paper-cont.paper-padd`
   - Loads PhotoSwipe dynamically (only first time)
   - Wires up scroll fades, scroll buttons, manual touch swipe handler, year nav highlighting, smooth scroll, filter pill clicks

### Moments Page CSS Snippet — Site Wide Header

Organized in Parts A-I plus appended overrides:
- Part A: Page Layout (sticky nav + content)
- Part B: Header
- Part C: Event Block
- Part D: Horizontal Scroll + Fade Edge
- Part E: Individual Polaroids
- Part F: Page Shell (hide sidebar, full width)
- Part G: Category Filter Pills
- Part H: Scroll Buttons
- Part I: Responsive (tablet, mobile, tiny phones)
- + PhotoSwipe gallery styling
- + Multiple appended override blocks for mobile transparency, edge-to-edge layout, etc.

### liquid css (main CSS) — Site Wide Header

Liquid Glass theme. Sections:
1. Page Background (multi-source gradient)
2. Kill theme backgrounds
3. Profile card — high intensity liquid glass
4. Avatar — glass-style ring
5. Name + tagline
6. Social icons — mini liquid glass pills
7. Download CV button — dark liquid glass
8. **Content sections — strong glass** ← THIS IS THE CULPRIT for the latest pending issue
9. Typography polish
10. Mobile responsive padding
- Moments menu item — custom SVG icon
- Global mobile fixes
- Active nav item — works regardless of which selector

### Liquid Glass SVG Filter — Site Wide Header

Two SVG filters: `liquid-glass-distortion` (for profile card) and `liquid-glass-distortion-strong` (for content papers). Use `feTurbulence` + `feGaussianBlur` + `feDisplacementMap` for refraction effect.

---

## Common gotchas

1. **EasyWP cache** is aggressive — must clear via admin bar "Clear Cache" button after CSS changes
2. **iOS Safari** caches even harder — force-quit Safari + Settings → Apps → Safari → Clear History and Website Data
3. **WPCode** snippets need explicit "Update" click — toggling Active alone doesn't save
4. **Specificity wars** with Certy theme — sometimes need `html body.moments-active .selector` to win against theme rules
5. **Polaroids must be `<a>` tags** for PhotoSwipe — DON'T change back to `<div>`
6. **Captions** must be set in Media Library Caption field, NOT inline in Gallery block (inline doesn't update `post_excerpt`)
7. **`overflow-x: hidden`** on mobile parents BREAKS iOS horizontal scroll — must use `clip`

---

## Skipped/not yet done

- **Replace Certy demo Portfolio section** on homepage (Garden/Tree/Cactus) with real research projects, papers, GitHub repos
- **Migrate remaining moments** — user has photos for many events but hasn't created Moment posts yet:
  - Dassault 2025 internship
  - JHU Year 3 (2025)
  - JHU Year 2 (2024)
  - Baltimore 2023
  - Started PhD 2022
  - Moved to US 2022
  - RoboCup 2019
  - RoboCup Championships 2018
  - Undergrad 2017
  - High school 2015
- **Test PhotoSwipe lightbox on iPhone** — works on desktop, untested on iPhone after all the iOS scroll fixes
- **Fix empty `crt-icon-` class on Moments menu** — see above

---

## User's communication style

Amir is technical, gives clear feedback, uses voice-to-text (so messages sometimes have typos or unusual phrasings). Often asks "Give me the full CSS code" when an iterative change has been suggested — needs entire file, not just diff. Sends screenshots when something visual is off. Tests on iPhone Plus / Pro Max in portrait mode (the primary problem device). When something is fixed he says "Great, now..." — when broken he says "It haven't been fixed" or "still doesn't work" with screenshots.

Be ready to provide complete file replacements rather than just diffs, since he prefers copy-paste over patching.

---

## Final state at end of session

- Site is functional on desktop, iPad, iPhone landscape
- /moments page works fully with PhotoSwipe, filters, mobile-responsive layout
- LAST PENDING fix is the `.crt-paper` background gradient overriding mobile transparency — fix has been provided, awaiting user verification
- Homepage Experience section has real company logos
- Right floating nav cleaned up (References/Blog removed)
- Top header padding on /moments matches homepage
- Mobile portrait scroll works via JS touch handler + scroll buttons
- Engineering · Science · Design logo no longer overflows
