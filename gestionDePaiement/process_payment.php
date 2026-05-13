<?php
require_once __DIR__ . '/paydunya_service.php';

ini_set('display_errors', '0');

function paydunyaRespondJson(array $payload, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function paydunyaHandleProcessPaymentRequest()
{
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            paydunyaRespondJson([
                'success' => false,
                'message' => 'Méthode non autorisée. Utilisez POST.',
            ], 405);
        }

        $request = paydunyaRequestData();
        $mode = strtolower(trim((string) ($request['mode'] ?? 'full_checkout')));

        paydunyaWriteLog('Nouvelle requête process_payment', [
            'mode' => $mode,
            'request' => $request,
        ]);

        switch ($mode) {
            case 'create_invoice':
                $result = paydunyaCreateInvoice($request);
                paydunyaRespondJson($result, $result['success'] ? 200 : 422);
                break;

            case 'initiate_payment':
                $result = paydunyaInitiatePayment($request);
                paydunyaRespondJson($result, $result['success'] ? 200 : 422);
                break;

            case 'confirm_wizall':
                $request['payment_method'] = 'wizall_confirm';
                $result = paydunyaInitiatePayment($request);
                paydunyaRespondJson($result, $result['success'] ? 200 : 422);
                break;

            case 'full_checkout':
            default:
                $customerName = trim((string) paydunyaRequestValue($request, ['customer_name', 'name', 'full_name'], 'Client'));
                $phoneNumber = paydunyaRequestValue($request, ['phone_number', 'phone'], '');
                $amount = (int) paydunyaRequestValue($request, ['amount', 'total_amount'], 0);
                $paymentMethod = paydunyaRequestValue($request, ['payment_method', 'method'], '');
                $actionUrls = paydunyaDefaultActions(array_filter([
                    'callback_url' => $request['callback_url'] ?? '',
                    'return_url' => $request['return_url'] ?? '',
                    'cancel_url' => $request['cancel_url'] ?? '',
                ]));

                $result = processPaymentPayDunya(
                    $phoneNumber,
                    $customerName,
                    $amount,
                    $paymentMethod,
                    $actionUrls,
                    $request
                );

                paydunyaRespondJson($result, $result['success'] ? 200 : 422);
                break;
        }
    } catch (Throwable $e) {
        paydunyaWriteLog('Erreur non gérée process_payment', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        paydunyaRespondJson([
            'success' => false,
            'message' => 'Erreur interne du serveur',
        ], 500);
    }
}

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    paydunyaHandleProcessPaymentRequest();
}
