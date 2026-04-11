# CCS Sit-In Monitoring — Backend Documentation

This document describes the current PHP backend (API) located in this folder.

## Overview

- Language: PHP (uses PDO)
- Database: PostgreSQL (PDO pgsql driver used in `includes/config.php`)
- API folder: `api/` with subfolders: `auth/`, `sitin/`, `student/`, `lab/`, `announcements/`, `admin/`
- File uploads: `uploads/profiles/`

## Prerequisites

- PHP 8+ with PDO and PDO_PGSQL extension enabled.
- A PostgreSQL server and credentials.
- Web server or PHP built-in server (XAMPP/Apache commonly used on Windows). If using XAMPP, ensure Apache is running and PHP has pgsql enabled.

## .env configuration

The backend loads environment variables from the project `.env` file (see `includes/env.php`). Example variables expected by `includes/config.php`:

```
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=sitin_db
DB_USERNAME=sitin_user
DB_PASSWORD=secret
APP_NAME=CCS_SitIn

# Optional / environment-specific
VITE_API_URL=...
```

Put the `.env` file at the project root: `c:\xampp\htdocs\sitIn\.env`.

## Install / Run

1. Ensure PostgreSQL is running and create the database and user matching `.env`.

2. Start the webserver (XAMPP / Apache). The API endpoints are expected at `http://localhost/sitIn/api/...` when using default XAMPP setup.

3. Migrations & seeders are included under `src/database/migrations/` and `src/database/seeders/`.

### Running migrations

From the backend project root (`c:\xampp\htdocs\sitIn`) you can run the migration tool using PHP CLI. Examples:

```powershell
cd "c:\xampp\htdocs\sitIn\src\database\migrations"
php db.php migrate            # run all pending migrations
php db.php migrate:fresh      # drop all tables and re-run migrations
php db.php make create_xxx    # create new migration template
php db.php seed               # run seeders
php db.php seed:fresh         # clear and run seeders
```

The migration runner uses `src/database/migrations/versions/` files and a `migrations` tracking table to avoid re-running migrations.

## CORS

`includes/cors.php` sets permissive CORS headers (Access-Control-Allow-Origin: *). That allows requests from the frontend during development. For production, lock this down to allowed origins.

## Important files

- `includes/env.php` — .env loader
- `includes/config.php` — database connection (PDO) and environment-derived constants
- `includes/initialize.php` — path constants and common includes
- `api/` — all endpoint scripts
- `src/database/migrations/` — migration runner and versions

## API endpoints (current snapshot)

Below are the main API endpoints and method/parameters inferred from the current PHP scripts and frontend services. For exact fields check the corresponding `api/*/*.php` file.

Auth
- POST `api/auth/login.php`
  - Payload (JSON): { student_id, password }
  - Responses:
    - 200 (admin): { message, role: 'admin', student_id: 'admin', first_name }
    - 200 (student): { message, role: 'student', id, student_id, first_name, last_name, email, ... }
    - 401 / 404 / 403 for invalid credentials / missing student / deactivated account

- POST `api/auth/register.php`
  - Payload (JSON): { student_id, first_name, last_name, email, password, middle_name?, course?, course_level? }
  - Response: 201 on success; 400 if incomplete

Students
- GET `api/student/read.php` — list students
- GET `api/student/read_single.php?id=<id>` — single student
- PUT `api/student/update.php` — update student (expects JSON body)
- DELETE `api/student/delete.php` — delete student (expects JSON body with id)
- POST `api/student/reset_sessions.php` — reset session counts
- POST `api/student/upload_profile.php` — multipart/form-data; saves to `uploads/profiles/`

Sit-in / Sessions
- POST `api/sitin/create.php` — create sit-in session (payload: student_id, lab_id, purpose)
- GET `api/sitin/read.php` — all records
- GET `api/sitin/read_active.php` — active sessions
- GET `api/sitin/read_by_student.php?student_id=<id>` — student history
- GET `api/sitin/read_single.php?id=<id>` — single sit-in
- POST `api/sitin/end_session.php` — end session (payload: { log_id })
- `api/sitin/time_in.php`, `api/sitin/time_out.php` — time in/out endpoints may exist for timestamped actions (check files for expected bodies)

Labs
- GET `api/lab/read.php` — list laboratories

Announcements
- GET `api/announcement/read.php` — list announcements
- POST `api/announcement/create.php` — create announcement

Admin
- GET `api/admin/dashboard_stats.php` — returns numeric stats for the dashboard

## Security notes

- The sample admin account is defined in `api/auth/login.php` (`ADMIN_USERNAME`, `ADMIN_PASSWORD`) for convenience; replace with proper admin user and secure storage for production.
- Currently CORS is wide open. Restrict origins in production.
- Ensure `uploads/profiles` is not executable and validates uploaded file types.

## Debug & troubleshooting

- Database connection errors will return a 500 with a JSON message from `includes/config.php`.
- Check PHP error logs for stack traces when migrations fail.

## Next steps & improvements

- Add authentication tokens (JWT) and refresh tokens, persist securely (httpOnly cookies).
- Harden CORS and input validation. Use prepared statements and additional validation layers.
- Add integration tests for major endpoints.

---
Documentation generated from the current backend snapshot of the repository.
