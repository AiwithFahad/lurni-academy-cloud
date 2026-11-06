# Security Notes — Lurni Academy Cloud

## Authentication & sessions
- Passwords are stored with `password_hash()` (bcrypt) and checked with
  `password_verify()`. Seed/demo passwords are hashed the same way.
- Sessions use `httponly` cookies, `samesite=Lax`, `secure` when HTTPS is
  detected, and the session id is regenerated on login. Idle sessions expire
  after `SESSION_LIFETIME` seconds (default 7200).
- Login rate limiting: 5 failed attempts lock an email for 15 minutes
  (tracked in `users.failed_login_count` / `users.locked_until`).
- Password reset tokens are random (256-bit), stored hashed (SHA-256),
  expire after 1 hour, and are single-use.

## CSRF
- Every state-changing form includes `csrf_token()`; every POST handler calls
  `verify_csrf()` before doing any work. Tokens are per-session and compared
  with `hash_equals()`.

## SQL injection
- All database access goes through PDO prepared statements. No user input is
  ever concatenated into SQL. Dynamic `ORDER BY`/`IN (...)` clauses are built
  only from whitelisted column names and integer-cast ids.

## Multi-tenancy
- Every business table carries `tenant_id`. Every tenant-scoped query filters
  by the caller's tenant, and ownership checks (course instructor, submission
  student, certificate owner) are enforced in the WHERE clause, not in PHP
  after the fact.
- Suspended/cancelled tenants are blocked from all tenant dashboards by
  `check_tenant_active()`; past-due subscriptions inside the grace period
  still work, expired ones are auto-marked by `refresh_subscription_status()`.

## Payments integrity
- A payment is **never** marked successful by the person recording it. Tenant
  admins record payments as `pending`; only the Super Admin verifies them,
  which flips the payment to `completed`, marks the invoice paid, extends the
  subscription period and counts the coupon redemption. Revenue dashboards
  count `completed` payments only.

## Uploads
- `handle_upload()` enforces an extension allow-list, MIME checks, random
  file names and a 25 MB cap. Uploads land under `uploads/` with an
  `index.html` guard; on Apache also deny script execution in that directory.
  Storage usage is tracked per subscription and enforced against plan limits.

## XSS
- All output is escaped with `e()` (htmlspecialchars, ENT_QUOTES, UTF-8).
  Rich content fields are stored as plain text and escaped on render.

## Configuration
- Secrets live only in `.env` (never committed — see `.gitignore`). The
  shipped `.env.example` contains placeholders only.
- Set `APP_DEBUG=false` in production: debug password-reset links and error
  detail are disabled automatically when it is false.

## Production checklist
1. Serve over HTTPS; set strong `DB_*` credentials and a dedicated DB user.
2. Delete `install.php` after running it.
3. Make `uploads/` and `storage/` writable by PHP but not executable.
4. Keep `APP_ENV=production`, `APP_DEBUG=false`.
5. Back up the database regularly (`storage/backups/` is a suggested target).
