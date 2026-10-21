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
$existing = fetchOne("SELECT * FROM categories WHERE id = :id", ['id' => $id]);
if (!$existing) { setFlashMessage('error', 'Category not found.'); redirect('categories'); }
$nameRaw = trim($_POST['name'] ?? '');
$name = ($nameRaw !== '' && mb_strlen($nameRaw) <= 100) ? $nameRaw : '';
$descRaw = trim($_POST['description'] ?? '');
$description = mb_strlen($descRaw) <= 2000 ? $descRaw : null;
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];
if ($name === '') { $errors[] = 'Name required.'; }
elseif ($name !== $existing['name']) {
    $dup = fetchOne("SELECT id FROM categories WHERE name = :name AND id != :id", ['name' => $name, 'id' => $id]);
    if ($dup) { $errors[] = 'Name must be unique.'; }
}
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('categories/edit?id=' . $id); }
execute("UPDATE categories SET name = :name, description = :desc, is_active = :active WHERE id = :id", [
    'name' => $name, 'desc' => $description, 'active' => $isActive, 'id' => $id,
]);
logAuditAction('update', 'categories', $id, ['name' => $existing['name'], 'is_active' => (int)$existing['is_active']], ['name' => $name, 'is_active' => $isActive]);
setFlashMessage('success', 'Category updated.');
redirect('categories');
