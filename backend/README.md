# Backend service boundary

The existing PHP pages remain the compatibility UI. The HTTP service is currently implemented in `api/` so it runs under XAMPP without replacing the existing application. `api/bootstrap.php` owns JSON responses, session authentication, role checks, location authorization, prepared queries, and audit logging. New transactional workflows should be added there or extracted into this folder as controllers/services rather than embedded in templates.

All mutating validation workflows use transactions. The API never accepts user identity or role as an authorization source; it derives them from the PHP session and database.
