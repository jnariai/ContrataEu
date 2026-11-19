# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Primarily the author: a developer in an active, weekly job search, applying both to local Brazilian jobs (pt-BR) and to remote/international jobs (English). The job they are doing is "turn this job description into a ready-to-send application package, then get back to applying". Other developers with the same routine are a secondary audience: sign-up is public, but design decisions serve the author's own workflow first.

## Product Purpose
ContrataEu removes the boring, manual part of tailoring an application. Today that means gathering base files, pasting them with a prompt and the job description into an AI chat, and copying results back; the boredom lowers the number of applications sent. ContrataEu turns "paste JD → ready-to-send package" into a task of about a minute: a tailored CV (Markdown source, PDF deliverable), an optional cover letter (text + PDF), an optional recruiter message and answers to screening questions, plus a report (match table, keyword coverage, gaps, TODOs, interview prep).

Success: more applications sent per week, each one faithful to the candidate's real experience, and the ability to look back before an interview at exactly what was sent.

## Positioning
It runs on the user's own rules, prompts and AI key (bring your own key), not a black-box generator: a shipped screening playbook and default prompts the user can override section by section, while the app owns the output contract so documents always parse and render. It never invents experience the user doesn't have. One master CV produces English or Brazilian Portuguese applications, with each language's conventions applied.

## Operating Context
- Setup once: candidate profile, master CV (pasted Markdown or imported from a PDF), optional base cover letter and recruiter-message inspiration, one or more AI keys (one active), optional prompt overrides.
- Per job: create an application (company, role, URL, location, work mode, output language, notes, JD, extras, up to 10 screening questions) → Generate (queued, polled; takes from seconds to a few minutes depending on the provider) → review and edit text, fix `[TODO: …]` markers → copy texts and download PDFs → apply on the job site → set status by hand.
- Used side by side with job boards, LinkedIn and application forms, often copying one answer at a time into another tab. **Mobile matters**: the user will also work from a phone (reading the package, copying answers, changing status), not only from a desktop.
- Later: reopen an application before an interview to reread the sent documents and the report.

## Capabilities and Constraints
- Laravel + Inertia + React (TypeScript), Tailwind v4, Radix-based UI components, header navigation (Dashboard, New application) plus settings pages.
- UI in English and Brazilian Portuguese; every visible string is translated (`lang/pt_BR.json`); dates and numbers formatted with `Intl` in the user's locale.
- AI providers: Anthropic, Gemini, OpenRouter, Groq, OpenAI, DeepSeek, and a generic OpenAI-compatible endpoint. Keys are encrypted and only their last 4 characters ever reach the browser.
- Up to 3 successful versions kept per application; failed runs kept separately as a short failure log.
- PDFs: one template per document type, single column, ATS- and recruiter-friendly, Inter, accent `#1F3A5F`. This is the deliverable's look, fixed by the spec, and separate from the app UI.
- Terminology: Application, Generation / version, master CV, AI key ("active" key), prompt override, playbook, report, screening question, output language.
- Non-goals: scraping job boards, auto-applying, writing experience the user does not have.
- Detailed spec and decisions log: `docs/SPEC.md`.

## Brand Commitments
- Name **ContrataEu**, a parody of the Brazilian meme "contrata eu" ("hire me"); technical name `contrataeu`. The joke lives in the name only: interface copy is calm, precise and professional, because the product handles the user's CV, job search and API keys.
- Mark: an entry stamp (carimbo) inked at an angle and pressed twice, ink-blue frame with the violet "applied" check off register; wordmark "Contrata" medium + "Eu" extra-bold in Inter (app logo and favicons, since 2026-11-18; replaced a CV page with a check).

## Evidence on Hand
- No users, testimonials, customers or usage metrics exist. Never invent them.
- Real content available: the default playbook and prompts (`docs/defaults/`, `resources/prompts/`).

## Product Principles
1. Speed to a sendable package: every screen shortens the path from JD to "applied".
2. Truthful output: never add facts; surface gaps and `[TODO: …]` markers so the user fixes them before sending.
3. The user stays in control: their prompts, their key, editable results, versions they can go back to.
4. Show what was sent: past applications stay readable and exact, for interview prep.
5. Bilingual by design: English and Brazilian Portuguese are equal citizens in both the documents and the UI.

## Accessibility & Inclusion
Must work well on phone-sized screens as well as desktop. Full English and Brazilian Portuguese UI. No specific WCAG level has been set.
