<?php
require_once __DIR__ . '/paydunya_service.php';

function paydunyaRegistryConnection()
{
    static $initialized = false;
    static $db = null;

    if ($initialized) {
        return $db;
    }

    $initialized = true;

    try {
        require paydunyaProjectRoot() . '/controller/connexion.php';
        if (isset($connexion) && $connexion instanceof PDO) {
            $db = $connexion;
            return $db;
        }
    } catch (Throwable $e) {
        paydunyaWriteLog('Connexion registre partenaires indisponible', [
            'message' => $e->getMessage(),
        ]);
    }

    return null;
}

function paydunyaRegistryHostFromUrl($url)
{
    $value = trim((string) $url);
    if ($value === '') {
        return '';
    }

    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }

    return strtolower(trim((string) $parts['host']));
}

function paydunyaRegistryHostFromOrigin($origin)
{
    return paydunyaRegistryHostFromUrl((string) $origin);
}

function paydunyaRegistryRequestHeaders()
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $normalized = [];
    foreach ($headers as $key => $value) {
        $normalized[strtolower((string) $key)] = (string) $value;
    }
    return $normalized;
}

function paydunyaRegistryClientIp()
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value === '') {
            continue;
        }
        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            $value = trim((string) $parts[0]);
        }
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function paydunyaRegistryResolveDomainContext(array $request = [])
{
    $db = paydunyaRegistryConnection();
    if (!$db) {
        return ['found' => false, 'reason' => 'db_unavailable'];
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $baseUrl = trim((string) ($request['base_url'] ?? $request['baseUrl'] ?? ''));
    $callbackUrl = trim((string) ($request['callback_url'] ?? $request['callbackUrl'] ?? ''));
    $returnUrl = trim((string) ($request['return_url'] ?? $request['returnUrl'] ?? ''));
    $cancelUrl = trim((string) ($request['cancel_url'] ?? $request['cancelUrl'] ?? ''));
    $httpHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($httpHost !== '') {
        $httpHost = preg_replace('/:\d+$/', '', $httpHost);
    }

    $candidates = array_values(array_unique(array_filter([
        paydunyaRegistryHostFromOrigin($origin),
        paydunyaRegistryHostFromUrl($baseUrl),
        paydunyaRegistryHostFromUrl($callbackUrl),
        paydunyaRegistryHostFromUrl($returnUrl),
        paydunyaRegistryHostFromUrl($cancelUrl),
        $httpHost,
    ])));

    if (empty($candidates)) {
        return ['found' => false, 'reason' => 'no_domain_candidate'];
    }

    $placeholders = implode(',', array_fill(0, count($candidates), '?'));
    $sql = "SELECT
                d.id AS domaine_id,
                d.partenaire_id,
                d.domaine,
                d.require_https,
                d.allow_cors,
                d.allow_actions,
                d.callback_url,
                d.return_url,
                d.cancel_url,
                p.code AS partenaire_code,
                p.nom AS partenaire_nom,
                p.api_key AS partenaire_api_key,
                p.actif AS partenaire_actif
            FROM domaine_autorise d
            LEFT JOIN partenaire_api p ON p.id = d.partenaire_id
            WHERE d.actif = 1
              AND d.domaine IN ($placeholders)
              AND (p.id IS NULL OR p.actif = 1)";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($candidates);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        paydunyaWriteLog('Lecture domaine_autorise impossible', [
            'message' => $e->getMessage(),
        ]);
        return ['found' => false, 'reason' => 'registry_table_missing'];
    }

    if (empty($rows)) {
        return [
            'found' => false,
            'reason' => 'domain_not_registered',
            'candidates' => $candidates,
            'origin' => $origin,
        ];
    }

    $byDomain = [];
    foreach ($rows as $row) {
        $byDomain[strtolower((string) $row['domaine'])] = $row;
    }

    $selected = null;
    foreach ($candidates as $candidate) {
        if (isset($byDomain[$candidate])) {
            $selected = $byDomain[$candidate];
            break;
        }
    }

    if (!$selected) {
        $selected = reset($rows);
    }

    $headers = paydunyaRegistryRequestHeaders();
    $providedKey = trim((string) ($headers['x-paydunya-partner-key'] ?? $request['partner_key'] ?? ''));
    $expectedKey = trim((string) ($selected['partenaire_api_key'] ?? ''));
    if ($expectedKey !== '' && $providedKey !== '') {
        if ($providedKey !== $expectedKey) {
            return [
                'found' => false,
                'reason' => 'invalid_partner_key',
                'domaine' => $selected['domaine'] ?? '',
                'partenaire_id' => (int) ($selected['partenaire_id'] ?? 0),
            ];
        }
    }

    return [
        'found' => true,
        'origin' => $origin,
        'domaine_id' => (int) ($selected['domaine_id'] ?? 0),
        'partenaire_id' => !empty($selected['partenaire_id']) ? (int) $selected['partenaire_id'] : null,
        'domaine' => strtolower((string) ($selected['domaine'] ?? '')),
        'require_https' => (int) ($selected['require_https'] ?? 1) === 1,
        'allow_cors' => (int) ($selected['allow_cors'] ?? 0) === 1,
        'allow_actions' => (int) ($selected['allow_actions'] ?? 0) === 1,
        'callback_url' => trim((string) ($selected['callback_url'] ?? '')),
        'return_url' => trim((string) ($selected['return_url'] ?? '')),
        'cancel_url' => trim((string) ($selected['cancel_url'] ?? '')),
        'partenaire_code' => (string) ($selected['partenaire_code'] ?? ''),
        'partenaire_nom' => (string) ($selected['partenaire_nom'] ?? ''),
    ];
}

function paydunyaRegistryAllowedCorsOrigins()
{
    $db = paydunyaRegistryConnection();
    if (!$db) {
        return [];
    }

    try {
        $sql = "SELECT domaine FROM domaine_autorise WHERE actif = 1 AND allow_cors = 1";
        $stmt = $db->query($sql);
        $hosts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        paydunyaWriteLog('Lecture CORS domaines impossible', [
            'message' => $e->getMessage(),
        ]);
        return [];
    }

    $origins = [];
    foreach ($hosts as $host) {
        $domain = strtolower(trim((string) $host));
        if ($domain === '') {
            continue;
        }
        $origins[] = 'https://' . $domain;
        if ($domain === 'localhost' || strpos($domain, '127.0.0.1') === 0) {
            $origins[] = 'http://' . $domain;
        }
    }

    return array_values(array_unique($origins));
}

function paydunyaRegistryAllowedMethods($partenaireId = null)
{
    $db = paydunyaRegistryConnection();
    if (!$db) {
        return [];
    }

    try {
        if ($partenaireId) {
            $sql = "SELECT m.code
                    FROM partenaire_methode_paiement pm
                    JOIN methode_paiement m ON m.id = pm.methode_id
                    WHERE pm.partenaire_id = :partenaire_id
                      AND pm.actif = 1
                      AND m.actif = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':partenaire_id' => (int) $partenaireId]);
            $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($codes)) {
                return array_values(array_unique(array_map('strtolower', $codes)));
            }
        }

        $sql = "SELECT code FROM methode_paiement WHERE actif = 1 AND public_collab = 1";
        $stmt = $db->query($sql);
        $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map('strtolower', $codes)));
    } catch (Throwable $e) {
        paydunyaWriteLog('Lecture méthode paiement impossible', [
            'message' => $e->getMessage(),
        ]);
        return [];
    }
}

function paydunyaRegistryInsertTransaction(array $payload)
{
    $db = paydunyaRegistryConnection();
    if (!$db) {
        return null;
    }

    $sql = "INSERT INTO transaction_paiement
            (partenaire_id, domaine_id, reference_client, customer_name, phone_number, amount, payment_method,
             request_origin, request_ip, base_url, callback_url, return_url, cancel_url, status,
             request_payload, response_payload)
            VALUES
            (:partenaire_id, :domaine_id, :reference_client, :customer_name, :phone_number, :amount, :payment_method,
             :request_origin, :request_ip, :base_url, :callback_url, :return_url, :cancel_url, :status,
             :request_payload, :response_payload)";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':partenaire_id' => $payload['partenaire_id'] ?? null,
            ':domaine_id' => $payload['domaine_id'] ?? null,
            ':reference_client' => $payload['reference_client'] ?? null,
            ':customer_name' => $payload['customer_name'] ?? '',
            ':phone_number' => $payload['phone_number'] ?? '',
            ':amount' => (int) ($payload['amount'] ?? 0),
            ':payment_method' => $payload['payment_method'] ?? '',
            ':request_origin' => $payload['request_origin'] ?? null,
            ':request_ip' => $payload['request_ip'] ?? null,
            ':base_url' => $payload['base_url'] ?? null,
            ':callback_url' => $payload['callback_url'] ?? null,
            ':return_url' => $payload['return_url'] ?? null,
            ':cancel_url' => $payload['cancel_url'] ?? null,
            ':status' => $payload['status'] ?? 'pending',
            ':request_payload' => isset($payload['request_payload']) ? json_encode($payload['request_payload']) : null,
            ':response_payload' => isset($payload['response_payload']) ? json_encode($payload['response_payload']) : null,
        ]);

        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        paydunyaWriteLog('Insertion transaction_paiement impossible', [
            'message' => $e->getMessage(),
        ]);
        return null;
    }
}

function paydunyaRegistryUpdateTransaction($transactionId, array $payload)
{
    $db = paydunyaRegistryConnection();
    if (!$db || !$transactionId) {
        return;
    }

    $sql = "UPDATE transaction_paiement
            SET status = :status,
                provider_token = :provider_token,
                provider_payment_url = :provider_payment_url,
                provider_http_code = :provider_http_code,
                provider_message = :provider_message,
                response_payload = :response_payload,
                updated_at = NOW()
            WHERE id = :id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':status' => $payload['status'] ?? 'failed',
            ':provider_token' => $payload['provider_token'] ?? null,
            ':provider_payment_url' => $payload['provider_payment_url'] ?? null,
            ':provider_http_code' => isset($payload['provider_http_code']) ? (int) $payload['provider_http_code'] : null,
            ':provider_message' => $payload['provider_message'] ?? null,
            ':response_payload' => isset($payload['response_payload']) ? json_encode($payload['response_payload']) : null,
            ':id' => (int) $transactionId,
        ]);
    } catch (Throwable $e) {
        paydunyaWriteLog('Mise à jour transaction_paiement impossible', [
            'message' => $e->getMessage(),
        ]);
    }
}

function paydunyaRegistryInsertLog(array $payload)
{
    $db = paydunyaRegistryConnection();
    if (!$db) {
        return null;
    }

    $sql = "INSERT INTO journal_api
            (partenaire_id, domaine_id, event_type, severity, message, origin, ip, context_json)
            VALUES
            (:partenaire_id, :domaine_id, :event_type, :severity, :message, :origin, :ip, :context_json)";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':partenaire_id' => $payload['partenaire_id'] ?? null,
            ':domaine_id' => $payload['domaine_id'] ?? null,
            ':event_type' => $payload['event_type'] ?? 'general',
            ':severity' => $payload['severity'] ?? 'info',
            ':message' => $payload['message'] ?? '',
            ':origin' => $payload['origin'] ?? null,
            ':ip' => $payload['ip'] ?? paydunyaRegistryClientIp(),
            ':context_json' => isset($payload['context']) ? json_encode($payload['context']) : null,
        ]);

        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        paydunyaWriteLog('Insertion journal_api impossible', [
            'message' => $e->getMessage(),
        ]);
        return null;
    }
}
