# MM Register — Import JSON format

The MM Register importer (Tools → Import) accepts a single JSON file. This
document defines that format. The JSON is produced **separately** from
`REGISTER_2026.xlsx` (a converter step keeps PhpSpreadsheet out of the plugin).

The importer is:

- **Dry-run first** — shows counts and warnings without writing anything.
- **Guarded** — refuses to run on non-empty tables unless *merge* is enabled.

---

## Top-level shape

```json
{
  "version": 1,
  "students": [],
  "classes": [],
  "lessons": [],
  "settlements": [],
  "budget_items": [],
  "recurring_expenses": [],
  "projections": []
}
```

Every top-level key is optional; a missing array is treated as empty. Order in
the file does not matter — the importer resolves cross-references itself.

## References (`ref`)

Because real database IDs are unknown before import, entities are linked by
**string references** you assign, not by numeric IDs:

- A `student` may carry a `ref` (any unique string, e.g. `"s_jakub"`).
- A `class` may carry a `ref` (e.g. `"c_psych12"`).
- Class members link to a student via `student_ref`.
- Lessons and settlements link to a class via `class_ref`.

As a fallback, if a `ref` is omitted, the entity **name** is used as its
reference. So a lesson may reference a class either by `class_ref` or by
`class_name` (the class's exact `name`). Unknown references produce a **warning**
and those lessons are **skipped** (not fatal).

---

## `students`

| field | type | notes |
|---|---|---|
| `ref` | string | optional; unique reference for linking |
| `name` | string | **required** |
| `email` | string | optional |
| `tbt_monthly_fee` | number | optional, default 0 |
| `notes` | string | optional |
| `status` | `"active"` \| `"archived"` | optional, default `active` |

```json
{ "ref": "s_jakub", "name": "Jakub Nowak", "email": "j@x.pl", "tbt_monthly_fee": 25 }
```

## `classes`

| field | type | notes |
|---|---|---|
| `ref` | string | optional |
| `name` | string | **required** — this is what shows in the calendar grid |
| `fee` | number | price per lesson for the whole class |
| `client_type` | string | free text (`private`, `AWP`, `PWC`, …) |
| `venue` | string | `online` / `office` / other |
| `settle_method` | string | `invoice` / `REVOLUT` / `rozliczenie` / other |
| `notes` | string | optional |
| `status` | `"active"` \| `"archived"` | optional |
| `sort_name` | string | optional; picker ordering |
| `members` | array | see below |

Each `members` entry:

| field | type | notes |
|---|---|---|
| `student_ref` | string | reference (or student name) of a member |
| `share_per_lesson` | number \| null | member's per-lesson share; `null`/omitted = equal split of `fee` |

A 1-on-1 class has exactly one member with `share_per_lesson: null`.

```json
{
  "ref": "c_psych12",
  "name": "English for Psychologists 1.2",
  "fee": 200,
  "client_type": "AWP",
  "members": [
    { "student_ref": "s_kamil", "share_per_lesson": null },
    { "student_ref": "s_kasia", "share_per_lesson": null },
    { "student_ref": "s_tamara", "share_per_lesson": null },
    { "student_ref": "s_magda", "share_per_lesson": null }
  ]
}
```

## `lessons`

One entry per booked lesson. **The fee printed in the sheet becomes
`fee_snapshot`** — do not re-derive it from the current class fee.

| field | type | notes |
|---|---|---|
| `class_ref` | string | reference (or `class_name`) of the class |
| `date` | string | `YYYY-MM-DD`, Monday–Friday |
| `slot` | integer | 1–12, vertical position in the day column |
| `fee_snapshot` | number | fee for this lesson; omit to use the class fee |
| `note` | string | optional |

```json
{ "class_ref": "c_psych12", "date": "2026-06-03", "slot": 4, "fee_snapshot": 200 }
```

Member share snapshots are computed at import time from the class members and
`fee_snapshot`, so later membership changes never rewrite past months.

## `settlements`

Maps the SETTLEMENT sheet verbatim.

| field | type | notes |
|---|---|---|
| `doc_type` | string | `invoice` / `rachunek` / `revolut` / other / blank |
| `doc_number` | string | e.g. `eFA/2026/07/01/4/KSeF`, `158/2025`, or empty |
| `doc_date` | string | `YYYY-MM-DD` (convert Excel serials beforehand) |
| `payer_label` | string | free text — not a hard class link |
| `class_ref` | string \| null | optional link back to a class |
| `period` | string \| null | `YYYY-MM` the settlement covers |
| `amount` | number | |
| `ksef` | boolean | |
| `sent` | boolean | |
| `paid` | boolean | |
| `paid_on` | string \| null | `YYYY-MM-DD` |
| `notes` | string | optional |

## `budget_items`

Maps the BUDGET sheet's left column.

| field | type | notes |
|---|---|---|
| `label` | string | e.g. `CASH`, `mKonto`, `Bitcoin`, `card debt`, `rainy day` |
| `amount` | number | |
| `kind` | `"asset"` \| `"liability"` \| `"reserve"` \| `"note_only"` | |
| `sort_order` | integer | optional |
| `notes` | string | optional |

Suggested mapping: assets — CASH, mKonto, BUSINESS ACC, OSZCZĘDNOŚCIOWE,
Revolut ×4, Euros in cash, Bitcoin; liability — card debt; reserves — rainy day
(20 000), Chiny Rowerem (3 000).

## `recurring_expenses`

| field | type | notes |
|---|---|---|
| `title` | string | |
| `projected` | number | |
| `pay_type` | `"manual"` \| `"subscription"` | |
| `frequency` | `"monthly"` \| `"yearly"` | |
| `category` | `"critical"` \| `"moderate"` \| `"whim"` | |
| `due_note` | string | e.g. `20 dnia`, `Ania dorzuca połowę` |
| `active` | boolean | |
| `payments` | array | optional current-month payment rows |

Each `payments` entry: `{ "period": "YYYY-MM", "spent": number, "paid_on": "YYYY-MM-DD" }`.

## `projections`

| field | type | notes |
|---|---|---|
| `period` | string | `YYYY-MM` (unique) |
| `projected` | number | projected revenue |
| `expenses` | number | projected expenses |
| `notes` | string | optional |

Actual revenue is **not** imported — it is computed live from lessons.

---

## Export

The Tools → Export button produces a JSON file in exactly this format (with
`ref` values auto-generated as `s{id}` / `c{id}`), so an export can be
re-imported into an empty install and re-link correctly.

## Validation checklist (spec §10)

- A full import of `REGISTER_2026` should dry-run cleanly and produce matching
  month totals for Jan–Jun 2026 (spot-check e.g. JUNE total `14 970`).
- Because fees are snapshotted per lesson, changing a class fee afterwards must
  not alter past-month totals.
