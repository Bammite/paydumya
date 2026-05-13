<?php

define('PAYDUNYA_LOG_FILE', __DIR__ . '/debug_log.txt');

function paydunyaProjectRoot()
{
    return dirname(__DIR__, 2);
}

function paydunyaWriteLog($message, $data = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $entry = '[' . $timestamp . '] ' . $message;

    if ($data !== null) {
        $entry .= "\nDATA: " . print_r(paydunyaMaskSensitiveData($data), true);
    }

    $entry .= "\n-----------------------------------\n";
    $logDir = dirname(PAYDUNYA_LOG_FILE);
    if (!is_dir($logDir) || !is_writable($logDir)) {
        return;
    }

    if (is_file(PAYDUNYA_LOG_FILE) && !is_writable(PAYDUNYA_LOG_FILE)) {
        return;
    }

    @file_put_contents(PAYDUNYA_LOG_FILE, $entry, FILE_APPEND);
}

function writeLog($message, $data = null)
{
    paydunyaWriteLog($message, $data);
}

function paydunyaMaskSensitiveData($data)
{
    if (!is_array($data)) {
        return $data;
    }

    $sensitiveKeys = [
        'password',
        'card_number',
        'card_cvv',
        'PAYDUNYA-MASTER-KEY',
        'PAYDUNYA-PRIVATE-KEY',
        'PAYDUNYA-TOKEN',
        'MASTER_KEY',
        'PRIVATE_KEY',
        'TOKEN',
    ];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = paydunyaMaskSensitiveData($value);
            continue;
        }

        if (in_array($key, $sensitiveKeys, true)) {
            $data[$key] = '******';
        }
    }

    return $data;
}

function paydunyaLoadConfig()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // Chargement du fichier .env si présent
    $root = paydunyaProjectRoot();
    $autoload = $root . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dotenv\Dotenv')) {
            try {
                $envDirectories = array_unique([$root, dirname(__DIR__)]);
                foreach ($envDirectories as $envDirectory) {
                    if (!is_file(rtrim($envDirectory, '/') . '/.env')) {
                        continue;
                    }
                    $dotenv = Dotenv\Dotenv::createImmutable($envDirectory);
                    $dotenv->safeLoad();
                }
            } catch (Exception $e) {
                paydunyaWriteLog('Erreur lors du chargement de .env: ' . $e->getMessage());
            }
        }
    }

    $legacyConfigPath = __DIR__ . '/paydunya_config.php';
    $legacyConfig = [];

    if (is_file($legacyConfigPath)) {
        $legacyConfigRaw = require $legacyConfigPath;
        if (!is_array($legacyConfigRaw)) {
            paydunyaWriteLog('Configuration legacy PayDunya invalide (non-array).', [
                'path' => $legacyConfigPath,
                'type' => gettype($legacyConfigRaw),
            ]);
        } else {
            foreach ($legacyConfigRaw as $key => $value) {
                $legacyConfig[$key] = $value;
                // Normalisation : PAYDUNYA_MASTER_KEY -> MASTER_KEY
                $cleanKey = strtoupper($key);
                if (strpos($cleanKey, 'PAYDUNYA') === 0) {
                    $cleanKey = substr($cleanKey, 8);
                    $cleanKey = ltrim($cleanKey, '-_');
                }
                $cleanKey = str_replace('-', '_', $cleanKey);
                if (!empty($cleanKey) && !isset($legacyConfig[$cleanKey])) {
                    $legacyConfig[$cleanKey] = $value;
                }
            }
        }
    }

    $localConfigPath = __DIR__ . '/paydunya_config.local.php';
    $localConfig = [];

    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (!is_array($localConfig)) {
            paydunyaWriteLog('Configuration locale PayDunya invalide (non-array).', [
                'path' => $localConfigPath,
                'type' => gettype($localConfig),
            ]);
            $localConfig = [];
        }
    }

    $envConfig = [
        'MASTER_KEY' => $_ENV['PAYDUNYA_MASTER_KEY'] ?? getenv('PAYDUNYA_MASTER_KEY') ?: '',
        'PRIVATE_KEY' => $_ENV['PAYDUNYA_PRIVATE_KEY'] ?? getenv('PAYDUNYA_PRIVATE_KEY') ?: '',
        'TOKEN' => $_ENV['PAYDUNYA_TOKEN'] ?? getenv('PAYDUNYA_TOKEN') ?: '',
        'BASE_URL' => $_ENV['PAYDUNYA_BASE_URL'] ?? getenv('PAYDUNYA_BASE_URL') ?: '',
        'CHECKOUT_URL' => $_ENV['PAYDUNYA_CHECKOUT_ENDPOINT'] ?? getenv('PAYDUNYA_CHECKOUT_ENDPOINT') ?: '',
        'PUBLIC_BASE_URL' => $_ENV['PAYDUNYA_PUBLIC_BASE_URL'] ?? getenv('PAYDUNYA_PUBLIC_BASE_URL') ?: '',
        'CALLBACK_URL' => $_ENV['PAYDUNYA_CALLBACK_URL'] ?? getenv('PAYDUNYA_CALLBACK_URL') ?: '',
        'RETURN_URL' => $_ENV['PAYDUNYA_RETURN_URL'] ?? getenv('PAYDUNYA_RETURN_URL') ?: '',
        'CANCEL_URL' => $_ENV['PAYDUNYA_CANCEL_URL'] ?? getenv('PAYDUNYA_CANCEL_URL') ?: '',
        'AUTH_KEYS' => $_ENV['PAYDUNYA_AUTH_KEYS'] ?? getenv('PAYDUNYA_AUTH_KEYS') ?: '',
        'ALLOWED_HOSTS' => $_ENV['PAYDUNYA_ALLOWED_HOSTS'] ?? getenv('PAYDUNYA_ALLOWED_HOSTS') ?: '',
        'ENFORCE_HTTPS' => $_ENV['PAYDUNYA_REQUIRE_HTTPS'] ?? getenv('PAYDUNYA_REQUIRE_HTTPS') ?: '',
        'PRIVILEGED_ALLOWED_HOSTS' => $_ENV['PAYDUNYA_PRIVILEGED_ALLOWED_HOSTS'] ?? getenv('PAYDUNYA_PRIVILEGED_ALLOWED_HOSTS') ?: '',
        'PRIVILEGED_REQUIRE_HTTPS' => $_ENV['PAYDUNYA_PRIVILEGED_REQUIRE_HTTPS'] ?? getenv('PAYDUNYA_PRIVILEGED_REQUIRE_HTTPS') ?: '',
        'PRIVILEGED_METHODS' => $_ENV['PAYDUNYA_PRIVILEGED_METHODS'] ?? getenv('PAYDUNYA_PRIVILEGED_METHODS') ?: '',
        'CORS_ORIGINS' => $_ENV['PAYDUNYA_CORS_ORIGINS'] ?? getenv('PAYDUNYA_CORS_ORIGINS') ?: '',
        'STORE_NAME' => $_ENV['PAYDUNYA_STORE_NAME'] ?? getenv('PAYDUNYA_STORE_NAME') ?: '',
        'STORE_TAGLINE' => $_ENV['PAYDUNYA_STORE_TAGLINE'] ?? getenv('PAYDUNYA_STORE_TAGLINE') ?: '',
        'STORE_PHONE' => $_ENV['PAYDUNYA_STORE_PHONE'] ?? getenv('PAYDUNYA_STORE_PHONE') ?: '',
        'STORE_WEBSITE_URL' => $_ENV['PAYDUNYA_STORE_WEBSITE_URL'] ?? getenv('PAYDUNYA_STORE_WEBSITE_URL') ?: '',
        'STORE_LOGO_URL' => $_ENV['PAYDUNYA_STORE_LOGO_URL'] ?? getenv('PAYDUNYA_STORE_LOGO_URL') ?: '',
    ];

    $filterStrings = function ($value) {
        return is_string($value) && trim($value) !== '';
    };

    $config = array_merge(
        [
            'MASTER_KEY' => '',
            'PRIVATE_KEY' => '',
            'TOKEN' => '',
            'BASE_URL' => 'https://app.paydunya.com/api/v1',
            'CHECKOUT_URL' => 'https://app.paydunya.com/api/v1/checkout-invoice/create',
            'PUBLIC_BASE_URL' => 'https://pay.bammite.com/paydumya',
            'CALLBACK_URL' => '',
            'RETURN_URL' => '',
            'CANCEL_URL' => '',
            'AUTH_KEYS' => '',
            'ALLOWED_HOSTS' => 'sanarois.com,www.sanarois.com,pay.bammite.com,www.pay.bammite.com',
            'ENFORCE_HTTPS' => '1',
            'PRIVILEGED_ALLOWED_HOSTS' => 'sanarois.com,www.sanarois.com,pay.bammite.com,www.pay.bammite.com',
            'PRIVILEGED_REQUIRE_HTTPS' => '1',
            'PRIVILEGED_METHODS' => '',
            'CORS_ORIGINS' => '',
            'STORE_NAME' => 'Sanarois Fast-Food',
            'STORE_TAGLINE' => '',
            'STORE_PHONE' => '',
            'STORE_WEBSITE_URL' => '',
            'STORE_LOGO_URL' => '',
        ],
        array_filter($legacyConfig, $filterStrings),
        array_filter($localConfig, $filterStrings),
        array_filter($envConfig, $filterStrings)
    );

    paydunyaWriteLog('Configuration PayDunya chargée', [
        'MASTER_KEY' => !empty($config['MASTER_KEY']) ? 'OK' : 'MANQUANT',
        'PRIVATE_KEY' => !empty($config['PRIVATE_KEY']) ? 'OK' : 'MANQUANT',
        'TOKEN' => !empty($config['TOKEN']) ? 'OK' : 'MANQUANT',
        'BASE_URL' => $config['BASE_URL'] ?? '',
        'CHECKOUT_URL' => $config['CHECKOUT_URL'] ?? '',
        'PUBLIC_BASE_URL' => $config['PUBLIC_BASE_URL'] ?? '',
        'CALLBACK_URL' => $config['CALLBACK_URL'] ?? '',
        'RETURN_URL' => $config['RETURN_URL'] ?? '',
        'CANCEL_URL' => $config['CANCEL_URL'] ?? '',
    ]);

    return $config;
}

function paydunyaConfigEnabled($value, $default = false)
{
    if ($value === null || $value === '') {
        return (bool) $default;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function paydunyaAllowedHostsFromConfig($value, $fallback = 'sanarois.com,www.sanarois.com,pay.bammite.com,www.pay.bammite.com')
{
    $source = trim((string) $value);
    if ($source === '') {
        $source = $fallback;
    }

    $hosts = array_filter(array_map(function ($host) {
        return strtolower(trim($host));
    }, explode(',', $source)));

    return array_values(array_unique($hosts));
}

function paydunyaHostIsAllowed($host, array $allowedHosts)
{
    $normalizedHost = strtolower(trim((string) $host));
    if ($normalizedHost === '') {
        return false;
    }

    foreach ($allowedHosts as $allowedHost) {
        if ($normalizedHost === $allowedHost) {
            return true;
        }
    }

    return false;
}

function paydunyaUrlIsAllowed($url, array $allowedHosts, $requireHttps = true)
{
    $trimmedUrl = trim((string) $url);
    if ($trimmedUrl === '') {
        return false;
    }

    $parts = parse_url($trimmedUrl);
    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
        return false;
    }

    $scheme = strtolower((string) $parts['scheme']);
    if ($requireHttps && $scheme !== 'https') {
        return false;
    }

    return paydunyaHostIsAllowed((string) $parts['host'], $allowedHosts);
}

function paydunyaRequestHost()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST']);
        if (!empty($forwarded[0])) {
            $host = strtolower(trim($forwarded[0]));
            return preg_replace('/:\d+$/', '', $host);
        }
    }

    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    return preg_replace('/:\d+$/', '', $host);
}

function paydunyaIsHttpsRequest()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower(trim((string) $_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https';
    }

    if (!empty($_SERVER['REQUEST_SCHEME'])) {
        return strtolower(trim((string) $_SERVER['REQUEST_SCHEME'])) === 'https';
    }

    return false;
}

function paydunyaCurrentBaseUrl()
{
    $config = paydunyaLoadConfig();
    if (!empty($config['PUBLIC_BASE_URL'])) {
        return rtrim($config['PUBLIC_BASE_URL'], '/');
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . ($basePath !== '' ? $basePath : '');
    }

    return 'https://sanarois.com/paydumya';
}

function paydunyaApiHeaders()
{
    $config = paydunyaLoadConfig();

    return [
        'Content-Type: application/json',
        'User-Agent: PayDunya-PHP-SDK-Custom/1.0',
        'PAYDUNYA-MASTER-KEY: ' . $config['MASTER_KEY'],
        'PAYDUNYA-PRIVATE-KEY: ' . $config['PRIVATE_KEY'],
        'PAYDUNYA-TOKEN: ' . $config['TOKEN'],
    ];
}

function paydunyaApiBaseUrl()
{
    $config = paydunyaLoadConfig();
    $baseUrl = $config['BASE_URL'] ?? 'https://app.paydunya.com/api/v1';
    return rtrim($baseUrl, '/');
}

function paydunyaMethodDefinitions()
{
    $baseUrl = paydunyaApiBaseUrl();

    return [
        'orange_money' => [
            'aliases' => ['orange money', 'orange_money', 'orange_money_sn', 'om_sn'],
            'endpoint' => $baseUrl . '/softpay/new-orange-money-senegal',
        ],
        'wave' => [
            'aliases' => ['wave', 'wave_sn', 'wave_senegal'],
            'endpoint' => $baseUrl . '/softpay/wave-senegal',
        ],
        'free_money' => [
            'aliases' => ['free money', 'free_money', 'free_money_sn'],
            'endpoint' => $baseUrl . '/softpay/free-money-senegal',
        ],
        'expresso' => [
            'aliases' => ['expresso', 'expresso_sn', 'expresso_senegal'],
            'endpoint' => $baseUrl . '/softpay/expresso-senegal',
        ],
        'wizall' => [
            'aliases' => ['wizall', 'wizall_sn', 'wizall_money_senegal'],
            'endpoint' => $baseUrl . '/softpay/wizall-money-senegal',
        ],
        'wizall_confirm' => [
            'aliases' => ['wizall_confirm', 'wizall-confirm'],
            'endpoint' => $baseUrl . '/softpay/wizall-money-senegal/confirm',
        ],
        'card' => [
            'aliases' => ['card', 'carte', 'carte_bancaire', 'bank_card'],
            'endpoint' => $baseUrl . '/softpay/card',
        ],
        'orange_money_ci' => [
            'aliases' => ['orange_money_ci', 'orange money ci', 'om_ci'],
            'endpoint' => $baseUrl . '/softpay/orange-money-ci',
        ],
        'mtn_ci' => [
            'aliases' => ['mtn_ci', 'mtn money ci'],
            'endpoint' => $baseUrl . '/softpay/mtn-ci',
        ],
        'moov_ci' => [
            'aliases' => ['moov_ci', 'moov ci'],
            'endpoint' => $baseUrl . '/softpay/moov-ci',
        ],
        'wave_ci' => [
            'aliases' => ['wave_ci', 'wave ci'],
            'endpoint' => $baseUrl . '/softpay/wave-ci',
        ],
        'orange_money_burkina' => [
            'aliases' => ['orange_money_burkina', 'orange money burkina', 'om_bf'],
            'endpoint' => $baseUrl . '/softpay/orange-money-burkina',
        ],
        'moov_burkina' => [
            'aliases' => ['moov_burkina', 'moov burkina'],
            'endpoint' => $baseUrl . '/softpay/moov-burkina',
        ],
        'moov_benin' => [
            'aliases' => ['moov_benin', 'moov benin'],
            'endpoint' => $baseUrl . '/softpay/moov-benin',
        ],
        'mtn_benin' => [
            'aliases' => ['mtn_benin', 'mtn benin'],
            'endpoint' => $baseUrl . '/softpay/mtn-benin',
        ],
        't_money_togo' => [
            'aliases' => ['t_money_togo', 't-money', 't money togo'],
            'endpoint' => $baseUrl . '/softpay/t-money-togo',
        ],
        'moov_togo' => [
            'aliases' => ['moov_togo', 'moov togo'],
            'endpoint' => $baseUrl . '/softpay/moov-togo',
        ],
        'orange_money_mali' => [
            'aliases' => ['orange_money_mali', 'orange money mali'],
            'endpoint' => $baseUrl . '/softpay/orange-money-mali',
        ],
        'moov_mali' => [
            'aliases' => ['moov_mali', 'moov mali'],
            'endpoint' => $baseUrl . '/softpay/moov-mali',
        ],
        'mtn_cameroun' => [
            'aliases' => ['mtn_cameroun', 'mtn cameroun'],
            'endpoint' => $baseUrl . '/softpay/mtn-cameroun',
        ],
        'paydunya_account' => [
            'aliases' => ['paydunya', 'paydunya_account'],
            'endpoint' => $baseUrl . '/softpay/paydunya',
        ],
    ];
}

function paydunyaNormalizeMethod($method)
{
    $candidate = strtolower(trim((string) $method));

    foreach (paydunyaMethodDefinitions() as $canonical => $definition) {
        if ($candidate === $canonical || in_array($candidate, $definition['aliases'], true)) {
            return $canonical;
        }
    }

    return '';
}

function paydunyaParseArrayInput($value)
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value)) {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function paydunyaRequestData()
{
    static $request = null;

    if ($request !== null) {
        return $request;
    }

    $request = $_POST;
    $rawBody = file_get_contents('php://input');

    if (!empty($rawBody)) {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $request = array_merge($request, $decoded);
        }
    }

    return $request;
}

function paydunyaRequestValue(array $data, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') {
            return $data[$key];
        }
    }

    return $default;
}

function paydunyaCleanPhone($phone)
{
    return preg_replace('/\D+/', '', (string) $phone);
}

function paydunyaNormalizeSnPhone($phone)
{
    $digits = paydunyaCleanPhone($phone);

    if (strlen($digits) === 12 && strpos($digits, '221') === 0) {
        $digits = substr($digits, 3);
    }

    if (strlen($digits) !== 9) {
        return '';
    }

    if (!preg_match('/^7[0-9]{8}$/', $digits)) {
        return '';
    }

    return $digits;
}

function paydunyaDefaultActions(array $overrides = [])
{
    $config = paydunyaLoadConfig();
    $baseUrl = paydunyaCurrentBaseUrl();
    $allowedHosts = paydunyaAllowedHostsFromConfig($config['ALLOWED_HOSTS'] ?? '');
    $requireHttps = paydunyaConfigEnabled($config['ENFORCE_HTTPS'] ?? '1', true);

    $defaultActions = [
        'callback_url' => $config['CALLBACK_URL'] ?: ($baseUrl . '/gestionDePaiement/callback.php'),
        'return_url' => $config['RETURN_URL'] ?: ($baseUrl . '/gestionDePaiement/confirm_payment.php'),
        'cancel_url' => $config['CANCEL_URL'] ?: ($baseUrl . '/gestionDePaiement/confirm_payment.php?status=cancelled'),
    ];

    $resolvedActions = $defaultActions;

    foreach (['callback_url', 'return_url', 'cancel_url'] as $key) {
        if (!isset($overrides[$key])) {
            continue;
        }

        $candidate = trim((string) $overrides[$key]);
        if ($candidate === '') {
            continue;
        }

        if (!paydunyaUrlIsAllowed($candidate, $allowedHosts, $requireHttps)) {
            paydunyaWriteLog('URL d\'action refusée (hors domaine autorisé ou non HTTPS).', [
                'key' => $key,
                'value' => $candidate,
            ]);
            continue;
        }

        $resolvedActions[$key] = $candidate;
    }

    return $resolvedActions;
}

function paydunyaBuildStoreData(array $data)
{
    $config = paydunyaLoadConfig();
    $store = paydunyaParseArrayInput($data['store'] ?? []);

    return array_filter([
        'name' => paydunyaRequestValue($store, ['name'], $data['store_name'] ?? $config['STORE_NAME']),
        'tagline' => paydunyaRequestValue($store, ['tagline'], $data['store_tagline'] ?? $config['STORE_TAGLINE']),
        'postal_address' => paydunyaRequestValue($store, ['postal_address'], $data['store_postal_address'] ?? ''),
        'phone' => paydunyaRequestValue($store, ['phone'], $data['store_phone'] ?? $config['STORE_PHONE']),
        'logo_url' => paydunyaRequestValue($store, ['logo_url'], $data['store_logo_url'] ?? $config['STORE_LOGO_URL']),
        'website_url' => paydunyaRequestValue($store, ['website_url'], $data['store_website_url'] ?? $config['STORE_WEBSITE_URL']),
    ], function ($value) {
        return $value !== '';
    });
}

function paydunyaApiRequest($url, array $payload)
{
    $headers = paydunyaApiHeaders();

    paydunyaWriteLog('Appel API PayDunya', [
        'url' => $url,
        'payload' => $payload,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $rawResponse = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        paydunyaWriteLog('Erreur cURL PayDunya', ['url' => $url, 'error' => $error]);

        return [
            'success' => false,
            'http_code' => 0,
            'message' => $error,
            'response' => null,
            'raw_response' => '',
        ];
    }

    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    $fallbackMessage = '';

    if (!is_array($decoded)) {
        $trimmed = ltrim((string) $rawResponse);
        $isHtml = stripos($contentType, 'text/html') !== false
            || stripos($trimmed, '<!doctype html') === 0
            || stripos($trimmed, '<html') === 0;

        if ($isHtml && $httpCode >= 500) {
            $fallbackMessage = 'Le serveur PayDunya a retourné une erreur HTML (HTTP ' . $httpCode . '). Vérifiez endpoint, clés API et mode (test/live).';
        } elseif ($isHtml) {
            $fallbackMessage = 'Réponse HTML inattendue reçue depuis l\'API de paiement.';
        }
    }

    paydunyaWriteLog('Réponse API PayDunya', [
        'url' => $url,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response' => $decoded ?? $rawResponse,
    ]);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'message' => is_array($decoded)
            ? ($decoded['message'] ?? $decoded['description'] ?? $decoded['response_text'] ?? '')
            : $fallbackMessage,
        'url' => $url,
        'content_type' => $contentType,
        'response' => $decoded,
        'raw_response' => $rawResponse,
    ];
}

function paydunyaCreateInvoice(array $data)
{
    $amount = (int) paydunyaRequestValue($data, ['amount', 'total_amount'], 0);
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Le montant total est obligatoire.'];
    }

    $items = paydunyaParseArrayInput($data['items'] ?? []);
    $taxes = paydunyaParseArrayInput($data['taxes'] ?? []);
    $customData = paydunyaParseArrayInput($data['custom_data'] ?? []);
    $actions = paydunyaParseArrayInput($data['actions'] ?? []);

    $actions = paydunyaDefaultActions(array_filter([
        'callback_url' => paydunyaRequestValue($actions, ['callback_url'], $data['callback_url'] ?? ''),
        'return_url' => paydunyaRequestValue($actions, ['return_url'], $data['return_url'] ?? ''),
        'cancel_url' => paydunyaRequestValue($actions, ['cancel_url'], $data['cancel_url'] ?? ''),
    ]));

    $invoice = [
        'total_amount' => $amount,
        'description' => paydunyaRequestValue($data, ['description'], 'Paiement via PayDunya'),
    ];

    if (!empty($items)) {
        $invoice['items'] = $items;
    }

    if (!empty($taxes)) {
        $invoice['taxes'] = $taxes;
    }

    $payload = [
        'invoice' => $invoice,
        'store' => paydunyaBuildStoreData($data),
        'actions' => $actions,
    ];

    if (!empty($customData)) {
        $payload['custom_data'] = $customData;
    }

    $apiResult = paydunyaApiRequest(paydunyaLoadConfig()['CHECKOUT_URL'], $payload);

    if (!$apiResult['success'] || empty($apiResult['response']['token'])) {
        return [
            'success' => false,
            'message' => $apiResult['message'] ?: 'Impossible de créer la facture PayDunya.',
            'api_result' => $apiResult,
        ];
    }

    return [
        'success' => true,
        'message' => 'Facture créée avec succès.',
        'token' => $apiResult['response']['token'],
        'checkout_url' => $apiResult['response']['response_text'] ?? '',
        'invoice_response' => $apiResult['response'],
    ];
}

function paydunyaBuildPaymentPayload($method, array $data)
{
    $token = paydunyaRequestValue($data, ['invoice_token', 'payment_token', 'token'], '');
    $name = trim((string) paydunyaRequestValue($data, ['customer_name', 'full_name', 'name'], 'Client'));
    $email = trim((string) paydunyaRequestValue($data, ['customer_email', 'email'], 'client@example.com'));
    $phone = paydunyaCleanPhone(paydunyaRequestValue($data, ['phone_number', 'phone'], ''));
    $snPhone = paydunyaNormalizeSnPhone($phone);
    $amount = (int) paydunyaRequestValue($data, ['amount', 'total_amount'], 0);
    $address = trim((string) paydunyaRequestValue($data, ['customer_address', 'address'], ''));
    $walletProvider = trim((string) paydunyaRequestValue($data, ['wallet_provider'], ''));
    $otpCode = trim((string) paydunyaRequestValue($data, ['otp_code'], ''));
    $password = trim((string) paydunyaRequestValue($data, ['password'], ''));

    switch ($method) {
        case 'wave':
            return [
                'wave_senegal_fullName' => $name,
                'wave_senegal_email' => $email,
                'wave_senegal_phone' => $snPhone,
                'wave_senegal_payment_token' => $token,
                'total_amount' => $amount,
            ];

        case 'orange_money':
            return [
                'customer_name' => $name,
                'customer_email' => $email,
                'phone_number' => $snPhone,
                'invoice_token' => $token,
                'total_amount' => $amount,
            ];

        case 'free_money':
            return [
                'customer_name' => $name,
                'customer_email' => $email,
                'phone_number' => $snPhone,
                'payment_token' => $token,
                'total_amount' => $amount,
            ];

        case 'expresso':
            return [
                'expresso_sn_fullName' => $name,
                'expresso_sn_email' => $email,
                'expresso_sn_phone' => $snPhone,
                'payment_token' => $token,
                'total_amount' => $amount,
            ];

        case 'wizall':
            return [
                'customer_name' => $name,
                'customer_email' => $email,
                'phone_number' => $snPhone,
                'invoice_token' => $token,
                'total_amount' => $amount,
            ];

        case 'wizall_confirm':
            return [
                'authorization_code' => paydunyaRequestValue($data, ['authorization_code'], ''),
                'phone_number' => $phone,
                'transaction_id' => paydunyaRequestValue($data, ['transaction_id'], ''),
            ];

        case 'card':
            return [
                'full_name' => $name,
                'email' => $email,
                'card_number' => preg_replace('/\s+/', '', paydunyaRequestValue($data, ['card_number'], '')),
                'card_cvv' => paydunyaRequestValue($data, ['card_cvv'], ''),
                'card_expired_date_year' => paydunyaRequestValue($data, ['card_expired_date_year'], ''),
                'card_expired_date_month' => paydunyaRequestValue($data, ['card_expired_date_month'], ''),
                'token' => $token,
            ];

        case 'orange_money_ci':
            return [
                'orange_money_ci_customer_fullname' => $name,
                'orange_money_ci_email' => $email,
                'orange_money_ci_phone_number' => $phone,
                'orange_money_ci_otp' => $otpCode,
                'payment_token' => $token,
            ];

        case 'mtn_ci':
            return [
                'mtn_ci_customer_fullname' => $name,
                'mtn_ci_email' => $email,
                'mtn_ci_phone_number' => $phone,
                'mtn_ci_wallet_provider' => $walletProvider ?: 'MTNCI',
                'payment_token' => $token,
            ];

        case 'moov_ci':
            return [
                'moov_ci_customer_fullname' => $name,
                'moov_ci_email' => $email,
                'moov_ci_phone_number' => $phone,
                'payment_token' => $token,
            ];

        case 'wave_ci':
            return [
                'wave_ci_fullName' => $name,
                'wave_ci_email' => $email,
                'wave_ci_phone' => $phone,
                'wave_ci_payment_token' => $token,
            ];

        case 'orange_money_burkina':
            return [
                'name_bf' => $name,
                'email_bf' => $email,
                'phone_bf' => $phone,
                'otp_code' => $otpCode,
                'payment_token' => $token,
            ];

        case 'moov_burkina':
            return [
                'moov_burkina_faso_fullName' => $name,
                'moov_burkina_faso_email' => $email,
                'moov_burkina_faso_phone_number' => $phone,
                'moov_burkina_faso_payment_token' => $token,
            ];

        case 'moov_benin':
            return [
                'moov_benin_customer_fullname' => $name,
                'moov_benin_email' => $email,
                'moov_benin_phone_number' => $phone,
                'payment_token' => $token,
            ];

        case 'mtn_benin':
            return [
                'mtn_benin_customer_fullname' => $name,
                'mtn_benin_email' => $email,
                'mtn_benin_phone_number' => $phone,
                'mtn_benin_wallet_provider' => $walletProvider ?: 'MTNBENIN',
                'payment_token' => $token,
            ];

        case 't_money_togo':
            return [
                'name_t_money' => $name,
                'email_t_money' => $email,
                'phone_t_money' => $phone,
                'payment_token' => $token,
            ];

        case 'moov_togo':
            return [
                'moov_togo_customer_fullname' => $name,
                'moov_togo_email' => $email,
                'moov_togo_customer_address' => $address,
                'moov_togo_phone_number' => $phone,
                'payment_token' => $token,
            ];

        case 'orange_money_mali':
            return [
                'orange_money_mali_customer_fullname' => $name,
                'orange_money_mali_email' => $email,
                'orange_money_mali_phone_number' => $phone,
                'orange_money_mali_customer_address' => $address,
                'payment_token' => $token,
            ];

        case 'moov_mali':
            return [
                'moov_ml_customer_fullname' => $name,
                'moov_ml_email' => $email,
                'moov_ml_phone_number' => $phone,
                'moov_ml_customer_address' => $address,
                'payment_token' => $token,
            ];

        case 'mtn_cameroun':
            return [
                'mtn_cameroun_customer_fullname' => $name,
                'mtn_cameroun_email' => $email,
                'mtn_cameroun_phone_number' => $phone,
                'mtn_cameroun_wallet_provider' => $walletProvider ?: 'MTNCAMEROUN',
                'payment_token' => $token,
            ];

        case 'paydunya_account':
            return [
                'customer_name' => $name,
                'customer_email' => $email,
                'phone_phone' => $phone,
                'password' => $password,
                'invoice_token' => $token,
            ];
    }

    return [];
}

function paydunyaValidatePaymentPayload($method, array $payload)
{
    $requiredByMethod = [
        'wave' => ['wave_senegal_fullName', 'wave_senegal_email', 'wave_senegal_phone', 'wave_senegal_payment_token'],
        'orange_money' => ['customer_name', 'customer_email', 'phone_number', 'invoice_token'],
        'free_money' => ['customer_name', 'customer_email', 'phone_number', 'payment_token'],
        'expresso' => ['expresso_sn_fullName', 'expresso_sn_email', 'expresso_sn_phone', 'payment_token'],
        'wizall' => ['customer_name', 'customer_email', 'phone_number', 'invoice_token'],
        'wizall_confirm' => ['authorization_code', 'phone_number', 'transaction_id'],
        'card' => ['full_name', 'email', 'card_number', 'card_cvv', 'card_expired_date_year', 'card_expired_date_month', 'token'],
        'orange_money_ci' => ['orange_money_ci_customer_fullname', 'orange_money_ci_email', 'orange_money_ci_phone_number', 'orange_money_ci_otp', 'payment_token'],
        'mtn_ci' => ['mtn_ci_customer_fullname', 'mtn_ci_email', 'mtn_ci_phone_number', 'mtn_ci_wallet_provider', 'payment_token'],
        'moov_ci' => ['moov_ci_customer_fullname', 'moov_ci_email', 'moov_ci_phone_number', 'payment_token'],
        'wave_ci' => ['wave_ci_fullName', 'wave_ci_email', 'wave_ci_phone', 'wave_ci_payment_token'],
        'orange_money_burkina' => ['name_bf', 'email_bf', 'phone_bf', 'otp_code', 'payment_token'],
        'moov_burkina' => ['moov_burkina_faso_fullName', 'moov_burkina_faso_email', 'moov_burkina_faso_phone_number', 'moov_burkina_faso_payment_token'],
        'moov_benin' => ['moov_benin_customer_fullname', 'moov_benin_email', 'moov_benin_phone_number', 'payment_token'],
        'mtn_benin' => ['mtn_benin_customer_fullname', 'mtn_benin_email', 'mtn_benin_phone_number', 'mtn_benin_wallet_provider', 'payment_token'],
        't_money_togo' => ['name_t_money', 'email_t_money', 'phone_t_money', 'payment_token'],
        'moov_togo' => ['moov_togo_customer_fullname', 'moov_togo_email', 'moov_togo_customer_address', 'moov_togo_phone_number', 'payment_token'],
        'orange_money_mali' => ['orange_money_mali_customer_fullname', 'orange_money_mali_email', 'orange_money_mali_phone_number', 'orange_money_mali_customer_address', 'payment_token'],
        'moov_mali' => ['moov_ml_customer_fullname', 'moov_ml_email', 'moov_ml_phone_number', 'moov_ml_customer_address', 'payment_token'],
        'mtn_cameroun' => ['mtn_cameroun_customer_fullname', 'mtn_cameroun_email', 'mtn_cameroun_phone_number', 'mtn_cameroun_wallet_provider', 'payment_token'],
        'paydunya_account' => ['customer_name', 'customer_email', 'phone_phone', 'password', 'invoice_token'],
    ];

    $requiredFields = $requiredByMethod[$method] ?? [];
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($payload[$field]) || $payload[$field] === '') {
            $missing[] = $field;
        }
    }

    return $missing;
}

function paydunyaIsTransientWaveError($method, array $apiResult)
{
    if ($method !== 'wave') {
        return false;
    }

    $message = strtolower((string) ($apiResult['message'] ?? ''));
    $responseMessage = strtolower((string) (($apiResult['response']['message'] ?? '')));

    $needleList = [
        'une erreur est survenue au niveau du serveur',
        'veuillez réssayer plus tard',
        'veuillez reessayer plus tard',
    ];

    foreach ($needleList as $needle) {
        if (strpos($message, $needle) !== false || strpos($responseMessage, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function paydunyaIsAlreadyInitiatedError(array $paymentResult)
{
    $message = strtolower((string) ($paymentResult['message'] ?? ''));
    return strpos($message, 'deja ete initie') !== false
        || strpos($message, 'déjà été initié') !== false
        || strpos($message, 'a dejà été initié') !== false;
}

function paydunyaInitiatePayment(array $data)
{
    $method = paydunyaNormalizeMethod(paydunyaRequestValue($data, ['payment_method', 'method'], ''));
    if ($method === '') {
        return ['success' => false, 'message' => 'La méthode de paiement PayDunya est invalide ou absente.'];
    }

    $definitions = paydunyaMethodDefinitions();
    $payload = paydunyaBuildPaymentPayload($method, $data);
    $missingFields = paydunyaValidatePaymentPayload($method, $payload);

    if (!empty($missingFields)) {
        return [
            'success' => false,
            'message' => 'Des champs obligatoires sont manquants pour ' . $method . ': ' . implode(', ', $missingFields),
        ];
    }

    $apiResult = paydunyaApiRequest($definitions[$method]['endpoint'], $payload);

    if (paydunyaIsTransientWaveError($method, $apiResult)) {
        paydunyaWriteLog('Wave direct a retourne une erreur transitoire, retry automatique', [
            'http_code' => $apiResult['http_code'] ?? 0,
            'message' => $apiResult['message'] ?? '',
        ]);
        usleep(500000);
        $apiResult = paydunyaApiRequest($definitions[$method]['endpoint'], $payload);
    }

    if (!$apiResult['success'] || !is_array($apiResult['response'])) {
        return [
            'success' => false,
            'message' => $apiResult['message'] ?: 'Le lancement du paiement a échoué.',
            'api_result' => $apiResult,
        ];
    }

    return [
        'success' => !empty($apiResult['response']['success']),
        'message' => $apiResult['response']['message'] ?? 'Réponse PayDunya reçue.',
        'payment_method' => $method,
        'payment_response' => $apiResult['response'],
        'payment_url' => $apiResult['response']['url'] ?? '',
        'other_url' => $apiResult['response']['other_url'] ?? [],
    ];
}

function processPaymentPayDunya($phoneNumber, $customerName, $amount, $paymentMethod, $actionUrls = [], $extraData = [])
{
    $request = array_merge($extraData, [
        'phone_number' => $phoneNumber,
        'customer_name' => $customerName,
        'amount' => (int) $amount,
        'payment_method' => $paymentMethod,
        'actions' => $actionUrls,
        'callback_url' => $actionUrls['callback_url'] ?? '',
        'return_url' => $actionUrls['return_url'] ?? '',
        'cancel_url' => $actionUrls['cancel_url'] ?? '',
    ]);

    $invoice = paydunyaCreateInvoice($request);
    if (!$invoice['success']) {
        return $invoice;
    }

    $payment = paydunyaInitiatePayment(array_merge($request, [
        'invoice_token' => $invoice['token'],
        'payment_token' => $invoice['token'],
        'token' => $invoice['token'],
    ]));

    if (!$payment['success']) {
        $normalizedMethod = paydunyaNormalizeMethod($paymentMethod);
        $paymentMessage = strtolower((string) ($payment['message'] ?? ''));
        $isWaveServerError = $normalizedMethod === 'wave'
            && ($paymentMessage === 'une erreur est survenue au niveau du serveur'
                || strpos($paymentMessage, 'veuillez réssayer plus tard') !== false
                || strpos($paymentMessage, 'veuillez reessayer plus tard') !== false);
        $isAlreadyInitiated = paydunyaIsAlreadyInitiatedError($payment);

        if ($isAlreadyInitiated && !empty($invoice['checkout_url'])) {
            return [
                'success' => true,
                'message' => 'Paiement deja initie. Utilisez la page checkout pour finaliser.',
                'data' => [
                    'token' => $invoice['token'],
                    'checkout_url' => $invoice['checkout_url'],
                    'invoice' => $invoice['invoice_response'],
                    'payment' => $payment['payment_response'] ?? [],
                    'url' => $invoice['checkout_url'],
                    'other_url' => [],
                    'phone_number' => $phoneNumber,
                    'customer_name' => $customerName,
                    'amount' => (int) $amount,
                    'payment_method' => $normalizedMethod,
                    'fallback' => true,
                    'reason' => 'already_initiated',
                ],
            ];
        }

        // Fallback de sécurité: on laisse continuer via la page checkout de facture.
        if ($isWaveServerError && !empty($invoice['checkout_url'])) {
            return [
                'success' => true,
                'message' => 'Wave indisponible pour le lancement direct. Redirection vers la page de checkout PayDunya.',
                'data' => [
                    'token' => $invoice['token'],
                    'checkout_url' => $invoice['checkout_url'],
                    'invoice' => $invoice['invoice_response'],
                    'payment' => $payment['payment_response'] ?? [],
                    'url' => $invoice['checkout_url'],
                    'other_url' => [],
                    'phone_number' => $phoneNumber,
                    'customer_name' => $customerName,
                    'amount' => (int) $amount,
                    'payment_method' => $normalizedMethod,
                    'fallback' => true,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => $payment['message'],
            'invoice' => $invoice,
            'payment' => $payment,
        ];
    }

    return [
        'success' => true,
        'message' => $payment['message'],
        'data' => [
            'token' => $invoice['token'],
            'checkout_url' => $invoice['checkout_url'],
            'invoice' => $invoice['invoice_response'],
            'payment' => $payment['payment_response'],
            'url' => $payment['payment_url'],
            'other_url' => $payment['other_url'],
            'phone_number' => $phoneNumber,
            'customer_name' => $customerName,
            'amount' => (int) $amount,
            'payment_method' => paydunyaNormalizeMethod($paymentMethod),
        ],
    ];
}

function paydunyaReadCallbackPayload()
{
    if (isset($_POST['data'])) {
        if (is_array($_POST['data'])) {
            return $_POST['data'];
        }

        $decodedData = json_decode($_POST['data'], true);
        if (is_array($decodedData)) {
            return $decodedData;
        }
    }

    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        $decoded = json_decode($rawBody, true);
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function paydunyaVerifyCallbackHash(array $callbackData)
{
    $config = paydunyaLoadConfig();
    $receivedHash = (string) ($callbackData['hash'] ?? '');
    $expectedHash = hash('sha512', (string) $config['MASTER_KEY']);

    return $receivedHash !== '' && hash_equals($expectedHash, $receivedHash);
}

function paydunyaFindPendingOrder(PDO $connexion, $token = null, $orderCode = null)
{
    if ($token) {
        $stmt = $connexion->prepare('SELECT * FROM commandes_en_attente WHERE token_paiement = ? LIMIT 1');
        $stmt->execute([$token]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            return $found;
        }
    }

    if ($orderCode) {
        $stmt = $connexion->prepare('SELECT * FROM commandes_en_attente WHERE code_commande = ? LIMIT 1');
        $stmt->execute([$orderCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return false;
}

function paydunyaUpdatePendingStatus(PDO $connexion, $token, $status, array $callbackData = [])
{
    $stmt = $connexion->prepare(
        'UPDATE commandes_en_attente
         SET payment_status = :status,
             callback_payload = :payload,
             updated_at = NOW()
         WHERE token_paiement = :token'
    );

    $stmt->execute([
        ':status' => $status,
        ':payload' => !empty($callbackData) ? json_encode($callbackData) : null,
        ':token' => $token,
    ]);
}

function paydunyaFinalizePendingOrder(PDO $connexion, array $pendingOrder, array $callbackData = [])
{
    if (!empty($pendingOrder['commande_id'])) {
        return [
            'success' => true,
            'message' => 'Commande déjà finalisée.',
            'commande_id' => (int) $pendingOrder['commande_id'],
            'code_validation' => $pendingOrder['code_commande'],
        ];
    }

    $cartItems = paydunyaParseArrayInput($pendingOrder['panier'] ?? '[]');
    if (empty($cartItems)) {
        throw new Exception('Le panier associé au paiement est vide.');
    }

    $codeValidation = $pendingOrder['code_commande'] ?: 'PANIER-' . strtoupper(substr(uniqid(), -6));
    $codeLivreur = 'LIV-' . strtoupper(substr(uniqid(), -5));
    $paymentMethodLabel = strtoupper(str_replace('_', ' ', $pendingOrder['paiement_methode']));

    $connexion->beginTransaction();

    $sqlCommande = "INSERT INTO commande
        (ClientId, statutCommande, statutPaiement, totalCommande, tel, descriptionCmd, lieuLivraison, CodeValidation, codeLivreur, secteur, heureLivraison)
        VALUES
        (:client_id, 'Nouvelle commande', :statut_paiement, :total_commande, :tel, :description_cmd, :lieu_livraison, :code_validation, :code_livreur, :secteur, :heure_livraison)";
    $stmtCommande = $connexion->prepare($sqlCommande);
    $stmtCommande->execute([
        ':client_id' => $pendingOrder['user_id'] ?? -1,
        ':statut_paiement' => 'A Payé avec ' . $paymentMethodLabel,
        ':total_commande' => (int) $pendingOrder['montant_total'],
        ':tel' => $pendingOrder['telephone'],
        ':description_cmd' => $pendingOrder['commentaires'] ?? '',
        ':lieu_livraison' => $pendingOrder['lieu_livraison'] ?: ($pendingOrder['chambre'] ?: 'Inconnu'),
        ':code_validation' => $codeValidation,
        ':code_livreur' => $codeLivreur,
        ':secteur' => $pendingOrder['secteur'] ?? '',
        ':heure_livraison' => $pendingOrder['heure_livraison'] ?? null,
    ]);

    $commandeId = (int) $connexion->lastInsertId();

    $sqlDetails = 'INSERT INTO commande_details (id_commande, id_plat, quantite, prix_unitaire) VALUES (:id_commande, :id_plat, :quantite, :prix_unitaire)';
    $stmtDetails = $connexion->prepare($sqlDetails);

    foreach ($cartItems as $item) {
        $platId = explode('-', (string) ($item['id'] ?? '0'))[0];
        $stmtDetails->execute([
            ':id_commande' => $commandeId,
            ':id_plat' => (int) $platId,
            ':quantite' => (int) ($item['quantity'] ?? 0),
            ':prix_unitaire' => (int) ($item['price'] ?? 0),
        ]);
    }

    try {
        $sqlTransaction = "INSERT INTO transactions (id_commande, payment_provider, provider_token, status, amount, payment_url)
            VALUES (:id_commande, 'PayDunya', :provider_token, :status, :amount, :payment_url)";
        $stmtTransaction = $connexion->prepare($sqlTransaction);
        $stmtTransaction->execute([
            ':id_commande' => $commandeId,
            ':provider_token' => $pendingOrder['token_paiement'],
            ':status' => 'completed',
            ':amount' => (int) $pendingOrder['montant_total'],
            ':payment_url' => $pendingOrder['payment_url'] ?? '',
        ]);
    } catch (Exception $transactionException) {
        paydunyaWriteLog('Insertion transaction ignorée', [
            'message' => $transactionException->getMessage(),
            'token' => $pendingOrder['token_paiement'],
        ]);
    }

    $stmtPending = $connexion->prepare(
        'UPDATE commandes_en_attente
         SET payment_status = :status,
             callback_payload = :payload,
             commande_id = :commande_id,
             confirmed_at = NOW(),
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmtPending->execute([
        ':status' => 'completed',
        ':payload' => !empty($callbackData) ? json_encode($callbackData) : null,
        ':commande_id' => $commandeId,
        ':id' => $pendingOrder['id'],
    ]);

    $connexion->commit();

    return [
        'success' => true,
        'message' => 'Commande finalisée avec succès.',
        'commande_id' => $commandeId,
        'code_validation' => $codeValidation,
    ];
}
