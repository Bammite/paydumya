<?php

if (!function_exists('paydunyaProjectRoot')) {
    function paydunyaProjectRoot()
    {
        return dirname(__DIR__);
    }
}

function paydunyaDomainRegistryConnection()
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    try {
        require paydunyaProjectRoot() . '/controller/connexion.php';
        if (isset($connexion) && $connexion instanceof PDO) {
            $db = $connexion;
            return $db;
        }
    } catch (Throwable $e) {
        error_log('paydunyaDomainRegistryConnection: ' . $e->getMessage());
    }

    return null;
}

function paydunyaDomainRegistryFetchAuthorizedDomains(array $filters = [])
{
    $db = paydunyaDomainRegistryConnection();
    if (!$db) {
        return [];
    }

    $sql = "SELECT d.*, p.code AS partenaire_code, p.nom AS partenaire_nom, p.api_key AS partenaire_api_key
            FROM domaine_autorise d
            LEFT JOIN partenaire_api p ON p.id = d.partenaire_id
            WHERE 1=1";
    $params = [];

    if (isset($filters['actif'])) {
        $sql .= ' AND d.actif = :actif';
        $params[':actif'] = $filters['actif'] ? 1 : 0;
    }
    if (isset($filters['allow_cors'])) {
        $sql .= ' AND d.allow_cors = :allow_cors';
        $params[':allow_cors'] = $filters['allow_cors'] ? 1 : 0;
    }
    if (isset($filters['allow_actions'])) {
        $sql .= ' AND d.allow_actions = :allow_actions';
        $params[':allow_actions'] = $filters['allow_actions'] ? 1 : 0;
    }
    if (!empty($filters['domaine'])) {
        $sql .= ' AND LOWER(d.domaine) = LOWER(:domaine)';
        $params[':domaine'] = trim((string) $filters['domaine']);
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('paydunyaDomainRegistryFetchAuthorizedDomains: ' . $e->getMessage());
        return [];
    }
}

function paydunyaDomainRegistryFetchAuthorizedHosts(array $filters = [])
{
    $rows = paydunyaDomainRegistryFetchAuthorizedDomains($filters);
    $hosts = [];

    foreach ($rows as $row) {
        $domain = strtolower(trim((string) ($row['domaine'] ?? '')));
        if ($domain !== '') {
            $hosts[] = $domain;
        }
    }

    return array_values(array_unique($hosts));
}

function paydunyaDomainRegistryResolvePartenaireId(PDO $db, array $data)
{
    if (!empty($data['partenaire_id'])) {
        return (int) $data['partenaire_id'];
    }

    if (!empty($data['partenaire_code'])) {
        $stmt = $db->prepare('SELECT id FROM partenaire_api WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => trim((string) $data['partenaire_code'])]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($result['id'])) {
            return (int) $result['id'];
        }
    }

    if (!empty($data['partenaire_api_key'])) {
        $stmt = $db->prepare('SELECT id FROM partenaire_api WHERE api_key = :api_key LIMIT 1');
        $stmt->execute([':api_key' => trim((string) $data['partenaire_api_key'])]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($result['id'])) {
            return (int) $result['id'];
        }
    }

    if (!empty($data['partenaire_code']) || !empty($data['partenaire_nom'])) {
        $code = trim((string) ($data['partenaire_code'] ?? ''));
        $nom = trim((string) ($data['partenaire_nom'] ?? $code));

        if ($code === '') {
            return null;
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO partenaire_api (code, nom, api_key, actif)
                 VALUES (:code, :nom, :api_key, :actif)
                 ON DUPLICATE KEY UPDATE nom = VALUES(nom), api_key = VALUES(api_key), actif = VALUES(actif)'
            );
            $stmt->execute([
                ':code' => $code,
                ':nom' => $nom,
                ':api_key' => trim((string) ($data['partenaire_api_key'] ?? '')),
                ':actif' => isset($data['partenaire_actif']) ? ($data['partenaire_actif'] ? 1 : 0) : 1,
            ]);

            $id = (int) $db->lastInsertId();
            if ($id > 0) {
                return $id;
            }

            $stmt = $db->prepare('SELECT id FROM partenaire_api WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result['id']) ? (int) $result['id'] : null;
        } catch (Throwable $e) {
            error_log('paydunyaDomainRegistryResolvePartenaireId: ' . $e->getMessage());
            return null;
        }
    }

    return null;
}

function paydunyaDomainRegistryUpsertAuthorizedDomain(array $data)
{
    $db = paydunyaDomainRegistryConnection();
    if (!$db) {
        return null;
    }

    $domain = strtolower(trim((string) ($data['domaine'] ?? $data['domain'] ?? '')));
    if ($domain === '') {
        return null;
    }

    $partenaireId = paydunyaDomainRegistryResolvePartenaireId($db, $data);
    $values = [
        ':partenaire_id' => $partenaireId,
        ':domaine' => $domain,
        ':require_https' => isset($data['require_https']) ? ($data['require_https'] ? 1 : 0) : 1,
        ':allow_cors' => isset($data['allow_cors']) ? ($data['allow_cors'] ? 1 : 0) : 1,
        ':allow_actions' => isset($data['allow_actions']) ? ($data['allow_actions'] ? 1 : 0) : 1,
        ':callback_url' => trim((string) ($data['callback_url'] ?? $data['callbackUrl'] ?? '')),
        ':return_url' => trim((string) ($data['return_url'] ?? $data['returnUrl'] ?? '')),
        ':cancel_url' => trim((string) ($data['cancel_url'] ?? $data['cancelUrl'] ?? '')),
        ':actif' => isset($data['actif']) ? ($data['actif'] ? 1 : 0) : 1,
    ];

    try {
        $stmt = $db->prepare('SELECT id FROM domaine_autorise WHERE domaine = :domaine LIMIT 1');
        $stmt->execute([':domaine' => $domain]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($existing['id'])) {
            $stmt = $db->prepare(
                'UPDATE domaine_autorise
                 SET partenaire_id = :partenaire_id,
                     require_https = :require_https,
                     allow_cors = :allow_cors,
                     allow_actions = :allow_actions,
                     callback_url = :callback_url,
                     return_url = :return_url,
                     cancel_url = :cancel_url,
                     actif = :actif,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $values[':id'] = (int) $existing['id'];
            $stmt->execute($values);
            return (int) $existing['id'];
        }

        $stmt = $db->prepare(
            'INSERT INTO domaine_autorise
             (partenaire_id, domaine, require_https, allow_cors, allow_actions, callback_url, return_url, cancel_url, actif)
             VALUES
             (:partenaire_id, :domaine, :require_https, :allow_cors, :allow_actions, :callback_url, :return_url, :cancel_url, :actif)'
        );
        $stmt->execute($values);
        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        error_log('paydunyaDomainRegistryUpsertAuthorizedDomain: ' . $e->getMessage());
        return null;
    }
}

function paydunyaDomainRegistryImportAuthorizedDomains(array $domains)
{
    $results = [];
    foreach ($domains as $domainData) {
        $results[] = [
            'domain' => $domainData['domaine'] ?? $domainData['domain'] ?? null,
            'id' => paydunyaDomainRegistryUpsertAuthorizedDomain($domainData),
        ];
    }
    return $results;
}

function paydunyaDomainRegistryExportAuthorizedDomains(array $filters = [])
{
    return paydunyaDomainRegistryFetchAuthorizedDomains($filters);
}

if (php_sapi_name() === 'cli' && basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    $command = $_SERVER['argv'][1] ?? 'list';
    $output = [];

    switch ($command) {
        case 'list':
            $output = paydunyaDomainRegistryExportAuthorizedDomains(['actif' => true]);
            break;
        case 'all':
            $output = paydunyaDomainRegistryExportAuthorizedDomains();
            break;
        default:
            echo "Usage: php paydunya_domain_registry.php [list|all]\n";
            exit(1);
    }

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}
