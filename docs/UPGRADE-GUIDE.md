# CivicServe 3.0 — Upgrade guide and refactoring plan

CivicServe 3.0 replaces CivicServe 2.x (Sprint 10). It is a general edition that any municipality can configure in `includes/config.php`.
Follow the steps in order. Allow about 30 minutes.

---

## Part A — What changed and why

### 1. The separate Noise & Nuisance Mapper is removed

| Removed | Replaced by |
| --- | --- |
| `noise_mapper.php` (staff-only map page) | Noise is a normal category in the case queue (`cases.php?category=noise`), with the same routing, target date and overdue tracking as every other issue |
| `assets/noise-mapper-sprint9.css` | One stylesheet: `assets/css/app.css` |
| `database/migrations/002_noise_mapper.sql` | Folded into `database/schema.sql` (new installs) and `tools/migrate.php` (existing databases). Its latitude/longitude/priority columns are kept because every category can now carry a map pin |
| "Map pin required for noise complaints" rule | The map pin is optional for every category |
| Noise reports forced to High priority | Each category has a starting priority in `includes/config.php` (noise starts at Normal; staff can raise it) |
| Mapper indexes `idx_reports_category_coordinates`, `idx_reports_priority_status` | Dropped by the migration; replaced by indexes that match the queue's filters |

### 2. Configurable for any municipality (all in `includes/config.php`)

- **Divisions:** North, Central, South, East and West (and "Not sure") by default. Every report records its division. Rename them for a real area.
- **Departments:** Works & Technical Services, Health & Environment, Physical Planning, Community-Based Services, Administration.
- **Categories**, each routed to a department with a service standard (target days): potholes & road damage (14), drainage/flooding/water leaks (7), uncollected garbage & sanitation (5), streetlighting (10), noise & nuisance (7), building control & land use (14), markets & public facilities (14), other (14).
- **Reference numbers:** `CS-26-7KQ4XZ` (prefix, year, 6 random characters). Old `CR-2026-00042` numbers still work.
- **Branding:** CivicServe name and a neutral placeholder mark. A council's own name and crest can be set in config once it adopts the system (see `assets/brand/README.md`). Official government insignia are not included.
- **Resident wording:** residents see plain statuses ("Received", "Assigned to department", "Work in progress", "Resolved", "Closed — not actioned").

### 3. Security hardening

| Area | 2.x | 3.0 |
| --- | --- | --- |
| Dashboard search | Crashed (`SQLSTATE[HY093]`): one named parameter used three times with native prepares | Every parameter is unique (`includes/case_query.php`) |
| SQL injection | Mostly prepared | 100% prepared statements; filter values whitelisted; `LIKE` wildcards escaped |
| XSS | Mostly escaped | Every output goes through `e()`; no inline JavaScript; strict Content-Security-Policy |
| Clickjacking / MIME sniffing | None | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff` |
| Sessions | Basic | Strict mode, HttpOnly, SameSite, Secure on HTTPS, new ID at sign-in, 30-minute idle and 10-hour absolute timeouts |
| Sign-out | GET link (CSRF-able) | POST + CSRF token |
| Brute force | None | 5 failed sign-ins per email+IP per 15 min; 25 per IP; constant-time check for unknown emails |
| Enumeration of tracking numbers | 5 digits, unlimited lookups | ~887 million combinations per year + 30 lookups / 10 min per IP |
| Spam submissions | None | 10 reports / hour per IP; one-time form tokens stop double submissions |
| Roles | Everyone could do everything | Administrator / Supervisor / Case officer (see `user_can()` in `includes/security.php`) |
| Public tracking page | Showed every staff note and the staff member's name | Shows status only, plus notes staff explicitly mark "show to resident". No names |
| Photo uploads | Type-checked | Type-checked, then **re-encoded** (strips GPS/EXIF and hidden payloads), resized, random name, served to staff only |
| Concurrent edits | Last write wins silently | Optimistic locking: a second editor is told the case changed |
| Closing a case | Allowed with no explanation | A note is required to resolve or close |
| CSV export | None | Supervisors only; spreadsheet-formula injection neutralised |
| Database errors | Generic message | Generic message + logged detail; DB password from `config.local.php` or environment |
| Folders | Code readable by URL | `.htaccess` blocks `includes/`, `tools/`, `database/`, `docs/`, `uploads/`, `.md`, `.sql` |

### 4. Database integrity and performance

- Unique index on `reference_number`; unique, lower-cased staff emails.
- Composite indexes matched to the queue: `(status, due_date)`, `(assigned_department, status)`, `(category, status)`, `(division, status)`, `created_at`, `resolved_at`.
- New columns: `division`, `channel` (online / walk-in / phone), `created_by`, `updated_at`, `resolved_at` (enables "resolved on time" and average days to resolve), `status_logs.is_public`, `users.department / is_active / last_login_at`.
- Category and department are stored as stable keys (`noise`, `health`), so the Council can rename labels without touching data.
- Fresh installs get foreign keys; the migration does not add them to old data (orphan rows could block it).

### 5. File map

| 2.x file | 3.0 |
| --- | --- |
| `db.php`, `security.php` | `includes/db.php`, `includes/security.php`, loaded by `includes/bootstrap.php` |
| `index.php` | Rebuilt portal; report form in `includes/report_form.php` |
| `process_report.php` | Rewritten: field-level errors, Post/Redirect/Get, confirmation page `report_submitted.php` |
| Tracking inside `index.php` | `track.php` |
| `admin.php` | Overview dashboard; the queue moved to `cases.php` |
| `report_details.php` | `case.php` (old links redirect) |
| `update_status.php` | Rewritten with permissions, audit entries, locking |
| `login.php`, `logout.php`, `view_attachment.php` | Rewritten |
| `style.css`, `admin-sprint8.css`, `admin-sprint10.css`, `login-sprint7.css`, `assets/*-sprint*.css`, `assets/map-fallback.css` | `assets/css/app.css` |
| `assets/civic-map-loader.js` + inline scripts | `assets/js/map.js`, `report-form.js`, `staff.js`; Leaflet bundled in `assets/vendor/leaflet` (no CDN needed; only map tiles need internet) |
| `assets/civicserve-hero.svg` | Removed |
| `SPRINT-*-NOTES.md`, `DEVELOPMENT.md` | Replaced by this guide and `README.md` |
| — | New: `intake.php` (walk-in/phone complaints), `staff.php`, `account.php`, `export.php`, `tools/*` |

---

## Part B — Upgrade steps (existing MAMP installation)

1. **Back up the database.** phpMyAdmin → `civic_eservice` → Export → Quick → SQL → Go. Keep the file.
2. **Back up the code.** Rename `/Applications/MAMP/htdocs/civice-service` to `civice-service-2x-backup`.
3. **Copy in 3.0.** Put this package's `civice-service` folder at `/Applications/MAMP/htdocs/civice-service`.
4. **Copy old photos.** Copy the contents of `civice-service-2x-backup/uploads/` into the new `uploads/` (keep the new `.htaccess`).
5. **Database settings.** MAMP defaults (root/root, `civic_eservice`) work as-is. If yours differ, copy `includes/config.local.example.php` to `includes/config.local.php` and edit it. For the demo laptop set `'env' => 'demo'`.
6. **Preview the database upgrade.** Open Terminal:
   ```
   cd /Applications/MAMP/htdocs/civice-service
   /Applications/MAMP/bin/php/php8.2.0/bin/php tools/migrate.php
   ```
   (Use the PHP version folder you have: `ls /Applications/MAMP/bin/php/`.) Read the plan.
7. **Apply it:** run the same command with `--apply` at the end. It is safe to run again.
8. **Check your admin account.** Existing accounts with an unrecognised role become **Administrator**. To create or reset one:
   ```
   /Applications/MAMP/bin/php/php8.2.0/bin/php tools/create_staff.php --name="Your Name" --email=you@example.org --role=admin
   ```
9. **Demo data (presentation laptop only):** `… php tools/seed_demo.php --reset` loads 48 realistic, fictional cases across the last 30 days. Remove them before real use with `--remove`.
10. **Hard-refresh** the browser (Cmd+Shift+R) and run the checks in Part D.

**Rollback:** delete the new folder, rename the backup back, and import the SQL backup from step 1.

## Part C — New installation

Create an empty database, import `database/schema.sql`, set `includes/config.local.php`, then create the first administrator with `tools/create_staff.php`.

## Part D — Acceptance checks

- [ ] Resident: submit a report with a photo and a map pin → confirmation shows a `CS-…` reference.
- [ ] Press the browser's Back and Submit again → no duplicate case is created.
- [ ] Submit with a missing phone number → the error appears next to the field and other answers are kept.
- [ ] Track the reference → status shows; no name, phone or staff names appear.
- [ ] Staff: wrong password 5 times → temporary lock message.
- [ ] Search the queue by reference, name, phone and place (this crashed in 2.x).
- [ ] Update a case with a public note → the note appears on the tracking page.
- [ ] Try to resolve without a note → refused.
- [ ] Case officer (Works) opening a Health case → update form is locked.
- [ ] Supervisor → Export CSV opens in Excel.
- [ ] Old link `report_details.php?id=1` opens the case.
- [ ] `http://localhost:8888/civice-service/includes/config.php` and `/uploads/` are blocked.
- [ ] Phone width (Chrome DevTools, 390 px): portal, tracking and queue are usable.

## Part E — Before go-live with real residents (not code)

- Council-approved hosting with HTTPS; `includes/config.local.php` with `'env' => 'production'`, a dedicated database user with a strong password, and `uploads.dir` outside the web root.
- Independent penetration test.
- Daily encrypted database and uploads backups, with a tested restore.
- Council registration with the Personal Data Protection Office; privacy notice wording approved by the Council; agreed retention period.
- Written authorisation before adding the Council crest or any national insignia.
- Remove demo data: `php tools/seed_demo.php --remove`.
- Replace the demo locations and coordinates with real local examples if you keep any for training.
