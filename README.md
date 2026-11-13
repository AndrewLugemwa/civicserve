# CivicServe 3.0 — Case study edition: Makindye Ssabagabo Municipality

**Academic project.** CivicServe was designed from a field study of how community complaints are reported and
handled in Makindye Ssabagabo Municipality, Wakiso District. It is a student systems-analysis and development
project and **group work by Group 5**. It is **not** an official service of Makindye
Ssabagabo Municipal Council and does not use the Council's insignia. All cases in the demonstration are sample data.

## Project team — Group 5

| Member | Role |
| --- | --- |
| Andrew Lugemwa | Team lead & lead developer |
| Arthur Ssemujju | Systems analyst (field study) |
| Aber Mercy | Field research & user interviews |
| Nalukwago Shanitah | Requirements & documentation |
| Julius | Database design |
| Nyamanyama | Deployment & technical support |
| Daniel Ochen | Back-end development & security |
| Nabakka Caroline | User interface design |
| Najjuma Anisha | Testing & quality assurance |
| Shamirah | Presentation & reporting |

The team is also listed in the system on the **About the project** page (`about.php`).

## The study setting reflected in the system

| Study setting | In CivicServe |
| --- | --- |
| Three divisions: Bunamwaya, Masajja, Ndejje | Every report records its division; dashboard shows open cases per division |
| Departments that handle complaints (Works & Technical Services, Health & Environment, Physical Planning, Community-Based Services, Administration) | Each issue category is routed to its department automatically |
| Issue types: roads, drainage and flooding, garbage, street lights, noise and nuisance, building control, markets and facilities | Report categories, each with a target number of days |
| Complaints made in person and by phone, not only online | Staff "Log a complaint" page for walk-in and phone reports |

Confirm these rows against your field notes. `docs/FIELD-STUDY.md` records the study methods, findings and how each finding became a requirement.

## Features

**Resident portal** — guided report form with field-level validation · optional map pin (with typed-coordinates
fallback when maps are unavailable) · photo upload · reference number on submission · public tracking page that
shows progress without any personal data.

**Staff workspace** — secure sign-in · overview dashboard (open, overdue, new today, resolved, on-time rate,
14-day trend, workload by department, issue and division) · case queue with search, filters, sorting and
pagination · case page with status updates, assignment, priority, target dates, internal or public notes and full
history · walk-in / phone complaint intake · CSV export · staff accounts with roles (administrator, supervisor,
case officer).

**Security** — prepared statements throughout · output escaping and strict Content-Security-Policy · CSRF
protection on every form · hardened sessions with idle timeout · sign-in and lookup rate limiting · role-based
permissions · uploaded photos re-encoded (location metadata stripped) and shown to staff only · optimistic
locking on case updates · full audit history.

## Technology

PHP 8, MySQL, HTML, CSS, JavaScript; Leaflet 1.9.4 maps (bundled, BSD-2-Clause) with OpenStreetMap tiles. Runs
locally on MAMP (macOS).

## Setup (new installation)

1. Copy this folder into `/Applications/MAMP/htdocs/` and start MAMP.
2. In phpMyAdmin create a database (e.g. `civic_eservice`), then import `database/schema.sql`.
3. If your database name or MySQL password differ from MAMP's defaults (`civic_eservice`, `root`/`root`), copy
   `includes/config.local.example.php` to `includes/config.local.php` and edit it. Keep `'env' => 'demo'`.
4. In Terminal, from this folder:
   ```
   /Applications/MAMP/bin/php/php8.3.30/bin/php tools/create_staff.php --name="Your Name" --email=you@example.com --role=admin
   /Applications/MAMP/bin/php/php8.3.30/bin/php tools/seed_demo.php --reset     # sample cases for the demonstration
   ```
5. Open `http://localhost:8888/civicserve-case-study/`. Staff sign-in: `login.php`.

All study-specific settings (divisions, departments, categories, target days, map area) are in
`includes/config.php`. Setting `'case_study' => ''` there turns this into the general edition for any council.
