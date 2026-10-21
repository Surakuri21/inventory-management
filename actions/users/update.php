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
$existing = fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $id]);
if (!$existing) { setFlashMessage('error', 'Not found.'); redirect('users'); }
$usernameRaw = trim($_POST['username'] ?? '');
$username = ($usernameRaw !== '' && mb_strlen($usernameRaw) <= 50) ? $usernameRaw : '';
$emailRaw = trim($_POST['email'] ?? '');
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
$fullName = sanitizeString($_POST['full_name'] ?? '', 100) ?: null;
$role = ($_POST['role'] ?? 'standard') === 'admin' ? 'admin' : 'standard';
$isActive = isset($_POST['is_active']) ? 1 : 0;
$passwordRaw = $_POST['password'] ?? '';
$errors = [];
if ($username === '') { $errors[] = 'Username required.'; }
elseif ($username !== $existing['username']) {
    $dup = fetchOne("SELECT id FROM users WHERE username = :u AND id != :id", ['u' => $username, 'id' => $id]);
    if ($dup) { $errors[] = 'Username taken.'; }
}
if ($email === '') { $errors[] = 'Email required.'; }
elseif ($email !== $existing['email']) {
    $dup = fetchOne("SELECT id FROM users WHERE email = :e AND id != :id", ['e' => $email, 'id' => $id]);
    if ($dup) { $errors[] = 'Email taken.'; }
}
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('users/edit?id='.$id); }
$params = [
    'username' => $username, 'email' => $email, 'full_name' => $fullName,
    'role' => $role, 'is_active' => $isActive, 'id' => $id,
];
$sql = "UPDATE users SET username = :username, email = :email, full_name = :full_name, role = :role, is_active = :is_active";
if ($passwordRaw !== '') {
    $sql .= ", password_hash = :hash";
    $params['hash'] = password_hash($passwordRaw, PASSWORD_DEFAULT);
}
$sql .= " WHERE id = :id";
execute($sql, $params);
logAuditAction('update', 'users', $id, ['username' => $existing['username'], 'role' => $existing['role']], ['username' => $username, 'role' => $role]);
setFlashMessage('success', 'User updated.');
redirect('users');
