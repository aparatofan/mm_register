=== MM Register ===
Contributors: mariuszmirecki
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private back-office register for NET ENGLISH: weekly lesson calendar,
students & classes database and a computed monthly settlement report.

== Description ==

MM Register recreates the REGISTER Google Sheet's scheduling core inside
WordPress: a Monday–Friday weekly calendar grid where filling a slot with a
class name books a billable lesson, and a monthly settlement report — computed
entirely from booked lessons and their fee snapshots — that says exactly what
to bill each student, including TBT monthly surcharges and group splits.

This is a single-user administrator tool. It produces no front-end output and
loads its assets only on its own admin pages. It does not issue invoices
(handled externally by the KSeF program) and does not track payments — it
produces the per-student "to bill" numbers, with copy and CSV export.

Pure PHP + vanilla JavaScript. No jQuery, no external frameworks.

== Installation ==

1. Upload the `mm-register` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu. Database tables are created
   (and any pending upgrade runs) on activation.
3. Go to Register → Tools → Import to load data from a JSON file (dry-run
   first). See docs/import-format.md.

== Changelog ==

= 2.0.0 =
* Trim: removed Receivables, Budget, Expenses and Projections (pages,
  handlers, tables). The upgrade backs the dropped tables up to
  wp-content/uploads/mm-register-backups/ first.
* Students and Classes split into standalone pages with search, filters and
  archive/restore.
* Settlement reworked into a read-only computed monthly report with a
  per-student "to bill" summary, copy-to-clipboard and CSV export.
* Calendar now fills the full admin content width; modal forms rebuilt on a
  grid so no field can overflow the modal.

= 1.0.0 =
* Initial build to specification: Calendar, Settlement, Receivables,
  Students & Classes, Budget, Expenses, Projections, Tools (import/export/wipe).
