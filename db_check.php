<?php
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => 'Diagnostic non exécuté',
    'details' => [],
];

try {
    require_once __DIR__ . '/controller/connexion.php';

    if (!isset($connexion) || !($connexion instanceof PDO)) {
        throw new RuntimeException('PDO non initialisé.');
    }

    $response['success'] = true;
    $response['message'] = 'Connexion PDO OK';
    $response['details']['db_host'] = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
    $response['details']['db_name'] = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? null);
    $response['details']['db_user'] = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? null);

    $stmt = $connexion->query('SELECT DATABASE() AS database_name, @@hostname AS db_server, VERSION() AS db_version');
    $response['details']['server_info'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $connexion->query("SHOW TABLES LIKE 'domaine_autorise'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response['details']['has_domaine_autorise'] = !empty($tables);
    $response['details']['tables'] = $tables;

    if (!empty($tables)) {
        $stmt = $connexion->query("SELECT COUNT(*) AS count FROM domaine_autorise");
        $response['details']['domaine_autorise_count'] = (int) $stmt->fetchColumn();

        $stmt = $connexion->prepare('SELECT * FROM domaine_autorise WHERE domaine IN (:d1, :d2) LIMIT 2');
        $stmt->execute([':d1' => 'pay.bammite.com', ':d2' => 'www.pay.bammite.com']);
        $response['details']['pay_bammite_domains'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $response['details']['registry_table_missing'] = false;
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Erreur de diagnostic DB';
    $response['details']['exception'] = get_class($e);
    $response['details']['exception_message'] = $e->getMessage();
    $response['details']['exception_trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
