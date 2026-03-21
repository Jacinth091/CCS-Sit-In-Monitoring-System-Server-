<?php

class Seeder {
    private $db;
    private $seedersPath;

    public function __construct($db) {
        $this->db = $db;
        $this->seedersPath = __DIR__ . '/data/';
        $this->createSeedersTable();
    }

    private function createSeedersTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS seeders (
                id       SERIAL PRIMARY KEY,
                seeder   VARCHAR(255) NOT NULL UNIQUE,
                ran_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function getRanSeeders() {
        $stmt = $this->db->query("SELECT seeder FROM seeders ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getSeederFiles() {
        $files = glob($this->seedersPath . '*.php');
        sort($files);
        return $files;
    }

    private function resolveClassName(string $fileBaseName): string {
        return preg_replace('/^\d+_/', '', $fileBaseName);
    }

    private function matchesSpecific(?string $specific, string $fileBaseName, string $className): bool {
        if (!$specific) {
            return true;
        }

        return $specific === $fileBaseName || $specific === $className;
    }

    public function run($specific = null) {
        $ran        = $this->getRanSeeders();
        $files      = $this->getSeederFiles();
        $newCount   = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $className = $this->resolveClassName($name);

            // If a specific seeder is requested, skip others
            if (!$this->matchesSpecific($specific, $name, $className)) continue;

            if (in_array($name, $ran)) {
                echo "[SKIP]  $name — already ran\n";
                continue;
            }

            require_once $file;

            if (!class_exists($className)) {
                echo "[ERROR] Class '$className' not found in $name.php\n";
                continue;
            }

            try {
                $seeder = new $className($this->db);
                $seeder->run();

                $stmt = $this->db->prepare("INSERT INTO seeders (seeder) VALUES (:name)");
                $stmt->execute([':name' => $name]);

                echo "[OK]    $name\n";
                $newCount++;
            } catch (Exception $e) {
                echo "[FAIL]  $name — " . $e->getMessage() . "\n";
                break;
            }
        }

        if ($newCount === 0) {
            echo "\nNothing new to seed.\n";
        } else {
            echo "\n$newCount seeder(s) ran successfully.\n";
        }
    }

    // Force re-run even if already ran
    public function fresh() {
        $this->db->exec("TRUNCATE TABLE seeders RESTART IDENTITY CASCADE");
        echo "Seeder history cleared. Re-running all seeders...\n\n";
        $this->run();
    }
}