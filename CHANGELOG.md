## 2026-09-20 — Role terminology rename

Renamed roles across the app to avoid confusion between the two different
meanings "staff" used to carry:

- `admin` role → `staff`
- combined `admin + reviewer` group (formerly called `staff`) → `personnel`
- `reviewer` role → unchanged

Affected: controllers, views, CSS, email templates, and a one-time DB
migration (`migrateAdminRoleToStaff()` in `app/core/Model.php`) that
updates existing `users.role` and `invite_tokens.role` values from
`admin` to `staff`.

Note: `audit_logs.role` was intentionally left untouched because those are historical records of the role at the time of the action.
