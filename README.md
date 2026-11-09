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
  one row per class (with company and expandable per-student splits) plus the
  per-student **"to bill"** summary used when issuing KSeF invoices, with a
  Company column and copy-to-clipboard / CSV export. Every *to bill* figure —
  per class, per student, and the one the Events picker offers — includes each
  student's monthly TBT surcharge, counted once, so it never has to be added by
  hand. Nothing is stored; any month is reproducible.

  **Draft events (2.7).** Each billable line — a class, or each member of a
  class with *Members pay separately* — gets an **Event** column: its status
  chip, or a *Create event* button. Tick lines with the same payer and settle
  method to *Combine into one event*, or use *Create drafts for remaining* for
  the whole month. New events start as **drafts**: refreshable to the current
  settlement and left out of the Events totals until issued (invoice number
  typed in Events, Revolut request sent, cash received, rozliczenie confirmed).
  Once issued an amount never changes; if the settlement moves the row warns
  instead. The figures themselves are unchanged — lines read the report.
  **Prepaid credit (2.8).** A class whose settle method maps to
  *rozliczenie* has a list of payments (date, amount, note) on the class form:
  the first is the credit, later ones are top-ups. The balance is computed,
  never stored: *Left now* = Σ payments − Σ `fee_snapshot` of the class's
  lessons from the earliest payment date up to and including today. Lessons
  booked after today only move *After booked*. *Lessons left* =
  ⌊Left now ÷ current class fee⌋ (omitted when the fee is 0). The chip under
  the settle method is **red** when overdrawn, **amber** at ≤ 2 lessons left
  (*Used up* at 0) and **green** otherwise; it opens a panel with the facts,
  a ledger and a top-up form. Credit is per class, counts lesson fees only
  (no TBT surcharge), and changes no settlement total, billable line or event.
  A settle-method dropdown filters the table; on *rozliczenie* it also lists
  active rozliczenie classes with no lesson that month.

  **Per-member credit (2.9).** When a rozliczenie class has *Members pay
  separately* ticked and at least two current members, each payment belongs
  to one member (*Paid by*) and every member has their own balance: what
  they paid minus their share (from each lesson's `shares_snapshot`) of
  every lesson since their own first payment — only lessons they were in, so
  a member who joined later is never charged for earlier ones. The class row
  shows a group chip for the member needing attention most (e.g. *1 of 4
  overdrawn*), each member line its own chip, and the panel a members table
  plus the selected member's facts and ledger. Payments with no member (for
  example entered before the class was split) are **unassigned**: they count
  toward nobody and the panel asks for them to be assigned in the class form.
  Unticking the box turns the class back into one pot; stored payers are kept.
- **Events** — the receivables register: every document issued (invoice /
  revolut / cash / rozliczenie) with KSEF · SENT · PAID ticks you flip inline, a payment date
  and a year-scoped **Outstanding** total. A document starts with **Who** — one
  or more classes, picked as chips straight from the register, plus free text
  for a payer with no class on record — and its **Type** is then derived from
  those classes' settle methods rather than chosen. Amounts are taken straight
  from the computed settlement — pick a class, student or company for a month
  and the figure fills itself — and are then frozen as snapshots, so later
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
  `mmr_events`, `mmr_event_classes`, `mmr_class_credits`.
- DB version tracked in the `mmr_db_version` option; upgrades run
  automatically on plugin load and are idempotent. The v2.1 upgrade backs up
  `mmr_classes` before dropping its `venue` and `sort_name` columns; v2.2 only
  adds `mmr_events` via `dbDelta` and touches nothing existing, and v2.3 only
  adds `mmr_event_classes` and the `mmr_events.who_text` column — existing
  events keep their payer label and are never migrated. v2.5 only adds
  `mmr_class_credits` (prepaid-credit payments); nothing existing changes. v2.6 adds
  `mmr_class_credits.student_id` (who paid); existing payments get NULL.
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
