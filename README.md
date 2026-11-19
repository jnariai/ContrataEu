# ContrataEu

Paste a job description and get a ready-to-send application package in about a minute: a tailored CV, an optional cover letter and recruiter message, answers to the job's screening questions, and a report on how well you fit the role.

The name is a nod to the Brazilian meme *"contrata eu"* ("hire me").

## Highlights

- **Bring your own AI key.** Generations run on your account with Anthropic, OpenAI, Google Gemini, OpenRouter, Groq, DeepSeek or any OpenAI-compatible endpoint. Keys are encrypted at rest, and only their last four characters ever reach the browser.
- **Never invents experience.** Everything comes from your master CV. When a fact is missing, such as a metric or your notice period, the output gets a `[TODO: …]` placeholder and the report asks you for it.
- **Your rules.** A screening playbook and default prompts ship with the app, and you can override them section by section. The app keeps control of the output format, so documents always parse and render.
- **English and Brazilian Portuguese.** One master CV produces applications in either language, each following its own conventions. The interface is in both languages.
- **PDFs that pass ATS.** One single-column template per document type, rendered by Cloudflare Browser Rendering with a dompdf fallback, and cached.
- **What you sent stays readable.** Up to three versions per application, editable, revertible and ready to reread before an interview.

## How it works

1. **Set up once.** Fill in your candidate profile, add a master CV (paste Markdown or import a PDF), optionally a base cover letter and recruiter message, and add an AI key.
2. **Create an application.** Enter the company, role, link, location, work mode, output language, the job description and up to 10 screening questions.
3. **Generate.** The run happens in the background with visible progress and takes seconds to a few minutes depending on the provider.
4. **Review and send.** Read each document as it will be sent, fix the highlighted TODOs, copy answers one at a time, download the PDFs, and track the application's status as it moves from applied to interviewing to offer.

## Tech stack

- [Laravel 13](https://laravel.com) on PHP 8.5, with PostgreSQL and a database queue
- [Inertia 3](https://inertiajs.com) with React and TypeScript, Tailwind CSS 4 and Radix-based components
- [Prism](https://prismphp.com) for the AI providers
- [Laravel Fortify](https://laravel.com/docs/fortify) for accounts: email verification, password reset, two-factor authentication and passkeys
- Cloudflare R2 for files and Cloudflare Browser Rendering for PDFs (both optional locally)
- Pest, PHPStan and Pint, plus `vp check` and `tsc` for the frontend

## Getting started

Development runs in Docker with [Laravel Sail](https://laravel.com/docs/sail), which provides PHP 8.5, PostgreSQL 18 and Mailpit. You need Docker and Composer.

```bash
git clone https://github.com/jnariai/ContrataEu.git && cd ContrataEu
```

```bash
composer install && cp .env.example .env
```

To run without any Cloudflare account, keep files on the local disk, render PDFs with dompdf, and send mail to Mailpit by setting these in `.env`:

```dotenv
FILES_DISK=local
PDF_DRIVER=dompdf
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Then start the containers, prepare the app, and run it. The last command starts the web server, a queue worker, the log tail and Vite together:

```bash
vendor/bin/sail up -d
```

```bash
vendor/bin/sail artisan key:generate && vendor/bin/sail artisan migrate
```

```bash
vendor/bin/sail npm install && vendor/bin/sail npm run build
```

```bash
vendor/bin/sail composer run dev
```

Register an account (the verification email lands in Mailpit at <http://localhost:8025>), complete **Settings**, add an AI key, and create your first application.

Generations run on the queue. If you start the app some other way, run a worker with `vendor/bin/sail artisan queue:work`.

## Configuration

Besides the standard Laravel settings, `.env.example` lists these:

| Variable | Purpose |
|---|---|
| `FILES_DISK` | `r2` (default) or `local`. |
| `CLOUDFLARE_R2_ACCESS_KEY_ID`, `CLOUDFLARE_R2_SECRET_ACCESS_KEY`, `CLOUDFLARE_R2_BUCKET`, `CLOUDFLARE_R2_ENDPOINT` | The private R2 bucket. The endpoint must not end with the bucket name. |
| `PDF_DRIVER` | `cloudflare` (default) or `dompdf`. |
| `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN` | Browser Rendering. The token needs only that permission. |
| `CLOUDFLARE_BROWSER_RENDERING_TIMEOUT` | Seconds per render attempt (default 20). |
| `DB_QUEUE_RETRY_AFTER` | Must stay above the generation job's 330-second timeout (default 360). |

AI keys are not set here: each user adds their own under **Settings → AI keys**.

## Testing

```bash
vendor/bin/sail artisan test --compact
```

```bash
vendor/bin/sail composer run ci:check
```

`ci:check` runs Pint, the frontend lint and format checks, the TypeScript and PHPStan checks, and the test suite. Every user-facing string must have a Brazilian Portuguese translation in `lang/pt_BR.json`, and a test fails when one is missing.

## Project structure

The code uses Laravel's default folders, plus:

| Path | Contents |
|---|---|
| `app/Actions` | One use case per class, for example `StartGeneration`, `RunGeneration`, `PersistGenerationOutput` |
| `app/Gateways` | Vendor clients (Prism, Cloudflare), so swapping a vendor touches one folder |
| `app/Datas`, `app/ValueObjects`, `app/Enums` | Data objects, value objects, and enums backed by lookup tables |
| `resources/prompts` | The default playbook and prompts users can override; `resources/prompts/app` holds the output contract the app owns |
| `resources/views/pdf` | PDF templates |
| `resources/views/vendor/mail` | Email theme |
| `resources/js` | The Inertia React frontend |

Records reach the browser through prefixed public IDs (for example `app_…`), never through database IDs. Business-rule violations are dedicated exceptions, shown to the user as a message on the page.

The interface follows its own design system, documented in [DESIGN.md](DESIGN.md).
