<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('suppliers'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlashMessage('error', 'Invalid CSRF token.'); redirect('suppliers'); }
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
elseif (fetchOne("SELECT id FROM suppliers WHERE name = :name", ['name' => $name])) { $errors[] = 'Name must be unique.'; }
if (!empty($errors)) { $_SESSION['form_errors'] = $errors; $_SESSION['form_data'] = $_POST; redirect('suppliers/create'); }
$supId = insert("INSERT INTO suppliers (name, contact_person, email, phone, address, city, country, is_active) VALUES (:name, :contact, :email, :phone, :address, :city, :country, :active)", [
    'name' => $name, 'contact' => $contact, 'email' => $email, 'phone' => $phone,
    'address' => $address, 'city' => $city, 'country' => $country, 'active' => $isActive,
]);
logAuditAction('create', 'suppliers', (int)$supId, null, ['name' => $name]);
setFlashMessage('success', 'Supplier created.');
redirect('suppliers');
