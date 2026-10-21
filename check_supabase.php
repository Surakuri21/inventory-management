<?php
declare(strict_types=1);
require __DIR__ . '/includes/supabase.php';

$c = getSupabaseConfig();
$restBase = rtrim($c['url'], '/') . '/rest/v1';

// 1. List tables via PostgREST OpenAPI discovery (at /rest/v1/)
$ch = curl_init($restBase . '/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $c['serviceKey'],
        'apikey: ' . $c['serviceKey'],
    ],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "OpenAPI discovery ($restBase/): HTTP $code\n";
$doc = json_decode((string)$resp, true);
if (isset($doc['definitions'])) {
    echo "Tables found: " . implode(', ', array_keys($doc['definitions'])) . "\n";
} elseif (isset($doc['paths'])) {
    echo "Paths found: " . implode(', ', array_keys($doc['paths'])) . "\n";
} else {
    echo "Response: " . substr((string)$resp, 0, 600) . "\n";
}
