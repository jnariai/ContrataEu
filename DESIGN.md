---
name: ContrataEu
description: Working papers for a job search; every application is a dated, stamped, bilingual entry.
colors:
  ink-blue-cover: "#1f3a5f"
  on-cover: "#f1f5f8"
  cover-muted: "#b9c8d6"
  visa-page: "#e7edea"
  visa-card: "#f3f6f4"
  visa-popover: "#f8faf9"
  visa-secondary: "#d9e3df"
  visa-muted: "#dce5e1"
  visa-accent: "#d5e0db"
  ink-text: "#14202e"
  ink-text-muted: "#45586a"
  rule: "#c2cfca"
  field-stroke: "#7c908a"
  sheet-paper: "#fdfcf9"
  sheet-text: "#111827"
  sheet-text-muted: "#5f6774"
  stamp-grey: "#4c5e6f"
  stamp-violet: "#5b3e8f"
  stamp-green: "#2f6b4f"
  stamp-red: "#b3362c"
  todo-highlight: "#f6e7c8"
  todo-ink: "#6b4300"
  night-cover-ground: "#0e1722"
  night-card: "#142130"
  night-band: "#17345a"
  night-primary: "#3b6296"
  night-text: "#e3e9ee"
  night-rule: "#283a4d"
  night-sheet-paper: "#ebe8e1"
  night-stamp-blue: "#9dbce3"
  night-stamp-violet: "#bba6ea"
  night-stamp-green: "#85c7a5"
  night-stamp-red: "#f29185"
typography:
  display:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "3.5rem"
    fontWeight: 600
    lineHeight: 1.05
    letterSpacing: "-0.03em"
    fontFeature: "'cv11', 'ss03'"
  headline:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.015em"
    fontFeature: "'cv11', 'ss03'"
  title:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.55
    fontFeature: "'cv11', 'ss03'"
  item-title:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 600
    lineHeight: 1.5
    fontFeature: "'cv11', 'ss03'"
  body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
    fontFeature: "'cv11', 'ss03'"
  label:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 600
    letterSpacing: "0.11em"
    fontFeature: "'cv11', 'ss03'"
  stamp:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "0.06em"
  document-name:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  document-section:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 700
    letterSpacing: "0.02em"
  document-body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
rounded:
  sheet: "2px"
  stamp: "3px"
  md: "3px"
  lg: "4px"
  pill: "9999px"
spacing:
  gutter-phone: "16px"
  gutter-tablet: "24px"
  gutter-desktop: "48px"
  column-gap: "40px"
  section: "32px"
  page-top: "40px"
  frame-max: "1440px"
  entry-column: "272px"
  package-rail: "288px"
components:
  button-primary:
    backgroundColor: "{colors.ink-blue-cover}"
    textColor: "{colors.on-cover}"
    rounded: "{rounded.md}"
    typography: "{typography.body}"
    height: "36px"
    padding: "8px 16px"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.ink-blue-cover}"
    rounded: "{rounded.md}"
    height: "36px"
    padding: "8px 16px"
  button-secondary:
    backgroundColor: "{colors.visa-secondary}"
    textColor: "{colors.ink-text}"
    rounded: "{rounded.md}"
    height: "36px"
    padding: "8px 16px"
  button-secondary-hover:
    backgroundColor: "{colors.visa-accent}"
  input:
    backgroundColor: "{colors.visa-card}"
    textColor: "{colors.ink-text}"
    rounded: "{rounded.md}"
    height: "36px"
    padding: "4px 12px"
  nav-band:
    backgroundColor: "{colors.ink-blue-cover}"
    textColor: "{colors.on-cover}"
    height: "64px"
  status-stamp:
    backgroundColor: "transparent"
    textColor: "{colors.stamp-violet}"
    rounded: "{rounded.stamp}"
    typography: "{typography.stamp}"
    padding: "8px 12px 6px"
  status-badge:
    backgroundColor: "transparent"
    textColor: "{colors.stamp-violet}"
    rounded: "{rounded.stamp}"
    typography: "{typography.label}"
    padding: "1px 6px"
  document-tab-active:
    backgroundColor: "{colors.sheet-paper}"
    textColor: "{colors.sheet-text}"
    rounded: "{rounded.stamp}"
    padding: "8px 14px"
  document-sheet:
    backgroundColor: "{colors.sheet-paper}"
    textColor: "{colors.sheet-text}"
    rounded: "{rounded.sheet}"
    padding: "48px"
  todo-mark:
    backgroundColor: "{colors.todo-highlight}"
    textColor: "{colors.todo-ink}"
    rounded: "{rounded.sheet}"
  card:
    backgroundColor: "{colors.visa-card}"
    textColor: "{colors.ink-text}"
    rounded: "{rounded.md}"
    padding: "24px"
---

# Design System: ContrataEu

## Overview

**Creative North Star: "The Working Papers"**

ContrataEu is a ledger of applications kept the way a Brazilian worker keeps their passport and carteira de trabalho: each job is an entry, dated, inked with a stamp, marked with its language. The chrome borrows the passport's materials (an ink-blue cover band with fine guilloché security hairlines, grounds tinted from the pale green-grey visa page, small tracked field labels, 1px rules), while the document being sent sits on a warm white paper sheet, the largest and lightest thing on the page. The sheet always shows the document as it will be sent; editing swaps the Markdown source in place on the same sheet.

Density is that of an operational tool used weekly beside job boards: compact Inter, tabular figures for dates and counts, three ink values in chrome, and almost no decoration beyond the band's hairlines and the stamp. The night theme is the passport at night: a deep navy ground with lifted inks, and the sheet stays paper because it is the thing that leaves the app. The world is a reference, never a costume: no seals, crests, fake security fonts or textures outside the band.

**Key Characteristics:**
- Ink-blue cover band with guilloché hairlines on every page (app, welcome, auth); nowhere else carries pattern.
- Green-grey tinted grounds, never neutral grey.
- The document on a warm paper sheet, joined to the tab that opened it like a sheet pulled from a folder.
- Status as an inked entry stamp in carimbo inks, always readable as text.
- Small uppercase tracked field labels and 1px rules instead of boxed cards.
- Near-square corners (2 to 4px) throughout.

## Colors

A cool, document-office palette: one ink blue for authority, green-grey paper grounds, warm white for the deliverable, and five stamp inks reserved for status.

### Primary
- **Ink-Blue Cover** (`ink-blue-cover`): the passport cover and the PDF's accent. Fills the header band, primary buttons, the selected half of the language pair, checked checkboxes and the focus ring; outlines secondary actions. User-pinned: it matches the PDF deliverable.

### Secondary
- **Stamp Inks** (`stamp-grey`, `ink-blue-cover`, `stamp-violet`, `stamp-green`, `stamp-red`): carimbo inks used only for status stamps, the status line and small "Ready"/void markers. Grey is draft (dashed) and ready; violet is applied (the classic stamp ink, intentional and user-confirmed); ink blue is interviewing; green is offer; red is rejected and, dashed, withdrawn.

### Tertiary
- **TODO Highlight** (`todo-highlight` on `todo-ink`): an amber marker for what is still left to fix: `[TODO: ...]` marks on the sheet, "N to fix" counts in the package list and tab counts, and unsaved-change notes.

### Neutral
- **Visa Page** (`visa-page`): the page ground, tinted pale green-grey.
- **Visa Card / Popover / Secondary / Muted / Accent** (`visa-card`, `visa-popover`, `visa-secondary`, `visa-muted`, `visa-accent`): stepped tints of the same page for fields, menus, secondary buttons and hover fills.
- **Ink Text** (`ink-text`, `ink-text-muted`): body and secondary text on chrome.
- **Rule** (`rule`): every 1px divider, table row line and card border. **Field Stroke** (`field-stroke`) outlines inputs, selects and checkboxes so they stay findable at 3:1.
- **Sheet Paper** (`sheet-paper`, `sheet-text`, `sheet-text-muted`): the document surface and its own type colours, mirroring the PDF.
- **On Cover** (`on-cover`, `cover-muted`): text on the band; muted for inactive nav items and the account name.
- **Night** (`night-*`): the dark theme's ground, card, band, primary, text, rule, dimmed paper and lifted stamp inks. Inks lighten at night so they hold AA on the navy ground.

### Named Rules
**The Tinted Ground Rule.** Grounds are tinted from the visa page's green-grey (light) or the cover's navy (dark). Neutral #f5f5f5-style greys do not appear.

**The Ink Is State Rule.** Stamp inks mean state and nothing else: status stamps, green for done or sent confirmations and "Ready", red for errors and destructive actions. Violet appears only as the applied stamp, and in the logo, whose check is that same outcome. No ink decorates.

**The Paper Stays Paper Rule.** The document sheet is warm white in both themes (dimmed to `night-sheet-paper` at night, never inverted), because it is what gets sent.

## Typography

**Display Font:** Inter (with ui-sans-serif, system-ui)
**Body Font:** Inter
**Label/Mono Font:** Inter for labels; the platform monospace only inside the Markdown source editor.

**Character:** One self-hosted Inter (400/500/600/700) with `cv11` and `ss03` on, used as an official form uses type: semibold titles, small tracked uppercase labels, tabular figures for dates, counts and versions. User-pinned to match the PDF.

### Hierarchy
- **Display** (600, 2.5rem phone / 3.5rem from sm, 1.05, -0.03em, balanced): the welcome hero headline only.
- **Headline** (600, 1.75rem, tight, -0.015em): the page's own h1 (dashboard, settings, role title on the application page).
- **Title** (600, 1.125rem): section headings inside a page and dialog titles.
- **Item title** (600, 0.9375rem): list rows, document labels.
- **Body** (400, 0.875rem in chrome; 1rem for page descriptions and phone inputs): everything else.
- **Label** (600, 0.6875rem, 0.11em, uppercase): field labels and small group headings (Status, Output language, Your package, settings groups).
- **Stamp** (700, 1.375rem, 0.06em, uppercase) over a 0.6875rem 0.1em tabular date line: the entry stamp.
- **Document** (name 700 1.75rem; section 700 0.875rem in ink blue with a 1px ink-blue underline; body 0.875rem/1.6, 75ch): the rendered sheet, mirroring the PDF template.
- **Source** (platform monospace, 0.8125rem, relaxed): the Markdown editor on the sheet only.
- **Wordmark** (1.0625rem, -0.015em; "Contrata" 500, "Eu" 800): the logo's name, on the band and in the phone menu.
- **Email** (px, because mail clients ignore rem): heading 22px 600, body 15px/1.6, wordmark 17px, subcopy 13px, footer 12px; Inter with system fallbacks, never a web font loaded from a third party.

### Named Rules
**The Field Label Rule.** The tracked uppercase label names a field or a group, the way documents label their fields. It is never a kicker or eyebrow placed above a heading.

**The Tabular Figures Rule.** Dates, counts, versions and percentages use tabular figures.

## Layout

One page frame for every signed-in page: max 1440px, centred, with 16px / 24px / 48px side gutters at phone / sm / lg, matching the band exactly so content edges never move between pages. Pages open with 32px (40px from lg) of top space; sections stack at 32px.

The application page is three columns at xl: a 272px entry column (title, company, place, stamp, status line, status select, language pair, version line), the document column (tabs over the sheet, the widest element), and a 288px package rail (package list, download, generate, run state), 40px apart. At lg the rail folds under the entry column; on a phone the order is entry, documents, then the rest, with scrollable tabs (fading at the right edge), a full-width sheet and a sticky bottom action bar (Copy, Preview, Download) on a blurred ground.

Lists are ruled, not boxed: rows divide with 1px rules, settings navigation is a ruled list under its field label. The welcome page splits a text column from a 34rem sample sheet, with features in a two-column ruled grid.

## Elevation & Depth

Chrome is flat and divided by 1px rules. Depth is reserved for paper and for floating layers: the document sheet casts a soft drop as if lying on the page, and menus and dialogs lift above it. Shadows are navy-tinted, never black.

### Shadow Vocabulary
- **Sheet lie** (`box-shadow: 0 1px 0 var(--border), 0 14px 30px -16px rgb(20 32 46 / 0.32)`): the document sheet. The welcome sample sheet uses a deeper version (`0 1px 0 var(--border), 0 28px 50px -24px rgb(20 32 46 / 0.4)`); the auth card a lighter one (`0 14px 30px -18px rgb(20 32 46 / 0.3)`).
- **Menu lift** (`box-shadow: 0 10px 24px -12px rgb(20 32 46 / 0.35)`): dropdown menus and select popovers.
- **Dialog lift** (`box-shadow: 0 24px 48px -20px rgb(10 29 51 / 0.45)`) over a navy scrim (`#0a1d33` at 55%).

### Named Rules
**The Only Paper Floats Rule.** At rest, only paper (the sheet, the sample, the auth card) and open overlays cast shadows. Cards, rows and buttons are flat.

## Shapes

Corners are near square, as cut paper and rubber stamps are: 2px for paper (sheet, tooltip, TODO marks), 3px for controls, stamps, tabs and checkboxes, 4px at most. Full rounding appears only on status-line dots and the small "N to fix" count pills. The entry stamp is rotated -1.5deg with a 2px ink border; dashed borders mark the provisional (draft) and the voided (withdrawn). Active document tabs drop their bottom border and join the sheet with no top corners between them.

## Components

### Buttons
Solid and quiet: semibold Inter, flat, colour-only transitions.
- **Shape:** 3px corners; heights 32 / 36 / 40px.
- **Primary:** ink-blue fill, white text, 16px side padding; hover at 88% fill. One per area (Generate again, Get started, New application).
- **Outline:** 1px ink-blue border at 70%, ink-blue text; hover adds an 8% ink-blue wash. Used for secondary actions such as Download, Edit, Copy and Log in; in the dark theme it switches to the lifted ink blue.
- **Secondary / Ghost:** visa-tint fill or none, hover to the accent tint.
- **Focus:** 2px ring-coloured outline, 2px offset. Disabled at 50% opacity with a not-allowed cursor.

### Status Stamp (signature)
- **Entry stamp:** the status in stamp type over a tabular date and language line, 2px ink border, 3px corners, rotated -1.5deg. When the status changes on the page it is pressed once (320ms, `cubic-bezier(0.16, 1, 0.3, 1)`, from 118% scale to rest); never on load, and instant under reduced motion.
- **Compact badge:** 1.5px ink border, 0.6875rem bold tracked uppercase; used in lists and the status select.
- **Status line:** the ordered path draft, ready, applied, interviewing, offer as ink-blue dots on a hairline, the current dot filled in its stamp ink and named underneath; a closed application greys the line and names how it closed.

### Language Pair
EN and PT-BR as one segmented mark with a 1px ink-blue border; the language in use is filled ink blue.

### Document Tabs and Sheet (signature)
- **Tabs:** text tabs on a 1px rule with optional counts; the active tab turns to paper and joins the sheet. Arrow keys, Home and End move between tabs.
- **Sheet:** warm paper, 2px corners, sheet-lie shadow, 20px phone padding up to 48 to 64px wide. It always renders the document; Edit swaps in the Markdown source (monospace, 0.8125rem) in place on the same sheet.
- **TODO mark:** amber highlight with a 2px halo, semibold amber ink.

### Cards / Containers
- **Corner Style:** 3px. **Background:** visa card. **Border:** 1px rule. **Shadow:** none. **Padding:** 20 to 24px. Used sparingly (dashboard onboarding, empty states with a dashed field-stroke border); most grouping is ruled lists.

### Inputs / Fields
- **Style:** 1px field-stroke border, visa-card fill, 3px corners, 36px height, 12px side padding; 1rem text on phones, 0.875rem from md.
- **Focus:** border turns ring colour plus a 2px ring outline at 40%, 1px offset.
- **Error / Disabled:** destructive border; 50% opacity.

### Navigation
The navy band, 64px tall, guilloché hairlines, logo at left: the stamp mark (a carimbo rotated -10deg and pressed twice: the outer frame in currentColor, the inner frame and check a hair off register in the applied violet, lifted to `band-ink` #bba6ea on the band; 24px) and the wordmark, "Contrata" in 500 and "Eu" in 800 at 1.0625rem, -0.015em. favicon.svg drops the inner frame so it stays open at 16px; favicon.ico (16/32/48) and apple-touch-icon.png (180, on-cover frame and band-ink impression on the band colour) and mail-stamp.png (the band version, for emails) are rendered from those SVGs, never drawn separately. Desktop nav items are 0.875rem in cover-muted, turning on-cover on hover; the current item is semibold on-cover with a 2px on-cover underline at the band's bottom edge. The account name sits at the right as a plain text trigger. On the welcome page, the band's call to action inverts: an on-cover fill with band-coloured text. Below lg, a menu button opens a left sheet with a ruled list; the current item is ink blue with a 2px underline.

## Do's and Don'ts

### Do:
- **Do** top every page with the navy band and keep signed-in content inside the shared 1440px frame and its 16 / 24 / 48px gutters.
- **Do** show the document rendered on the paper sheet; Edit swaps in the source in place.
- **Do** express status as a stamp whose text names the status, in its carimbo ink (violet for applied).
- **Do** use the tracked uppercase field label for fields and groups, and 1px rules to separate them.
- **Do** use tabular figures for dates, counts and versions, and Inter for everything.
- **Do** keep the sheet paper-coloured in the dark theme.

### Don't:
- **Don't** put a kicker or eyebrow label above a heading.
- **Don't** carry status by colour alone; the stamp text is the status.
- **Don't** use neutral greys for grounds; tint from the visa page or the navy cover.
- **Don't** use stamp inks decoratively or put guilloché anywhere but the band.
- **Don't** add passport costume: seals, crests, security fonts or paper textures.
- **Don't** stack shadowed cards; only paper and open overlays float.
- **Don't** restyle the PDF template from here; it is a separate system that the sheet mirrors.
