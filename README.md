# ITAM

Open-source IT asset management, in Arabic and English, from the makers of the [AIM platform](https://demo.unifiedsofthub.com) by Unified Software Hub.

ITAM keeps one register of every device, licence and accessory your IT team looks after, who holds what, and what needs attention.

## Features

- **Asset register**: live list with filters, saved views, quick edit, bulk changes, table, card and grouped layouts, a quick-look panel, custom fields per category, files and comments on every asset.
- **Import and labels**: import from Excel or CSV, print QR labels.
- **Custody**: check out and check in with hand-over and return receipts, signed on screen or by a link sent to the employee.
- **Loaner devices**: a pool of spare devices that staff and employees book for dates.
- **Software licences**: seats per device or per person, over-allocation and expiry.
- **Accessories and consumables**: stock by quantity with minimums and issue to employees.
- **Physical audits**: scan QR labels with a phone camera; found, misplaced, unexpected and missing in real time.
- **Work orders**: corrective and preventive work with due times per priority, before and after photos, and fault reports from a QR label without an account.
- **Employee portal**: employees see their devices and licences, sign receipts, report a fault and book a loaner (sign-in by e-mail link or Microsoft).
- **Reports**: warranties ending, utilization by category and department, data quality; export to CSV, Excel, PDF or JSON.
- **Administration**: users, roles and permissions, branch scope, two-factor authentication, Microsoft single sign-on, themes, SMTP e-mail, nightly backups, a read-only REST API, and an activity log.
- **Arabic and English** throughout, right-to-left included, and usable on a phone.

Need a service desk, procurement, inventory, finance, HR, health scores, a device agent or integrations on the same data? See **About ITAM** in the app, or the [AIM platform](https://demo.unifiedsofthub.com).

## Requirements

- PHP 8.2 or later with `pdo_sqlite`, `openssl`, `curl`, `zip`, `mbstring`, `fileinfo` and `sodium` (`gd` and `intl` recommended).
- SQLite (nothing to set up) or MySQL / MariaDB (`pdo_mysql`).
- Node.js only to rebuild the front end.

## Install

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080. The installation wizard asks for the language, checks the requirements, then the database, your organisation (name, time zone, currency) and the first administrator, and can add demo data.

In production, point Apache or Nginx at `public/` (an `.htaccess` is included) and schedule:

```bash
php scripts/scheduler.php            # nightly: reminders, backup, weekly digest
php scripts/scheduler.php --quick    # every 15 minutes: overdue work orders and e-mail delivery
```

## Updating

Replace the code with the new release and keep `config/local.php` and `storage/`. Database changes in `database/migrations/` are applied automatically on the next request.

## Development

```bash
npm install && npm run build         # TypeScript in resources/ts → public/assets/js
php tests/run.php                    # tests on MariaDB (see the top of tests/run.php)
php tests/run.php --sqlite           # tests on SQLite
```

```
public/            front controller, CSS, compiled JS, images
app/Core           router, database, authentication and roles, translations, views
app/Controllers    HTTP controllers
app/Services       the work behind the pages
resources/views    PHP templates
resources/lang     en.php and ar.php
resources/help     the user guide, per language
resources/ts       TypeScript sources
database           schema.sql and migrations/
scripts            scheduler, logo generator, guide checker
tests              test runner and suites
```

No framework and no Composer dependencies. CSRF tokens on every form, hashed passwords and API tokens, login throttling, role-based permissions and output escaping everywhere.

## Licence

ITAM is free software under the **GNU Affero General Public License v3.0** (see `LICENSE`), with the additional attribution term in `NOTICE`. Copyright © 2026 Unified Software Hub.
