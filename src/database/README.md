# Database migrations (PostgreSQL)

This project uses SQL migration files under [src/database/migrations](migrations) and a lightweight PHP runner at [src/database/migrate.php](migrate.php).

## 1) Configure `.env`

Required keys:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USERNAME`
- `DB_PASSWORD`

## 2) Run migrations

From the project root (`sitIn`):

```bash
php src/database/migrate.php
```

What it does:

- Creates `schema_migrations` table if needed.
- Applies each `.sql` file once, in filename order.
- Records applied filenames in `schema_migrations`.

## 3) Add a new migration

Create a new SQL file with the next prefix, for example:

- `006_add_student_phone.sql`

Then run:

```bash
php src/database/migrate.php
```

## Notes

- Existing API code expects these tables: `students`, `laboratories`, `sit_in_logs`, `announcements`.
- `005_seed_laboratories.sql` inserts default labs safely (`ON CONFLICT DO NOTHING`).
- Keep every schema change in a new migration file (never edit old applied files in shared environments).
