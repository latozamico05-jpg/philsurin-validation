# XAMPP setup

1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\philsurin`.
3. Open `http://localhost/phpmyadmin`.
4. Select **Import**, choose `database.sql`, and run it.
5. Open `http://localhost/philsurin/`.

Default login: `admin` / `admin123` (or `viewer` / `viewer123`). Change these passwords before use.

The application uses PHP PDO and MySQL. Database settings are in `config.php`.
