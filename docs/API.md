# PHILSURIN API

All API responses use `{ "success": true, "data": ... }` or `{ "success": false, "error": { "code": "...", "message": "..." } }`. Session cookies authenticate requests. All protected routes check the server session and active user.

Implemented routes:

| Method | Route | Access |
|---|---|---|
| POST | `/api/auth/login` | Public |
| POST | `/api/auth/logout` | Authenticated |
| GET | `/api/auth/session` | Authenticated |
| GET | `/api/dashboard/summary` | Authenticated |
| GET | `/api/dashboard/activity` | Authenticated |
| GET | `/api/dashboard/analytics` | Authenticated |
| GET | `/api/masterlist` | Authenticated |
| GET | `/api/varieties` | Authenticated |
| GET | `/api/audit-logs` | ADMIN |

The requested CRUD and C4.5 routes are represented by the schema and are the next service layer to expose. Do not expose database exceptions, credentials, hashes, or stack traces through the API.
