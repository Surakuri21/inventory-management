# AGENTS.md — Inventory Management System

## Project Overview
- **Stack**: PHP 8.x (strict_types), MySQL/MariaDB (InnoDB), PDO, Tailwind CSS (CDN)
- **Architecture**: Modular front-controller (`index.php` router) → `includes/` (core libs) → `actions/` (POST handlers) → `views/` (templates)
- **Auth**: Session-based, `password_hash()`/`verify()`, roles: `admin` / `standard`
- **Database**: 3NF schema in `database.sql` (6 tables + seed data); `config/database.php` is gitignored (copy from `database.example.php`)
- **Default login**: `admin` / `admin123` — **must change on first login**

## Key Files & Directories
```
index.php                 # Front controller / router (all routes defined here)
config/database.php       # DB credentials (gitignored; template: database.example.php)
includes/
  db.php                  # PDO singleton + query helpers (fetchAll, fetchOne, insert, execute, transaction)
  auth.php                # Session, login/logout, RBAC (requireAuth, requireAdmin, isAdmin)
  functions.php           # Utilities (sanitize, flash messages, CSRF, formatting, audit logging)
  audit.php               # AuditLogger class (structured audit trail with JSON old/new values)
actions/                  # POST handlers (currently only auth/login.php, auth/logout.php exist)
views/
  layouts/                # header.php, sidebar.php, footer.php (wraps all authenticated pages)
  dashboard/index.php     # Stats cards, low-stock alerts, recent transactions, audit activity
  auth/login.php          # Login form
  # Other module views (products/, categories/, etc.) ROUTED BUT NOT YET CREATED
assets/
  css/style.css           # Minimal custom styles
  js/main.js              # Search, filters, confirmations
database.sql              # Full schema + seed data (run once to initialize)
.htaccess                 # Apache rewrite to index.php
```

## Routing (defined in `index.php`)
- Public: `login`, `logout`
- Authenticated (all roles): `dashboard`, `products` (index), `transactions` (index/create)
- **Admin only**: `categories`, `suppliers`, `users`, `audit`, `settings` + all create/edit/delete actions
- Route format: `?route=module&action=create|edit|delete` (empty action = index)

## Developer Commands
| Task | Command |
|------|---------|
| **Initialize DB** | `mysql -u root -p < database.sql` (creates `inventory_db`) |
| **Configure DB** | `cp config/database.example.php config/database.php` → edit credentials |
| **Run (Apache)** | Place in document root; ensure `mod_rewrite` + `AllowOverride All` |
| **Run (Nginx)** | See README.md for server block example |
| **PHP syntax check** | `php -l <file.php>` (no linting framework configured) |

## Conventions & Constraints
- **Strict typing**: Every PHP file starts with `declare(strict_types=1);`
- **PDO only**: Never use `mysqli` or string concatenation for SQL
- **Prepared statements**: Enforced via `PDO::ATTR_EMULATE_PREPARES => false`
- **Input handling**: Use `filter_var()`, `htmlspecialchars()` (helpers: `sanitize()`, `sanitizeString()`, `validateId()`)
- **Flash messages**: `setFlashMessage($type, $msg)` → retrieved once via `getFlashMessages()`
- **CSRF**: `csrfToken()` / `csrfField()` in forms; `verifyCsrfToken()` on POST
- **Audit trail**: Call `AuditLogger::logXxx()` or `logAuditAction()` for all CRUD + auth events
- **Session**: 30-min timeout, auto-regeneration, secure/httponly cookies (auto-HTTPS detection)

## Current State (Sept 2026) — updated 2026-09-26
- ✅ Database schema, auth, dashboard, routing, sidebar nav, audit logging
- ✅ `views/products/index.php`, `views/products/create.php`, `views/products/edit.php`, `views/categories/index.php`, `views/categories/create.php`, `views/categories/edit.php`, `views/suppliers/index.php`, `views/suppliers/create.php`, `views/suppliers/edit.php`, `views/users/index.php`, `views/users/create.php`, `views/users/edit.php`, `views/transactions/index.php`, `views/audit/index.php`, `views/settings/index.php` — all module views created
- ✅ `actions/products/create.php`, `actions/products/update.php`, `actions/products/delete.php`, `actions/products/transaction.php`, `actions/categories/create.php`, `actions/categories/update.php`, `actions/categories/delete.php`, `actions/suppliers/create.php`, `actions/suppliers/update.php`, `actions/suppliers/delete.php`, `actions/users/create.php`, `actions/users/update.php`, `actions/users/delete.php` — all module actions created
- ✅ Auth POST handler (front-controller) fully operational (`index.php` lines 85–125): CSRF (`verifyCsrfToken` / `hash_equals`), `password_verify()` via `authenticateUser()` in `includes/auth.php`, session regeneration (`session_regenerate_id(true)`) on login, `$_SESSION['user_id/username/role']` population, redirect `/dashboard` on success, generic error message on failure (no user enumeration), view fall-through preserved. Login form (`views/auth/login.php`) aligned: `method="POST"`, CSRF field (`csrfField()`), `$error` block; **fixed 2026-09-26**: form `action` corrected from `/index.php?route=login` to `/login`.
- ✅ Security fixes executed: `.env` (`APP_SECRET` = 64-char hex `5d5d...`); `security/update_admin_password.sql` created; `mysql` CLI executed (`UPDATE users SET...` applied — DB admin hash verified different from original seed); `.env` cleaned (`DB_USERNAME`/`DB_PASSWORD` aligned); `database.php` reads `.env`; `php.ini` session settings; nginx TLS/443 (+HSTS); SSL certs (`nginx/ssl/`); Docker stack rebuilt (`docker-compose up -d --build` running); DB user `inventory_app` created with `GRANT SELECT, INSERT, UPDATE, DELETE` (`mysql.user` verified). Pending: apply real `password_hash()` on production instance; verify HTTPS end-to-end.
- ⚠️ Approval policy changed to "never" (session setting) — no approvals/escalation possible; execute directly

## Current Global Status
**We are transitioning between Phase 4 (Security Hardening) and Phase 5 (Production Readiness), but with critical bottlenecks:**

1. **Default admin credentials unchanged** — admin/admin123 still active
2. **APP_SECRET placeholder** — shell command instead of random secret
3. **Database root access** — no dedicated low-privilege user (`inventory_app`)
4. **No HTTPS/TLS** — Nginx only listens on port 80
5. **Unverified Docker stack** — end-to-end functionality not validated

## Immediate Next Development Tasks (Top 3 Critical)
1. **Invalid Default Admin Credentials & Rotate APP_SECRET** — Security blocker: generate new admin password hash and real APP_SECRET
2. **Create Dedicated DB User & Enforce Least Privilege** — Security blocker: add `inventory_app` user with minimal privileges, update .env/docker-compose
3. **Validate Docker Stack & Add HTTPS Termination** — Production readiness: run `docker-compose up`, test full CRUD, configure Nginx SSL

## Gotchas
- `config/database.php` **must exist** before any page load (copy from example first)
- `$route` variable used in `sidebar.php` — ensure router sets it before including layouts
- `SESSION_TIMEOUT` constant defined in `auth.php` — used in header.php JavaScript
- `getIpAddress()` checks proxy headers — works behind Cloudflare/load balancers
- `total_amount` in transactions table is `GENERATED ALWAYS AS (quantity * unit_price) STORED` — don't insert manually

## References
- `README.md` — Installation, features, security checklist, role permissions, project structure
- `KOS/PROJECT-CONTEXT.md` — Durable project context (this repo linked from `C:\Users\Adria\KOS Command Center\02 - Projects\Active\Inventory_Management\`)
- `KOS/handoff.md` — Current execution continuity (replace-and-refresh)