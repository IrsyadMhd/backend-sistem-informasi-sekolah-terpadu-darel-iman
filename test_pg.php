<?php
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "STARTING PG TEST...\n";
try {
    $pdo = new PDO('pgsql:host=/tmp;port=5432;dbname=school_management', 'postgres', 'password', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "SUCCESS: CONNECTED VIA UNIX SOCKET /tmp!\n";
    echo "TABLE COUNT: " . count($tables) . "\n";
    echo "TABLES:\n" . implode("\n", $tables) . "\n";
} catch (Exception $e) {
    echo "UNIX SOCKET /tmp ERROR: " . $e->getMessage() . "\n";
}

try {
    $pdo = new PDO('pgsql:host=/private/tmp;port=5432;dbname=school_management', 'postgres', 'password', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "SUCCESS: CONNECTED VIA UNIX SOCKET /private/tmp!\n";
    echo "TABLE COUNT: " . count($tables) . "\n";
    echo "TABLES:\n" . implode("\n", $tables) . "\n";
} catch (Exception $e) {
    echo "UNIX SOCKET /private/tmp ERROR: " . $e->getMessage() . "\n";
}

try {
    $pdo2 = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=school_management', 'postgres', 'password', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "SUCCESS: CONNECTED VIA TCP 127.0.0.1!\n";
} catch (Exception $e) {
    echo "TCP 127.0.0.1 ERROR: " . $e->getMessage() . "\n";
}
