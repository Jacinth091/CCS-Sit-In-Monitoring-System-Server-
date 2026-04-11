# sitIn — Codebase Documentation

## 1. Executive Summary
   - **What this system does:** The **sitIn** system is a laboratory management backend designed to track student computer laboratory usage ("sit-ins"). It handles student registration, authentication, session logging (time-in/time-out), and administrative oversight including dashboard statistics and announcements.
   - **Development Philosophy:** Built as a **"Pure PHP"** project. The system deliberately avoids heavy frameworks or external libraries (with the exception of `firebase/php-jwt` for security) to prioritize fundamental PHP mastery and minimize overhead.
   - **Current state assessment:** **Healthy / Needs Attention**. The system is functional and utilizes modern tools (PostgreSQL, JWT, Composer), but suffers from architectural inconsistencies and specific security risks that should be addressed before a full production launch.
   - **Top 3 strengths:**
     1. **Modern Stack:** Use of PostgreSQL with UUIDs and JWT for stateless authentication.
     2. **Clear Module Separation:** API endpoints are logically grouped by feature (auth, sitin, student, admin).
     3. **Robust Auth logic:** Standardized JWT validation and role-based access control (RBAC) via middleware-like functions.
   - **Top 3 risks or concerns:**
     1. **Hardcoded Credentials:** Admin credentials are hardcoded in the login endpoint.
     2. **Architectural Inconsistency:** Business logic and database queries are split between Model classes and procedural API scripts.
     3. **Input Validation:** Reliance on manual sanitization (`htmlspecialchars`) rather than a centralized validation layer.

## 2. System Architecture Overview
   - **Architectural pattern identified:** **Custom Procedural-OO Hybrid (Filesystem-based Routing)**.
   - **High-level component diagram:**
     ```
     [Client (Web/Mobile)] 
            |
     [API Endpoint (api/*.php)] <--- [Auth/Middleware (includes/)]
            |
     [Model Class (src/models/)] OR [Direct PDO Query]
            |
     [PostgreSQL Database]
     ```
   - **Request lifecycle walkthrough:**
     1. Request hits a specific file in `api/` (e.g., `api/auth/login.php`).
     2. Script includes `initialize.php`, which boots the app, loads `.env`, and sets up the `$db` PDO instance.
     3. Authentication middleware (`requireAuth()` or `requireAdmin()`) validates the JWT if necessary.
     4. Logic is performed either via a model in `src/models/` or directly using the `$db` instance.
     5. Standardized JSON response is sent via `sendSuccess()` or `sendError()`.
   - **Data flow summary:** Stateless API; state is managed via JWT in the Authorization header and persisted in PostgreSQL.

## 3. Project Structure
   - **`api/`**: Public entry points (Controllers). Grouped by domain (auth, lab, sitin, etc.).
   - **`includes/`**: Core infrastructure: `config.php` (DB), `initialize.php` (Bootstrap), `validate_token.php` (Auth).
   - **`src/database/`**: Database schema management via migrations and seeders.
   - **`src/models/`**: Data Access Objects (DAOs) for core entities (`Student`, `SitIn`).
   - **`uploads/`**: Local storage for student profile pictures.

## 4. Core Capabilities
   - **Student Management:** Registration, profile updates, and profile picture uploads.
   - **Sit-In Tracking:** Atomic "Time-In" and "Time-Out" operations with session credit tracking.
   - **Session Limits:** Students are granted **30 sessions per semester** (approx. 4-5 months). Administrators can trigger a manual reset for all students at the start of a new term.
   - **Laboratory Management:** CRUD for laboratory rooms.
   - **Announcements:** Admin-created broadcasts for students.
   - **Dashboard Analytics:** Real-time stats on registered students, active sessions, and purpose distribution.

## 5. Dependencies & Integrations
   - **PHP version:** Target PHP 7.4+ (uses typed properties and PostgreSQL UUIDs).
   - **Composer Packages:**
     - `firebase/php-jwt`: For encoding and decoding authentication tokens.
   - **External Services:** None identified; the system is self-contained.

## 6. Code Quality Assessment
   - **Overall code quality rating:** **6/10**
   - **Patterns used:** DAO (Data Access Object), Dependency Injection (Database in Models), Singleton-like PDO instance.
   - **Anti-patterns found:** 
     - **Hardcoded Admin:** Admin login logic bypasses the database.
     - **Global State:** Reliance on the global `$db` variable in procedural scripts.
     - **Dry violations:** Repeated `http_response_code` and `json_encode` logic in some endpoints.
   - **Security observations:** 
     - **Good:** Password hashing via `password_hash`, JWT expiration, and ILIKE for safe searching.
     - **Weak:** Lack of rate limiting on login and hardcoded admin credentials.
   - **Performance observations:** PostgreSQL UUIDs and indexing on `student_id` ensure scalability.

## 7. Component Analysis — Keep / Change / Remove

| Label | Component | Location | Reason | Risk | Recommendation |
|---|---|---|---|---|---|
| ✅ **KEEP** | JWT Auth | `includes/validate_token.php` | Clean implementation of token validation. | Low | No changes needed. |
| 🔄 **REFACTOR** | Admin Auth | `api/auth/login.php` | Admin credentials are hardcoded. | **High (Security)** | Move admin users to a dedicated `admins` table or the `students` table with a role flag. |
| 🔄 **REFACTOR** | Statistics | `api/admin/dashboard_stats.php` | Direct SQL queries instead of model methods. | Moderate (Maintainability) | Encapsulate stats logic in a `Dashboard` or `Stats` model. |
| ⚠️ **REFACTOR** | Model Usage | `api/announcements/read.php` | Lacks an `Announcement` model; queries DB directly. | Low | Create an `Announcement` model to centralize queries. |
| 🔍 **INVESTIGATE** | Session Logic | `api/student/reset_sessions.php` | Hardcoded value of '30'. | Low | Clarify if session limits should be configurable via DB/Admin panel. |

## 8. Recommended Action Plan
   - **Priority 1 — Critical:** 
     - Remove hardcoded admin credentials and implement a secure `admins` table or role-based user management.
     - Ensure all endpoints use the standardized `sendError`/`sendSuccess` functions to maintain consistent JSON structure.
   - **Priority 2 — Important:**
     - Centralize all database queries into Model classes (`Announcement`, `Laboratory`) to remove direct SQL from API endpoints.
     - Implement a central Validation utility to handle input filtering.
   - **Priority 3 — Nice to Have:**
     - Implement soft-delete logic consistently across all models.
     - Add a logging layer for administrative actions (e.g., who reset the sessions?).

## 9. Open Questions
   - **Database Scalability:** Are there plans to move to a cloud-managed database (e.g., AWS RDS) or remain on a local XAMPP/PostgreSQL setup?
   - **Client Implementation:** Is the frontend being built as a SPA (React/Vue) or a mobile application?

## 10. Glossary
   - **Sit-in:** A session where a student uses a computer laboratory for individual study or practice.
   - **Session Credit:** The remaining number of times a student is permitted to sit-in before requiring a reset.
