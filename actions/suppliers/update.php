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
$existing = fetchOne("SELECT * FROM suppliers WHERE id = :id", ['id' => $id]);
if (!$existing) { setFlashMessage('error', 'Not found.'); redirect('suppliers'); }
$nameRaw = trim($_POST['name'] ?? '');
$name = ($nameRaw !== '' && mb_strlen($nameRaw) <= 150) ? $nameRaw : '';
$contact = sanitizeString($_POST['contact_person'] ?? '', 100) ?: null;
$email = sanitizeString($_POST['email'] ?? '', 100) ?: null;
$phone = sanitizeString($_POST['phone'] ?? '', 30) ?: null;
$address = sanitizeString($_POST['address'] ?? '', 500) ?: null;
$city = sanitizeString($_POST['city'] ?? '', 100) ?: null;
$country = sanitizeString($_POST['country'] ?? '', 100) ?: null;
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];
if ($name === '') { $errors[] = 'Name required.'; }
elseif ($name !== $existing['name']) {
    $dup = fetchOne("SELECT id FROM suppliers WHERE name = :name AND id != :id", ['name' => $name, 'id' => $id]);
    if ($dup) { $errors[] = 'Name must be unique.'; }
}
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('suppliers/edit?id='.$id); }
execute("UPDATE suppliers SET name = :name, contact_person = :contact, email = :email, phone = :phone, address = :address, city = :city, country = :country, is_active = :active WHERE id = :id", [
    'name' => $name, 'contact' => $contact, 'email' => $email, 'phone' => $phone,
    'address' => $address, 'city' => $city, 'country' => $country, 'active' => $isActive, 'id' => $id,
]);
logAuditAction('update', 'suppliers', $id, ['name' => $existing['name']], ['name' => $name, 'is_active' => $isActive]);
setFlashMessage('success', 'Supplier updated.');
redirect('suppliers');
