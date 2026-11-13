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

Kampala International University · School of Mathematics and Computing · Bachelor of Information Technology
Course unit: **System Analysis and Design (COS2102)**, Year 2 Semester 1 · Lecturer: **Mr. Byamugisha Peter**

| No. | Reg. number | Member name | Course | Role |
| --- | --- | --- | --- | --- |
| 1 | 2025-08-43544 | Lugemwa Andrew | BIT | Team lead & lead developer |
| 2 | 2025-08-39931 | Najjuma Anisha | BIT | Testing & quality assurance |
| 3 | 2025-08-40657 | Ochen Daniel | BIT | Back-end development & security |
| 4 | 2025-08-42879 | Ssemujju Arthur Silver | BIT | Systems analyst (field study) |
| 5 | 2025-08-41733 | Nabakka Caroline | BIT | User interface design |
| 6 | 2025-08-42859 | Nalukwago Shanitah | BIT | Requirements & documentation |
| 7 | 2025-08-44034 | Obanya Julius | BCS | Database design |
| 8 | 2025-08-43000 | Aber Mercy Unia | BIT | Field research & user interviews |
| 9 | 2025-08-42503 | Muroreukwere Shamirah | BIT | Presentation & reporting |
| 10 | 2025-08-40571 | Nyamanyama Essenine | BIT | Deployment & technical support |

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

## Setup on macOS (MAMP)

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

## Setup on Windows (XAMPP)

1. Install **XAMPP for Windows** (PHP 8.1+) from https://www.apachefriends.org to `C:\xampp`.
2. In the XAMPP Control Panel, **Start** Apache and MySQL.
3. Copy this folder to `C:\xampp\htdocs\civicserve-case-study`.
4. Open `http://localhost/phpmyadmin`, create a database named `civicserve_kansanga`, and **Import** `database\schema.sql`.
5. XAMPP's MySQL `root` user has no password. Create `includes\config.local.php` containing:
   ```php
   <?php
   return ['db' => ['user' => 'root', 'password' => '']];
   ```
6. In Command Prompt:
   ```
   cd C:\xampp\htdocs\civicserve-case-study
   C:\xampp\php\php.exe tools\create_staff.php --name="Your Name" --email=you@example.com --role=admin
   C:\xampp\php\php.exe tools\seed_demo.php --reset
   ```
7. Open `http://localhost/civicserve-case-study/`. Staff sign-in: `login.php`.

If photo uploads fail, enable `extension=fileinfo` and `extension=gd` in `C:\xampp\php\php.ini` (remove the `;`) and restart Apache.


All study-specific settings (divisions, departments, categories, target days, map area) are in
`includes/config.php`. Setting `'case_study' => ''` there turns this into the general edition for any council.
