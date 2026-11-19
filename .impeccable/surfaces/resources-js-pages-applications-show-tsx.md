---
version: 1
slug: "resources-js-pages-applications-show-tsx"
primary_target: "resources/js/pages/applications/show.tsx"
related_targets: ["resources/js/pages/dashboard.tsx","resources/js/pages/welcome.tsx","resources/js/pages/auth/login.tsx"]
---

# Surface: ContrataEu app — application page (anchor), inherited by dashboard, settings, welcome, login

Mode: Operate (application page, dashboard, settings, login); welcome is Persuade inside the same world.
Audience/job: the author, weekly, laptop with job boards open, phone for copying answers and changing status. Turn a JD into a package that is visibly ready to send.
Constraints: pinned Inter, ink blue #1F3A5F, document on a warm white sheet; PDF template untouched; pt-BR strings; WCAG AA; no new dependencies. Anti-goals: word-processor clone, dev-tool cosplay, passport costume.
Decisions: the sheet shows the rendered document, Edit swaps in the Markdown editor in place; stamps use carimbo inks (violet applied, navy interviewing, green offer, red void) — violet intentional.

## Direction contract

THESIS: Every application is an entry in your working papers: dated, stamped, bilingual. Refuses the shadcn card stack where the document sits as raw source under alerts.

OWN-WORLD: Brazilian passport and carteira de trabalho. Navy #1F3A5F band with fine guilloché hairlines; grounds tinted from the visa page (pale green-grey), never neutral grey; warm white sheet for documents; three ink values and 1px rules in chrome; Inter with small tracked field labels and tabular figures; status as an inked entry stamp. Dark theme is the passport at night: deep navy ground, the sheet stays paper.

STORY: The visitor sees which job this is, its stamp and language, reads the tailored document as it will be sent, sees what is left to fix, then copies, downloads or stamps it applied.

FIRST VIEWPORT: Navy band (mark, Applications, New application, Settings, account). Left column ~300px: role title, company, place, entry stamp, ordered status line, EN | PT-BR pair, version line. Centre: document tabs with counts over the rendered sheet, the largest element. Right rail ~300px: package list with each document's TODO count or Ready, then Download (secondary, "N to fix" while TODOs remain) and Generate again; run progress and failures live here. Phone: header block with stamp, scrollable tabs, full-width sheet, sticky bottom bar (Copy, Preview, Download). Signature interaction: changing status presses the stamp once (short scale/ink settle), instant under reduced motion.

FORM: Working Papers (grounded, kept from the first hand at the user's request; chosen on the re-rolled hand). seed:d8263d36b933754b

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
