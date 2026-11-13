# CivicServe 3.0 — Case study: Kansanga, Makindye Division (Kampala)

**Academic project — group work by Group 5.** CivicServe is a community complaint reporting and tracking
system. It grew out of fieldwork carried out on **14 October 2026 in Kansanga Ssebagala Village, Kansanga
(Makindye Division, Kampala)**, where the group studied community noise for a proposed *Community Noise
Mapper*. The findings showed that residents need a way to report any community problem, with its place
and time, and to follow it up themselves. With the lecturer's approval, the project was widened into a
general complaint reporting and tracking system, with noise and nuisance kept as a core category.

CivicServe is **not** an official service of Kampala Capital City Authority (KCCA) or any government body,
and uses no official insignia. All cases in the demonstration are sample data.

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

## The case study in the system

| From the field study | In CivicServe |
| --- | --- |
| Noise from bars is tied to specific places | Every report records an area (Kansanga, Kabalagala, Muyenga, Ggaba, Bukasa), a location and an optional map pin |
| Noise depends on the time of day | Every report and update is dated and timed |
| Affected residents are hard to reach during the day | Residents report at any time and track progress with a reference number |
| Scope widened to all community complaints (approved by the lecturer) | Categories for noise (listed first), roads, drainage, garbage, street lights, building control, markets and facilities |

Departments are modelled on KCCA's directorates (Engineering & Technical Services; Public Health Services
& Environment; Physical Planning; Gender, Community Services & Production; Administration & Human
Resource Management).

Full details, evidence and limitations: `docs/FIELD-STUDY.md`.

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
2. In phpMyAdmin create a database named `civicserve_kansanga`, then import `database/schema.sql`.
3. MAMP's defaults (MySQL user `root`, password `root`) work as they are. If yours differ, copy
   `includes/config.local.example.php` to `includes/config.local.php` and edit it.
4. In Terminal, from this folder:
   ```
   /Applications/MAMP/bin/php/php8.3.30/bin/php tools/create_staff.php --name="Your Name" --email=you@example.com --role=admin
   /Applications/MAMP/bin/php/php8.3.30/bin/php tools/seed_demo.php --reset     # sample cases for the demonstration
   ```
5. Open `http://localhost:8888/civicserve-case-study/`. Staff sign-in: `login.php`.

All study-specific settings (divisions, departments, categories, target days, map area) are in
`includes/config.php`. Setting `'case_study' => ''` there turns this into the general edition for any council.
