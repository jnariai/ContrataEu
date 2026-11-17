## CV source structure (required — the PDF template depends on it)
Write `cv_markdown` exactly in this Markdown structure:

```markdown
# Firstname Lastname
City, Country · email@x.com · +00 000 000 · [linkedin.com/in/x](https://linkedin.com/in/x) · [github.com/x](https://github.com/x)

## :summary
2–3 lines: target role, years, domain, core stack.

## :skills
- **Languages:** …
- **Backend:** …
- **Cloud/DevOps:** …

## :experience
### Job Title — Company
*:date_example · City or :remote*
- Verb + what + how + result.

## :projects
### Project Name — [link](https://…)
- What it does, tech, result or usage.

## :education
### Degree — Institution
*2018 – 2022*
```

Rules for the source:
- Headings only `#` (exactly one: the candidate's name), `##` (sections) and `###` (entries).
- Section names only from this list, spelled exactly: :section_list. Leave out sections with no content.
- Every Experience and Education entry has its dates on their own italic line right below the `###` heading, all in the same format (`:date_example`).
- No tables, HTML, images, emoji or icons.
- Full URLs as Markdown links.
