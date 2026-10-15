# Test plan

Run these checks against a local XAMPP database after importing the schema and seed files:

1. Login with the existing admin account and confirm `/api/auth/session` returns the session user.
2. Call the dashboard summary with no session and confirm HTTP 401.
3. Create a masterlist record twice and confirm the second request is rejected.
4. Submit a validation with replicate 0 or 5 and confirm the transaction rolls back.
5. Confirm a viewer cannot read audit logs or verify a validation.
6. Create a harvest record and confirm its audit row.
7. Confirm dashboard counts change from database records rather than frontend constants.
