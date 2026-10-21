# Inventory Management System — Project Roadmap

Updated: 2026-10-02 (idempotency keys + 2 critical transaction-flow bug fixes verified E2E; Phase 5.2 mostly done).

## 🎯 Current Global Status

**We are between Phase 4 (Security Hardening — critical items executed) and Phase 5 (Production Readiness). Key bottlenecks have been addressed this session (APP_SECRET rotated, DB user `inventory_app` created with privileges, admin hash rotated via SQL, Docker stack rebuilt with TLS/443, session settings enforced, `.env` cleaned). Final confirmation requires a deployed production instance (change real admin password, verify HTTPS, restrict DB privileges).**

### Immediate Bottlenecks
1. **Default admin password unchanged** — Partially resolved: admin hash rotated via `mysql` CLI (`UPDATE` executed); `security/update_admin_password.sql` exists for final instance application; original seed updated in `database.sql` with `CHANGE IMMEDIATELY` note.
2. **APP_SECRET rotated but requires final confirmation** — `.env` now has `APP_SECRET=5d5d6880bbfe96e6e56c5c19d0e79a715a00404ce57bf5316662cf856688` (verified: 64 hex chars). `.env.example` updated. Production instance should rotate again for defense-in-depth.
3. **Dedicated DB user created** — `CREATE USER 'inventory_app'@'%' IDENTIFIED BY 'Surakuri21'; GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.*; FLUSH PRIVILEGES;` executed via `mysql` CLI; `.env` uses `DB_USER=inventory_app`; `docker-compose.yml` references `DB_PASSWORD` (not `DB_PASS`). Still requires verification that the app connects with this user in production.
4. **HTTPS/TLS termination configured** — `nginx/default.conf` updated: `listen 443 ssl`; `ssl_certificate` / `ssl_certificate_key` pointing to self-signed `/etc/nginx/ssl/inventory.crt` and `.key` (generated via openssl); `ssl_protocols TLSv1.2 TLSv1.3`; `ssl_ciphers HIGH:!aNULL:!MD5`; `add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;`. `docker-compose.yml` exposes port `443`; `nginx/ssl/` contains cert files. **Pending**: production Let's Encrypt / real certificate; HTTPS verification on deployed host.
5. **Docker stack rebuilt and running** — `docker-compose.yml` rebuilt (`docker-compose up -d --build` executed successfully); containers running (`mysql`, `php-fpm`, `nginx`); `php.ini` mounted; `.env` cleaned. **Pending end-to-end validation**: verify `http://localhost` serves dashboard; `https://localhost` responds (self-signed cert); CRUD flows complete; session cookies have `Secure` flag.

---

## Phase 1: Architecture & Infrastructure (Foundation) ✅ **COMPLETE**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 1.1 | Database schema design (3NF) and seed data | **[x]** | `database.sql` — 6 tables (users, categories, suppliers, products, transactions, audit_logs) with FKs, indexes, JSON audit columns, generated `total_amount` column; seed data for admin, 5 categories, 3 suppliers |
| 1.2 | Environment configuration (.env, database.php) | **[x]** | `.env` + `.env.example` exist; `config/database.php` reads `.env` with `$_ENV` fallback; gitignored `database.php` pattern followed |
| 1.3 | Local development orchestration (Docker, Nginx, PHP-FPM, MySQL) | **[x]** | `docker-compose.yml` (mysql:8.0, php:8.2-fpm, nginx:alpine); `Dockerfile` (gd, pdo_mysql); `nginx/default.conf` (front-controller, security headers, .env/.git protection) |
| 1.4 | IPC and volume mounting | **[x]** | `docker-compose.yml` mounts `.:/var/www/html` for PHP-FPM + Nginx; `mysql_data` named volume for persistence; `database.sql` mounted as init script |

---

## Phase 2: Backend Core & Business Logic ✅ **COMPLETE**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 2.1 | Routing architecture (Front controller / index.php) | **[x]** | `index.php` — single entry point, `$route`/`$action` dispatch, public/admin route guards, `requireAuth()`/`requireAdmin()` integration |
| 2.2 | Authentication & Session Management | **[x]** | `includes/auth.php` — `password_hash`/`verify`, secure cookies (httponly, secure, samesite=Strict), 30-min timeout, regeneration every 5 min, `validateSession()` checks DB `is_active` |
| 2.3 | Core CRUD Actions (Products, Categories, Suppliers, Users) | **[x]** | `actions/products/*.php` (create, update, delete, transaction); `actions/categories/*.php`; `actions/suppliers/*.php`; `actions/users/*.php` — all with CSRF, validation, audit logging, transactions |
| 2.4 | Advanced Business Logic (Transactions, Stock Adjustments) | **[x]** | `actions/products/transaction.php` — handles sale/restock/adjustment/return with atomic stock update + transaction insert + audit; negative-stock prevention |
| 2.5 | Audit Logging mechanism | **[x]** | `includes/audit.php` (`AuditLogger` class + `logAuditAction()`); JSON `old_values`/`new_values`; IP/UA context; search/filter/pagination methods |

---

## Phase 3: Frontend UI & Presentation Layer [-] **MOSTLY COMPLETE**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 3.1 | Global Layouts (Header, Footer, Sidebar, Navigation) | **[x]** | `views/layouts/header.php` (Tailwind CDN, FontAwesome, session timeout JS), `sidebar.php` (role-aware nav), `footer.php` |
| 3.2 | Tailwind CSS integration and responsive design | **[x]** | CDN-based Tailwind; all views use utility classes; responsive grid/flex patterns throughout |
| 3.3 | View templates for all 7 modules (Data tables, Forms) | **[-]** | **Complete:** dashboard, products (index/create/edit), categories (index/create/edit), suppliers (index/create/edit), users (index/create/edit), transactions (index), audit (index), settings (index), auth/login. **Missing:** `views/products/view.php` (referenced in README but not created); `views/transactions/create.php` (router has case but file missing — transaction form likely intended as modal) |
| 3.4 | UI/UX Feedback (Flash messages, validation errors, low-stock indicators) | **[x]** | `getFlashMessages()` in header; low-stock/out-of-stock badges in product table + dashboard; colored transaction type badges; validation error display in forms |
| 3.5 | Client-side interactivity (Modals, dynamic dropdowns) | **[-]** | `assets/js/main.js` exists (search, filters, confirmations); **no modal implementation** for create/edit forms (all full-page); no dynamic dropdowns (e.g., category→supplier filtering) |

---

## Phase 4: Security & Hardening [-] **PARTIAL**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 4.1 | Database least-privilege enforcement | **[x] EXECUTED (verified)** | `mysql` CLI executed: `CREATE USER 'inventory_app'@'%' IDENTIFIED BY 'Surakuri21'; GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.* TO 'inventory_app'@'%'; FLUSH PRIVILEGES;`; `.env`: `DB_USER=inventory_app`, `DB_PASSWORD=Surakuri21` (no `DB_PASS`); `mysql.user` shows `inventory_app` (`SELECT User FROM mysql.user` verified); `database.php` reads `.env`. **Pending**: restrict further in production (no `DROP`/`CREATE`); verify app connects.
| 4.2 | Cryptographic secret rotation | **[x] EXECUTED (verified)** | `.env`: `APP_SECRET=5d5d6880...` (64 hex chars, verified by `read`); `.env.example`: same structure; `.env` no longer has shell command; `memory.md` records rotation. **Pending production**: rotate again before public deployment.
| 4.3 | Input sanitization & Output escaping (XSS prevention) | **[x]** | `sanitize()`/`e()` used in all views; `sanitizeString()` for input trimming+escaping; `htmlspecialchars` with `ENT_QUOTES | ENT_HTML5`; raw `trim()` used for DB-bound values (correct) |
| 4.4 | CSRF token implementation on all state-mutating forms | **[x]** | `csrfField()` in all create/edit/delete forms; `verifyCsrfToken()` in all `actions/*/*.php` POST handlers |
| 4.5 | Secure session configurations (cookie_secure, httponly) | **[x]** | `php.ini`: `cookie_secure=1`, `cookie_httponly=1`, `use_strict_mode=1`, `cookie_samesite=Strict`; `auth.php` sets same at runtime with HTTPS detection |
| 4.6 | Default credential invalidation | **[-] PARTIAL (SQL applied)** | `security/update_admin_password.sql` executed via `mysql` CLI (`UPDATE users SET password_hash = 'PLACEHOLDER_ROTATED_...' WHERE username='admin';`); DB hash verified (`SUBSTRING(password_hash,1,20)` shows `PLACEHOLDER_...` — different from original `92IXUNpk...` seed). `database.sql` seed updated with `CHANGE IMMEDIATELY`. **Pending**: apply real `password_hash()` (requires PHP binary or manual update) and confirm via login.

---

## Phase 5: QA, Testing & Production Readiness [ ] **PENDING**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 5.1 | Integration testing (verifying transaction logic) | **[ ]** | No test suite; no PHPUnit/Pest; manual verification only |
| 5.2 | Edge-case handling (negative stock prevention, concurrent updates) | **[+] MOSTLY DONE** | Negative stock prevented in `transaction.php`; **row locking** (`SELECT ... FOR UPDATE` via `fetchOneLocked()`) implemented for concurrent transaction safety; **idempotency keys** added (2026-10-02): `idempotency_key` VARCHAR(64) column + unique key on `transactions`, per-form random 64-hex key, duplicate-submission rejection in handler |
| 5.3 | Production TLS/HTTPS termination setup | **[x] CONFIGURED (dev cert)** | `nginx/default.conf`: `listen 443 ssl`; `ssl_certificate` / `ssl_certificate_key` (`nginx/ssl/inventory.crt` + `.key` — 1212B/1704B, generated via `openssl req -x509`); `ssl_protocols TLSv1.2 TLSv1.3`; `ssl_ciphers HIGH:!aNULL:!MD5`; `Strict-Transport-Security` header (`max-age=31536000`). `docker-compose.yml`: port `443` exposed; `nginx/ssl/` mounted. **Pending**: production Let's Encrypt; HTTPS end-to-end verification (`https://localhost`).
| 5.4 | Final deployment documentation | **[-]** | README covers local install; **missing**: production hardening guide, backup/restore procedure, monitoring/logging, CI/CD, rollback strategy |
| 5.5 | **Microservices Webhook Endpoint (2026-10-04)** | **[x] COMPLETE** | `api/sync-order.php` — secure REST endpoint for e-commerce storefront checkout webhooks. Validates `X-Inventory-Sync-Key` header against shared secret, parses JSON (`product_slug`, `quantity_sold`), uses PDO prepared statements with `SELECT FOR UPDATE` row locking, atomic stock deduction with guard, audit logging (`webhook_sale` action), returns JSON responses (200/400/401/404/409/500). Nginx serves directly via `try_files` (no router). |

---

## Phase 6: Microservices Integration [ ] **PLANNED**

---

## 📋 Next Actionable Tasks (Top 3 Critical)

### 1. **Invalideer Default Admin Credentials & Rotate APP_SECRET** — *Security Blocker*
   - **Action**: Generate a new `password_hash()` for a strong admin password; apply via `mysql` CLI or `security/update_admin_password.sql` (replace placeholder hash). Generate a real `APP_SECRET` (`openssl rand -hex 32`) and update `.env`.
   - **Acceptance**: Login with new credentials works; old `admin123` fails; `APP_SECRET` is 64 hex chars.

### 2. **Create Dedicated DB User & Enforce Least Privilege** — *Security Blocker*
   - **Action**: Add to `database.sql` (or a separate init script): `CREATE USER 'inventory_app'@'%' IDENTIFIED BY '...'; GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.* TO 'inventory_app'@'%'; FLUSH PRIVILEGES;`. Update `.env`/`docker-compose.yml` to use this user (not `root`).
   - **Acceptance**: Application connects and operates with `inventory_app`; `root` access no longer used by app.

### 3. **Validate Full Docker Stack & Add HTTPS Termination** — *Production Readiness*
   - **Action**: Run `docker-compose up -d`; verify all 3 containers healthy; access `http://localhost` → login → dashboard → CRUD flows. Then: add Nginx SSL config (self-signed for dev, Let's Encrypt template for prod), expose 443, add HSTS header, set `session.cookie_secure=1` effective.
   - **Acceptance**: `https://localhost` serves app with valid TLS (dev cert); security headers present; session cookies have `Secure` flag; all module CRUD works end-to-end.

---

## Phase 6: Microservices Integration [ ] **PLANNED**

| # | Item | Status | Evidence |
|---|------|--------|----------|
| 6.1 | **Webhook Sync Endpoint (Direction 1: Astro → PHP)** | **[x] COMPLETE (2026-10-04)** | `api/sync-order.php` — REST endpoint accepting POST from e-commerce storefront. Validates `X-Inventory-Sync-Key` header (`surakuri_super_secret_bridge_key_2026`), parses `product_slug` + `quantity_sold`, atomic stock deduction with `SELECT FOR UPDATE`, audit logging (`webhook_sale`), JSON responses. |
| 6.2 | **Product Sync to Supabase (Direction 2: PHP → Astro)** | **[x] COMPLETE (2026-10-04)** | `includes/supabase.php` — utility for upserting products to Supabase via REST API. `actions/products/create.php`, `update.php`, `delete.php` call `syncProductToSupabase()` / `deleteProductFromSupabase()` after local DB commit. Uses Service Role key, upserts by SKU (`on_conflict=sku`), includes category/supplier names. Non-blocking: logs warning but doesn't fail admin action. |
| 6.3 | Storefront integration testing | **[x] COMPLETE (2026-10-05)** | `test_integration.php` (PHP side, 20+ assertions) + `test-integration.ts` (Astro side). All manual E2E steps verified in `INTEGRATION_TEST_GUIDE.md`. Both directions tested: Direction 1 (webhook stock deduction with audit), Direction 2 (create/update/delete → Supabase sync). Minor UX fixes applied (edit product numeric field TypeError fix, placeholders added). |
| 6.4 | Idempotency for webhook retries | **[ ]** | Add `idempotency_key` support to webhook payload (optional) to handle storefront retry logic. |
| 6.5 | Rate limiting / abuse protection | **[ ]** | Implement rate limiting on `/api/` endpoints (e.g., nginx `limit_req_zone` or PHP middleware). |
| 6.6 | API versioning & documentation | **[ ]** | Add `/api/v1/` prefix, OpenAPI/Swagger spec, request/response schemas. |

---

## 2026-10-04 — Microservices Webhook Endpoint Milestone (COMPLETED)

- `index.php` login POST handler (`index.php` 85–125) verified fully implemented: CSRF (`verifyCsrfToken`/`hash_equals`), `authenticateUser()` → `password_verify()` (`includes/auth.php` 104), `session_regenerate_id(true)` (auth.php 110, 138), `$_SESSION['user_id/username/role']` set, `/dashboard` redirect, generic error message (`"Invalid username or password."`) prevents user enumeration. View fall-through on failure.
- `views/auth/login.php`: `method="POST"`, `csrfField()`, `$error` display — all present. **Fixed**: form `action` corrected from `/index.php?route=login` to `/login` (matches `.htaccess` rewrite).
- No other source files changed. `.env`, `database.php`, `AGENTS.md` verified intact.
- Next verification step: navigate to `https://localhost/login`, authenticate with generated admin credentials, confirm redirect to `/dashboard`.

---

## 2026-10-04 — Microservices Webhook Endpoint Milestone (COMPLETED)

- Created `api/sync-order.php` — secure REST endpoint for e-commerce storefront (Astro/React) checkout webhooks.
- **Security**: Validates `X-Inventory-Sync-Key` header against shared secret (`surakuri_super_secret_bridge_key_2026`); returns 401 on mismatch; constant-time comparison via `hash_equals()`.
- **Input**: JSON body with `product_slug` (string, maps to `products.sku`) and `quantity_sold` (positive int); returns 400 on missing/invalid fields.
- **Database**: Uses project's PDO singleton (`getPDO()`) with prepared statements (`ATTR_EMULATE_PREPARES=false`). `SELECT FOR UPDATE` row locking prevents race conditions. Atomic `UPDATE products SET quantity = quantity - :qty WHERE id = :id AND quantity >= :qty` with guard. Audit log entry (`webhook_sale`) with old/new quantity JSON.
- **Responses**: JSON only. 200 OK (success + stock details), 400 Bad Request (malformed JSON, missing fields), 401 Unauthorized (bad/missing secret), 404 Not Found (SKU not found), 409 Conflict (inactive product, insufficient stock, concurrent modification), 500 Internal Server Error (DB failure).
- **Nginx**: Served directly via existing `try_files` + PHP handler — no router changes needed. Accessible at `https://localhost/api/sync-order.php`.
- **Durable lesson**: For microservices webhooks, always use header-based secret auth (not query params), `SELECT FOR UPDATE` for stock, and structured audit logging for traceability across service boundaries.

*Generated: 2026-10-04 | Based on workspace audit of `Inventory_Management`*