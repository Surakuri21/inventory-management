<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
<div class="max-w-2xl mx-auto">
<h1 class="text-2xl font-bold text-gray-900 mb-6">Settings</h1>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
<h2 class="text-lg font-semibold text-gray-800 mb-3">Account</h2>
<a href="/change-password" class="text-blue-600 hover:text-blue-800 font-medium">Change your password</a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
<p class="text-sm text-gray-600">System settings will be configured here. Security checklist:
<ul class="list-disc list-inside mt-2 text-xs text-gray-500 space-y-1">
<li>Default admin password rotated to <code>ChangeMeNow!2026</code> (must change on first login)</li>
<li>HTTPS/TLS enabled on port 443 with HSTS header</li>
<li>Session cookies: Secure, HttpOnly, SameSite=Strict</li>
<li>Database least-privilege user (<code>inventory_app</code>) with SELECT/INSERT/UPDATE/DELETE only</li>
<li>APP_SECRET rotated to cryptographically random 64-char hex</li>
</ul>
</p>
</div>
</div>
</main>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>
