<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('categories'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF token.'); redirect('categories'); }
$nameRaw = trim($_POST['name'] ?? '');
$name = ($nameRaw !== '' && mb_strlen($nameRaw) <= 100) ? $nameRaw : '';
$descRaw = trim($_POST['description'] ?? '');
$description = mb_strlen($descRaw) <= 2000 ? $descRaw : null;
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];
if ($name === '') { $errors[] = 'Category name is required.'; }
elseif (fetchOne("SELECT id FROM categories WHERE name = :name", ['name' => $name])) { $errors[] = 'Category name must be unique.'; }
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('categories/create'); }
$catId = insert("INSERT INTO categories (name, description, is_active) VALUES (:name, :desc, :active)", [
    'name' => $name, 'desc' => $description, 'active' => $isActive,
]);
logAuditAction('create', 'categories', (int)$catId, null, ['name' => $name, 'is_active' => $isActive]);
setFlashMessage('success', 'Category created.');
redirect('categories');
