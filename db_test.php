<?php
require_once __DIR__ . '/controller/connexion.php';

try {
    if (!isset($connexion) || !($connexion instanceof PDO)) {
        throw new RuntimeException('Connexion PDO non initialisée.');
    }

    echo "PDO OK" . PHP_EOL;
    echo "DSN: " . $connexion->getAttribute(PDO::ATTR_CONNECTION_STATUS) . PHP_EOL;

    $sql = "SELECT id, partenaire_id, domaine, actif FROM domaine_autorise WHERE domaine IN ('pay.bammite.com','www.pay.bammite.com') LIMIT 2";
    $stmt = $connexion->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Rows: " . count($rows) . PHP_EOL;
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo 'Trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
