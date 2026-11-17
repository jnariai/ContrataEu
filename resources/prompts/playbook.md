# Developer CV Screening Rules

Reference for AI agents writing or reviewing software developer CVs.

## Screening pipeline
1. ATS: parses the CV into fields and applies knockout questions (work authorization, location, years of experience, sponsorship). Recruiters search it by keyword. AI ranking reorders candidates and rarely rejects outright.
2. Recruiter skim (6–30s), usually non-technical: title, company, location/authorization, years, stack match, progression, links.
3. Hiring manager (1–3 min): depth, relevant problems, ownership, code evidence, consistency across CV/LinkedIn/GitHub.
4. Contact.

The top third of page 1 decides most outcomes.

## Hard rules
- NEVER invent or inflate experience, titles, dates, metrics, or skills. Every line must survive interview questioning and background checks.
- Missing data → ask the user or leave a `[TODO: …]` placeholder. Do not guess.
- One page for under ~10 years of experience. Two pages max for senior/staff.
- Single column. Standard headings. No tables, text boxes, icons, skill bars, or images.
- Contact info goes in the body, not in the header/footer.
- Same date format everywhere: `Mar 2022 – Present`.
- Output must be a text-based PDF or DOCX. It must pass a copy-paste-to-plain-text test.

## Structure
```
Name
City, Country · email · phone · LinkedIn · GitHub · portfolio
Summary (optional, 2–3 lines, specific: role + years + domain + core stack)
Skills (grouped: Languages / Frontend / Backend / Cloud-DevOps / Data)
Experience (reverse chronological; 3–5 bullets recent, 1–2 older)
Projects (put above Experience for juniors and career changers)
Education / Certifications
```

## Keywords
- Extract technologies, tools, methods, and responsibilities from the job description (JD).
- Mirror the JD's exact spelling ("PostgreSQL", not "Postgres").
- Give acronym + full form once: "Amazon Web Services (AWS)".
- Put keywords inside experience bullets, not only in Skills.
- Include only skills the candidate can discuss for 5+ minutes.
- No keyword stuffing, no hidden text, no 40-item tech lists.

## Bullet formula
`Verb + what + how/tech + measurable result`
(Google XYZ: Accomplished X, as measured by Y, by doing Z.)

Weak: "Responsible for backend API."
Strong: "Redesigned order API in Go, cutting p95 latency 800ms → 120ms for 2M daily requests."

Metric sources: scale (users, req/s, data), speed (latency, build time), money (cost, revenue), time saved, quality (bugs, uptime, coverage), counts (features, services, people mentored).

- Start with a strong past-tense verb: Built, Designed, Migrated, Reduced, Led, Automated.
- Banned openers: "Responsible for", "Helped with", "Worked on".
- Honest estimates use "~" and require a known basis.
- Most bullets should show a result. Not all need a number.

## Making it stand out
- Job title at the top matches or is close to the target role.
- Lead with the experience most relevant to the JD's core problem domain.
- Show progression and growing scope.
- Show ownership with specifics (designed, led, owned + what + result).
- Add a one-line company context if the company is unknown ("fintech, 50 employees").
- GitHub: pinned repos, real READMEs, deployed demos, tests.
- Keep CV, LinkedIn, and GitHub consistent.
- Tailor every application from a master CV.

## Red flags to remove
- Typos, inconsistent formatting.
- Generic AI phrasing: "passionate", "results-driven", "leveraging cutting-edge", "dynamic".
- Tech soup, skill ratings, hobbies with no relevance, "References available on request".
- Unexplained job hopping (label contracts as "Contract").
- Personal data not expected in the region (age, marital status, ID numbers). Photo only where it's the regional norm.
- Broken links, empty or tutorial-clone GitHub.
- Mismatch with the target seniority.

## Cover letter rules
- 150–300 words, 3–4 short paragraphs.
- P1: role + why this company (specific, researched detail).
- P2–3: 2–3 proofs mapped to the JD's top requirements, using the same metrics as the CV.
- P4: short close with availability.
- No repeating the CV verbatim, no clichés, no invented claims.
