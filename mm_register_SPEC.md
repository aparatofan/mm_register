# MM REGISTER — WordPress Plugin Specification

**Version:** 2.3
**Author of spec:** Mariusz Mirecki + Claude
**Target:** WordPress plugin replacing the REGISTER Google Sheet's scheduling core: weekly lesson calendar, students / classes / companies database, computed monthly settlement report, register of issued documents.
**Implementation:** Claude Code. Pure PHP + vanilla JS. **No jQuery. No external JS/CSS frameworks. No build step.**

> v2 trimmed the plugin down from v1 (Receivables, Budget, Expenses and
> Projections were removed — see §2). v2.1 adds student contact fields, a
> Companies page/table, and a per-class Company link; it drops the class
> `venue` and `sort_name` columns. v2.2 adds the **Events** page and the
> `mmr_events` table — receivables tracking, deliberately reintroduced on a
> new data model (see §2 and §7.3). v2.3 makes an event's **Who** the first
> field and a set of classes (`mmr_event_classes`, §5.6.1) plus free text, and
> derives **Type** from their settle methods instead of asking for it. History
> is in the repo.

---

## 1. Purpose and philosophy

Mariusz runs NET ENGLISH, a one-person English teaching business. The plugin's
job is narrow:

**Book lessons in a weekly calendar → know what to bill at the end of the month.**

Core philosophy inherited from the sheet:

- **The weekly grid is the calendar.** Monday–Friday columns, 12 lesson slots
  per day. Filling a slot with a class name = a billable lesson.
- **If a lesson is in the calendar, it is charged.** No cancellation tracking.
  Removing a lesson from the grid = it never happened financially.
- **Settlement is monthly and computed.** Nothing is stored: every number is
  derived from lessons (which carry fee snapshots), so any month, past or
  future, is reproducible. Invoices are issued externally in KSeF; the plugin
  only tells you *what* to bill.
- **Everything is private.** Single-user back-office tool for the site
  administrator only. No front-end output whatsoever.

## 2. Scope

### Included
1. **Calendar** — the weekly grid (default page).
2. **Settlement** — read-only computed monthly report with a per-student
   "to bill" summary, copy-to-clipboard and CSV export.
3. **Events** — the receivables register: a chronological list of documents
   issued (invoice / revolut / cash) with KSEF · SENT · PAID ticks, a payment
   date and a year-scoped **Outstanding** total. Amounts are taken from the
   computed settlement through a picker, never retyped.
4. **Students** — standalone CRUD page.
5. **Classes** — standalone CRUD page (with members repeater and quick-add
   1-on-1).
6. **Companies** — standalone CRUD page for invoice/billing details, linked
   from classes.
7. **Tools** — JSON import (dry-run first), full JSON export, danger-zone wipe.

### Removed in v2 (do not re-add)
- Receivables page and the `mmr_settlements` table
- Budget page and `mmr_budget_items`
- Expenses page and `mmr_recurring_expenses` / `mmr_expense_payments`
- Projections page and `mmr_projections`

**v2.2 reintroduces receivables tracking on purpose, as Events, on the new
`mmr_events` table (§5.6, §7.3).** The ban above stands only for the v1
`mmr_settlements` table and its Receivables page: `mmr_settlements` stays in
`MMR_DB::legacy_keys()` and is still dropped on every upgrade, so `events`
must never be added to that list. Events is not a revival of Receivables — it
records issued documents with a text payer label, not per-student balances.

No attendance, no e-mail, no reporting charts, no settings page beyond Tools.
Invoice *generation* remains out of scope: Events records what KSeF issued, it
does not create it.

## 3. Key modelling decisions

1. **Class, not student, is the schedulable unit.** A class has one or many
   member students. A private 1-on-1 student = a class with one member.
2. **TBT surcharge is a flat monthly amount per student.** It is added **once
   per month** to that student's bill if the student had ≥1 lesson that month,
   regardless of how many classes they attend. Stored per student, editable.
   Because the class — not the student — is usually what gets invoiced, the
   surcharge must also reach the per-class figures, and exactly once: it is
   **attributed to the one class where that student had the most lessons that
   month**, ties going to the first class by name, then by id, so the same
   month always resolves the same way. That class's *To bill* carries it, the
   others do not, and the per-student summary is unaffected — it always shows
   each student's own surcharge once. Every figure meant for an invoice
   (per-class, per-student, per-company, and the Events amount picker) is
   therefore inclusive of TBT; the lessons-only figure survives as
   `month_total` for reconciliation.
3. **Fee snapshotting.** Each lesson stores the class fee (and per-member
   shares) at the moment it is booked. Changing a class fee later affects only
   future lessons. This must never be broken.
4. **Invoicing is out of scope.** KSeF handles it; the plugin produces the
   per-student "to bill" numbers.
5. **An event's amount is a snapshot; its payer is a set of classes plus free
   text.** Once written to `mmr_events.amount` the figure never re-derives from
   lessons — adding, re-rating or deleting a lesson in an already-billed month
   must leave every existing event untouched, exactly as `fee_snapshot` does
   (§3.3). *(v2.2 stored the payer as free text only. v2.3 keeps that string as
   the readable snapshot in `payer_label` and adds the classes behind it in
   `mmr_event_classes`, because the class is what carries `settle_method` — the
   fact that says what kind of document this is.)* One document may name
   several classes, and the free-text remainder still covers a payer with no
   class on record. What is deliberately **not** modelled is a constraint: a
   month's settlement for one payer is routinely split across several documents
   (86% / 14% of the same amount), so nothing stops two events naming the same
   class in the same month, and the amount is never auto-summed from the
   selection. The optional `source_kind` / `source_id` / `source_period` trio
   still records where a *figure* came from, and is never cleared by later
   edits.

## 4. Technical requirements

- Plugin slug: `mm-register`. Main file `mm-register.php`. Prefix everything
  `mmr_`.
- WordPress 6.x, PHP 8.1+. Target environment: Divi theme + LiteSpeed Cache —
  assets are enqueued **only** on the plugin's own admin pages, with unique
  handles, never globally.
- **Custom database tables** via `dbDelta` (relational data; no CPTs).
- All admin pages gated by `manage_options`. All writes via nonces +
  capability checks. All SQL via `$wpdb->prepare`. All output escaped.
- Vanilla JS (ES6), one JS file + one CSS file.
- Money stored as `DECIMAL(10,2)`, PLN, displayed `1 234,56 zł` style.
- Dates stored as `DATE`. Business week is Monday–Friday only.
- Uninstall: keep data by default; the "Delete all plugin data" tool (typed
  confirmation) is the only way to remove it.

## 5. Database

Tables (all `{$wpdb->prefix}mmr_*`):

### 5.1 `mmr_students`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| name | VARCHAR(190) | required |
| email | VARCHAR(190) | optional |
| email_2 | VARCHAR(190) | optional; secondary e-mail |
| phone | VARCHAR(40) | optional; free text (`+48`, spaces, dashes) |
| level | VARCHAR(20) | optional; CEFR-ish free text with `A1`–`C2` suggestions |
| tbt_monthly_fee | DECIMAL(10,2) DEFAULT 0 | monthly surcharge, per student |
| notes | TEXT | free text |
| status | ENUM active/archived | archived students hidden from pickers |
| created_at / updated_at | DATETIME | |

### 5.2 `mmr_classes`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| name | VARCHAR(190) | what appears in the grid |
| fee | DECIMAL(10,2) | price per lesson (whole class) |
| client_type | VARCHAR(60) | free string; UI offers datalist of existing values |
| settle_method | VARCHAR(60) | free string; datalist |
| company_id | BIGINT NULL | FK-by-convention to `mmr_companies.id`; NULL for private students; indexed |
| notes | TEXT | |
| status | ENUM active/archived | |

Class pickers and lists order by `name`.

### 5.3 `mmr_companies`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| name | VARCHAR(190) | required, unique (case-insensitive) |
| address | TEXT | multi-line, as it should appear on an invoice |
| nip | VARCHAR(20) | Polish tax ID; digits and separators as typed |
| notes | TEXT | PO numbers, invoice-portal quirks, contact person, etc. |
| status | ENUM active/archived | archived companies hidden from the class picker |
| created_at / updated_at | DATETIME | |

Indexed on `name`.

### 5.4 `mmr_class_members`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| class_id / student_id | BIGINT FK | |
| share_per_lesson | DECIMAL(10,2) NULL | NULL → equal split of `class.fee` |

### 5.5 `mmr_lessons`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| lesson_date | DATE | Monday–Friday |
| slot | TINYINT | 1–12, vertical position in the day column |
| class_id | BIGINT FK | |
| fee_snapshot | DECIMAL(10,2) | copied from class.fee at creation; editable per lesson |
| shares_snapshot | TEXT NULL | JSON map student_id → share, captured at booking |
| note | VARCHAR(190) NULL | |
| created_at | DATETIME | |

### 5.6 `mmr_events`

The receivables register. One row per document issued.

| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| type | VARCHAR(20) DEFAULT `invoice` | one of `invoice` / `revolut` / `cash`, validated server-side. **Derived from the settle methods of the event's classes (§5.6.1), not chosen** — the posted value is accepted only where derivation cannot succeed |
| doc_number | VARCHAR(190) NULL | free text: KSeF ID, `czekam na PO`, or empty |
| issue_date | DATE | day the document was issued |
| payer_label | VARCHAR(190) | the readable "who": every selected class, then the free text, comma-joined. A snapshot — composed on save, and the only column the list, sort, search and export read |
| who_text | VARCHAR(190) NULL | the free-text remainder of the "who": a payer with no class on record. Pre-2.3 rows keep their whole label in `payer_label` and have none |
| amount | DECIMAL(10,2) DEFAULT 0 | snapshot; never re-derived from lessons |
| ksef | TINYINT(1) DEFAULT 0 | **invoices only** — any other type forces 0, server-side |
| sent | TINYINT(1) DEFAULT 0 | a document, e-mail or SMS went out; manual tick |
| paid | TINYINT(1) DEFAULT 0 | the money arrived |
| paid_date | DATE NULL | set to today when PAID is ticked; cleared when unticked |
| notes | TEXT NULL | NIP, address, "for the whole course", … |
| source_kind | VARCHAR(10) NULL | `class` / `student` / `company`, or NULL when typed by hand |
| source_id | BIGINT NULL | id of that class / student / company |
| source_period | CHAR(7) NULL | `YYYY-MM` the suggestion was taken from |
| created_at / updated_at | DATETIME | |

Indexed on `issue_date`, `paid`, `type` and `(source_kind, source_id)`.
Default list order is `issue_date DESC, id DESC`.

### 5.6.1 `mmr_event_classes` — who an event is for

An event bills one or more **classes**. One row per class, so events stay
queryable by class with a normal join rather than a `LIKE` over a label.

| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| event_id | BIGINT | the event |
| class_id | BIGINT | the class billed; any status — an archived class must stay selectable so a historical document can be recorded |

`UNIQUE (event_id, class_id)`, plus a key on `class_id`. Rows are deleted with
their event.

**Type derivation.** `mmr_classes.settle_method` is free text, so
`MMR_Data::event_type_hint()` is the single place that decides what a method
means (`invoice` / `revolut` / `cash`, or `''` when it maps to nothing).
`MMR_Data::derive_event_type()` resolves an event's type only when **every**
selected class maps to the **same** type. Anything else — classes that settle
differently, a class with no settle method, or no class at all — is reported
unresolved with the offending class names, and only then does the form ask the
user to choose. The server re-derives on every save and ignores the posted type
whenever derivation succeeds: the client value is a display convenience, never
an input.

**Legacy rows.** A pre-2.3 event has no class rows and no `who_text`; its whole
"who" is the `payer_label` string. It renders as it always did, and the edit
form puts that string in the free-text box so a re-save preserves it verbatim.
Old strings are never fuzzy-matched to class records — a wrong match would
silently corrupt financial history.

### 5.7 Versioning & upgrades

- `MMR_DB_VERSION` (currently `2.3`) is stored in the `mmr_db_version` option.
- On plugin load (and activation), if the stored option is behind, the upgrade
  routine runs and is idempotent:
  - **v1 → v2:** dump the legacy tables (settlements, budget_items,
    recurring_expenses, expense_payments, projections) to
    `.../mm-register-backups/pre-v2-{timestamp}.json` (best effort), then drop
    them.
  - **v2 → v2.1:** since `dbDelta` never drops columns, dump `mmr_classes` to
    `.../mm-register-backups/pre-v2.1-{timestamp}.json` (best effort), then
    `ALTER TABLE … DROP COLUMN` whichever of `venue` / `sort_name` still exist.
  - **v2.1 → v2.2:** no bespoke routine — `dbDelta` creates `mmr_events`. No
    existing table is touched and nothing is dropped.
  - **v2.2 → v2.3:** no bespoke routine — `dbDelta` creates `mmr_event_classes`
    and adds `mmr_events.who_text`. Existing events keep their `payer_label`
    and are never rewritten; no data is migrated (§5.6.1). The upgrade runs on
    `admin_init` at priority 1, ahead of the form handler, so the first request
    after an update cannot write to a table dbDelta has not reached yet.
  - Finally `dbDelta` runs for the current tables — creating `mmr_companies`
    and adding the new student columns (`email_2`, `phone`, `level`),
    `mmr_classes.company_id` and the `mmr_events` table. Backups are written to
    `wp-content/uploads/mm-register-backups/` (a write failure is logged, not
    fatal).

## 6. Admin UI structure

Top-level admin menu **"Register"** (dashicon `calendar-alt`), submenus in
this order:

1. **Calendar** (default)
2. **Settlement**
3. **Events**
4. **Students**
5. **Classes**
6. **Companies**
7. **Tools**

Design: clean, dense, spreadsheet-like. System font stack. TBT blue `#0859C6`
as the only accent color.

## 7. Feature specifications

### 7.1 Calendar (weekly grid)

- **5 columns (Mon–Fri)**, header = weekday + date. **12 slot rows** per day.
  Below each column: day total (Σ fee_snapshots). Toolbar: Prev / Today /
  Next / date jumper / Copy week on the left; Week total + month total on the
  right.
- **Filling a slot:** click an empty slot → inline type-ahead picker over
  active classes (keyboard: arrows + Enter). On select the lesson is created
  via AJAX with `fee_snapshot` = class fee and a `shares_snapshot`; totals
  update without reload.
- **Editing a filled slot:** click → popover: override fee (one-off), note,
  move to date/slot, delete. Deleting = the lesson never happened financially.
- **Copy week** duplicates the displayed week into a chosen target week,
  preserving fee snapshots and skipping occupied slots.
- Mobile (<782 px): one day per screen with day tabs.

### 7.2 Settlement (computed monthly report)

Read-only; recomputed from `mmr_lessons` for any requested month. Month picker
(prev / next / month jumper) defaults to the current month.

**Per-class table** — one row per class with ≥1 lesson:
Class | Client type | Company | Settle method | Lessons | Rate (snapshot) | To bill | Members.
***To bill* = Σ `fee_snapshot` (so one-off overrides are respected) + the TBT
surcharges attributed to that class (§3.2).** When a surcharge is included the
cell says `incl. TBT 25,00 zł` under the figure, so a total the lesson rates
alone cannot explain never looks like an error. Company shows the linked
company name (blank for private students). Expanding a row shows the
per-student breakdown: each member's share per lesson (explicit
`share_per_lesson` or equal split), summed for the month, plus that student's
TBT surcharge where this is the class carrying it. Footer: month total, on the
same *to bill* basis.

**Per-student summary** — the table actually used when issuing KSeF invoices:
Student | Company | Lessons | Lesson total | TBT surcharge | **To bill**. Company
lists the distinct companies of that student's lessons this month (blank for
private). TBT is added once per student per month (across all their classes).
Buttons: **Copy to clipboard** (TSV) and **Download CSV** — both include the
Company column. No PDF, no invoice generation.

### 7.3 Events (receivables register)

The question this page answers is **"what have I issued, and who still owes me
money?"** — the one the original REGISTER spreadsheet's `SETTLEMENT` sheet
answered, and the one the computed Settlement page deliberately does not. The
two are independent: Settlement stores nothing, Events stores everything.

**Toolbar.** Year selector (distinct `YEAR(issue_date)` plus the current year,
default = current year), type filter (all / invoice / revolut / cash), status
filter (all / **unpaid** / paid / not sent / invoice not in KSeF), search over
`payer_label`, `doc_number` and the names of the event's classes (so a class
renamed after a document was issued still finds it), and **+ Add event**. On
the right, three
`.mmr-total-chip`s — **all three scoped to the selected year and the active
filters, with the label saying so** — computed in one grouped query:

| Chip | Value |
|---|---|
| Issued | Σ amount |
| Received | Σ amount where paid |
| **Outstanding** | Σ amount where unpaid |

Outstanding replaces the spreadsheet's `SUMIF(…, FALSE, …)` counter (which
silently stopped at row 999; this has no ceiling) and gets the emphasis
treatment, as "To bill" has on Settlement.

**List:** Type | # | Issue date | Who | Amount | KSEF | SENT | PAID | Paid date
| Notes | actions. Sorted `issue_date DESC, id DESC` by default — the working
end of the register is the recent unpaid rows; Issue date, Who and Amount are
sortable (server-side, same header markup as Settlement).

- **KSEF, SENT and PAID are inline AJAX checkboxes, not modal fields.** Ticking
  PAID is the most frequent action here and must never require a form.
- **KSEF applies to invoices only** — `revolut` and `cash` render `—` with no
  control, and every write path forces `ksef = 0` for them regardless of what
  the client sends.
- Ticking PAID sets `paid_date` to today when empty and updates the row and the
  three chips with no reload; unticking clears the date.
- Unpaid rows carry `.mmr-event--unpaid`; the PAID checkbox stays the primary
  signal, so colour is never the only one.
- Row actions: Edit (modal) and **Delete** — a genuine delete behind a
  confirmation. Archive-not-delete does not apply: an event is the record of a
  document, and a wrong row must not linger inside a financial total.

**Add/Edit modal** (§8.2 grid): **Who** (required, full width) / Type ·
Issue date (defaults to today) / Amount + **Take amount from settlement** ·
Document number / Paid date · KSEF · SENT · PAID / Notes (full width), plus
hidden `id` and the `source_*` trio. Switching Type away from `invoice`
disables and unticks KSEF client-side; the server enforces the same rule
regardless.

**Who is the entry point of the form**, and it is a multi-select: the classes
this document bills, each a removable chip carrying its company and its
`settle_method` as a badge — so it is always visible why Type resolved the way
it did. Options are the register's own classes, searched through the same
`mmr_search_classes` endpoint the calendar picker uses; archived classes are
listed too (active first), because a historical document must still be
recordable. Focus alone opens the list: the options *are* the field, not an
extra action to reach for. Below the chips sits a free-text box for a payer
with no class on record. Who is required — at least one class, or free text.

**Type is derived, not chosen.** It renders read-only whenever the selected
classes agree on one settle method, and only becomes a selector in the cases
§5.6.1 calls unresolved, with an inline warning naming the classes responsible.
The client recomputes on every change to the selection; the server re-derives
independently on save.

**The amount picker** is the point of the feature: no number is ever retyped
from one page into another. **Take amount from settlement**, on the Amount
field, opens a panel *inside* the modal (never a nested modal) whose period
defaults to the month of the Issue date currently in the form, with ‹ / › to
step months. It lists three groups
built by `MMR_Data::event_suggestions()` from `MMR_Data::settlement()` — which
it reads and never modifies:

- **Classes** — `to_bill`: the class's lessons **plus the TBT surcharges
  attributed to it** (§3.2), because the class is what gets invoiced and the
  surcharge must not need remembering. Detail = lesson count + rates used, and
  the surcharge named when there is one.
- **Students** — `to_bill` (lesson shares + the TBT surcharge, once a month).
- **Companies** — Σ `to_bill` of that company's classes this month, with
  the rolled-up classes named in the detail so it is never an opaque number.

`Use` fills Amount and the hidden `source_*` fields, then closes the panel. A
**class** suggestion also adds that class to Who as a chip, from which Type
then derives exactly as it would had the class been picked by hand; student and
company suggestions fill the amount only, since neither is a class. **Every
field stays editable
afterwards, and editing does not clear `source_*`** — the provenance records
where the figure came from, not that it was left untouched. Because the trio is
recorded, the picker marks a suggestion that already produced an event for that
period (`already billed — 2 200,00 zł on 1 Jul`): a hint, never a block, since
splitting one settlement across several documents is normal. Empty state names
the month, so it is obvious the period — not the data — may be wrong.

### 7.4 Students (standalone page)

List: Name | Level | Phone | Email | Status. The Email cell shows the primary
e-mail with `email_2` on a muted second line when present. Search box (name /
email / email 2 / phone), status filter (active / archived / all), sortable by
Name (default) or Level. Row actions: Edit, Archive/Restore — **no hard
delete**.

Add/Edit modal (two-column grid, §8 layout rules):

| Row | Left | Right |
|---|---|---|
| 1 | Name (required) | Level |
| 2 | Email | Email 2 |
| 3 | Phone | TBT monthly fee (zł) |
| 4 | Status | *(empty)* |
| 5 | Notes (spans both columns) | |

Level is a text input backed by a `<datalist>` of `A1`–`C2`; free text allowed.

### 7.5 Classes (standalone page)

List: Name | Fee | Client type | Company | Settle method | Members (count +
names) | Status. Search (name), filters: client type, company, status. Row
actions: Edit, Archive/Restore.

Add/Edit modal layout:

| Row | Left column | Right column |
|---|---|---|
| 1 | Name (required) | Fee (zł) |
| 2 | Client type | Company (optional) |
| 3 | Settle method | Status |
| 4 | Notes (spans both columns) | |

Client type / Settle method are text inputs backed by `<datalist>`s of distinct
existing values. **Company** is a searchable text input backed by a `<datalist>`
of active company names; the form keeps a hidden `company_id` (synced
client-side) but resolution is authoritative server-side — a non-empty value
must match an existing company (any status, case-insensitive) or the save is
rejected with "Unknown company — add it on the Companies page first"; empty =
no company. A **"Manage companies →"** link opens the Companies page in a new
tab. Below the grid: the **members repeater** — student `<select>` +
"Share/lesson (zł)" input (placeholder `equal`, blank = equal split) + remove
button + "+ Add member".

A **"Quick add: new 1-on-1"** form creates a student and a same-named class
with one member in one step.

### 7.6 Companies (standalone page)

List: Name | NIP | Address (first line, ellipsised, full text in `title`) |
Classes (count linked) | Status. Search (name / NIP), status filter, sorted by
name. Row actions: Edit, Archive/Restore — **no hard delete**. Archiving a
company still linked to classes is allowed but warns first, listing the
affected class names; archived companies leave the class picker but stay on
existing classes.

Add/Edit modal: Name (required, full width) · NIP · Status · Address (full-width
textarea) · Notes (full-width textarea). Save enforces a case-insensitive
duplicate-name check.

### 7.7 Tools

- **Import**: JSON, dry-run first (counts + warnings before commit); refuses
  non-empty tables unless "merge". Format: `docs/import-format.md`.
- **Export**: full JSON dump of companies, students, classes, class members,
  lessons.
- **Danger zone**: delete all plugin data (typed confirmation).

## 8. Layout rules

### 8.1 Full-width pages

Every page is wrapped in `.mmr-wrap` (not core `.wrap`):

```css
.mmr-wrap { margin: 10px 20px 0 2px; max-width: none; width: auto; }
.mmr-wrap, .mmr-wrap *, .mmr-wrap *::before, .mmr-wrap *::after { box-sizing: border-box; }
```

The week grid is a CSS grid — `repeat(5, minmax(0, 1fr))` at `width: 100%`;
`minmax(0, 1fr)` is mandatory so long class names cannot push columns past the
container. No `max-width`, fixed pixel widths or centring containers anywhere
except small controls (fee inputs, icon buttons). Tables are `width: 100%;
table-layout: fixed;`. Long class names ellipsize with the full name in a
`title` attribute. The toolbar is a full-width flex row
(`justify-content: space-between; flex-wrap: wrap`). Must render without a
horizontal scrollbar at 1280 / 1440 / 1920 / 2560 px, admin menu folded or
expanded. Below 782 px the grid collapses to one day per screen with tabs.

### 8.2 Modal form grid

All modals (Student, Class, lesson popover) share one system:

```css
.mmr-modal        { width: min(760px, calc(100vw - 48px)); max-width: 100%; }
.mmr-modal__body  { padding: 24px; max-height: calc(100vh - 180px); overflow-y: auto; overflow-x: hidden; }

.mmr-form-grid    { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 24px; }
.mmr-field        { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.mmr-field--full  { grid-column: 1 / -1; }

.mmr-form-grid input, .mmr-form-grid select, .mmr-form-grid textarea {
  width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; margin: 0;
}

.mmr-member-row { display: grid; grid-template-columns: minmax(0, 1fr) 140px 32px; gap: 12px; align-items: center; }

@media (max-width: 640px) {
  .mmr-form-grid  { grid-template-columns: minmax(0, 1fr); }
  .mmr-member-row { grid-template-columns: minmax(0, 1fr); }
}
```

Every field is `<label>` above `<input>` inside an `.mmr-field` wrapper
(`min-width: 0` beats the grid-child intrinsic-width default). No inline
`style="width:…"`, no `size` attributes, no core admin width classes
(`regular-text`, `small-text`) inside modals. Acceptance: no element crosses
the modal border and no horizontal scrollbar inside any modal at 1280 px and
1920 px, admin menu folded or expanded.

## 9. Data import/export

See `docs/import-format.md`. The importer accepts students, classes (with
members) and lessons; the fee printed in the source sheet becomes
`fee_snapshot`; member share snapshots are computed at import time.

## 10. Out of scope (explicitly)

- Invoice generation of any kind (external KSeF program handles it). Events
  records documents issued in KSeF; it does not create them.
- Payment-gateway or bank integration; automatic payment matching. PAID is a
  manual tick, as SENT is.
- Overdue calculations, due dates, ageing buckets, dunning. Events answers
  "who still owes me", not "who is late" — there is no due-date column.
- Cancelled-lesson tracking / attendance analytics
- Budget, expenses or projection tracking
- Multi-user access, roles, front-end views
- Email sending / payment reminders
- Weekend columns; currencies other than PLN
- jQuery, npm, bundlers, CSS frameworks

## 11. Acceptance checklist

- [ ] Register menu shows exactly: Calendar, Settlement, Events, Students,
      Classes, Companies, Tools.
- [ ] No route, file, handler, asset, DB table or doc section for the v1
      Receivables / Budget / Expenses / Projections pages survives a repo-wide
      grep (the upgrade routine's legacy-table drop list is the only
      exception). The v2.2 Events page and `mmr_events` table are **not**
      covered by this: they are current scope (§2, §7.3), and `events` must
      never appear in `MMR_DB::legacy_keys()`.
- [ ] `venue` and `sort_name` survive nowhere: DB column, form, list, filters,
      AJAX, JS, CSS, import/export, docs (the v2.1 migration's drop list aside).
- [ ] Pre-drop JSON backups are written to `wp-content/uploads/mm-register-backups/`
      before any legacy table or class column is dropped.
- [ ] Existing students, classes, members and lessons are intact after
      upgrade; fee snapshots unchanged.
- [ ] Students and Classes are separate pages with their own list + modal.
- [ ] Companies page has full CRUD, archive-not-delete and case-insensitive
      duplicate-name protection; the class Company field searches active
      companies, saves `company_id`, rejects unknown names and allows empty.
- [ ] Settlement shows the Company column (per-class and per-student) and
      includes it in the CSV export.
- [ ] Settlement recomputes any month purely from lessons and produces the
      per-student "to bill" summary, with copy + CSV export.
- [ ] A 4-member group with fee 200 and no explicit shares shows 50 zł/lesson/member.
- [ ] A student with tbt_monthly_fee 25 and lessons in two classes is billed
      the surcharge once — once in the per-student summary, and once across the
      two class rows (on the class with more lessons, not on both).
- [ ] A 1-on-1 student with tbt_monthly_fee 25 and four lessons at 150 shows
      625 zł as the class's To bill, in the month total, in the per-student
      summary and in the Events amount picker — the surcharge never has to be
      added by hand.
- [ ] Calendar fills the full admin content width at 1280/1440/1920/2560 px,
      menu folded and unfolded, with no horizontal scrollbar.
- [ ] No form control overflows any modal at any of those widths.
- [ ] Changing a class fee does not alter past months' totals.

### Events (v2.2)

- [ ] `{$wpdb->prefix}mmr_events` is created by `dbDelta` on upgrade from 2.1
      with no data loss in any existing table; `mmr_db_version` reads `2.2`;
      a fresh install and an upgrade end with the same schema.
- [ ] `legacy_keys()` does not contain `events`; `all_keys()` and `is_empty()`
      do.
- [ ] Adding the Events page changed nothing on Settlement. *(v2.3 does change
      it, on purpose: `settlement()` gains `tbt_total` / `to_bill` per class and
      `month_to_bill`, so the TBT surcharge reaches the per-class figures —
      see §3.2. Nothing is renamed or removed.)*
- [ ] Taking a class suggestion fills Who, Amount and Type in one click, with
      no typing.

### Who-first events (v2.3)

- [ ] `{$wpdb->prefix}mmr_event_classes` and `mmr_events.who_text` are created
      by `dbDelta` on upgrade from 2.2 with no data loss; `mmr_db_version`
      reads `2.3`; a fresh install and an upgrade end with the same schema.
- [ ] The event form opens with Who as the first field, and it accepts several
      classes, each a removable chip.
- [ ] Class options come from the register with no extra click; there is no
      "Take from settlement" button on Who.
- [ ] Selecting a class whose settle method is *invoice* sets Type to invoice
      with no user action, displayed read-only.
- [ ] Selecting classes with differing settle methods shows a warning naming
      them and requires one explicit Type choice; so does a class with no
      settle method, and a free-text-only payer.
- [ ] A posted `type` is ignored whenever derivation succeeds, including from a
      crafted request.
- [ ] Class IDs that resolve to nothing are discarded, never stored; an event
      of nothing but junk IDs is rejected as "Who is required".
- [ ] Saving stores every selected class and the resolved type; reopening the
      event restores the exact selection.
- [ ] Searching Who matches an event when *any* of its classes matches.
- [ ] A pre-2.3 event still displays its label, and can be edited and re-saved
      without data loss or a guessed class match.
- [ ] A student suggestion's amount equals that student's "To bill" on the
      Settlement page for the same month, TBT surcharge included.
- [ ] A company suggestion equals the sum of its classes' totals for the month,
      and its detail names those classes.
- [ ] Adding a lesson to a month that already has events leaves every existing
      event's amount unchanged.
- [ ] KSEF cannot be set on a `revolut` or `cash` event — not from the list,
      not from the modal, not by a crafted AJAX request.
- [ ] Ticking PAID in the list sets today's date, updates the row and the three
      chips with no reload; unticking clears the date.
- [ ] Outstanding = Σ amount of unpaid events for the selected year, and
      switching year or filters recomputes all three chips consistently with
      the visible rows.
- [ ] Export contains `events`; re-importing a fresh export reproduces them
      exactly; a v3 export without `events` still imports.
- [ ] The events list has no horizontal scrollbar at 1280 / 1440 / 1920 /
      2560 px, and no control overflows the event modal.

- [ ] No PHP notices/warnings; no JS console errors on any Register page.
- [ ] No plugin assets loaded on any non-plugin admin page or any front-end page.
