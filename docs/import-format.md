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
  "version": 3,
  "companies": [],
  "students": [],
  "classes": [],
  "lessons": []
}
```

Every top-level key is optional; a missing array is treated as empty. Order in
the file does not matter — the importer resolves cross-references itself.
Only companies, students, classes (with members) and lessons are imported; any
other top-level keys are ignored.

## References (`ref`)

Because real database IDs are unknown before import, entities are linked by
**string references** you assign, not by numeric IDs:

- A `company` may carry a `ref` (e.g. `"co_awp"`).
- A `student` may carry a `ref` (any unique string, e.g. `"s_jakub"`).
- A `class` may carry a `ref` (e.g. `"c_psych12"`).
- Class members link to a student via `student_ref`.
- A class links to a company via `company_ref`.
- Lessons link to a class via `class_ref`.

As a fallback, if a `ref` is omitted, the entity **name** is used as its
reference (this applies to companies and classes too — a class may name its
company via `company_ref` or the company's exact `name`, and a lesson may name
its class via `class_ref` or `class_name`). Unknown references produce a
**warning**; unknown class refs on lessons cause those lessons to be **skipped**
(not fatal).

---

## `companies`

| field | type | notes |
|---|---|---|
| `ref` | string | optional; unique reference for linking |
| `name` | string | **required**, unique (case-insensitive) |
| `address` | string | multi-line, as it should appear on an invoice |
| `nip` | string | Polish tax ID, digits and separators as typed |
| `notes` | string | PO numbers, invoice-portal quirks, contact person, etc. |
| `status` | `"active"` \| `"archived"` | optional, default `active` |

```json
{ "ref": "co_awp", "name": "AWP UW", "nip": "525-000-00-00", "address": "ul. …\n00-000 Warszawa" }
```

## `students`

| field | type | notes |
|---|---|---|
| `ref` | string | optional; unique reference for linking |
| `name` | string | **required** |
| `email` | string | optional |
| `email_2` | string | optional; secondary e-mail |
| `phone` | string | optional; free text (`+48`, spaces, dashes allowed) |
| `level` | string | optional; CEFR-ish free text (`A1`–`C2`, `B2+`, `Matura`…) |
| `tbt_monthly_fee` | number | optional, default 0 |
| `notes` | string | optional |
| `status` | `"active"` \| `"archived"` | optional, default `active` |

```json
{ "ref": "s_jakub", "name": "Jakub Nowak", "email": "j@x.pl", "phone": "+48 600 100 200", "level": "B2", "tbt_monthly_fee": 25 }
```

## `classes`

| field | type | notes |
|---|---|---|
| `ref` | string | optional |
| `name` | string | **required** — this is what shows in the calendar grid |
| `fee` | number | price per lesson for the whole class |
| `client_type` | string | free text (`private`, `AWP`, `PWC`, …) |
| `company_ref` | string \| null | reference (or exact name) of a company; omit/`null` for private students |
| `settle_method` | string | `invoice` / `REVOLUT` / `rozliczenie` / other |
| `notes` | string | optional |
| `status` | `"active"` \| `"archived"` | optional |
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

---

## Export

The Tools → Export button produces a JSON file in exactly this format (with
`ref` values auto-generated as `co{id}` / `s{id}` / `c{id}`), so an export can
be re-imported into an empty install and re-link correctly.

## Validation checklist

- A full import should dry-run cleanly and produce matching month totals on
  the Settlement page for every imported month.
- Because fees are snapshotted per lesson, changing a class fee afterwards must
  not alter past-month totals.
