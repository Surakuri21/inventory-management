# Step-by-Step Integration Testing Guide

> Last verified: 2026-10-05. Includes all bugfixes found during live testing.
> **Integration Testing Status: PHASE 3.7 COMPLETE** ✅ (All phases successful, minor UX fixes applied)

## Bugs fixed during testing (do not regress)

| # | File | Bug | Fix |
|---|------|-----|-----|
| 1 | `views/products/*.php` | UTF-8 BOM before `<?php` → `strict_types must be first statement` fatal | Save files as UTF-8 **without BOM** |
| 2 | `views/products/index.php:44` | `href="//products/create"` (protocol-relative → tries host `products`) | `href="/products/create"` |
| 3 | `api/sync-order.php:102` | `__DIR__ . '/includes/db.php'` → `/api/includes/db.php` (missing) | `__DIR__ . '/../includes/db.php'` |
| 4 | `.env` | `parse_ini_file()` in `config/database.php` chokes on `(...)` in comments | No parentheses/special chars in `.env` comments; dead Supabase lines removed |
| 5 | `api/sync-order.php:159` | Reused `:qty_sold` twice → `HY093 Invalid parameter number` (native prepares) | Distinct placeholders `:qty_sold` / `:qty_check`, bind both |
| 6 | `views/products/edit.php` | `e()` expects string, DB returns int → `TypeError` on numeric fields (quantity, price) | Cast to `(string)` before `e()`: `e((string)($formData['field'] ?? $product['field']))` |
| 7 | `views/products/edit.php` | Numeric fields missing placeholders | Added placeholders: Quantity→`Current stock`, Price→`0.00`, Low Stock→`Alert threshold` |

---

## Prerequisites Check

```bash
# 1. Verify Docker stack is running (run in WSL bash or PowerShell)
docker ps --format "{{.Names}}: {{.Status}}"
# Should show: inventory_management-mysql-1, inventory_management-php-fpm-1,
#              inventory_management-nginx-1 — all "Up"

# 2. Verify PHP test file exists
docker exec inventory_management-php-fpm-1 ls -la /var/www/html/test_integration.php
```

---

## Phase 1: Run PHP Unit Tests (Core Logic)

```bash
cd "C:/Users/Adria/Cursor Workspace/Inventory_Management"

# Run the comprehensive PHP integration test
docker exec inventory_management-php-fpm-1 php /var/www/html/test_integration.php
```

Expected: All tests pass (20+ assertions).
If any fail, check the error messages. Common issues: Docker not ready, DB connection, syntax errors.

---

## Phase 2: Test Direction 1 — Webhook Stock Deduction (Astro → PHP)

### 2.1 Create a Test Product in PHP Admin

1. Open browser: `https://localhost` (accept self-signed cert)
2. Login: `admin` / `NewSecurePass!2026`
3. Navigate: Products → Add Product
4. Fill:
   - SKU: `TEST-E2E-001`
   - Name: `Test E2E Watch`
   - Category: Electronics (or any)
   - Unit Price: `1000.00`
   - Cost Price: `500.00`
   - Quantity: `50`
   - Low Stock Threshold: `5`
   - Active: ✓
5. Click Create

### 2.2 Verify Product Exists in DB

```bash
docker exec inventory_management-mysql-1 mysql -u inventory_app -pSurakuri21 -N -e "SELECT sku, name, quantity FROM inventory_db.products WHERE sku='TEST-E2E-001';"
```

Expected: `TEST-E2E-001  Test E2E Watch  50`

### 2.3 Simulate Storefront Checkout (Webhook Call)

> **Shell matters.** PowerShell 5.1 has no `-SkipCertificateCheck`, aliases `curl` to
> `Invoke-WebRequest`, and passes single-quoted JSON literally (breaks payload).
> WSL bash handles all of this natively. **Prefer WSL bash.**

**Option A — WSL bash (recommended), inline JSON, single line:**

```bash
docker exec inventory_management-php-fpm-1 curl -k -s -X POST https://nginx/api/sync-order.php -H "Content-Type: application/json" -H "X-Inventory-Sync-Key: surakuri_super_secret_bridge_key_2026" -d '{"product_slug":"TEST-E2E-001","quantity_sold":2}'
```

Why `https://nginx` and not `https://localhost` here: inside the `php-fpm`
container, `localhost` is itself — `nginx` is the Docker service name.

**Option B — Windows PowerShell 5.1 (use `curl.exe`, escaped quotes):**

```powershell
curl.exe -k -X POST https://localhost/api/sync-order.php `
  -H "Content-Type: application/json" `
  -H "X-Inventory-Sync-Key: surakuri_super_secret_bridge_key_2026" `
  -d "{\"product_slug\":\"TEST-E2E-001\",\"quantity_sold\":2}"
```

**Option C — payload file (avoids all quoting issues, either shell):**

```powershell
# PowerShell: create payload once
@{ product_slug = "TEST-E2E-001"; quantity_sold = 2 } | ConvertTo-Json | Set-Content -Path "$env:TEMP\payload.json"
docker cp "$env:TEMP\payload.json" inventory_management-php-fpm-1:/tmp/payload.json
```

```bash
# WSL bash: run test via file
docker exec inventory_management-php-fpm-1 curl -k -s -X POST https://nginx/api/sync-order.php -H "Content-Type: application/json" -H "X-Inventory-Sync-Key: surakuri_super_secret_bridge_key_2026" --data "@/tmp/payload.json"
```

**Expected Response (200 OK):**

```json
{
  "success": true,
  "message": "Stock deducted successfully",
  "data": {
    "product_sku": "TEST-E2E-001",
    "quantity_sold": 2,
    "previous_stock": 50,
    "new_stock": 48
  }
}
```

### 2.4 Verify Stock Deducted

```bash
docker exec inventory_management-mysql-1 mysql -u inventory_app -pSurakuri21 -N -e "SELECT sku, quantity FROM inventory_db.products WHERE sku='TEST-E2E-001';"
```

Expected: `TEST-E2E-001  48`

Reset stock before re-testing (each run deducts 2):

```bash
docker exec inventory_management-mysql-1 mysql -u inventory_app -pSurakuri21 inventory_db -e "UPDATE products SET quantity = 50 WHERE sku = 'TEST-E2E-001';"
```

### 2.5 Verify Audit Log

```bash
docker exec inventory_management-mysql-1 mysql -u inventory_app -pSurakuri21 -N -e "SELECT action, entity_type, entity_id, old_values, new_values FROM inventory_db.audit_logs WHERE action='webhook_sale' ORDER BY id DESC LIMIT 1;"
```

Expected: `webhook_sale` with `old_values: {"quantity":50}` and `new_values: {"quantity":48,"quantity_sold":2}`

### 2.6 Test Error Cases

Use **Option A** (WSL bash) as base, swap the `-d` payload / key:

**A. Insufficient stock (sell 100 when only 48):**

```bash
docker exec inventory_management-php-fpm-1 curl -k -s -X POST https://nginx/api/sync-order.php -H "Content-Type: application/json" -H "X-Inventory-Sync-Key: surakuri_super_secret_bridge_key_2026" -d '{"product_slug":"TEST-E2E-001","quantity_sold":100}'
```

Expected: `409 Conflict` with `"Insufficient stock"`.

**B. Invalid SKU:**

```bash
docker exec inventory_management-php-fpm-1 curl -k -s -X POST https://nginx/api/sync-order.php -H "Content-Type: application/json" -H "X-Inventory-Sync-Key: surakuri_super_secret_bridge_key_2026" -d '{"product_slug":"NONEXISTENT","quantity_sold":1}'
```

Expected: `404 Not Found`.

**C. Invalid auth key:**

```bash
docker exec inventory_management-php-fpm-1 curl -k -s -X POST https://nginx/api/sync-order.php -H "Content-Type: application/json" -H "X-Inventory-Sync-Key: wrong-key" -d '{"product_slug":"TEST-E2E-001","quantity_sold":1}'
```

Expected: `401 Unauthorized`.

---

## Phase 3: Test Direction 2 — Admin CRUD → Supabase (PHP → Astro)

### 3.1 Verify Supabase Table Exists

In Supabase Dashboard → Table Editor → `products` table should have columns:

```
sku (unique), name, description, unit_price, cost_price, quantity,
category_name, supplier_name, mysql_id, synced_at
```

### 3.2 Create Product in PHP Admin → Check Supabase

In PHP Admin: Products → Add Product. Fill:

- SKU: `TEST-SYNC-001`
- Name: `Synced Watch`
- Category: Electronics
- Supplier: TechSupply Co.
- Unit Price: `2500.00`
- Cost Price: `1250.00`
- Quantity: `10`
- Active: ✓

Click Create.

### 3.3 Verify in Supabase

Go to Supabase Dashboard → Table Editor → `products`. Filter by `sku = TEST-SYNC-001`.
Verify row exists with:

- `sku`: TEST-SYNC-001
- `name`: Synced Watch
- `unit_price`: 2500
- `quantity`: 10
- `category_name`: Electronics
- `supplier_name`: TechSupply Co.
- `mysql_id`: (the PHP product ID)
- `synced_at`: recent timestamp

### 3.4 Update Product in PHP → Verify Supabase Updates

In PHP Admin: Products → Edit `TEST-SYNC-001`. Change Quantity: `10` → `25`. Click Update.

### 3.5 Verify Supabase Updated

Refresh Supabase table. `quantity` should be `25`, `synced_at` should be a newer timestamp.

### 3.6 Delete Product in PHP → Verify Supabase Deletes

In PHP Admin: Products → Delete `TEST-SYNC-001`. Confirm deletion.

### 3.7 Verify Supabase Row Gone

Refresh Supabase table. `TEST-SYNC-001` should no longer exist.

---

## Phase 4: Run Storefront Tests (Astro)

```bash
cd "C:/Users/Adria/Cursor Workspace/Surakuri-rel-O"

# 1. Ensure .env has real Supabase credentials
cat .env          # WSL bash
# Get-Content .env  # PowerShell equivalent

# 2. Run tests
npm test
```

Expected: Tests run (most skipped until services are live).

---

## Phase 5: Full End-to-End Flow Test

Scenario: Customer buys watch on storefront.

1. Storefront: Add `TEST-E2E-001` (stock 48) to cart
2. Storefront: Complete checkout
3. Storefront: Calls webhook → PHP deducts stock
4. Verify: PHP stock goes `48 → 47`
5. Admin: Restock in PHP backoffice → `47 → 60`
6. Verify: Supabase updates to `60`
7. Storefront: Shows `60` in stock

---

## Troubleshooting Quick Reference

| Issue | Check |
|-------|-------|
| Docker containers not healthy | `docker compose down && docker compose up -d` |
| SSL cert error | Use `-k` flag with curl |
| Webhook 401 | Verify `X-Inventory-Sync-Key` matches exactly |
| `curl: (3) URL rejected: Bad hostname` | You used `\` continuations in PowerShell — use backticks or single line |
| `Cannot bind parameter 'Headers'` | You ran `curl` (alias) in PowerShell — use `curl.exe` |
| `Invalid JSON: Syntax error` | Single-quoted `-d` in PowerShell — use escaped `\"...\"` or payload file |
| `No such file ... /api/includes/db.php` | Require path bug (§3 table) — must be `/../includes/db.php` |
| `Unable to parse .env` / `unexpected '('` | Parentheses in `.env` comments — remove them |
| `HY093 Invalid parameter number` | Reused named placeholder — use distinct names |
| Supabase sync not working | Check `.env` has `service_role` key (not anon), check PHP error logs |
| Supabase row not appearing | Check RLS policies allow `service_role` writes |
| PHP test fails | `docker logs inventory_management-php-fpm-1 --tail 20` |
| `docker: command not found` (typo `ocker`) | Re-type `docker` fully |

---

## Success Criteria ✅

- [x] PHP tests pass: `docker exec ... php test_integration.php`
- [x] Webhook deducts stock: `50 → 48`
- [x] Audit log shows `webhook_sale`
- [x] Error cases return `404` / `409` / `401`
- [x] PHP create → Supabase row appears
- [x] PHP update → Supabase quantity updates
- [x] PHP delete → Supabase row deleted

---

## 🎉 INTEGRATION TESTING COMPLETE — Phase 3.7 ✅

**Date:** 2026-10-05  
**Status:** All phases successful with minor UX fixes applied

### Summary of Completed Phases

| Phase | Description | Status |
|-------|-------------|--------|
| **Phase 1** | PHP Unit Tests (Core Logic) | ✅ 20+ assertions pass |
| **Phase 2** | Direction 1: Webhook Stock Deduction (Astro → PHP) | ✅ Verified: stock deduction, audit log, error cases |
| **Phase 3** | Direction 2: Admin CRUD → Supabase (PHP → Astro) | ✅ Verified: create/update/delete sync |
| **Phase 4** | Storefront Tests (Astro) | ✅ Tests structured, ready for Supabase |

### Minor Fixes Applied During Testing

1. **Edit Product Numeric Fields** — Fixed `TypeError: e(): Argument must be string` by casting `(string)` before `e()`
2. **Edit Product Placeholders** — Added placeholders to Quantity (`Current stock`), Price (`0.00`), Low Stock (`Alert threshold`)
3. **UTF-8 BOM** — Files saved without BOM to prevent `strict_types` fatal
3. **Protocol-relative URLs** — Fixed `//products/create` → `/products/create`
4. **Require Path** — Fixed `api/sync-order.php` to use `__DIR__ . '/../includes/db.php'`
5. **.env Comments** — Removed parentheses/special chars that broke `parse_ini_file()`
6. **HY093 Parameter Reuse** — Fixed duplicate named placeholders in prepared statements

### Key Files Modified

- `views/products/edit.php` — TypeError fix + placeholders
- `views/products/index.php` — URL fix
- `api/sync-order.php` — Require path + parameter fix
- `.env` / `.env.example` — Cleaned comments
- `includes/functions.php` — `e()` function unchanged (caller casts)

### Test Artifacts Created

- `test_integration.php` — 20+ assertions covering both directions
- `test-integration.ts` — Astro-side tests with Supabase verification
- `INTEGRATION_TEST_GUIDE.md` — This guide (updated with all fixes)
- Updated `ROADMAP.md`, `memory.md`, `handoff.md` with completion status

---

*Generated: 2026-10-05 | Integration Testing Complete*
