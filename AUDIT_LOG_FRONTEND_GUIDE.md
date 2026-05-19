# 📜 Admin Audit Log — Frontend Integration Guide

The audit log system has been upgraded from a reservation-specific log to a **Unified System Audit Log**. This document explains how to use the new API to power your activity feeds.

---

## 1. Primary Endpoint
**URL:** `GET api/admin/reservations/audit_log.php`  
**Purpose:** Fetches a chronological list of administrative actions.

### Query Parameters (Optional Filters)
| Parameter | Type | Description |
|---|---|---|
| `page` | `int` | Page number (default: 1) |
| `per_page` | `int` | Records per page (default: 20) |
| `entity_type` | `string` / `array` | Filter by category: `pc`, `reservation`, `announcement`, `student`, `software`, `system` |
| `date_from` | `string` | Start date (YYYY-MM-DD) |
| `date_to` | `string` | End date (YYYY-MM-DD) |

---

## 2. JSON Response Structure

Each object in the `data` array now contains the following fields:

| Field | Description | Example |
|---|---|---|
| `action` | **Concise label** for the UI list view. | "Functional State updated to Active" |
| `description` | **Full context string** for details or tooltips. | "CCS Lab 1's (PC #1) Functional State was updated to Active" |
| `event_type` | Broad category badge text. | "PC status changed" |
| `entity_type` | The internal object type. | "pc" |
| `entity_id` | The ID of the affected object. | "41" |
| `lab_name` | Name of the lab (if applicable). | "CCS Lab 1" |
| `pc_number` | PC number (if applicable). | 1 |
| `student_name`| Name of student (for reservations/profiles).| "Juan Dela Cruz" |
| `admin_first_name` | Name of the admin who did it. | "System" |
| `created_at` | Timestamp of the event. | "2026-05-17 00:09:55" |

---

## 3. How to use in the "Reservation Management" Panel

In the right-hand panel of your Reservation module, you should continue using the default endpoint call. The backend **automatically filters** for `reservation` and `pc` types by default so you don't see announcement/student logs in this specific view.

**Recommended Display Pattern:**
- **Primary Text:** Use the `action` field.
- **Sub-text:** Use `lab_name` and `pc_number` if they exist.
- **Badge:** Use `event_type`.
- **Timestamp:** Display `created_at` using a relative format (e.g., "5 mins ago").

---

## 4. How to use in a "System Activity" Dashboard (New)

If you create a global dashboard, you can fetch **all** logs by passing an empty `entity_type` or including all types.

**Available `entity_type` values for your filters:**
1. `pc` — Hardware status changes.
2. `reservation` — Approval, Rejection, Rescheduling.
3. `announcement` — Created, Updated, Deleted.
4. `student` — Profile updates, Deactivations.
5. `software` — Created, Deleted, Lab Assignments, Bulk Imports.
6. `system` — Bulk actions like Session Resets.

---

## 5. Summary of Key Values

| Action String (Recommended for List View) | Description String (Recommended for Tooltip/Details) |
|---|---|
| "Functional State updated to [Status]" | "{Lab} (PC #{Num}) Functional State updated to [Status]" |
| "Reservation updated to [Status]" | "Reservation for {Lab} (PC #{Num}) by {Student} updated to [Status]" |
| "Created announcement \"{Title}\"" | "Admin created announcement: \"{Title}\"" |
| "Reset sessions for {Count} students" | "Admin performed bulk session reset to 30 for {Count} active students." |
| "Deactivated student {SID}" | "Admin deactivated student account: {Name} ({SID})" |
