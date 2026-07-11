# MM Register

A private, single-user WordPress back-office plugin for **NET ENGLISH** that
replaces the REGISTER Google Sheet's scheduling core: book lessons in a weekly
calendar, know what to bill at the end of the month.

Built to the specification in [`mm_register_SPEC.md`](mm_register_SPEC.md)
(v2 — trimmed). Pure PHP + vanilla JS — **no jQuery, no external JS/CSS
frameworks, no build step.**

## Highlights

- **Weekly calendar grid** (Mon–Fri, 12 slots/day) filling the whole admin
  content width, with a type-ahead class picker, one-off fee overrides, notes,
  move & delete, live day/week/month totals and a copy-week action. Collapses
  to one-day-per-screen on mobile.
- **Fee snapshotting** — each lesson stores its own fee (and member shares) at
  booking time, so changing a class fee never rewrites history.
- **Settlement** — a read-only monthly report computed entirely from lessons:
  one row per class (with expandable per-student splits and TBT monthly
  surcharges) plus the per-student **"to bill"** summary used when issuing
  KSeF invoices, with copy-to-clipboard and CSV export. Nothing is stored;
  any month is reproducible.
- **Students** and **Classes** as standalone pages: search, status filters,
  archive/restore (never hard delete), modal editing, members repeater with
  per-lesson shares, and a quick-add for new 1-on-1 students.
- **Tools** page for JSON import (dry-run first), full JSON export and a
  typed-confirmation data wipe.

Invoicing, receivables, budgeting, expenses and projections live **outside**
the plugin (they were removed in v2; the upgrade routine backs their tables up
to `wp-content/uploads/mm-register-backups/` before dropping them).

## Technical notes

- Slug `mm-register`, main file `mm-register.php`, everything prefixed `mmr_`.
- WordPress 6.x, PHP 8.1+. Custom `dbDelta` tables: `mmr_students`,
  `mmr_classes`, `mmr_class_members`, `mmr_lessons`.
- DB version tracked in the `mmr_db_version` option; upgrades run
  automatically on plugin load and are idempotent.
- All pages gated by `manage_options`; all writes via nonces + capability
  checks; all SQL via `$wpdb->prepare`; all output escaped.
- Assets are enqueued **only** on the plugin's own admin screens (avoids the
  LiteSpeed/Divi conflicts noted in the spec). No front-end output whatsoever.
- Money stored as `DECIMAL(10,2)`, displayed Polish-style (`1 234,56 zł`).

## Installation

1. Copy this directory into `wp-content/plugins/mm-register/`.
2. Activate **MM Register** in wp-admin → Plugins. Tables are created (and any
   pending upgrade runs) on activation.
3. Use **Register → Tools → Import** to load data (see
   [`docs/import-format.md`](docs/import-format.md)). Always dry-run first.

## Data import/export

The importer accepts a JSON file with students, classes (incl. members) and
lessons in the format documented in
[`docs/import-format.md`](docs/import-format.md). The Tools → Export button
emits the same format for backup/portability.

## Uninstall

Uninstalling keeps your data by default. To remove everything, use
**Tools → Danger zone → Delete all plugin data** (typed confirmation).
