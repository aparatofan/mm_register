# MM REGISTER — WordPress Plugin Specification

**Version:** 2.0 (trimmed)
**Author of spec:** Mariusz Mirecki + Claude
**Target:** WordPress plugin replacing the REGISTER Google Sheet's scheduling core: weekly lesson calendar, students & classes database, computed monthly settlement report.
**Implementation:** Claude Code. Pure PHP + vanilla JS. **No jQuery. No external JS/CSS frameworks. No build step.**

> v2 supersedes the v1 spec. v1 also shipped Receivables, Budget, Expenses and
> Projections; those were removed in v2 (see §2). The revision that drove the
> trim is documented in the repo history.

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
3. **Students** — standalone CRUD page.
4. **Classes** — standalone CRUD page (with members repeater and quick-add
   1-on-1).
5. **Tools** — JSON import (dry-run first), full JSON export, danger-zone wipe.

### Removed in v2 (do not re-add)
- Receivables page and the `mmr_settlements` table
- Budget page and `mmr_budget_items`
- Expenses page and `mmr_recurring_expenses` / `mmr_expense_payments`
- Projections page and `mmr_projections`

No paid/unpaid tracking, no invoice register, no attendance, no e-mail, no
reporting charts, no settings page beyond Tools.

## 3. Key modelling decisions

1. **Class, not student, is the schedulable unit.** A class has one or many
   member students. A private 1-on-1 student = a class with one member.
2. **TBT surcharge is a flat monthly amount per student.** It is added **once
   per month** to that student's bill if the student had ≥1 lesson that month,
   regardless of how many classes they attend. Stored per student, editable.
3. **Fee snapshotting.** Each lesson stores the class fee (and per-member
   shares) at the moment it is booked. Changing a class fee later affects only
   future lessons. This must never be broken.
4. **Invoicing is out of scope.** KSeF handles it; the plugin produces the
   per-student "to bill" numbers.

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
| venue | VARCHAR(60) | free string; datalist |
| settle_method | VARCHAR(60) | free string; datalist |
| notes | TEXT | |
| status | ENUM active/archived | |
| sort_name | VARCHAR(190) | optional; picker ordering |

### 5.3 `mmr_class_members`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| class_id / student_id | BIGINT FK | |
| share_per_lesson | DECIMAL(10,2) NULL | NULL → equal split of `class.fee` |

### 5.4 `mmr_lessons`
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

### 5.5 Versioning & upgrades

- `MMR_DB_VERSION` (currently `2.0`) is stored in the `mmr_db_version` option.
- On plugin load (and activation), if the stored option is behind, the upgrade
  routine runs: it first writes a JSON dump of any legacy v1 tables
  (settlements, budget_items, recurring_expenses, expense_payments,
  projections) to `wp-content/uploads/mm-register-backups/pre-v2-{timestamp}.json`
  (best effort — a write failure is logged, not fatal), then drops them, then
  runs `dbDelta` for the four current tables. The routine is idempotent.

## 6. Admin UI structure

Top-level admin menu **"Register"** (dashicon `calendar-alt`), submenus in
this order:

1. **Calendar** (default)
2. **Settlement**
3. **Students**
4. **Classes**
5. **Tools**

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
Class | Client type | Settle method | Lessons | Rate (snapshot) | Class total | Members.
*Class total = Σ fee_snapshot* (one-off overrides therefore respected).
Expanding a row shows the per-student breakdown: each member's share per
lesson (explicit `share_per_lesson` or equal split), summed for the month,
plus that student's TBT surcharge. Footer: month total.

**Per-student summary** — the table actually used when issuing KSeF invoices:
Student | Lessons | Lesson total | TBT surcharge | **To bill**. TBT is added
once per student per month (across all their classes). Buttons: **Copy to
clipboard** (TSV) and **Download CSV**. No PDF, no invoice generation.

### 7.3 Students (standalone page)

List: Name | Email | TBT monthly fee | Classes (comma-separated) | Status.
Search box (name/email), status filter (active / archived / all), sorted by
name. Row actions: Edit, Archive/Restore — **no hard delete**.
Add/Edit modal: Name (required) · Email · TBT monthly fee (zł) · Status ·
Notes (full width), per the layout rules in §8.

### 7.4 Classes (standalone page)

List: Name | Fee | Client type | Venue | Settle method | Members (count +
names) | Status. Search (name), filters: client type, status. Row actions:
Edit, Archive/Restore.

Add/Edit modal layout:

| Row | Left column | Right column |
|---|---|---|
| 1 | Name (required) | Fee (zł) |
| 2 | Client type | Venue |
| 3 | Settle method | Sort name (optional) |
| 4 | Status | *(empty)* |
| 5 | Notes (spans both columns) | |

Client type / Venue / Settle method are text inputs backed by `<datalist>`s of
distinct existing values. Below the grid: the **members repeater** — student
`<select>` + "Share/lesson (zł)" input (placeholder `equal`, blank = equal
split) + remove button + "+ Add member".

A **"Quick add: new 1-on-1"** form creates a student and a same-named class
with one member in one step.

### 7.5 Tools

- **Import**: JSON, dry-run first (counts + warnings before commit); refuses
  non-empty tables unless "merge". Format: `docs/import-format.md`.
- **Export**: full JSON dump of students, classes, class members, lessons.
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

- Invoice generation of any kind (external KSeF program handles it)
- Paid/unpaid or receivables tracking
- Cancelled-lesson tracking / attendance analytics
- Budget, expenses or projection tracking
- Multi-user access, roles, front-end views
- Email sending / payment reminders
- Weekend columns; currencies other than PLN
- jQuery, npm, bundlers, CSS frameworks

## 11. Acceptance checklist

- [ ] Register menu shows exactly: Calendar, Settlement, Students, Classes, Tools.
- [ ] No route, file, handler, asset, DB table or doc section for
      Receivables / Budget / Expenses / Projections survives a repo-wide grep
      (the upgrade routine's legacy-table drop list is the only exception).
- [ ] Pre-drop JSON backup is written to `wp-content/uploads/mm-register-backups/`
      before any legacy table is dropped.
- [ ] Existing students, classes, members and lessons are intact after
      upgrade; fee snapshots unchanged.
- [ ] Students and Classes are separate pages with their own list + modal.
- [ ] Settlement recomputes any month purely from lessons and produces the
      per-student "to bill" summary, with copy + CSV export.
- [ ] A 4-member group with fee 200 and no explicit shares shows 50 zł/lesson/member.
- [ ] A student with tbt_monthly_fee 25 and lessons in two classes is billed
      the surcharge once.
- [ ] Calendar fills the full admin content width at 1280/1440/1920/2560 px,
      menu folded and unfolded, with no horizontal scrollbar.
- [ ] No form control overflows any modal at any of those widths.
- [ ] Changing a class fee does not alter past months' totals.
- [ ] No PHP notices/warnings; no JS console errors on any Register page.
- [ ] No plugin assets loaded on any non-plugin admin page or any front-end page.
