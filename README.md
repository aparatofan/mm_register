# MM Register

A private, single-user WordPress back-office plugin for **NET ENGLISH** that
replaces the REGISTER Google Sheet: lesson calendar, client database, monthly
settlement, receivables register, personal budget, recurring expenses and
revenue projections.

Built to the specification in [`mm_register_SPEC.md`](mm_register_SPEC.md).
Pure PHP + vanilla JS — **no jQuery, no external JS/CSS frameworks.**

## Highlights

- **Weekly calendar grid** (Mon–Fri, 12 slots/day) with a type-ahead class
  picker, one-off fee overrides, notes, move & delete, live day/week/month
  totals and a copy-week action. Collapses to one-day-per-screen on mobile.
- **Fee snapshotting** — each lesson stores its own fee (and member shares) at
  booking time, so changing a class fee never rewrites history.
- **Monthly settlement** — one row per class with lesson counts, TBT monthly
  surcharges, per-member group splits, and a "Record settlement" action that
  writes receivables rows (optionally one per member).
- **Receivables register** with inline KSeF / Sent / Paid toggles and an
  unpaid-total that feeds the Budget dashboard.
- **Budget dashboard** reproducing the sheet's SUB-TOTAL → FREELY AVAILABLE
  math, combining hand-edited assets/liabilities/reserves with live receivables
  and remaining recurring expenses.
- **Expenses checklist**, **Projections** (with computed actual revenue), and a
  **Tools** page for JSON import (dry-run first), full JSON export and a
  typed-confirmation data wipe.

## Technical notes

- Slug `mm-register`, main file `mm-register.php`, everything prefixed `mmr_`.
- WordPress 6.x, PHP 8.1+. Custom `dbDelta` tables (relational data, no CPTs).
- All pages gated by `manage_options`; all writes via nonces + capability
  checks; all SQL via `$wpdb->prepare`; all output escaped.
- Assets are enqueued **only** on the plugin's own admin screens (avoids the
  LiteSpeed/Divi conflicts noted in the spec). No front-end output whatsoever.
- Money stored as `DECIMAL(10,2)`, displayed Polish-style (`1 234,56 zł`).

## Installation

1. Copy this directory into `wp-content/plugins/mm-register/`.
2. Activate **MM Register** in wp-admin → Plugins. Tables are created on
   activation.
3. Use **Register → Tools → Import** to load data (see
   [`docs/import-format.md`](docs/import-format.md)). Always dry-run first.

## Data import/export

The importer accepts a JSON file in the format documented in
[`docs/import-format.md`](docs/import-format.md). The JSON is produced
separately from `REGISTER_2026.xlsx` (this keeps PhpSpreadsheet out of the
plugin). The Tools → Export button emits the same format for backup/portability.

## Uninstall

Uninstalling keeps your data by default. To remove everything, use
**Tools → Danger zone → Delete all plugin data** (typed confirmation).
