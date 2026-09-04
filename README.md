# Deshapriya Motors Garage Management System

Lightweight Core PHP foundation for Deshapriya Motors. Iteration 1 includes MySQL/MariaDB connectivity, session authentication, Administrator and Cashier roles, guarded dashboards, logout, password changes, reusable includes, Bootstrap 5, and responsive navigation.

## Requirements

- PHP 8.0+ with PDO, PDO MySQL, Sessions, OpenSSL, and password hashing support.
- MySQL 8+ or MariaDB 10.4+.
- Apache or Nginx with PHP enabled.
- Bootstrap 5 is loaded from the jsDelivr CDN.

The application does not use Laravel, Composer, SQLite, React, or a Node backend. It is designed to run from a normal XAMPP `htdocs` directory or on shared hosting/cPanel.

## Local Setup

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin or the MySQL client and import `database/schema.sql`.
3. Review `.env` and set the MySQL credentials for `deshappriya_motors`.
4. Open `http://localhost/deshappriya-motors/`.

Expected local configuration:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=deshappriya_motors
DB_USERNAME=root
DB_PASSWORD=
```

The SQL creates the `users` table and seed accounts:

- Administrator: `admin@deshappriyamotors.test` / `ChangeMe!Admin123`
- Cashier: `cashier@deshappriyamotors.test` / `ChangeMe!Cashier123`

Change these passwords after first login.

## Structure

```text
config/database.php       PDO connection
database/schema.sql        MySQL schema and seed accounts
includes/auth.php          Sessions, login, logout, role guards
includes/functions.php     Escaping, CSRF, redirects, flash messages
includes/header.php        Shared Bootstrap shell and header
includes/sidebar.php       Shared responsive sidebar
includes/footer.php        Shared closing layout
views/                     Page templates
assets/css/style.css       Custom navy/red/blue UI styling
assets/js/app.js           Minimal sidebar interaction
index.php                  Application entrypoint and request routing
```

## Deployment

Upload the project files to the hosting document directory, create/import `deshappriya_motors`, configure `.env`, and ensure Apache/Nginx serves the project directory. No build step or Composer installation is required. Keep `.env` out of version control and use a non-root database user in production.

Iteration 2 business modules are intentionally not included.
