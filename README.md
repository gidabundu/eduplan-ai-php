# EduPlan AI — PHP Edition

A classic server-rendered PHP + MySQL "admin" system — same Genora AI-style dark/pink interface,
running on a standard PHP + MySQL host (Apache, Nginx, XAMPP, or just `php -S`). Every page is real:
real database, real file uploads, real AI calls, and every button on screen does something.

## What's in this edition

- **Real database — MySQL/MariaDB**, not SQLite. Tables are created automatically on first request
  (or import `schema.sql` yourself if you'd rather not rely on auto-creation).
- **No seed/sample curriculum data.** Resources and saved generations start empty. On first run,
  create the initial administrator account from the login screen.
- **Teacher sign-in.** Administrators can create or reset teacher logins from the User Directory.
  Teachers receive a temporary password and must change it at first sign-in. They can use the
  generator, manage resources, saved generations, and existing directory records, and edit their own
  profile. Only administrators can add directory users or review all teacher forecast progress;
  teachers can update their own forecast status.
  School-wide settings and AI credentials remain server/admin managed.
- **Teacher activity log.** Administrators can review successful teacher sign-ins/sign-outs and
  authenticated page or feature use by day and teacher. Successful generations and teaching-status
  updates include class, subject, topic, term, and year when available. The log does not store lesson
  content, custom prompts, or API request payloads.
- **Resource Library (books + syllabi).** Only a real upload creates a record — upload PDF, DOCX,
  TXT or MD, or paste text directly. Text is extracted automatically:
  - `.txt` / `.md` — read directly.
  - `.docx` — parsed from the file's own XML (needs PHP's `zip` extension).
  - `.pdf` — uses Poppler for text extraction and Tesseract OCR for scanned pages when installed;
    a best-effort built-in extractor is used when those tools aren't available.
- **AI grounded in your library.** Every tool in the AI Generator has a "Reference material" dropdown.
  Pick an uploaded book or syllabus and its extracted text is sent to the AI as source material.
- **Web search.** Each generator has a "Search the web for extra context" checkbox, which turns on
  Anthropic's server-side web search tool for that one request when using Anthropic.
- **Custom Request tool.** A fifth, open-ended tab: describe anything (a rubric, a parent letter, a
  revision checklist...) and the AI responds directly — not limited to the four structured generators.
- **Save / Edit / Regenerate on every AI result.** Save writes the output to the database (visible
  under Reports → "Saved AI generations"); Edit makes the result directly editable in place before you
  save it; Regenerate re-runs the same request.
- **Forecast progress tracking.** Save a generated forecast to the Progress Tracker to record its
  weeks, then update each week as Planned, In progress, or Taught in Reports. The normal Save action
  still stores the full forecast under Saved AI generations.
- **Teaching session log.** Teachers can record class, subject, topic, date, time, lesson outcome, and
  optional notes. Administrators can confirm each session or request changes; teachers can edit
  pending or returned entries. Records are separate from AI generation activity.
- **Every control is wired up** — see the full list below. Nothing on screen is a dead click.

## Every button, and what it actually does

| Control | Where | Action |
|---|---|---|
| Home hero pills / feature cards | Home | Jump into that Generator tab |
| Popular tools cards | Home | Go to that page |
| Sidebar nav items | Sidebar | Navigate; highlights the current page |
| Workspace name | Sidebar | Opens a dropdown showing your real school name (from Settings) with a link to edit it |
| Appearance (sun icon) | Sidebar + top bar | Toggles the whole interface between dark and light (saved in your browser) |
| Account / Sign out | Sidebar | Shows the signed-in user and ends the session |
| Hamburger menu | Top bar | Shows/hides the sidebar |
| Help / Tips | Top bar | Go to the Help page's FAQ / Tips sections |
| Login | Top bar | Goes to Settings |
| Start for Free | Top bar | Goes to the AI Generator |
| Generate (all 5 tools) | AI Generator | Calls the configured Anthropic or Google Gemini API and renders a real result |
| Stepper +/−, difficulty slider | Assessment tab | Update the question counts / difficulty live |
| Reference material, Search the web | All 5 tabs | Ground with an uploaded resource; web search is available when Anthropic is configured |
| Edit / Regenerate / Save | Every AI result | Edit in place / re-run / save to the database |
| Save to Progress Tracker | Forecast result | Saves weekly forecast rows for progress tracking in Reports |
| Upload & index | Resource Library | Real upload + text extraction + database insert |
| Filter, Remove | Resource Library | Real filtered query / real delete |
| + Add user, Search, Edit, Remove | User Directory | Admins add directory users and create/reset teacher logins; users with directory access can search, edit, and remove records |
| Teacher Activity | Admin tools | Review daily sign-ins, generation metadata, and teaching-status updates, filtered by day and teacher |
| Teaching Sessions | Tools | Teachers submit and update their class-session records; administrators confirm sessions or request changes |
| Save changes | Settings | Real update to the database |

## Requirements

- PHP 8.0+ with:
  - `pdo_mysql` — the database
  - `curl` — calling the Anthropic or Google Gemini API
  - `mbstring` *(optional but recommended)* — safer text truncation for long uploads
  - `zip` *(optional)* — needed only for `.docx` text extraction; without it, `.docx` files are still
    saved, just not auto-indexed (paste the text instead)
- MySQL or MariaDB (5.7+ / 10.3+), running locally or remotely

## Setup

1. **Create the database.**
   ```sql
   CREATE DATABASE eduplan_ai;
   ```
   Tables are created automatically the first time the app runs. If you'd rather not rely on
   auto-creation (e.g. your DB user can't `CREATE TABLE`), import `schema.sql` instead:
   ```bash
   mysql -u root -p eduplan_ai < schema.sql
   ```

2. **Set your database credentials** in `config.php` (defaults match a typical XAMPP/local setup —
   host `localhost`, user `root`, no password):
   ```php
   define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
   define('DB_NAME', getenv('DB_NAME') ?: 'eduplan_ai');
   define('DB_USER', getenv('DB_USER') ?: 'root');
   define('DB_PASS', getenv('DB_PASS') ?: '');
   ```
   Environment variables override the file if set — handy for shared hosting or Docker.

3. **Set an AI key** as an environment variable or in `config.local.php`. By default, EduPlan prefers
   Google Gemini using `gemini-flash-lite-latest`; Anthropic is used when configured as the provider:
   ```php
   define('AI_PROVIDER', getenv('AI_PROVIDER') ?: '');
   define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?: '');
   define('GOOGLE_MODEL', getenv('GOOGLE_MODEL') ?: 'gemini-flash-lite-latest');
   define('GOOGLE_FALLBACK_MODEL', getenv('GOOGLE_FALLBACK_MODEL') ?: 'gemini-flash-lite-latest');
   ```
   Set `GOOGLE_API_KEY` to your Google AI Studio key. Gemini works by itself; you do not need an
   Anthropic or any other provider key. If you also configure Anthropic, Gemini remains primary.
   To force Anthropic instead, set `AI_PROVIDER=anthropic` and `ANTHROPIC_API_KEY`; to force Gemini,
   set `AI_PROVIDER=google`. Without either key, every page still works — only Generate shows a
   configuration error. On temporary Gemini overload/rate-limit responses, the app tries the configured
   fallback model with the same Gemini key.
   Gemini requests wait up to 60 seconds by default. Override this with `GOOGLE_TIMEOUT_SECONDS`
   if your hosting environment supports a different request duration.
   For local XAMPP use, open the Git-ignored `config.local.php` and set
   `'GOOGLE_API_KEY' => 'your-key'` in its returned array. This file is not committed.
   Alternatively, set `GOOGLE_API_KEY` as a Windows environment variable and restart Apache.
   Never commit your API key.

4. **Configure email for password recovery (optional).** Set the SMTP variables described below
   and set `APP_URL` to the public base URL of this installation (for local XAMPP the default is
   `http://localhost/eduplan-ai-php`). Reset requests queue an email; run the mail worker to deliver it.
   If email is not configured, users should contact an administrator to reset their password.

5. **Run it.**
   ```bash
   php -S localhost:8000
   ```
   Then open `http://localhost:8000`. For real deployment, point an Apache or Nginx vhost's document
   root at this folder — standard PHP hosting, no special config beyond PHP + the extensions above.

6. **Create your administrator.** Open `http://localhost:8000/login.php`. On first run, the form
   creates the first administrator account. Use that account to sign in and manage the application.
   To give a teacher access, add them as a Teacher in User Directory, choose **Create login**, and
   set a temporary password of at least 10 characters. Give it to the teacher privately; they will
   be required to change it after signing in. Use **Reset** to issue a new temporary password.
7. **Build frontend assets only if you change the UI.** The compiled CSS and icon library are already
   included, so Node.js is not needed to run the app. To rebuild CSS after changing templates:
   ```bash
   npm install
   npm run build:css
   ```

## Email notifications

Set SMTP environment variables before starting Apache/PHP:

```powershell
$env:SMTP_HOST="smtp.example.com"
$env:SMTP_PORT="587"
$env:SMTP_ENCRYPTION="tls"
$env:SMTP_USER="mailer@example.com"
$env:SMTP_PASS="replace-with-app-password"
$env:SMTP_FROM_EMAIL="mailer@example.com"
$env:SMTP_FROM_NAME="EduPlan AI"
$env:APP_URL="https://school.example.com"
```

The `APP_URL` value should be the public base URL of the app, without a trailing slash. Password
reset requests queue a single-use link that expires after one hour. The same mail worker delivers
reset messages and notifications. Enable notification or weekly-report preferences in Settings.
Run notifications once manually:

```powershell
powershell.exe -ExecutionPolicy Bypass -File C:\xampp\htdocs\eduplan-ai-php\run_notifications.ps1
```

Schedule the same script daily with Windows Task Scheduler. `weekly_report.php` only queues a
report on Mondays; `mail_worker.php` delivers queued mail with retry handling.

## File structure

```
config.php              DB credentials + AI provider/model defaults
schema.sql               Standalone SQL schema (optional manual import)
includes/
  db.php                  MySQL connection + auto-created schema (no seed data)
  helpers.php             Authentication, CSRF, permissions, icon() and escaping
  extract.php             text extraction (txt/md/docx/pdf)
  header.php / footer.php shared page chrome + theme-init script
  sidebar.php / topbar.php Genora-style nav + top bar, all controls wired
  empty_state.php         generator result placeholder
index.php                Home launcher
syllabus.php             Resource Library (books + syllabi: upload, filter, edit, delete)
users.php                User Directory (add, edit, delete; admin teacher-login provisioning)
login.php                Initial admin setup and sign-in
change_password.php      Required first-login teacher password change
password_reset_request.php  Password reset request form
reset_password.php      One-time, expiring password reset form
generator.php            AI Generator — 5 tabs, forms only (AJAX does the work)
reports.php              Real counts, saved AI generations, and forecast progress tracking
teacher_activity.php     Admin activity log with generation and teaching metadata
teaching_sessions.php    Teacher-submitted class records with admin review
settings.php             Personal profile; school preferences are admin-only
help.php                 FAQ + tips (destination for the top-bar Help/Tips links)
api/generate.php         POST endpoint: builds the prompt, optionally adds
                          resource grounding + provider-supported web search, calls the configured
                          AI provider, returns JSON
api/save_generated.php   POST endpoint: saves an AI result to the database
assets/app.js            All interactivity: generator AJAX, theme/sidebar toggles, edit/save
assets/style.css         Light-theme overrides, accent colors, scrollbars
uploads/                 Uploaded resource files land here
```

## How a generation request works

1. The browser collects the form fields with vanilla JS (`assets/app.js`) and POSTs JSON to
   `api/generate.php`, including the selected `resourceId` and `webSearch` flag.
2. `api/generate.php` builds the prompt for that tool and prepends the resource's extracted text if one
   was selected. If web search is on and Anthropic is configured, it adds Anthropic's
   `web_search_20250305` server-side tool to the request.
3. It calls the configured provider via cURL: Anthropic Messages or the Gemini `generateContent` API.
   Gemini structured generators request JSON output mode.
4. The response is parsed (structured JSON for the four fixed tools; plain text for Custom Request)
   and returned to the browser, which renders it into the result panel with Edit/Regenerate/Save
   controls.
5. Clicking Save POSTs the (possibly edited) result to `api/save_generated.php`, which inserts it into
   the `generated_items` table — visible afterwards under Reports.
6. On a Forecast result, **Save to Progress Tracker** also stores weekly forecast rows in
   `forecast_plans` and `forecast_plan_items`. Teachers can update their own weekly status; Reports
   shows each teacher’s progress and the date/time of the latest status update to administrators.

## Notes

- Pages require an authenticated account. Create the first admin through `login.php`; passwords are
  stored as hashes. Teacher login accounts are linked to directory records, and deleting a linked
  record also removes its login (admin-only).
- `config.php` has `display_errors` on for easier local development — turn it off
  (`ini_set('display_errors', '0')`) before deploying somewhere public.
- PDF extraction uses Poppler when available and can fall back to Tesseract OCR for scanned pages.
  Without those optional local tools, extraction falls back to the built-in best-effort parser.
- This was built and tested end-to-end against a real MariaDB instance (lint-checked, server-tested,
  and the AI response-parsing logic unit-tested against realistic API response shapes including
  interleaved web-search tool blocks) before being packaged.
