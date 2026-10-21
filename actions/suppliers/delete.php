<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('suppliers'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF.'); redirect('suppliers'); }
$id = validateId($_POST['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid supplier.'); redirect('suppliers'); }
$sup = fetchOne("SELECT id, name FROM suppliers WHERE id = :id", ['id' => $id]);
if (!$sup) { setFlashMessage('error', 'Not found.'); redirect('suppliers'); }
execute("DELETE FROM suppliers WHERE id = :id", ['id' => $id]);
logAuditAction('delete', 'suppliers', $id, ['name' => $sup['name']], null);
setFlashMessage('success', 'Supplier deleted.');
redirect('suppliers');
