# MM REGISTER — WordPress Plugin Specification

**Version:** 1.0 (initial build)
**Author of spec:** Mariusz Mirecki + Claude
**Target:** WordPress plugin replacing the REGISTER Google Sheet (lesson calendar, client database, monthly settlement, receivables register, personal budget, recurring expenses, revenue projections).
**Implementation:** Claude Code. Pure PHP + vanilla JS. **No jQuery. No external JS/CSS frameworks.**

---

## 1. Purpose and philosophy

Mariusz runs NET ENGLISH, a one-person English teaching business. For years he has managed everything in one Google Sheet. This plugin recreates that system inside WordPress with the same mental model, plus a few structural improvements.

Core philosophy inherited from the sheet:

- **The weekly grid is the calendar.** Monday–Friday columns, ~10 lesson slots per day. Filling a slot with a class name = a billable lesson. Muscle memory matters: the plugin's calendar must feel like the sheet.
- **If a lesson is in the calendar, it is charged.** No cancellation tracking in v1. Removing a lesson from the grid = it never happened financially.
- **Settlement is monthly.** At the end of the month, each class's lessons are counted, multiplied by fee, TBT monthly surcharges added, group amounts split per member, invoices issued externally (KSeF program — the plugin does NOT issue invoices), and the resulting receivables tracked until paid.
- **Everything is private.** This is a single-user back-office tool for the site administrator only. No front-end output whatsoever.

## 2. Key modelling decisions (agreed with Mariusz)

1. **Class, not student, is the schedulable unit.** A class has one or many member students. A private 1-on-1 student = a class with one member. Group courses (e.g. "English for Psychologists 1.2") = a class with several members, each with a per-lesson share.
2. **TBT surcharge is a flat monthly amount per student** (currently 25 zł for some students; may differ per student and change over time). It is added once per month to that student's settlement if the student had ≥1 lesson that month. Stored per student, editable.
3. **Fee snapshotting.** Each lesson stores the class fee (and per-member shares) at the moment it is booked. Changing a class fee later affects only future lessons. (Improvement over the sheet, where VLOOKUP retro-changed history.)
4. **No CHECK column.** The sheet's manual "settled?" tick is replaced by a real settlement workflow (see §6.3).
5. **Invoicing is out of scope.** The legacy per-client invoice sheets are dead. The plugin only *registers* settlements (with external KSeF invoice numbers pasted in) and tracks SENT / PAID.

## 3. Technical requirements

- Plugin slug: `mm-register`. Main file `mm-register.php`. Prefix everything `mmr_`.
- WordPress 6.x, PHP 8.1+. Target environment: Divi theme + LiteSpeed Cache (assets must be enqueued only on the plugin's own admin pages — never globally — to avoid the LiteSpeed/Divi conflicts seen in previous TBT plugins).
- **Custom database tables** via `dbDelta` on activation (this is relational data; do not use CPTs/postmeta).
- All admin pages gated by `manage_options`. All writes via nonces + capability checks. All SQL via `$wpdb->prepare`. All output escaped.
- Vanilla JS (ES6), one JS file + one CSS file, enqueued only on `mmr_` admin screens.
- Money stored as `DECIMAL(10,2)`, PLN assumed everywhere, displayed as `1 234,56 zł` style (Polish formatting: space thousands separator, comma decimal).
- Dates stored as `DATE`. The business week is Monday–Friday only.
- Uninstall: keep data by default; provide a "Delete all plugin data" tool behind a confirmation.

## 4. Database schema

Tables (all with `{$wpdb->prefix}` and prefix `mmr_`):

### 4.1 `mmr_students`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| name | VARCHAR(190) | required |
| email | VARCHAR(190) | optional |
| tbt_monthly_fee | DECIMAL(10,2) DEFAULT 0 | monthly surcharge, per student |
| notes | TEXT | free text (payment quirks, addresses, "nie wysyłać rachunku" etc.) |
| status | ENUM active/archived | archived students hidden from pickers |
| created_at / updated_at | DATETIME | |

### 4.2 `mmr_classes`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| name | VARCHAR(190) | what appears in the grid, e.g. "AWP F01", "Przemek Niżyński", "English for Psychologists 1.2" |
| fee | DECIMAL(10,2) | price per lesson (whole class) |
| client_type | VARCHAR(60) | free-ish taxonomy: private, AWP, AWP Finance, AWP HR, AWP UW, PWC, Merito WSB, Sobecki, SAAN, VIALTO, SEDIVIO, BIOCODEX, KYP, NCH… (store as plain string; provide datalist suggestions from existing values) |
| venue | VARCHAR(60) | online / office / other |
| settle_method | VARCHAR(60) | invoice / REVOLUT / rozliczenie / other |
| notes | TEXT | |
| status | ENUM active/archived | |
| sort_name | VARCHAR(190) | optional; default = name; used for picker ordering |

### 4.3 `mmr_class_members`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| class_id | BIGINT FK | |
| student_id | BIGINT FK | |
| share_per_lesson | DECIMAL(10,2) NULL | member's share of one lesson. If NULL → computed as `class.fee / member_count` at settlement time. Explicit values allow uneven splits. |

A 1-on-1 class has exactly one member row (share NULL → full fee).

### 4.4 `mmr_lessons`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| lesson_date | DATE | Monday–Friday |
| slot | TINYINT | 1–12, vertical position in the day column (purely visual ordering) |
| class_id | BIGINT FK | |
| fee_snapshot | DECIMAL(10,2) | copied from class.fee at creation; editable per lesson (one-off price overrides happen: e.g. "English for Psychologists" once at 250) |
| note | VARCHAR(190) NULL | optional short note |
| created_at | DATETIME | |

Unique-ish constraint: (lesson_date, slot). Multiple lessons of the same class on one day are allowed (the sheet has e.g. Małgosia Kot 3× on one day).

### 4.5 `mmr_settlements`
The receivables register (replaces SETTLEMENT sheet).
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| doc_type | VARCHAR(30) | invoice / rachunek / revolut / other / blank |
| doc_number | VARCHAR(120) | e.g. `eFA/2026/07/01/4/KSeF`, `158/2025`, or empty for Revolut settlements |
| doc_date | DATE | |
| payer_label | VARCHAR(190) | free text ("AWP UW", "POLANGLO (BYM)", "Marta Stepaniuk - 1") — NOT a hard FK, because payers sometimes differ from classes (split invoices, book royalties, one-offs) |
| class_id | BIGINT NULL FK | optional link back to a class |
| period | CHAR(7) NULL | settlement month `YYYY-MM` this covers, if applicable |
| amount | DECIMAL(10,2) | |
| ksef | BOOL | |
| sent | BOOL | |
| paid | BOOL | |
| paid_on | DATE NULL | |
| notes | TEXT | |

**Receivables = SUM(amount) WHERE paid = 0.** This number feeds the Budget dashboard.

### 4.6 `mmr_budget_items`
Replaces the BUDGET sheet's left column (accounts/assets) and adjustments.
| column | type | notes |
|---|---|---|
| id, label, amount, kind, sort_order, notes | | kind ENUM: `asset` (cash, accounts, Bitcoin), `liability` (card debt), `reserve` (rainy day fund, earmarked budgets like "Chiny Rowerem"), `note_only` |

Budget math (see §6.5) combines these with live receivables and remaining recurring expenses.

### 4.7 `mmr_recurring_expenses`
| column | type | notes |
|---|---|---|
| id | BIGINT PK AI | |
| title | VARCHAR(190) | |
| projected | DECIMAL(10,2) | |
| pay_type | ENUM manual/subscription | |
| frequency | ENUM monthly/yearly | |
| category | ENUM critical/moderate/whim | |
| due_note | VARCHAR(190) | "20 dnia", "Ania dorzuca połowę" |
| active | BOOL | |

### 4.8 `mmr_expense_payments`
Monthly checklist state: (expense_id, period `YYYY-MM`, spent DECIMAL, paid_on DATE). One row appears when an expense is marked paid for a month. "Remaining this month" = SUM(projected of active monthly expenses) − SUM(spent for current period).

### 4.9 `mmr_projections`
(period `YYYY-MM` PK, projected DECIMAL, expenses DECIMAL, notes). The list view also shows **actual revenue** per month computed from lessons (read-only comparison column — a nice upgrade over the sheet).

## 5. Admin UI structure

Top-level admin menu **"Register"** (dashicon: `calendar-alt`), submenu pages:

1. **Calendar** (default) — the weekly grid
2. **Settlement** — monthly settlement workspace
3. **Receivables** — the settlements register
4. **Students & Classes** — two-tab CRUD
5. **Budget** — dashboard
6. **Expenses** — recurring expenses checklist
7. **Projections**
8. **Tools** — import / export / danger zone

Design: clean, dense, spreadsheet-like. System font stack. Subtle borders. TBT blue `#0859C6` only as the accent color (active states, buttons, totals). No decorative fluff — this is a working tool used daily.

## 6. Feature specifications

### 6.1 Calendar (weekly grid)

- Layout: **5 columns (Mon–Fri)**, header shows weekday name + date (e.g. `PON 6.07`). **12 slot rows** per day (sheet had 9–10; give headroom). Below each column: **day total** (sum of fee_snapshots). Top-right: **week total**. Also show a running **month total** for the month the displayed week belongs to.
- Navigation: prev/next week arrows, "Today" button, a month/year jumper. Current day column subtly highlighted.
- **Filling a slot:** click an empty slot → inline searchable picker (type-ahead over active classes, matching anywhere in the name; keyboard: arrows + Enter). On select: create lesson via AJAX (`fee_snapshot` = class fee), cell fills with name + fee, totals update without page reload.
- **Editing a filled slot:** click → small popover with: change class, override fee for this lesson (one-off), add note, delete lesson. Deleting = lesson never happened (philosophy: grid is the financial truth).
- Drag-and-drop between slots/days is a **nice-to-have, not v1-blocking**; a "move" action in the popover (pick new date/slot) is sufficient for v1.
- **Copy week** button: copies all lessons from the currently displayed week into a chosen target week (default: next week). This replaces the sheet habit of duplicating week blocks. Skips target slots already occupied.
- Weekends are not shown. (Future iteration may add Saturday; keep the day-columns count configurable in code via a constant.)
- Mobile: the grid should collapse to one day per screen with day tabs — Mariusz sometimes fills in lessons right after teaching, possibly on the phone.

### 6.2 Students & Classes

Two tabs.

**Students tab:** table (name, email, TBT monthly fee, classes they belong to, status) + add/edit modal. Archiving hides from pickers, never deletes history.

**Classes tab:** table (name, fee, type, venue, settle method, members, status). Add/edit modal includes a **members** repeater: pick students, optional explicit `share_per_lesson` each (leave empty = equal split). Creating a private student should be fast: a single **"Quick add: new 1-on-1"** form that creates the student and a same-named class with one member in one step — this will be the most common case.

### 6.3 Settlement (the monthly workflow — the heart of the plugin)

Replaces: STUDENTS sheet columns COUNTER/月/CHECK + PSYCHOLOGISTS sheet + the mental month-end routine.

- Month picker (defaults to previous month before the 10th, else current month).
- Table, one row per class with ≥1 lesson in the month:
  - class name, **lesson count**, fee(s) used (show breakdown if fee_snapshots differ), **lessons subtotal**
  - **TBT surcharges**: for each member student with `tbt_monthly_fee > 0` who had ≥1 lesson in the month → add their monthly fee once. Shown explicitly (e.g. `+ TBT: Jakub 25,00`).
  - **class total** = lessons subtotal + TBT surcharges
  - expandable **per-member split** for multi-member classes: per member = Σ(their share_per_lesson snapshot per lesson) — for NULL shares, computed as fee_snapshot / member_count per lesson; plus their own TBT surcharge. (This reproduces PSYCHOLOGISTS: 4 lessons × 50 zł share = 200 zł per member.)
  - settle_method and a **"Record settlement"** action → prefilled modal creating one `mmr_settlements` row (or one per member for per-member settlement — checkbox in the modal), with `period` set. After recording, the row shows a ✓ "settled" badge with links to the created register entries.
- Bottom line: month grand total; already-settled vs. not-yet-settled amounts.
- Per-lesson share snapshot: when a lesson is created for a multi-member class, also snapshot the member shares (store JSON in a `shares_snapshot` column on `mmr_lessons`, or a small `mmr_lesson_shares` table — implementer's choice; the requirement is that later membership changes don't rewrite past months).

### 6.4 Receivables (settlements register)

- Filterable table of `mmr_settlements` (period, payer, paid/unpaid, year). Inline toggles for KSEF / SENT / PAID (PAID toggle also stamps `paid_on` = today, editable).
- Header widgets: **Unpaid total** (the sheet's `SUMIF ... FALSE`), unpaid count, oldest unpaid.
- Manual "Add entry" for things not born from the settlement screen (POLANGLO royalties, one-off trainings, corrections).

### 6.5 Budget

Reproduces the BUDGET sheet as a live dashboard:

```
SUB-TOTAL 1  = Σ assets                                (available money)
receivables  = Σ unpaid settlements                    (auto, linked to Receivables)
SUB-TOTAL 2  = SUB-TOTAL 1 + receivables               (when all money arrives)
− liabilities (card debt)
− remaining recurring expenses this month              (auto from §6.6)
= TOTAL
− reserves (rainy day, earmarked funds)
= FREELY AVAILABLE
```

Assets/liabilities/reserves are editable inline (Mariusz updates balances by hand, as in the sheet). Each computed line labelled in the sheet's spirit ("tyle mi zostaje na koniec miesiąca"). Show a "last updated" timestamp on manually edited items.

### 6.6 Expenses

- Monthly checklist view for the current period: each active monthly expense with projected amount, a "mark paid" action (records spent + paid_on into `mmr_expense_payments`; spent prefilled with projected, editable — actual amounts sometimes differ).
- Header: projected total, paid so far, **remaining** (feeds Budget).
- Yearly-frequency items listed separately, no monthly nagging.
- CRUD for the expense definitions.

### 6.7 Projections

Simple table by month: projected revenue, projected expenses, difference, notes — plus a computed **actual** column (Σ fee_snapshot of lessons in that month) for reality checks.

### 6.8 Tools

- **Import** (see §7).
- **Export**: full JSON dump of all tables (backup / portability).
- **Danger zone**: wipe all plugin data (typed confirmation).

## 7. Data migration (one-time import)

The plugin ships with an importer accepting a **JSON file** in a defined format (documented in `docs/import-format.md`). The JSON will be produced separately by Claude from `REGISTER_2026.xlsx` (this keeps PhpSpreadsheet out of the plugin). Import must be:

- **Dry-run first**: show counts (students, classes, memberships, lessons, settlements, budget items, expenses, projections) and any warnings (unknown class names in lessons, duplicate students) before committing.
- Idempotent-ish: refuse to run on non-empty tables unless "merge" is explicitly chosen.

Import mapping notes for the JSON generation step (context for whoever builds the converter):

- SCHEDULE grid → lessons: month marker rows contain a date serial in column A; each week block starts with a row of 5 date serials (Mon–Fri) and has up to ~10 slot rows of (name, fee) pairs in columns B/C, D/E, F/G, H/I, J/K. `empty` = empty slot. The **fee printed in the sheet becomes `fee_snapshot`** (do NOT re-derive from current class fee).
- STUDENTS sheet → split into students, classes and memberships. Every row becomes a class; 1-on-1 rows also become a student + membership. Known group classes (English for Psychologists 1/1.2/2/3) get their members from the PSYCHOLOGISTS sheet + the note column ("Kamil, Kasia, Tamara, Magda, Sylwia"). TYPE/VENUE/SETTLE/EMAIL/notes map directly. `TBT` column → `tbt_monthly_fee` on the corresponding student. STATUS/CHECK/COUNTER/month columns are derived data — skip.
- SETTLEMENT sheet → `mmr_settlements` rows verbatim (doc_type from col A when present, number, date, payer_label, amount, KSEF/SENT/PAID). Dates are Excel serials in old rows and real dates in new ones — handle both.
- BUDGET → budget_items (assets: CASH, mKonto, BUSINESS ACC, OSZCZĘDNOŚCIOWE, Revolut ×4, Euros in cash, Bitcoin; liability: card debt; reserves: rainy day 20 000, Chiny Rowerem 3 000).
- RECURRING EXPENSES → definitions (+ current-month payment rows where SPENT/PAID ON filled).
- PROJECTION → projections.
- Legacy invoice sheets (Jadwiga Chorązka … VIALTO, KOREKTA PWC IT, ADMIN, MONTHS) — **ignored entirely**.

## 8. Out of scope for v1 (explicitly)

- Invoice generation of any kind (external KSeF program handles it)
- Cancelled-lesson tracking / attendance analytics (future iteration)
- Multi-user access, roles, front-end views
- Email sending / payment reminders
- Weekend columns
- Currency other than PLN

## 9. Security & deployment notes

- Contains client PII and personal financial data: everything behind `manage_options` + nonces; REST/AJAX endpoints must re-check capability server-side; no data ever enqueued/printed outside the plugin's own admin screens.
- Recommend deciding consciously whether this lives on thebluetree.pl (student-facing site) or a private install (e.g. mariuszmirecki.pl). The plugin itself must be safe either way.
- LiteSpeed Cache: admin pages are uncached by default, but ensure AJAX endpoints send no-cache headers anyway.

## 10. Acceptance checklist

- [ ] Book a lesson in an empty slot in under 3 seconds (click → type 3 letters → Enter)
- [ ] Week and day totals always match Σ fee_snapshot
- [ ] Copy-week populates next week correctly, skipping filled slots
- [ ] Settlement for a 4-member group with fee 200 and no explicit shares shows 50 zł/lesson/member
- [ ] Student with tbt_monthly_fee 25 and 2 lessons in the month settles at 2×fee + 25
- [ ] Changing a class fee does not alter past months' totals
- [ ] Unpaid receivables total on Receivables page = the same number shown in Budget
- [ ] Full JSON import of REGISTER_2026 dry-runs cleanly and produces matching month totals for Jan–Jun 2026 (spot-check against the sheet: e.g. JUNE total 14 970)
- [ ] No plugin assets loaded on any non-plugin admin page or any front-end page
