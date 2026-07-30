# MM Register

A private, single-user WordPress back-office plugin for **NET ENGLISH** that
replaces the REGISTER Google Sheet's scheduling core: book lessons in a weekly
calendar, know what to bill at the end of the month.

Built to the specification in [`mm_register_SPEC.md`](mm_register_SPEC.md)
(v2.2). Pure PHP + vanilla JS — **no jQuery, no external JS/CSS frameworks, no
build step.**

## Highlights

- **Weekly calendar grid** (Mon–Fri, 12 slots/day) filling the whole admin
  content width, with a type-ahead class picker, one-off fee overrides, notes,
  move & delete, live day/week/month totals and a copy-week action. Collapses
  to one-day-per-screen on mobile.
- **Fee snapshotting** — each lesson stores its own fee (and member shares) at
  booking time, so changing a class fee never rewrites history.
- **Settlement** — a read-only monthly report computed entirely from lessons:
  one row per class (with company, expandable per-student splits and TBT
  monthly surcharges) plus the per-student **"to bill"** summary used when
  issuing KSeF invoices, with a Company column and copy-to-clipboard / CSV
  export. Nothing is stored; any month is reproducible.
- **Events** — the receivables register: every document issued (invoice /
  revolut / cash) with KSEF · SENT · PAID ticks you flip inline, a payment date
  and a year-scoped **Outstanding** total. Amounts are taken straight from the
  computed settlement — pick a class, student or company for a month and Who,
  Amount and Type fill themselves — and are then frozen as snapshots, so later
  lesson edits never rewrite an issued document.
- **Students** and **Classes** as standalone pages: search, status filters,
  archive/restore (never hard delete), modal editing, members repeater with
  per-lesson shares, and a quick-add for new 1-on-1 students. Students carry
  name, level (CEFR), two e-mails, phone and TBT fee.
- **Companies** — a standalone page for invoice/billing details (name, NIP,
  address, notes) that classes link to, so Settlement can show which company
  each invoice goes to.
- **Tools** page for JSON import (dry-run first), full JSON export and a
  typed-confirmation data wipe.

Invoicing, budgeting, expenses and projections live **outside** the plugin
(they were removed in v2; the upgrade routine backs their tables up to
`wp-content/uploads/mm-register-backups/` before dropping them). Receivables
tracking came back in v2.2 as the Events page, on a different data model — a
flat register of issued documents, not the v1 `mmr_settlements` table.
Invoices themselves are still issued in KSeF; Events only records them.

## Technical notes

- Slug `mm-register`, main file `mm-register.php`, everything prefixed `mmr_`.
- WordPress 6.x, PHP 8.1+. Custom `dbDelta` tables: `mmr_students`,
  `mmr_classes`, `mmr_class_members`, `mmr_lessons`, `mmr_companies`,
  `mmr_events`.
- DB version tracked in the `mmr_db_version` option; upgrades run
  automatically on plugin load and are idempotent. The v2.1 upgrade backs up
  `mmr_classes` before dropping its `venue` and `sort_name` columns; v2.2 only
  adds `mmr_events` via `dbDelta` and touches nothing existing.
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

The importer accepts a JSON file with companies, students, classes (incl.
members), lessons and events in the format documented in
[`docs/import-format.md`](docs/import-format.md). The Tools → Export button
emits the same format for backup/portability.

## Uninstall

Uninstalling keeps your data by default. To remove everything, use
**Tools → Danger zone → Delete all plugin data** (typed confirmation).
