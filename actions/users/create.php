<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('users'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF.'); redirect('users'); }
$usernameRaw = trim($_POST['username'] ?? '');
$username = ($usernameRaw !== '' && mb_strlen($usernameRaw) <= 50) ? $usernameRaw : '';
$emailRaw = trim($_POST['email'] ?? '');
$email = ($emailRaw !== '' && mb_strlen($emailRaw) <= 100) ? $emailRaw : '';
$passwordRaw = $_POST['password'] ?? '';
$fullName = sanitizeString($_POST['full_name'] ?? '', 100) ?: null;
$role = ($_POST['role'] ?? 'standard') === 'admin' ? 'admin' : 'standard';
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];
if ($username === '') { $errors[] = 'Username required.'; }
elseif (fetchOne("SELECT id FROM users WHERE username = :u", ['u' => $username])) { $errors[] = 'Username taken.'; }
if ($email === '') { $errors[] = 'Email required.'; }
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email.'; }
elseif (fetchOne("SELECT id FROM users WHERE email = :e", ['e' => $email])) { $errors[] = 'Email taken.'; }
if ($passwordRaw === '') { $errors[] = 'Password required.'; }
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('users/create'); }
$hash = password_hash($passwordRaw, PASSWORD_DEFAULT);
$id = insert("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (:u, :e, :h, :fn, :r, :a)", [
    'u' => $username, 'e' => $email, 'h' => $hash, 'fn' => $fullName, 'r' => $role, 'a' => $isActive,
]);
logAuditAction('create', 'users', (int)$id, null, ['username' => $username, 'role' => $role]);
setFlashMessage('success', 'User created.');
redirect('users');
