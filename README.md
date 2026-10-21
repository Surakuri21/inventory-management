# PHP & MySQL Inventory Management System

A production-ready, modular Inventory Management System built with PHP 8.x, MySQL (InnoDB), PDO, and Tailwind CSS.

## Features

- **User Authentication** — Secure login with `password_hash()`/`password_verify()`, session management, role-based access (Admin / Standard)
- **Inventory CRUD** — Full Create/Read/Update/Delete for Products, Categories, Suppliers
- **Stock Transactions** — Sales, Restocks, Adjustments, Returns with automatic stock level updates
- **Low-Stock Alerts** — Visual threshold warnings on dashboard and product listings
- **Audit Trail** — Automatic logging of all critical actions (JSON-based old/new values)
- **Responsive UI** — Tailwind CSS via CDN, works on desktop and mobile
- **Search & Filter** — Real-time search and filter on inventory and transaction views
- **Prepared Statements** — 100% PDO prepared statements, zero SQL injection risk
- **Modular Architecture** — Clean separation: `/config`, `/includes`, `/actions`, `/views`, `/assets`

---

## Requirements

| Component | Version |
|-----------|---------|
| PHP       | 8.0+ (Strict typing enabled) |
| MySQL     | 5.7+ / MariaDB 10.3+ (InnoDB) |
| Web Server| Apache 2.4+ / Nginx 1.18+ |
| Extensions| `pdo_mysql`, `json`, `session`, `mbstring` |

---

## Installation

### 1. Clone / Copy the Project

Place the project folder in your web server's document root (e.g., `/var/www/html/inventory` or `C:\xampp\htdocs\inventory`).

### 2. Create the Database

```bash
# Option A: MySQL CLI
mysql -u root -p < database.sql

# Option B: phpMyAdmin / Adminer
# Import the `database.sql` file through the UI
```

This creates:
- Database: `inventory_db`
- Tables: `users`, `categories`, `suppliers`, `products`, `transactions`, `audit_logs`
- Default admin user (see credentials below)

### 3. Configure Database Connection

Copy the example config and edit with your credentials:

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php`:

```php
<?php
declare(strict_types=1);

return [
    'host'     => '127.0.0.1',
    'dbname'   => 'inventory_db',
    'username' => 'your_db_user',
    'password' => 'your_db_password',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
```

### 4. Set Permissions (Linux/macOS)

```bash
chmod -R 755 .
chmod -R 777 assets/uploads 2>/dev/null || true
```

### 5. Configure Web Server

**Apache** — Ensure `mod_rewrite` is enabled and `AllowOverride All` is set for the project directory. The included `.htaccess` handles routing.

**Nginx** — Example server block:

```nginx
server {
    listen 80;
    server_name inventory.local;
    root /var/www/html/inventory;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

### 6. Access the Application

Open your browser to `http://your-domain-or-ip/inventory` (or configured virtual host).

---

## Default Login Credentials

| Role    | Username | Password  |
|---------|----------|-----------|
| Admin   | `admin`  | `admin123` |

> **⚠️ SECURITY:** Change the admin password immediately after first login via the Users management page.

---

## Project Structure

```
inventory/
├── config/
│   ├── database.php          # DB credentials (gitignored)
│   └── database.example.php  # Template for database.php
├── includes/
│   ├── db.php                # PDO connection singleton
│   ├── auth.php              # Authentication & session helpers
│   ├── functions.php         # Shared utility functions
│   └── audit.php             # Audit logging helper
├── actions/
│   ├── auth/
│   │   ├── login.php
│   │   └── logout.php
│   ├── products/
│   │   ├── create.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   └── transaction.php   # Stock in/out
│   ├── categories/
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── suppliers/
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   └── users/
│       ├── create.php
│       ├── update.php
│       └── delete.php
├── views/
│   ├── layouts/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── sidebar.php
│   ├── auth/
│   │   └── login.php
│   ├── dashboard/
│   │   └── index.php
│   ├── products/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── view.php
│   ├── categories/
│   │   ├── index.php
│   │   ├── create.php
│   │   └── edit.php
│   ├── suppliers/
│   │   ├── index.php
│   │   ├── create.php
│   │   └── edit.php
│   ├── transactions/
│   │   └── index.php
│   ├── users/
│   │   ├── index.php
│   │   ├── create.php
│   │   └── edit.php
│   └── audit/
│       └── index.php
├── assets/
│   ├── css/
│   │   └── style.css         # Custom styles (minimal, Tailwind via CDN)
│   └── js/
│       └── main.js           # Search, filters, confirmations
├── database.sql              # Full schema + seed data
├── index.php                 # Entry point / router
├── .htaccess                 # Apache rewrite rules
└── README.md                 # This file
```

---

## Role Permissions

| Action                    | Admin | Standard |
|---------------------------|-------|----------|
| View Dashboard            | ✅    | ✅       |
| View Products             | ✅    | ✅       |
| Create/Edit/Delete Products | ✅  | ❌       |
| Process Sales (Stock Out) | ✅    | ✅       |
| Process Restock (Stock In)| ✅    | ✅       |
| Manage Categories         | ✅    | ❌       |
| Manage Suppliers          | ✅    | ❌       |
| Manage Users              | ✅    | ❌       |
| View Audit Logs           | ✅    | ❌       |

---

## Development

### Adding a New Module

1. Create action files in `actions/<module>/`
2. Create view files in `views/<module>/`
3. Add route handling in `index.php`
4. Update sidebar navigation in `views/layouts/sidebar.php`

### Coding Standards

- **Strict typing**: `declare(strict_types=1);` at top of every PHP file
- **PDO only**: Never use `mysqli` or raw SQL concatenation
- **Validation**: Use `filter_var()` and `htmlspecialchars()` on all input
- **Comments**: Document complex logic (stock calculations, role checks)

---

## Security Checklist

- [ ] Change default admin password
- [ ] Use HTTPS in production
- [ ] Set `session.cookie_secure=1` and `session.cookie_httponly=1` in `php.ini`
- [ ] Restrict database user to only `inventory_db` with minimal privileges
- [ ] Keep PHP and MySQL updated
- [ ] Review audit logs regularly

---

## Troubleshooting

**"Connection failed" error**
- Verify `config/database.php` credentials
- Ensure MySQL is running and `inventory_db` exists
- Check firewall/network if using remote DB

**Blank page / 500 error**
- Check PHP error log (`/var/log/apache2/error.log` or similar)
- Ensure all required PHP extensions are installed
- Verify file permissions

**Session issues**
- Ensure `session.save_path` is writable
- Check `session.gc_maxlifetime` in `php.ini`

---

## License

MIT License — Free for personal and commercial use.

---

## Support

For issues, check the audit logs first, then review PHP/MySQL error logs. The system is designed to be self-contained with no external dependencies beyond the PHP/MySQL stack.