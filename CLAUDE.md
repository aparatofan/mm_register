# TBT Register — Claude Code guidance

Keep this file concise. It is loaded at the start of every Claude Code session.

## Start here

- This plugin contains live back-office business data. Make focused changes and avoid speculative refactors.
- Check `git status`, use targeted search, and read only the files/sections required by the task.
- Do not load `mm_register_SPEC.md` (large) or the full docs tree by default. Read the relevant section only when the task needs historical/specification context.
- Do not change unrelated schema, calculations, billing semantics, UI wording, or formatting.
- Inspect the final diff before finishing.

## Project basics

- Private WordPress back-office plugin, branded TBT Register in the plugin header; repository/main file names retain the historical `mm_register` / `mm-register.php` naming.
- Pure PHP + vanilla JavaScript. No jQuery, external UI framework, or build step.
- Main file: `mm-register.php`.
- Implementation: `includes/`; assets: `assets/`; supporting documentation: `docs/`.
- Main data domains: students, classes/members, lessons/calendar, companies, settlement calculations, and issued-document Events.
- There is no front-end output; assets must remain scoped to the plugin's own admin screens.

## Data invariants — treat these as high risk

- Lessons snapshot their fee/member shares at booking time. Later class-fee edits must not rewrite historical lessons.
- Settlement is computed from lesson data rather than stored as a second source of truth.
- Issued Event/document amounts are snapshots. Later lesson edits must not rewrite an already-issued document.
- Archiving/restoring students/classes is preferred to hard deletion in normal flows.
- Money is stored as `DECIMAL(10,2)`; do not introduce floating-point storage/arithmetic for persisted money.
- Database upgrades are versioned through `MMR_DB_VERSION` / `mmr_db_version`, must be idempotent, and must preserve existing live data unless an explicit migration says otherwise.
- Do not add/drop/rename columns or tables as a side effect of a UI task.

## Shared TBT security contract

- The guarded `tbt_owner` / `tbt_is_owner()` capability block is shared with TBT Hub and is intended to stay equivalent. Do not casually edit one copy in isolation.
- Preserve capability and nonce checks on every write.
- Preserve `$wpdb->prepare()` for SQL containing values.
- Sanitize input and escape output for its context.
- Import must remain safe-first: dry-run behavior and validation are not optional conveniences.
- Never commit personal data exports, production database dumps, credentials, FTP details, secrets, or API keys.

## Scope boundaries

- Invoicing itself, budgeting, expenses, and projections are outside this plugin unless the user explicitly expands scope.
- Events is a receivables/document-status register, not the old v1 settlement table and not an invoice generator.
- Do not create a second source of truth for amounts that are currently computed or snapshotted by design.
- Preserve Polish money/date presentation unless the task explicitly concerns localisation.

## Coding style

- Follow surrounding WordPress/PHP and vanilla-JS style; do not reformat whole files.
- Prefer small, local changes and existing repository/helpers over parallel abstractions.
- Keep queries and business calculations readable and explicit; clever compression is not a win here.
- Keep comments around migration, money, snapshot, ownership, and deployment decisions when they explain a non-obvious invariant.

## Validation

For changed PHP files, run:

```bash
php -l <changed-file>
```

For changed JavaScript files, run `node --check <changed-file>` when Node is available.

For changes affecting money, settlement, snapshots, imports, migrations, or permissions, test the smallest relevant happy path plus a historical/existing-data case. State clearly what requires live WordPress verification.

If a schema change is genuinely required, verify upgrade behavior from the previous DB version as well as a fresh install; do not test only the new schema.

## Git and deployment

- `main` is the integration branch; use a focused feature branch.
- A non-documentation push to `main` automatically deploys the live plugin over FTPS.
- Documentation-only changes (`*.md`, `docs/**`, `.github/**`) are ignored by the automatic deployment trigger.
- The FTP sync-state file is part of deployment efficiency; do not delete or redesign that mechanism casually.
- Never change deployment paths, secrets, or clean-slate behavior unless the task is specifically about deployment.

## Context discipline

- Prefer targeted search + narrow reads over broad exploration.
- Use `README.md` for current architecture; consult the large spec only for decisions not explained by current code/README.
- Do not dump whole database classes, specs, exports, or logs into the conversation when a focused excerpt is sufficient.
- Use git history only when needed to resolve why a data/business invariant exists.
- Finish with a concise summary: what changed, validation performed, data/migration impact (even if none), and live-site checks still needed.
- For a new unrelated task, prefer a fresh Claude Code session.
