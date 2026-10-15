# XAMPP / VS Code deployment

1. Copy the project into `C:\xampp\htdocs\philsurin`.
2. Start Apache and MySQL in XAMPP.
3. Create or select the `philsurin` database.
4. Import `database/schema.sql`, then `database/seed.sql` in phpMyAdmin or the MySQL client.
5. Back up the existing database before running `database/migrations/001_core_architecture.sql`.
6. Set the PDO credentials in `config.php` for the local MySQL account.
7. Open `http://localhost/philsurin/index.php`.

The existing login remains available. Existing legacy SHA-256 password records continue to work for compatibility; new account-management code must generate hashes with PHP `password_hash()` and verify with `password_verify()`. Replace the default credentials after local setup.

The API is available through the Apache rewrite in `api/.htaccess`, for example `GET http://localhost/philsurin/api/dashboard/summary`. If rewrite is disabled, use `api/index.php` directly and enable `mod_rewrite`.
