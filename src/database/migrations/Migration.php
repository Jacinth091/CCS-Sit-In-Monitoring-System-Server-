<?php

class Migration {
    private $db;
    private $migrationsPath;

    public function __construct($db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/versions/';
        $this->createMigrationsTable();
    }

    // Create a tracker table so we know which migrations already ran
    private function createMigrationsTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id         SERIAL PRIMARY KEY,
                migration  VARCHAR(255) NOT NULL UNIQUE,
                ran_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Get list of already-ran migrations from DB
    private function getRanMigrations() {
        $stmt = $this->db->query("SELECT migration FROM migrations ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Get all migration files sorted by version number
    private function getMigrationFiles() {
        $files = glob($this->migrationsPath . '*.php');
        sort($files);
        return $files;
    }

    public function run() {
        $ran        = $this->getRanMigrations();
        $files      = $this->getMigrationFiles();
        $newCount   = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ran)) {
                echo "[SKIP]  $name — already ran\n";
                continue;
            }

            require_once $file;

            // Convert filename to class name: 001_create_students_table → CreateStudentsTable
            $className = $this->toClassName($name);

            if (!class_exists($className)) {
                echo "[ERROR] Class '$className' not found in $name.php\n";
                continue;
            }

            try {
                $migration = new $className($this->db);
                $migration->up();

                // Mark as ran
                $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (:name)");
                $stmt->execute([':name' => $name]);

                echo "[OK]    $name\n";
                $newCount++;
            } catch (Exception $e) {
                echo "[FAIL]  $name — " . $e->getMessage() . "\n";
                break; // Stop on first failure
            }
        }

        if ($newCount === 0) {
            echo "\nNothing new to migrate.\n";
        } else {
            echo "\n$newCount migration(s) ran successfully.\n";
        }
    }

    public function fresh() {
        echo "Dropping all tables...\n";
        
        try {
            // More comprehensive query to get all table-like relations in the public schema
            $query = "SELECT relname FROM pg_class WHERE relkind IN ('r', 'p', 'v', 'm') AND relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')";
            $stmt = $this->db->query($query);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo "Found relations to drop: " . implode(', ', $tables) . "\n";

            if (empty($tables)) {
                echo "No tables/views found in public schema.\n";
            } else {
                foreach ($tables as $table) {
                    try {
                        $drop_query = "DROP TABLE IF EXISTS public.\"" . $table . "\" CASCADE";
                        echo "  → Executing: $drop_query\n";
                        $this->db->exec($drop_query);
                    } catch (PDOException $e) {
                        echo "  [ERROR] Failed to drop table $table: " . $e->getMessage() . "\n";
                    }
                }
                echo "Table drop commands executed.\n\n";
            }
        } catch (PDOException $e) {
            echo "[ERROR] Could not fetch tables: " . $e->getMessage() . "\n";
        }

        // Re-create the migrations table and run all migrations
        echo "Running all migrations from scratch...\n";
        $this->createMigrationsTable();
        $this->run();
    }

    private function toClassName($filename) {
        // Remove version prefix: 001_create_students_table → create_students_table
        $withoutVersion = preg_replace('/^\d+_/', '', $filename);
        // snake_case → PascalCase: create_students_table → CreateStudentsTable
        return str_replace('_', '', ucwords($withoutVersion, '_'));
    }
}