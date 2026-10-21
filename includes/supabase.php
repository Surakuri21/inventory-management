<?php
/**
 * Supabase Sync Client
 * 
 * Handles pushing product data from PHP/MySQL (System B: Backoffice)
 * to Supabase/PostgreSQL (System A: Astro Storefront) via REST API.
 * 
 * Uses the Service Role key for admin-level writes (bypasses RLS).
 * 
 * @package InventoryManagement\Supabase
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Load environment variables and return Supabase config.
 * Returns array of Supabase config.
 */
function getSupabaseConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    loadEnv();

    $config = [
        'url'        => rtrim(envValue('SUPABASE_URL'), '/'),
        'serviceKey' => envValue('SUPABASE_SERVICE_KEY'),
        'table'      => envValue('SUPABASE_TABLE', 'products'),
    ];

    return $config;
}

/**
 * Check if Supabase sync is configured and enabled.
 */
function isSupabaseEnabled(): bool
{
    $config = getSupabaseConfig();
    return $config['url'] !== ''
        && $config['serviceKey'] !== ''
        && $config['url'] !== 'https://your-project.supabase.co'
        && $config['serviceKey'] !== 'your-service-role-key';
}

/**
 * Build product payload for Supabase sync.
 * Maps MySQL product row to Supabase table schema.
 *
 * SECURITY: `cost_price` (inventory cost / margin) is deliberately NOT sent.
 * The Supabase table is read by the server-side BFF and the storefront must
 * never receive cost data. Inventory cost stays owned by the IMS (MySQL) only.
 * See docs/runbooks/rb-0001-lock-down-products.md.
 */
function buildSupabaseProductPayload(array $product, array $category = [], array $supplier = []): array
{
    return [
        'sku'               => $product['sku'],
        'name'              => $product['name'],
        'description'       => $product['description'] ?? null,
        'category_id'       => $product['category_id'] ?? null,
        'category_name'     => $category['name'] ?? null,
        'supplier_id'       => $product['supplier_id'] ?? null,
        'supplier_name'     => $supplier['name'] ?? null,
        'unit_price'        => (float) ($product['unit_price'] ?? 0),
        // NOTE: cost_price intentionally omitted — see SECURITY note above.
        'quantity'          => (int) ($product['quantity'] ?? 0),
        'low_stock_threshold' => (int) ($product['low_stock_threshold'] ?? 10),
        'unit_of_measure'   => $product['unit_of_measure'] ?? 'pcs',
        'is_active'         => (bool) ($product['is_active'] ?? false),
        'mysql_id'          => (int) $product['id'],  // Reference back to MySQL PK
        'synced_at'         => date('c'),             // ISO 8601 timestamp
    ];
}

/**
 * Sync a single product to Supabase (upsert by SKU).
 * 
 * @param array $product    MySQL product row (with id, sku, etc.)
 * @param array $category   Optional category row (for name)
 * @param array $supplier   Optional supplier row (for name)
 * @return array ['success' => bool, 'message' => string, 'data' => mixed]
 */
function syncProductToSupabase(array $product, array $category = [], array $supplier = []): array
{
    if (!isSupabaseEnabled()) {
        return [
            'success' => false,
            'message' => 'Supabase sync not configured (missing SUPABASE_URL/SERVICE_KEY in .env)',
        ];
    }

    $config = getSupabaseConfig();
    $payload = buildSupabaseProductPayload($product, $category, $supplier);

    $endpoint = $config['url'] . '/rest/v1/' . $config['table'] . '?on_conflict=sku';
    
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['serviceKey'],
            'apikey: ' . $config['serviceKey'],
            'Prefer: resolution=merge-duplicates,return=representation',
        ],
        CURLOPT_POSTFIELDS     => json_encode([$payload]),  // Supabase expects array for upsert
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Supabase Sync cURL Error: ' . $curlError);
        return ['success' => false, 'message' => 'cURL error: ' . $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Synced to Supabase', 'data' => json_decode($response, true)];
    }

    // Log detailed error for debugging
    error_log('Supabase Sync Failed: HTTP ' . $httpCode . ' - ' . $response);
    return [
        'success' => false,
        'message' => "Supabase sync failed (HTTP $httpCode)",
        'data'    => json_decode($response, true) ?? $response,
    ];
}

/**
 * Delete a product from Supabase by SKU.
 * Called when product is deleted in MySQL backoffice.
 * 
 * @param string $sku Product SKU to delete
 * @return array ['success' => bool, 'message' => string]
 */
function deleteProductFromSupabase(string $sku): array
{
    if (!isSupabaseEnabled()) {
        return ['success' => false, 'message' => 'Supabase sync not configured'];
    }

    $config = getSupabaseConfig();
    $endpoint = $config['url'] . '/rest/v1/' . $config['table'] . '?sku=eq.' . urlencode($sku);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $config['serviceKey'],
            'apikey: ' . $config['serviceKey'],
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'cURL error: ' . $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Deleted from Supabase'];
    }

    error_log('Supabase Delete Failed: HTTP ' . $httpCode . ' - ' . $response);
    return ['success' => false, 'message' => "Supabase delete failed (HTTP $httpCode)"];
}