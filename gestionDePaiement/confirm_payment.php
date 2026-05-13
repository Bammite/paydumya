<?php
require_once __DIR__ . '/paydunya_service.php';
require_once paydunyaProjectRoot() . '/controller/connexion.php';

header('Content-Type: application/json');

try {
    $request = array_merge($_GET, paydunyaRequestData());
    $token = $request['token'] ?? null;
    $orderCode = $request['order_code'] ?? null;
    $status = strtolower((string) ($request['status'] ?? 'success'));

    if ($status === 'cancelled') {
        echo json_encode([
            'success' => false,
            'status' => 'cancelled',
            'message' => 'Le paiement a été annulé par le client.',
        ]);
        exit;
    }

    if (!$token && !$orderCode) {
        throw new Exception('Token de paiement ou code de commande manquant.');
    }

    $pendingOrder = paydunyaFindPendingOrder($connexion, $token, $orderCode);
    if (!$pendingOrder) {
        throw new Exception('Commande en attente introuvable.');
    }

    if (!empty($pendingOrder['commande_id'])) {
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => 'Commande déjà confirmée.',
            'commande_id' => (int) $pendingOrder['commande_id'],
            'order_code' => $pendingOrder['code_commande'],
        ]);
        exit;
    }

    $paymentStatus = strtolower((string) ($pendingOrder['payment_status'] ?? 'pending'));
    if ($paymentStatus !== 'completed') {
        http_response_code(202);
        echo json_encode([
            'success' => false,
            'status' => 'pending',
            'message' => 'Le paiement est encore en attente de confirmation IPN.',
            'order_code' => $pendingOrder['code_commande'],
            'token' => $pendingOrder['token_paiement'],
        ]);
        exit;
    }

    $result = paydunyaFinalizePendingOrder($connexion, $pendingOrder);

    echo json_encode([
        'success' => true,
        'status' => 'completed',
        'message' => $result['message'],
        'commande_id' => $result['commande_id'],
        'order_code' => $result['code_validation'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    writeLog('ERREUR confirm_payment: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
