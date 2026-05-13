<?php
require_once __DIR__ . '/paydunya_service.php';

ini_set('display_errors', '0');
header('Content-Type: application/json');

function paydunyaPrivilegedRespond(array $payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function paydunyaGetAuthKey(array $request)
{
    if (!empty($request['auth_key'])) {
        return trim((string) $request['auth_key']);
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-paydunya-auth') {
            return trim((string) $value);
        }
    }

    return '';
}

function paydunyaAuthorizedForPrivilegedFlow(array $request)
{
    $config = paydunyaLoadConfig();
    $requireHttps = paydunyaConfigEnabled($config['PRIVILEGED_REQUIRE_HTTPS'] ?? '1', true);
    $allowedHosts = paydunyaAllowedHostsFromConfig($config['PRIVILEGED_ALLOWED_HOSTS'] ?? '');
    $allowedKeys = array_filter(array_map('trim', explode(',', (string) ($config['AUTH_KEYS'] ?? ''))));

    if ($requireHttps && !paydunyaIsHttpsRequest()) {
        return [
            'authorized' => false,
            'reason' => 'Connexion HTTPS obligatoire',
        ];
    }

    $requestHost = paydunyaRequestHost();
    if ($requestHost !== '' && !paydunyaHostIsAllowed($requestHost, $allowedHosts)) {
        return [
            'authorized' => false,
            'reason' => 'Host non autorisé',
        ];
    }

    if (empty($allowedKeys)) {
        return [
            'authorized' => false,
            'reason' => 'PAYDUNYA_AUTH_KEYS non configuré dans .env',
        ];
    }

    $submittedKey = paydunyaGetAuthKey($request);
    if ($submittedKey === '') {
        return [
            'authorized' => false,
            'reason' => 'Clé d\'autorisation manquante',
        ];
    }

    if (!in_array($submittedKey, $allowedKeys, true)) {
        return [
            'authorized' => false,
            'reason' => 'Clé d\'autorisation invalide',
        ];
    }

    return ['authorized' => true, 'reason' => 'OK'];
}

function paydunyaPrivilegedAllowedMethods()
{
    $config = paydunyaLoadConfig();
    $methods = array_filter(array_map('trim', explode(',', (string) ($config['PRIVILEGED_METHODS'] ?? ''))));
    $normalized = [];

    foreach ($methods as $method) {
        $canonical = paydunyaNormalizeMethod($method);
        if ($canonical !== '') {
            $normalized[] = $canonical;
        }
    }

    return array_values(array_unique($normalized));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        paydunyaPrivilegedRespond([
            'success' => false,
            'message' => 'Méthode non autorisée. Utilisez POST.',
        ], 405);
    }

    $request = paydunyaRequestData();
    $authCheck = paydunyaAuthorizedForPrivilegedFlow($request);

    if (!$authCheck['authorized']) {
        paydunyaPrivilegedRespond([
            'success' => false,
            'message' => 'Accès refusé: ' . $authCheck['reason'],
        ], 403);
    }

    $customerName = trim((string) paydunyaRequestValue($request, ['customer_name', 'client_name', 'name'], ''));
    $phoneNumber = trim((string) paydunyaRequestValue($request, ['phone_number', 'client_phone', 'phone'], ''));
    $amount = (int) paydunyaRequestValue($request, ['amount', 'total_amount'], 0);
    $paymentMethod = trim((string) paydunyaRequestValue($request, ['payment_method', 'method'], ''));
    $normalizedMethod = paydunyaNormalizeMethod($paymentMethod);

    if ($customerName === '' || $phoneNumber === '' || $amount <= 0 || $paymentMethod === '') {
        paydunyaPrivilegedRespond([
            'success' => false,
            'message' => 'Champs obligatoires manquants: customer_name, phone_number, amount, payment_method.',
        ], 422);
    }

    if ($normalizedMethod === '') {
        paydunyaPrivilegedRespond([
            'success' => false,
            'message' => 'Méthode de paiement invalide.',
        ], 422);
    }

    $allowedMethods = paydunyaPrivilegedAllowedMethods();
    if (!empty($allowedMethods) && !in_array($normalizedMethod, $allowedMethods, true)) {
        paydunyaPrivilegedRespond([
            'success' => false,
            'message' => 'Méthode non autorisée pour ce canal privilégié.',
        ], 403);
    }

    $actions = paydunyaDefaultActions(array_filter([
        'callback_url' => $request['callback_url'] ?? '',
        'return_url' => $request['return_url'] ?? '',
        'cancel_url' => $request['cancel_url'] ?? '',
    ]));

    $extraData = [
        'customer_email' => paydunyaRequestValue($request, ['customer_email', 'email'], 'client@sanarois.com'),
        'description' => paydunyaRequestValue($request, ['description'], 'Paiement via canal privilégié'),
        'items' => paydunyaParseArrayInput($request['items'] ?? []),
        'taxes' => paydunyaParseArrayInput($request['taxes'] ?? []),
        'custom_data' => paydunyaParseArrayInput($request['custom_data'] ?? []),
        'store_name' => paydunyaRequestValue($request, ['store_name'], 'Sanarois Fast-Food'),
    ];

    $result = processPaymentPayDunya(
        $phoneNumber,
        $customerName,
        $amount,
        $normalizedMethod,
        $actions,
        $extraData
    );

    paydunyaPrivilegedRespond($result, $result['success'] ? 200 : 422);
} catch (Throwable $e) {
    paydunyaWriteLog('Erreur non gérée privileged_payment', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    paydunyaPrivilegedRespond([
        'success' => false,
        'message' => 'Erreur interne du serveur',
    ], 500);
}
