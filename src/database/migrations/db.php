<?php

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/Migration.php';
require_once __DIR__ . '/../seeders/Seeder.php'; 

load_env(__DIR__ . '/../../../.env');

$command = $argv[1] ?? null;

match($command) {
    'migrate'      => runMigrate($db),
    'migrate:fresh' => runMigrateFresh($db), 
    'make'         => runMake($argv[2] ?? null),
    'seed'         => runSeed($db, $argv[2] ?? null),
    'seed:fresh'   => runSeedFresh($db),
    'make:seeder'  => runMakeSeeder($argv[2] ?? null),
    default        => showHelp()
};
function runMigrate($db) {
    echo "=== CCS Sit-In Monitoring — Database Migrations ===\n\n";
    $migration = new Migration($db);
    $migration->run();
}

function runMake($name) {
    if (!$name) {
        echo "Error: Please provide a migration name.\n";
        echo "Usage: php migrations/db.php make <name>\n";
        exit(1);
    }

    $name         = strtolower(trim($name));
    $versionsPath = __DIR__ . '/versions/';
    $existing     = glob($versionsPath . '*.php');
    $next         = str_pad(count($existing) + 1, 3, '0', STR_PAD_LEFT);
    $filename     = "{$next}_{$name}.php";
    $className    = str_replace('_', '', ucwords($name, '_'));
    $fullPath     = $versionsPath . $filename;

    $template = <<<PHP
<?php

class {$className} {
    private \$db;

    public function __construct(\$db) { \$this->db = \$db; }

    public function up() {
        \$this->db->exec("
            -- Write your SQL here

        ");
    }
}
PHP;

    file_put_contents($fullPath, $template);
    echo "Migration created: migrations/versions/{$filename}\n";
}

function runMigrateFresh($db) {
    echo "=== CCS Sit-In Monitoring — Fresh Migration ===\n\n";

    echo "Dropping all tables...\n";

    // Drop in reverse order to respect foreign key constraints
    $tables = [
        'feedback',
        'reservations',
        'sit_in_logs',
        'announcements',
        'laboratories',
        'students',
        'seeders',
        'migrations',
    ];

    foreach ($tables as $table) {
        $db->exec("DROP TABLE IF EXISTS {$table} CASCADE");
        echo "  → Dropped: {$table}\n";
    }

    echo "\nRe-running all migrations...\n\n";

    $migration = new Migration($db);
    $migration->run();
}

function runMakeSeeder($name) {
    if (!$name) {
        echo "Error: Please provide a seeder name.\n";
        echo "Usage: php db.php make:seeder <name>\n";
        exit(1);
    }

    $name       = trim($name);
    $dataPath   = __DIR__ . '/../seeders/data/';
    $existing   = glob($dataPath . '*.php');
    $next       = str_pad(count($existing) + 1, 3, '0', STR_PAD_LEFT);

    // Ensure name ends with Seeder
    $className  = str_ends_with($name, 'Seeder') ? $name : $name . 'Seeder';
    $filename   = "{$next}_{$className}.php";
    $fullPath   = $dataPath . $filename;

    $template = <<<PHP
<?php

class {$className} {
    private \$db;

    public function __construct(\$db) { \$this->db = \$db; }

    public function run() {
        \$data = [
            // Add your data here
            // [
            //     'column' => 'value',
            // ],
        ];

        \$stmt = \$this->db->prepare("
            INSERT INTO your_table (column)
            VALUES (:column)
            ON CONFLICT DO NOTHING
        ");

        foreach (\$data as \$item) {
            \$stmt->execute(\$item);
        }
    }
}
PHP;

    file_put_contents($fullPath, $template);
    echo "Seeder created: src/database/seeders/data/{$filename}\n";
}

function runSeed($db, $specific = null) {
    echo "=== CCS Sit-In Monitoring — Seeders ===\n\n";
    $seeder = new Seeder($db);
    $seeder->run($specific);
}

function runSeedFresh($db) {
    echo "=== CCS Sit-In Monitoring — Fresh Seed ===\n\n";
    $seeder = new Seeder($db);
    $seeder->fresh();
}

function showHelp() {
    echo "=== CCS Sit-In DB Tool ===\n\n";
    echo "Commands:\n";
    echo "  php db.php migrate                — Run all pending migrations\n";
    echo "  php db.php migrate:fresh          — Drop all tables and re-run migrations\n";
    echo "  php db.php make <name>            — Create a new migration file\n";
    echo "  php db.php make:seeder <name>     — Create a new seeder file\n";
    echo "  php db.php seed                   — Run all pending seeders\n";
    echo "  php db.php seed <SeederName>      — Run a specific seeder\n";
    echo "  php db.php seed:fresh             — Clear and re-run all seeders\n\n";
    echo "Examples:\n";
    echo "  php db.php migrate\n";
    echo "  php db.php migrate:fresh\n";
    echo "  php db.php make create_resources_table\n";
    echo "  php db.php make:seeder Laboratory\n";
    echo "  php db.php seed\n";
    echo "  php db.php seed:fresh\n";
}