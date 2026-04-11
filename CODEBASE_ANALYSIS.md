# sitIn Codebase Analysis & Development Guide

## 1. Codebase Overview

The **sitIn** project is a pure PHP, API-driven backend for a laboratory management system. It relies on a custom Procedural-Object-Oriented hybrid architecture with filesystem-based routing.

### Core Stack
*   **Language:** PHP (7.4+ targeting)
*   **Database:** PostgreSQL
*   **Authentication:** JWT (via `firebase/php-jwt`)
*   **Dependency Management:** Composer

### Key Architectural Components

1.  **API Layer (`api/`)**: Functions as the application's controllers. Endpoints are organized into subdirectories by domain (e.g., `admin/`, `auth/`, `student/`, `sitin/`). Files here handle request parsing, middleware checks, model invocation, and standard JSON response generation.
2.  **Model Layer (`src/models/`)**: Contains Data Access Objects (DAOs) like `Student`, `SitIn`, `Admin`, `Dashboard`, and `Announcement`. These classes encapsulate database interactions, utilizing PDO for prepared statements to prevent SQL injection.
3.  **Core Infrastructure (`includes/`)**:
    *   `initialize.php`: The bootstrap script. It sets up paths, loads models, configures standardized JSON response handlers (`sendSuccess`, `sendError`), and includes essential utilities.
    *   `config.php` & `env.php`: Database configuration and environment variable loading.
    *   `validate_token.php`: Houses authentication middleware (`requireAuth()`, `requireAdmin()`).
    *   `validator.php`: Centralized input sanitization and validation.
4.  **Database Management (`src/database/`)**:
    *   **Migrations**: Custom PHP-based migration system for schema versioning.
    *   **Seeders**: Custom seeder system to populate initial data, ensuring environments are reproducible.

---

## 2. Recent Structural Refactoring

The codebase recently underwent a critical architectural cleanup to address technical debt and security risks:

*   **Security (Admin Auth):** Hardcoded admin credentials in `api/auth/login.php` were removed. The system now uses a dedicated `admins` database table, populated via `AdminSeeder`, and managed by the `Admin` model. Passwords use standard `password_hash` bcrypt hashing.
*   **Architectural Consistency:** Direct SQL queries previously scattered across procedural API files (like `dashboard_stats.php`, `announcements/read.php`, and `sitin/read.php`) were moved into dedicated Model classes (`Dashboard`, `Announcement`, `SitIn`). This enforces the DAO pattern uniformly.
*   **Input Validation:** The `includes/validator.php` class was introduced. Models were refactored to use `Validator::sanitizeString()` and `Validator::sanitizeEmail()` instead of redundant manual `htmlspecialchars(strip_tags(...))` calls.

---

## 3. Recommended Approach for Adding or Changing Modules

To maintain architectural consistency, security, and code quality, please adhere to the following workflow when implementing new features or updating existing ones.

### Step 1: Database First (Migrations)
If your new module requires database changes:
1.  **Create a Migration**: Use the custom migration tool (or manually create a versioned PHP file in `src/database/migrations/versions/`). Follow the naming convention `0XX_descriptive_name.php`.
2.  **Define Schema**: Write the `up()` method using standard `CREATE TABLE` or `ALTER TABLE` SQL syntax. Always include standard timestamps (`created_at`, `updated_at`, `deleted_at` for soft deletes).
3.  **Run Migration**: Execute `php src/database/migrations/migrate.php`.
4.  *(Optional)* **Create a Seeder**: If the new module requires default or mock data, add a seeder in `src/database/seeders/data/`.

### Step 2: Implement the Model (DAO)
Never write direct SQL queries in the `api/` folder.
1.  **Create the Model**: Add a new `.php` file in `src/models/` (e.g., `Course.php`).
2.  **Structure**: 
    *   The class should accept the PDO `$db` connection via dependency injection in the constructor.
    *   Define public properties corresponding to database columns.
    *   Create standard CRUD methods (`create()`, `read()`, `update()`, `delete()`, `read_single()`).
3.  **Validation inside Model**: Inside methods that process input (like `create` or `update`), aggressively sanitize and validate assigned properties using the centralized `Validator` utility *before* passing them to the PDO statement.
    ```php
    $this->title = Validator::sanitizeString($this->title);
    if (empty($this->title)) { return false; } // Example validation
    ```
4.  **Register Model**: Add a `require_once(MODEL_PATH . DS . 'your_model.php');` to `includes/initialize.php` so it is globally available.

### Step 3: Create API Endpoints (Controllers)
1.  **Folder Structure**: Create a domain folder under `api/` if it doesn't exist (e.g., `api/courses/`).
2.  **File Naming**: Create separate scripts for discrete actions (e.g., `create.php`, `read.php`, `update.php`, `delete.php`). This keeps files small and focused.
3.  **Boilerplate**: Start every endpoint by requiring the environment and initialization:
    ```php
    require_once '../../includes/cors.php';
    require_once '../../includes/initialize.php';
    ```
4.  **Authentication**: If the endpoint requires protection, invoke the middleware immediately:
    ```php
    requireAuth(); // For students/admins
    // OR
    requireAdmin(); // Strictly for administrators
    ```
5.  **Data Processing**: 
    *   Decode incoming JSON: `$data = json_decode(file_get_contents("php://input"));`
    *   Perform basic presence checks (`if(empty($data->required_field))`).
    *   Instantiate your model, assign properties, and invoke the model method.
6.  **Standardized Responses**: Always use the global helper functions for JSON output. Never manually use `echo json_encode()` or `http_response_code()` inside the endpoints.
    *   **Success**: `sendSuccess(200, 'Message', $dataArray);`
    *   **Error**: `sendError(400, 'Bad Request description.');`
    *   **Exceptions**: Catch blocks should use: `sendError(500, 'Internal Error', $e);`

---

## 4. Best Practices Summary
*   **Fat Models, Skinny Controllers**: Keep logic inside `api/*.php` minimal. The API file should strictly handle HTTP parsing, permission checks, model invocation, and HTTP responses. Business logic and queries belong in `src/models/`.
*   **Security First**: Assume all input is malicious. Rely on `Validator::sanitizeString()` and PDO Prepared statements (`bindParam`) without exception.
*   **No Global State Abuse**: Avoid referencing the global `$db` object directly inside functions. Always inject it via the constructor when instantiating a class.
*   **Uniformity**: Follow the naming conventions established in existing modules to ensure the codebase remains predictable.
