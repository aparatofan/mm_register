=== MM Register ===
Contributors: mariuszmirecki
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private back-office register for NET ENGLISH: lesson calendar, client database,
monthly settlement, receivables, budget, recurring expenses and projections.

== Description ==

MM Register recreates the REGISTER Google Sheet inside WordPress with the same
mental model: a Monday–Friday weekly calendar grid where filling a slot with a
class name books a billable lesson; a monthly settlement workflow that counts
lessons, adds TBT monthly surcharges, splits group amounts per member and
records receivables; a live budget dashboard; a recurring-expenses checklist;
and revenue projections.

This is a single-user administrator tool. It produces no front-end output and
loads its assets only on its own admin pages. It does not issue invoices
(handled externally by the KSeF program) — it registers settlements and tracks
SENT / PAID.

Pure PHP + vanilla JavaScript. No jQuery, no external frameworks.

== Installation ==

1. Upload the `mm-register` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu. Database tables are created on
   activation.
3. Go to Register → Tools → Import to load data from a JSON file (dry-run
   first). See docs/import-format.md.

== Changelog ==

= 1.0.0 =
* Initial build to specification: Calendar, Settlement, Receivables,
  Students & Classes, Budget, Expenses, Projections, Tools (import/export/wipe).
