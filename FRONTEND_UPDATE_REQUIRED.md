# 🚨 Frontend Update Required — Reservation & PC Status Logic

The backend reservation logic and terminology have been refined. This document covers the **Functional States**, **Booking States**, and how they interact.

---

## 1. Updated Terminology (Non-Negotiable)

### PC Status (The "Functional State")
This describes the hardware/physical condition of the PC. Use these values in your **Left Panel** dropdowns and badges.

| Value | Label | Meaning |
|---|---|---|
| `active` | **Active** | PC is healthy and ready for use. |
| `disabled` | **Disabled** | Hardware is broken or decommissioned. |
| `under maintenance` | **Under Maintenance** | Temporarily out of service for updates/repairs. |

### Reservation Status (The "Booking State")
This describes whether the PC is available for a student to reserve.

| Value | Label | Derived Logic |
|---|---|---|
| `open` | **Open** | Manually set to open AND PC is `active`. |
| `reserved` | **Reserved** | Manually set to reserved by Admin. |
| `occupied` | **Occupied** | **Dynamic:** PC currently has an ongoing sit-in session or approved reservation. |
| `unavailable` | **Unavailable** | **Dynamic:** Automatically set if PC status is `disabled` or `under maintenance`. |

---

## 2. Critical UI Rules

1.  **State Blocking:** If a PC's status is `disabled` or `under maintenance`, the backend **will reject** attempts to manually change its Reservation Status. You should **disable/grey out** the Reservation Status dropdown in your PC Card if the Functional State is not `active`.
2.  **Terminology Alignment:** Ensure your Badge components and Dropdowns use the exact string values above (`active`, `disabled`, `under maintenance`, `open`, `reserved`).
3.  **Audit Log Display:** Always use the `action` field for the primary text in your activity list, as it contains the concise description (e.g., "Functional State updated to Disabled").

---

## 3. Updated API Examples

### Fetching PCs (`api/admin/pcs/read_by_lab.php`)
The JSON now returns the derived `reservation_status` automatically.
```json
{
    "pc_number": 1,
    "pc_status": "disabled",
    "reservation_status": "unavailable"  // Derived because it's disabled
}
```

### Updating Functional State (`api/admin/pcs/update_status.php`)
Payload: `{ "pc_id": 41, "status": "under maintenance" }`

### Updating Booking State (`api/admin/pcs/update_reservation_status.php`)
Payload: `{ "pc_id": 41, "reservation_status": "reserved" }`
*(Note: This only works if pc_status is "active")*

---

## 4. Student-Facing Impact
The student endpoint (`api/reservation/occupied_pcs.php`) now automatically includes all `disabled`, `under maintenance`, and `reserved` PCs in the "occupied" list. This means students will never see or be able to click on broken or held PCs.
