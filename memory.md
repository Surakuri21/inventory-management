---
title: Memory — Verified Facts (Inventory_Management)
project: Inventory_Management
updated: 2026-09-30
---

# Memory — Verified Development Facts

Verified during 2026-09-26 session (approval policy = "never", no approvals requested):

- Database `inventory_db` exists (6 tables: users, categories, suppliers, products, transactions, audit_logs). Import verified via `mysql` CLI using `root` / `092104`.
- Seed data present: 1 admin user (`admin` / `admin123` — must change), 5 categories, 3 suppliers. No seed products (products count = 0 before CRUD use).
- `config/database.php` exists and configured (`127.0.0.1`, `inventory_db`, `root`, `092104`, `utf8mb4`, `PDO::ATTR_EMULATE_PREPARES => false`). Connection verified.
- Product module CRUD (minus transaction) complete:
  - `actions/products/create.php` — admin POST, CSRF (`verifyCsrfToken`), validation (unique SKU, required name/category, non-negative prices/quantity), audit log (`logAuditAction`), flash redirect.
  - `views/products/index.php` — listing with category/supplier LEFT JOINs, low-stock (`isLowStock`) and out-of-stock (`isOutOfStock`) indicators, admin Edit/Delete links (Delete is protected POST form with CSRF).
  - `views/products/create.php` — form with categories/suppliers dropdowns, CSRF hidden field, error/flash display (`getFlashMessages`).
  - `views/products/edit.php` — edit form pre-filled from DB, same dropdowns.
  - `actions/products/update.php` — admin POST, CSRF, validation (SKU unique check excluding current id), audit (`old_values` / `new_values`), redirect.
  - `actions/products/delete.php` — admin POST, CSRF, audit (`delete`), redirect.
- Key fix applied (verified in source): `actions/products/create.php` and `update.php` use raw `trim()` (not `sanitizeString` which applies `htmlspecialchars`) for DB-bound values; output in views uses `e()` / `sanitize()`.
- Router (`index.php`) handles `products` route with `create`, `edit`, `delete` actions; no router changes needed.
- Approval policy = "never" (session setting) — all file writes and DB changes executed directly without escalation requests.
- Security checklist remains open: default admin password unchanged (`admin`/`admin123`); HTTPS/cookie settings not configured.

Next confirmed priorities (completed through 2026-09-26 session):
1. ✅ `actions/products/transaction.php` + `views/transactions/index.php` (all 4 types: sale/restock/return/adjustment).
2. ✅ Categories module (view + actions complete).
3. ✅ Suppliers module (view + actions complete).
4. ✅ Users module (view + actions complete — includes `password_hash` for create/update).
5. ✅ Audit module (`views/audit/index.php` — admin log listing).
6. ✅ Settings module (`views/settings/index.php` — placeholder; checklist reminder).
7. ✅ Docker + Nginx infrastructure: `docker-compose.yml` (mysql 8.0, php-fpm 8.2, nginx alpine), `Dockerfile`, `nginx/default.conf` (security headers + front controller), `.env`/`.env.example`, `database.php` reads `.env`, `php.ini` session settings (`cookie_secure=1`, `httponly=1`, `use_strict_mode=1`).

---

## Security Hardening — Verified 2026-09-30 (End-to-End via Docker Stack)

All critical security checklist items **resolved and verified** against running Docker stack (`docker compose up --build -d`):

- **Default admin password rotated**: `admin` / `admin123` → `admin` / `ChangeMeNow!2026` (bcrypt cost=10). Old seed hash (`$2y$10$92IXUNpkjO0rO...`) replaced in both `database.sql` seed and live MySQL container. Verified: old password rejected, new password accepted → 302 redirect to `/change-password`.

- **APP_SECRET rotated**: New cryptographically random 64-char hex applied to `.env` and `.env.example`:
  ```
  58e44074bafc19d831689baf3d5e9d52b4da6f847f537f211ed4077fa4fb97e0
  ```
  Separated `DB_ROOT_PASSWORD` from `DB_PASSWORD` in `.env`/`.env.example`/`docker-compose.yml`.

- **Dedicated DB user with least privilege**: `inventory_app`@`%` confirmed with `GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.*`. Application connects as `inventory_app` (verified via `SELECT USER()` = `inventory_app@localhost`). `root` no longer used by application.

- **HTTPS/TLS configured**: Nginx listens on ports 80 + 443; HTTP→HTTPS redirect; HSTS header (`max-age=31536000; includeSubDomains`); self-signed cert at `nginx/ssl/inventory.crt` + `.key`; `ssl_protocols TLSv1.2 TLSv1.3`. Verified: `https://localhost` responds with valid TLS, `Secure` cookie flag present.

- **Password-change-on-first-login enforced**: `must_change_password` column added to `users` table (migration applied). Login POST handler redirects to `/change-password` when flag=1. POST handler validates current password, hashes new one via `password_hash()`, clears flag, logs audit event (`password_change`), redirects to dashboard. Verified end-to-end:
  1. Old password (`admin123`) rejected
  2. Rotated password (`ChangeMeNow!2026`) accepted → 302 `/change-password`
  3. Dashboard inaccessible until password changed
  4. Password change succeeds (`ChangeMeNow!2026` → `NewSecurePass!2026`) → 302 `/dashboard`
  5. Flag cleared in DB (`must_change_password = 0`)
  6. Audit trail — `password_change` event logged (audit_logs id=10)
  7. New password works for subsequent logins (no change-password prompt)

- **Session security hardened**: Cookies set with `Secure`, `HttpOnly`, `SameSite=Strict`; `php.ini` session settings enforced (`cookie_secure=1`, `cookie_httponly=1`, `use_strict_mode=1`).

- **Security headers confirmed**: HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`.

- **Dashboard renders correctly after login**: stats cards, sidebar nav, audit activity all functional.

---

## Remaining (Post-Production Deployment)
- Production HTTPS: Let's Encrypt cert for public deployment (self-signed works for dev/staging)
- Promote additional verified facts to this file as they occur

## Completed 2026-10-01
- **Password change link in user profile (self-service)**: Implemented `/profile` route accessible to all authenticated users via sidebar link. Profile page (`views/auth/profile.php`) displays account info (username, email, full name, role, last login, member since) and provides "Change Password" button linking to `/change-password`. Admin users also see "Manage Users" shortcut. All changes verified via Docker stack (route requires auth, redirects to login when unauthenticated).

## Completed 2026-10-02 (verified end-to-end via Docker stack)
- **Idempotency keys for transactions**: `transactions.idempotency_key` VARCHAR(64) + unique key `uk_transactions_idempotency`; form generates `bin2hex(random_bytes(32))` per load; handler rejects reused keys with warning flash before any DB write. Verified: duplicate POST → no double insert, no double stock deduction.
- **Two pre-existing critical bugs found & fixed during verification** (both would have failed in production):
  1. **Action files never started the session** — all 13 `actions/*/*.php` call `requireAuth()`/`requireAdmin()` without `startSecureSession()`; `$_SESSION` empty → every direct-action POST redirected to login. Fixed in `requireAuth()` (single point): starts session if `session_status() === PHP_SESSION_NONE`.
  2. **HY093 repeated named placeholder** — `transaction.php` stock UPDATE used `:delta` twice; with `PDO::ATTR_EMULATE_PREPARES=false`, native prepares reject repeated named params → every transaction silently rolled back ("Transaction failed"). Fixed with distinct `:delta_set`/`:delta_where` binding the same value.
- **Durable lesson**: with emulated prepares disabled, each named placeholder may appear only ONCE per SQL statement — bind the value under separate names if reused. Direct-action scripts get their session from `requireAuth()`, not the front controller.

## Completed 2026-10-04
- **Microservices webhook endpoint (Direction 1: Astro → PHP)**: `api/sync-order.php` — secure REST endpoint for e-commerce storefront checkout webhooks. Validates `X-Inventory-Sync-Key` header (`surakuri_super_secret_bridge_key_2026`) via `hash_equals()`; parses JSON (`product_slug`, `quantity_sold`); atomic stock deduction using `SELECT FOR UPDATE` row locking + guarded UPDATE; audit logging (`webhook_sale` action with JSON old/new values); JSON responses (200/400/401/404/409/500). Served directly by Nginx via `try_files` (no router). Ready for Astro/React storefront integration.
- **Microservices product sync (Direction 2: PHP → Astro/Supabase)**: `includes/supabase.php` — utility for upserting products to Supabase via REST API (Service Role key, `on_conflict=sku`). `actions/products/create.php`, `update.php`, `delete.php` call `syncProductToSupabase()` / `deleteProductFromSupabase()` after local DB commit. Payload includes product data + category/supplier names. Non-blocking: logs warning but doesn't fail admin action (eventual consistency). `.env` gains `SUPABASE_URL`, `SUPABASE_SERVICE_KEY`, `SUPABASE_TABLE`. Now the full bidirectional sync loop is implemented:
  1. **Astro checkout** → `POST /api/sync-order.php` → MySQL stock deducted
  2. **Admin adds product** → MySQL insert → Supabase upsert → Astro storefront sees it

## Completed 2026-10-05
- **Integration Testing Phase 3.7 Complete** — All manual E2E tests passed for bidirectional microservices sync:
  - **Direction 1 (Astro → PHP)**: Webhook stock deduction verified — stock 50→48, audit log `webhook_sale` recorded, error cases (404/409/401) tested
  - **Direction 2 (PHP → Astro/Supabase)**: Admin CRUD sync verified — create/update/delete in PHP admin pushes to Supabase `products` table with `sku`, `name`, `price`, `quantity`, `category_name`, `supplier_name`, `mysql_id`, `synced_at`
  - **Minor fixes applied**: Edit product numeric fields (`quantity`, `unit_price`, `cost_price`, `low_stock_threshold`) — fixed `TypeError: e() expects string` by casting `(string)` before `e()`; added placeholders (Quantity→`Current stock`, Price→`0.00`, Low Stock→`Alert threshold`); fixed UTF-8 BOM issue in product views
  - Test artifacts: `test_integration.php` (20+ assertions), `test-integration.ts` (Astro), `INTEGRATION_TEST_GUIDE.md` updated
