# Bruno API Testing Guide

### Environment:
- `base_url`: `http://localhost/sitIn`
- `token`: (Get from Login)

### 1. Generate Reports
- **Method**: GET
- **URL**: `{{base_url}}/api/admin/reports/generate.php?type=json&from=2024-01-01`
- **Header**: `Authorization: Bearer {{token}}`

### 2. Admin Analytics
- **Method**: GET
- **URL**: `{{base_url}}/api/admin/analytics.php`
- **Header**: `Authorization: Bearer {{token}}`

### 3. Create Reservation
- **Method**: POST
- **URL**: `{{base_url}}/api/reservation/create.php`
- **Header**: `Authorization: Bearer {{token}}`
- **Body** (JSON):
  ```json
  {
    "lab_id": 1,
    "pc_number": 10,
    "reserved_date": "2026-06-15",
    "time_slot": "13:00:00",
    "purpose": "Study Session"
  }
  ```

### 4. Bulk Import Software
- **Method**: POST
- **URL**: `{{base_url}}/api/software/import.php`
- **Header**: `Authorization: Bearer {{token}}`
- **Body** (JSON):
  ```json
  {
    "lab_id": 1,
    "items": [
      { "name": "Google Chrome", "version": "latest" },
      { "name": "Node.js", "version": "20.11.0" }
    ]
  }
  ```

### 5. Submit Testimonial
- **Method**: POST
- **URL**: `{{base_url}}/api/testimonial/create.php`
- **Header**: `Authorization: Bearer {{token}}`
- **Body** (JSON):
  ```json
  {
    "message": "Excellent facilities!",
    "rating": 5
  }
  ```
