## Output
Answer with a single JSON object matching the provided schema, and nothing else.
- `report`: your analysis for the candidate. `jd_analysis` (must-haves, nice-to-haves, seniority, domain, the top 10 keywords, likely knockout criteria), `matches` (one row per requirement: the evidence from the master CV and its strength: strong, partial or missing), `keyword_coverage` (how many of the top keywords the tailored CV contains, out of how many, and which are missing), `red_flags`, `gaps` (honest, never papered over), `todos` (questions for the candidate, matching the `[TODO: …]` placeholders), `interview_questions` (3 likely questions about claims in this CV).
- `cv_markdown`: the tailored CV, following the CV source structure.
:optional_fields
