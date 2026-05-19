# CCS Sit-In Monitoring System — Backend Reference (FINAL)

This document is the definitive reference for the backend modules (A–E) refactored to align with the project implementation guide.

## 🛠 Database Schema

### New / Updated Tables
- **`software`**: `id`, `lab_id` (FK), `name`, `version`, `description`.
- **`testimonials`**: `id`, `student_id` (FK), `message`, `rating` (1-5), `created_at`.
- **`system_settings`**: `key` (PK), `value`, `updated_at`. Contains `reservations_enabled`.
- **`reservations`**: (Updated) Added `pc_number` (INT), `time_slot` (TIME), `admin_note` (TEXT).
- **`sit_in_logs`**: (Updated) Added `pc_number` (VARCHAR).

---

## 🚀 API Catalogue

### Auth Conventions
- All endpoints require `Authorization: Bearer <token>`.
- Use `requireAdmin()` for 🔒 endpoints, `requireStudent()` for 🔑, and `requireAuth()` for 🔓 (any).

### Module A: Reports
- 🔒 **`GET api/admin/reports/generate.php`**
  - Params: `type` (json|csv), `from`, `to`, `lab_id?`, `purpose?`
  - JSON Response: `data` contains logs, `meta` contains `total_records`.
  - CSV Response: Direct file download.

### Module B: Analytics
- 🔒 **`GET api/admin/analytics/trends.php`**
  - Params: `days` (int)
  - *Note: This was previously api/admin/analytics.php.*

### Module C: Reservations
- 🔓 **`POST api/reservation/create.php`**: Book a slot.
- 🔓 **`GET api/reservation/my_reservations.php`**: History.
- 🔓 **`POST api/reservation/cancel.php`**: Cancel pending.
- 🔒 **`GET api/admin/reservations/read_all.php`**: List all (Admin).
- 🔒 **`POST api/admin/reservations/update_status.php`**: Approve/Reject (Admin).
- 🔒 **`GET/POST api/reservation/settings.php`**: Toggle reservations (Admin).

### Module E: Extras
- 🔓 **`GET api/student/sitin/summary.php`**: Student stats.
- 🔓 **`POST api/testimonial/create.php`**: Submit.
- 🔒 **`GET api/admin/testimonials/read_all.php`**: View all (Admin).

---

## 🏗 PHP Models (PascalCase)
Located in `src/models/`:
- `Announcement.php`, `Dashboard.php`, `Report.php`, `Reservation.php`, `SitIn.php`, `Software.php`, `Student.php`, `Testimonial.php`.
