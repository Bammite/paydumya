<?php
require_once __DIR__ . '/paydunya_service.php';
require_once __DIR__ . '/paydunya_partner_registry.php';

ini_set('display_errors', '0');
header('Content-Type: application/json');

function paydunyaSimpleCorsOrigins()
{
    $config = paydunyaLoadConfig();
    $originsRaw = (string) ($config['CORS_ORIGINS'] ?? '');
    $envOrigins = array_filter(array_map('trim', explode(',', $originsRaw)));
    $dbOrigins = paydunyaRegistryAllowedCorsOrigins();
    return array_values(array_unique(array_merge($envOrigins, $dbOrigins)));
}

function paydunyaSimpleApplyCorsHeaders()
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return;
    }

    $allowedOrigins = paydunyaSimpleCorsOrigins();
    if (empty($allowedOrigins)) {
        return;
    }

    $allowAny = in_array('*', $allowedOrigins, true);
    if (!$allowAny && !in_array($origin, $allowedOrigins, true)) {
        return;
    }

    header('Vary: Origin');
    header('Access-Control-Allow-Origin: ' . ($allowAny ? '*' : $origin));
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

function paydunyaSimpleRespond(array $payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function paydunyaSimpleIsSnMethod($method)
{
    return in_array($method, ['wave', 'orange_money', 'free_money', 'expresso', 'wizall'], true);
}

function paydunyaSimpleNormalizePhoneByMethod($rawPhone, $normalizedMethod)
{
    if (paydunyaSimpleIsSnMethod($normalizedMethod)) {
        return paydunyaNormalizeSnPhone($rawPhone);
    }

    return paydunyaCleanPhone($rawPhone);
}

function paydunyaSimpleNormalizeBaseUrl($rawBaseUrl)
{
    $candidate = trim((string) $rawBaseUrl);
    if ($candidate === '') {
        return '';
    }

    $parts = parse_url($candidate);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = isset($parts['path']) ? '/' . ltrim((string) $parts['path'], '/') : '';

    return rtrim($scheme . '://' . $host . $port . $path, '/');
}

function paydunyaSimpleJoinUrl($baseUrl, $path)
{
    if ($baseUrl === '') {
        return '';
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function paydunyaSimpleUrlHasPath($url)
{
    $value = trim((string) $url);
    if ($value === '') {
        return false;
    }

    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }

    $path = (string) ($parts['path'] ?? '');
    return $path !== '' && $path !== '/';
}

paydunyaSimpleApplyCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'Méthode non autorisée. Utilisez POST.',
        ], 405);
    }

    $request = paydunyaRequestData();
    $domainContext = paydunyaRegistryResolveDomainContext($request);
    $requestOrigin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $requestIp = paydunyaRegistryClientIp();

    if (empty($domainContext['found'])) {
        paydunyaRegistryInsertLog([
            'event_type' => 'domain_blocked',
            'severity' => 'warning',
            'message' => 'Domaine non autorisé pour usage API partenaire.',
            'origin' => $requestOrigin,
            'ip' => $requestIp,
            'context' => [
                'reason' => $domainContext['reason'] ?? 'unknown',
                'request' => $request,
            ],
        ]);
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'Domaine non autorisé. Veuillez demander l\'activation de votre domaine.',
            'reason' => $domainContext['reason'] ?? 'domain_not_registered',
        ], 403);
    }

    $customerName = trim((string) paydunyaRequestValue($request, ['customer_name', 'name', 'full_name'], ''));
    $rawPhone = trim((string) paydunyaRequestValue($request, ['phone_number', 'phone'], ''));
    $amount = (int) paydunyaRequestValue($request, ['amount', 'total_amount'], 0);
    $paymentMethod = trim((string) paydunyaRequestValue($request, ['payment_method', 'method'], ''));
    $normalizedMethod = paydunyaNormalizeMethod($paymentMethod);

    if ($customerName === '' || $rawPhone === '' || $amount <= 0 || $normalizedMethod === '') {
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'Paramètres invalides. Champs requis: customer_name, phone_number, amount, payment_method.',
        ], 422);
    }

    $phoneNumber = paydunyaSimpleNormalizePhoneByMethod($rawPhone, $normalizedMethod);
    if ($phoneNumber === '') {
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'Numéro de téléphone invalide pour le moyen de paiement sélectionné.',
        ], 422);
    }

    $allowedMethods = paydunyaRegistryAllowedMethods($domainContext['partenaire_id'] ?? null);
    if (!empty($allowedMethods) && !in_array($normalizedMethod, $allowedMethods, true)) {
        paydunyaRegistryInsertLog([
            'partenaire_id' => $domainContext['partenaire_id'] ?? null,
            'domaine_id' => $domainContext['domaine_id'] ?? null,
            'event_type' => 'payment_method_blocked',
            'severity' => 'warning',
            'message' => 'Méthode de paiement non autorisée pour ce partenaire.',
            'origin' => $requestOrigin,
            'ip' => $requestIp,
            'context' => [
                'payment_method' => $normalizedMethod,
                'allowed_methods' => $allowedMethods,
            ],
        ]);
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'Cette méthode de paiement n\'est pas autorisée pour ce partenaire.',
            'allowed_methods' => $allowedMethods,
        ], 403);
    }

    if (!empty($domainContext['require_https']) && !paydunyaIsHttpsRequest()) {
        paydunyaSimpleRespond([
            'success' => false,
            'message' => 'HTTPS est obligatoire pour ce domaine partenaire.',
        ], 403);
    }

    $baseUrl = paydunyaSimpleNormalizeBaseUrl(
        paydunyaRequestValue($request, ['base_url', 'baseUrl'], '')
    );

    $fallbackActions = [];
    if ($baseUrl !== '') {
        $fallbackActions = [
            'callback_url' => paydunyaSimpleJoinUrl($baseUrl, 'gestionDePaiement/callback.php'),
            'return_url' => paydunyaSimpleJoinUrl($baseUrl, 'gestionDePaiement/confirm_payment.php'),
            'cancel_url' => paydunyaSimpleJoinUrl($baseUrl, 'gestionDePaiement/confirm_payment.php?status=cancelled'),
        ];
    }

    $callbackCandidate = paydunyaRequestValue($request, ['callback_url', 'callbackUrl'], '');
    $returnCandidate = paydunyaRequestValue($request, ['return_url', 'returnUrl'], '');
    $cancelCandidate = paydunyaRequestValue($request, ['cancel_url', 'cancelUrl'], '');

    if (!paydunyaSimpleUrlHasPath($callbackCandidate) && !empty($domainContext['callback_url'])) {
        $callbackCandidate = $domainContext['callback_url'];
    }
    if (!paydunyaSimpleUrlHasPath($callbackCandidate) && !empty($fallbackActions['callback_url'])) {
        $callbackCandidate = $fallbackActions['callback_url'];
    }
    if (!paydunyaSimpleUrlHasPath($returnCandidate) && !empty($domainContext['return_url'])) {
        $returnCandidate = $domainContext['return_url'];
    }
    if (!paydunyaSimpleUrlHasPath($returnCandidate) && !empty($fallbackActions['return_url'])) {
        $returnCandidate = $fallbackActions['return_url'];
    }
    if (!paydunyaSimpleUrlHasPath($cancelCandidate) && !empty($domainContext['cancel_url'])) {
        $cancelCandidate = $domainContext['cancel_url'];
    }
    if (!paydunyaSimpleUrlHasPath($cancelCandidate) && !empty($fallbackActions['cancel_url'])) {
        $cancelCandidate = $fallbackActions['cancel_url'];
    }

    $actionUrls = paydunyaDefaultActions(array_filter([
        'callback_url' => $callbackCandidate,
        'return_url' => $returnCandidate,
        'cancel_url' => $cancelCandidate,
    ], function ($value) {
        return trim((string) $value) !== '';
    }));

    $extraData = array_merge($request, [
        'customer_name' => $customerName,
        'phone_number' => $phoneNumber,
        'payment_method' => $normalizedMethod,
        'amount' => $amount,
        'customer_email' => paydunyaRequestValue($request, ['customer_email', 'email'], 'client@sanarois.com'),
        'description' => paydunyaRequestValue($request, ['description'], 'Paiement initié via endpoint simplifié'),
        'callback_url' => $actionUrls['callback_url'] ?? '',
        'return_url' => $actionUrls['return_url'] ?? '',
        'cancel_url' => $actionUrls['cancel_url'] ?? '',
    ]);

    $transactionId = paydunyaRegistryInsertTransaction([
        'partenaire_id' => $domainContext['partenaire_id'] ?? null,
        'domaine_id' => $domainContext['domaine_id'] ?? null,
        'reference_client' => paydunyaRequestValue($request, ['partner_ref', 'reference_client'], ''),
        'customer_name' => $customerName,
        'phone_number' => $phoneNumber,
        'amount' => $amount,
        'payment_method' => $normalizedMethod,
        'request_origin' => $requestOrigin,
        'request_ip' => $requestIp,
        'base_url' => $baseUrl,
        'callback_url' => $actionUrls['callback_url'] ?? '',
        'return_url' => $actionUrls['return_url'] ?? '',
        'cancel_url' => $actionUrls['cancel_url'] ?? '',
        'status' => 'pending',
        'request_payload' => $extraData,
    ]);

    $result = processPaymentPayDunya(
        $phoneNumber,
        $customerName,
        $amount,
        $normalizedMethod,
        $actionUrls,
        $extraData
    );

    paydunyaRegistryUpdateTransaction($transactionId, [
        'status' => !empty($result['success']) ? 'success' : 'failed',
        'provider_token' => $result['data']['token'] ?? ($result['invoice']['token'] ?? null),
        'provider_payment_url' => $result['data']['url'] ?? ($result['data']['checkout_url'] ?? null),
        'provider_http_code' => $result['api_result']['http_code'] ?? null,
        'provider_message' => $result['message'] ?? null,
        'response_payload' => $result,
    ]);

    paydunyaRegistryInsertLog([
        'partenaire_id' => $domainContext['partenaire_id'] ?? null,
        'domaine_id' => $domainContext['domaine_id'] ?? null,
        'event_type' => !empty($result['success']) ? 'payment_success' : 'payment_failed',
        'severity' => !empty($result['success']) ? 'info' : 'error',
        'message' => $result['message'] ?? 'Paiement traité',
        'origin' => $requestOrigin,
        'ip' => $requestIp,
        'context' => [
            'transaction_id' => $transactionId,
            'payment_method' => $normalizedMethod,
            'amount' => $amount,
        ],
    ]);

    paydunyaSimpleRespond($result, !empty($result['success']) ? 200 : 422);
} catch (Throwable $e) {
    paydunyaWriteLog('Erreur non gérée simple_payment.php', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    paydunyaRegistryInsertLog([
        'event_type' => 'simple_payment_exception',
        'severity' => 'error',
        'message' => $e->getMessage(),
        'origin' => trim((string) ($_SERVER['HTTP_ORIGIN'] ?? '')),
        'ip' => paydunyaRegistryClientIp(),
        'context' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ],
    ]);

    paydunyaSimpleRespond([
        'success' => false,
        'message' => 'Erreur interne du serveur',
    ], 500);
}
