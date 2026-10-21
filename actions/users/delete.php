<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('users'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF.'); redirect('users'); }
$id = validateId($_POST['id'] ?? null);
if ($id === null) { setFlashMessage('error', 'Invalid user.'); redirect('users'); }
$user = fetchOne("SELECT id, username FROM users WHERE id = :id", ['id' => $id]);
if (!$user) { setFlashMessage('error', 'Not found.'); redirect('users'); }
execute("DELETE FROM users WHERE id = :id", ['id' => $id]);
logAuditAction('delete', 'users', $id, ['username' => $user['username']], null);
setFlashMessage('success', 'User deleted.');
redirect('users');
