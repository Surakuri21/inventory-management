<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('categories'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF token.'); redirect('categories'); }
$id = validateId($_POST['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid category.'); redirect('categories'); }
$cat = fetchOne("SELECT id, name FROM categories WHERE id = :id", ['id' => $id]);
if (!$cat) { setFlashMessage('error', 'Category not found.'); redirect('categories'); }
execute("DELETE FROM categories WHERE id = :id", ['id' => $id]);
logAuditAction('delete', 'categories', $id, ['name' => $cat['name']], null);
setFlashMessage('success', 'Category deleted.');
redirect('categories');
