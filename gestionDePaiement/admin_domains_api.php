<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/paydunya_domain_registry.php';
    // require_once __DIR__ . '/paydunya_service.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de chargement: ' . $e->getMessage()
    ]);
    exit;
}

function handleDomainRequest()
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

    $response = [
        'success' => false,
        'message' => 'Action non reconnue',
        'data' => null,
    ];

    try {
        if ($method === 'GET' && $action === 'list') {
            $response = handleListDomains();
        } elseif ($method === 'GET' && $action === 'partners') {
            $response = handleListPartners();
        } elseif ($method === 'POST' && $action === 'add') {
            $response = handleAddDomain();
        } elseif ($method === 'POST' && $action === 'update') {
            $response = handleUpdateDomain();
        } elseif ($method === 'POST' && $action === 'delete') {
            $response = handleDeleteDomain();
        } else {
            $response['message'] = 'Méthode ou action non supportée';
        }
    } catch (Throwable $e) {
        $response['success'] = false;
        $response['message'] = 'Erreur serveur: ' . $e->getMessage();
    }

    return $response;
}

$result = handleDomainRequest();

// Vérifier si l'encodage JSON échoue
$json = json_encode($result);
if ($json === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur d\'encodage JSON: ' . json_last_error_msg()
    ]);
} else {
    echo $json;
}

function handleListDomains() {
    $rows = paydunyaDomainRegistryFetchAuthorizedDomains();
    return [
        'success' => true,
        'message' => 'Domaines récupérés',
        'data' => $rows,
    ];
}

function handleListPartners()
{
    $db = paydunyaDomainRegistryConnection();
    if (!$db) {
        return [
            'success' => false,
            'message' => 'Connexion BD indisponible',
            'data' => [],
        ];
    }

    try {
        $stmt = $db->query('SELECT id, code, nom, api_key, actif FROM partenaire_api ORDER BY nom ASC');
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'success' => true,
            'message' => 'Partenaires récupérés',
            'data' => $partners,
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Erreur lecture partenaires: ' . $e->getMessage(),
            'data' => [],
        ];
    }
}

function validateDomainData(array $data)
{
    $errors = [];

    $domaine = trim((string) ($data['domaine'] ?? ''));
    if ($domaine === '') {
        $errors[] = 'Le domaine est obligatoire';
    } elseif (!preg_match('/^[a-z0-9\-\.]+\.[a-z]{2,}$/i', $domaine)) {
        $errors[] = 'Le domaine n\'est pas valide';
    }

    $partenaire_code = trim((string) ($data['partenaire_code'] ?? ''));
    $partenaire_id = (int) ($data['partenaire_id'] ?? 0);
    if ($partenaire_code === '' && $partenaire_id === 0) {
        $errors[] = 'Un partenaire est obligatoire';
    }

    if (isset($data['callback_url']) && trim((string) $data['callback_url']) !== '') {
        $url = trim((string) $data['callback_url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL callback invalide';
        }
    }

    if (isset($data['return_url']) && trim((string) $data['return_url']) !== '') {
        $url = trim((string) $data['return_url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL return invalide';
        }
    }

    if (isset($data['cancel_url']) && trim((string) $data['cancel_url']) !== '') {
        $url = trim((string) $data['cancel_url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL cancel invalide';
        }
    }

    return $errors;
}

function handleAddDomain()
{
    $payload = $_POST ?? [];
    $errors = validateDomainData($payload);

    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => 'Validation échouée: ' . implode(', ', $errors),
            'data' => null,
        ];
    }

    $id = paydunyaDomainRegistryUpsertAuthorizedDomain($payload);

    if ($id === null) {
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'ajout du domaine',
            'data' => null,
        ];
    }

    return [
        'success' => true,
        'message' => 'Domaine ajouté avec succès',
        'data' => ['id' => $id],
    ];
}

function handleUpdateDomain()
{
    $payload = $_POST ?? [];
    $domaine_id = (int) ($payload['domaine_id'] ?? 0);

    if ($domaine_id === 0) {
        return [
            'success' => false,
            'message' => 'ID domaine manquant',
            'data' => null,
        ];
    }

    $errors = validateDomainData($payload);
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => 'Validation échouée: ' . implode(', ', $errors),
            'data' => null,
        ];
    }

    $id = paydunyaDomainRegistryUpsertAuthorizedDomain($payload);

    if ($id === null) {
        return [
            'success' => false,
            'message' => 'Erreur lors de la mise à jour',
            'data' => null,
        ];
    }

    return [
        'success' => true,
        'message' => 'Domaine mis à jour',
        'data' => ['id' => $id],
    ];
}

function handleDeleteDomain()
{
    $domaine_id = (int) ($_POST['domaine_id'] ?? 0);

    if ($domaine_id === 0) {
        return [
            'success' => false,
            'message' => 'ID domaine manquant',
            'data' => null,
        ];
    }

    $db = paydunyaDomainRegistryConnection();
    if (!$db) {
        return [
            'success' => false,
            'message' => 'Connexion BD indisponible',
            'data' => null,
        ];
    }

    try {
        $stmt = $db->prepare('DELETE FROM domaine_autorise WHERE id = :id');
        $stmt->execute([':id' => $domaine_id]);

        if ($stmt->rowCount() === 0) {
            return [
                'success' => false,
                'message' => 'Domaine non trouvé',
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Domaine supprimé',
            'data' => null,
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Erreur suppression: ' . $e->getMessage(),
            'data' => null,
        ];
    }
}
