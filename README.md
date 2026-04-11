# CCS Sit-In Monitoring System — Database Setup

Backend database: **PostgreSQL**. All schema and seed scripts run through a lightweight PHP CLI runner — no external tools required.

---

## Prerequisites

| Requirement | Notes |
|---|---|
| PHP 8.x | Must be in your `PATH` (`php -v` to confirm) |
| PostgreSQL | Running locally (default port `5432`) |
| XAMPP / Apache | Only needed to serve the API, not for migrations |

---

## 1. Configure `.env`

Create or edit `sitIn/.env` (project root). Copy the template below and fill in your values:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=sitIn
DB_USERNAME=postgres
DB_PASSWORD=your_password

APP_NAME="CCS Sit-In Monitoring System"
JWT_SECRET=your_random_secret_here
```

> **Generate a JWT secret** (PowerShell):
> ```powershell
> -join ((48..57) + (97..122) | Get-Random -Count 64 | ForEach-Object {[char]$_})
> ```

---

## 2. Create the Database

In `psql` or pgAdmin, run once:

```sql
CREATE DATABASE "sitIn";
```

---

## 3. Run Migrations

From the project root (`sitIn/`):

```bash
php src/database/migrations/migrate.php
```

This will:
- Auto-create a `migrations` tracker table on first run
- Apply each file in `src/database/migrations/versions/` in order
- Skip files that have already been applied
- Stop immediately on the first failure

**Expected output:**
```
=== CCS Sit-In Monitoring — Database Migrations ===

[OK]    001_create_students_table
[OK]    002_create_laboratories_table
[OK]    003_create_sit_in_logs_table
[OK]    004_create_reservations_table
[OK]    005_create_announcments_table
[OK]    006_create_feedback_table
[OK]    007_add_session_to_students
[OK]    008_fix_students_schema_for_api
[OK]    009_create_jwt_blacklist

9 migration(s) ran successfully.
```

---

## 4. Run Seeders

Seeders populate the database with sample/default data.

First, make sure a seeder runner script exists. If `src/database/seeders/seed.php` does not exist yet, create it:

```php
<?php
// src/database/seeders/seed.php

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/../migrations/db.php';
require_once __DIR__ . '/Seeder.php';

load_env(__DIR__ . '/../../../.env');

echo "=== CCS Sit-In Monitoring — Seeders ===\n\n";

$seeder = new Seeder($db);

// Run all seeders:
$seeder->run();

// To run a single seeder by name:
// $seeder->run('002_LaboratorySeeder');

// To re-run ALL seeders from scratch (clears history):
// $seeder->fresh();
```

Then run:

```bash
php src/database/seeders/seed.php
```

**Expected output:**
```
=== CCS Sit-In Monitoring — Seeders ===

[OK]    001_StudentSeeder
[OK]    002_LaboratorySeeder
[OK]    003_AnnouncementSeeder
[OK]    004_SitInLogSeeder
[OK]    005_ReservationSeeder
[OK]    006_FeedbackSeeder

6 seeder(s) ran successfully.
```

### Run a specific seeder only

```bash
# Edit seed.php and change run() to:
$seeder->run('002_LaboratorySeeder');
```

### Re-run all seeders from scratch

```bash
# Edit seed.php and change run() to:
$seeder->fresh();
```

> `fresh()` clears the seeder history table then re-runs every seeder from the beginning.

---

## 5. Adding a New Migration

1. Create a new file in `src/database/migrations/versions/` with the next number prefix:

```
010_add_phone_to_students.php
```

2. Use this class template (filename prefix stripped, snake_case → PascalCase):

```php
<?php
// 010_add_phone_to_students.php

class AddPhoneToStudents {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function up() {
        $this->db->exec("
            ALTER TABLE students
            ADD COLUMN IF NOT EXISTS phone VARCHAR(20)
        ");
    }
}
```

> The class name must match the filename with the numeric prefix removed and converted to PascalCase:
> `010_add_phone_to_students` → `AddPhoneToStudents`

3. Run migrations again — only the new file will be applied:

```bash
php src/database/migrations/migrate.php
```

---

## 6. Adding a New Seeder

1. Create a new file in `src/database/seeders/data/`:

```
007_MyNewSeeder.php
```

2. Use this class template:

```php
<?php
// 007_MyNewSeeder.php

class MyNewSeeder {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function run() {
        $this->db->exec("
            INSERT INTO my_table (column) VALUES ('value')
            ON CONFLICT DO NOTHING
        ");
    }
}
```

3. Run the seeder:

```bash
php src/database/seeders/seed.php
```

---

## 7. Table Overview

| Table | Created by | Purpose |
|---|---|---|
| `admins` | `010` | Administrator accounts |
| `students` | `001` | Student accounts |
| `laboratories` | `002` | Lab rooms available for sit-in |
| `sit_in_logs` | `003` | Sit-in session records |
| `reservations` | `004` | Lab reservation requests |
| `announcements` | `005` | Admin announcements |
| `feedback` | `006` | Student feedback on sessions |
| `migrations` | auto | Migration run tracker |
| `seeders` | auto | Seeder run tracker |
| `jwt_blacklist` | `009` | Revoked JWT tokens |

---

## 8. Full Reset (Fresh Start)

To wipe everything and start clean:

```sql
-- In psql or pgAdmin:
DROP DATABASE "sitIn";
CREATE DATABASE "sitIn";
```

Then re-run migrations and seeders:

```bash
php src/database/migrations/migrate.php
php src/database/seeders/seed.php
```

---

## Troubleshooting

| Error | Cause | Fix |
|---|---|---|
| `could not connect to server` | PostgreSQL not running | Start PostgreSQL service |
| `password authentication failed` | Wrong `.env` credentials | Check `DB_USERNAME` / `DB_PASSWORD` |
| `database "sitIn" does not exist` | DB not created yet | Run `CREATE DATABASE "sitIn"` in psql |
| `[ERROR] Class 'Foo' not found` | Class name doesn't match filename convention | Check PascalCase conversion of the filename |
| `[FAIL] ... already exists` | Re-running a migration that can't be skipped | Use `IF NOT EXISTS` in your SQL |
he filename |
| `[FAIL] ... already exists` | Re-running a migration that can't be skipped | Use `IF NOT EXISTS` in your SQL |
