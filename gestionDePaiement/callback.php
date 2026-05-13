<?php
require_once __DIR__ . '/paydunya_service.php';
require_once paydunyaProjectRoot() . '/controller/connexion.php';

ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=UTF-8');

define('PAYDUNYA_CALLBACK_LOG_FILE', __DIR__ . '/callback_log.txt');

function paydunyaCallbackAppendLog($entry)
{
    $logDir = dirname(PAYDUNYA_CALLBACK_LOG_FILE);
    if (!is_dir($logDir) || !is_writable($logDir)) {
        return;
    }
    if (is_file(PAYDUNYA_CALLBACK_LOG_FILE) && !is_writable(PAYDUNYA_CALLBACK_LOG_FILE)) {
        return;
    }

    @file_put_contents(PAYDUNYA_CALLBACK_LOG_FILE, $entry, FILE_APPEND);
}

function paydunyaCallbackLog($message, $data = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $entry = '[' . $timestamp . '] ' . $message;

    if ($data !== null) {
        $entry .= "\nDATA: " . print_r(paydunyaMaskSensitiveData($data), true);
    }

    $entry .= "\n-----------------------------------\n";
    paydunyaCallbackAppendLog($entry);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        paydunyaCallbackLog('Méthode callback refusée.', ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }

    paydunyaCallbackLog('Nouveau callback PayDunya reçu.');

    $callbackData = paydunyaReadCallbackPayload();
    if (empty($callbackData)) {
        paydunyaCallbackLog('Payload callback vide ou illisible.', [
            'post' => $_POST,
        ]);
        http_response_code(400);
        echo 'Payload invalide';
        exit;
    }

    paydunyaCallbackLog('Payload callback décodé.', $callbackData);

    if (!paydunyaVerifyCallbackHash($callbackData)) {
        paydunyaCallbackLog('Hash callback invalide.', [
            'received_hash' => $callbackData['hash'] ?? '',
        ]);
        http_response_code(401);
        echo 'Hash invalide';
        exit;
    }

    $token = $callbackData['invoice']['token'] ?? null;
    $status = strtolower((string) ($callbackData['status'] ?? 'unknown'));

    if (!$token) {
        paydunyaCallbackLog('Token de facture absent dans le callback.');
        http_response_code(400);
        echo 'Token manquant';
        exit;
    }

    $pendingOrder = paydunyaFindPendingOrder($connexion, $token, null);
    if (!$pendingOrder) {
        paydunyaCallbackLog('Aucune commande en attente trouvée pour ce token.', ['token' => $token]);
        http_response_code(200);
        echo 'OK';
        exit;
    }

    paydunyaUpdatePendingStatus($connexion, $token, $status, $callbackData);

    if ($status === 'completed') {
        $result = paydunyaFinalizePendingOrder($connexion, $pendingOrder, $callbackData);
        paydunyaCallbackLog('Commande finalisée depuis le callback.', $result);
    } else {
        paydunyaCallbackLog('Paiement non finalisé, statut enregistré.', [
            'token' => $token,
            'status' => $status,
        ]);
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    paydunyaCallbackLog('Erreur serveur callback.', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    echo 'Erreur serveur';
}
