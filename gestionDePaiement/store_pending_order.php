<?php
require_once __DIR__ . '/paydunya_service.php';
require_once paydunyaProjectRoot() . '/controller/connexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

try {
    $request = paydunyaRequestData();
    writeLog('store_pending_order - Données reçues', $request);

    $cartJson = $request['cart'] ?? '[]';
    $cartItems = json_decode($cartJson, true);

    if (empty($cartItems) || !is_array($cartItems)) {
        throw new Exception('Votre panier est vide ou invalide.');
    }

    $numeroTel = trim((string) ($request['tel'] ?? $request['phone_number'] ?? ''));
    $paiement = trim((string) ($request['paiement'] ?? $request['payment_method'] ?? ''));
    $comments = trim((string) ($request['comments'] ?? ''));
    $secteur = trim((string) ($request['deliveryLocation'] ?? $request['secteur'] ?? ''));
    $chambre = trim((string) ($request['chambre'] ?? ''));
    $position = trim((string) ($request['position'] ?? ''));
    $heureLivraison = trim((string) ($request['scheduledTime'] ?? $request['heure_livraison'] ?? ''));
    $customerName = trim((string) ($request['customer_name'] ?? $request['name'] ?? 'Client'));
    $customerEmail = trim((string) ($request['customer_email'] ?? $request['email'] ?? 'client@example.com'));
    $userId = isset($_COOKIE['info']) ? (int) $_COOKIE['info'] : -1;

    if ($numeroTel === '') {
        throw new Exception('Le numéro de téléphone est obligatoire.');
    }

    if ($secteur === '') {
        throw new Exception('Le lieu de livraison est obligatoire.');
    }

    $paymentMethodMap = [
        'Wave' => 'wave',
        'Orange Money' => 'orange_money',
        'Free Money' => 'free_money',
        'Expresso' => 'expresso',
        'Wizall' => 'wizall',
        'wave' => 'wave',
        'orange_money' => 'orange_money',
        'free_money' => 'free_money',
        'expresso' => 'expresso',
        'wizall' => 'wizall',
    ];
    $normalizedPaymentMethod = $paymentMethodMap[$paiement] ?? paydunyaNormalizeMethod($paiement);

    if ($normalizedPaymentMethod === '') {
        throw new Exception('Méthode de paiement PayDunya non supportée.');
    }

    $stmtLieu = $connexion->prepare('SELECT frais_livraison FROM lieux_livraison WHERE nom = ? AND actif = 1 LIMIT 1');
    $stmtLieu->execute([$secteur]);
    $fraisLivraison = 0;
    if ($lieuData = $stmtLieu->fetch(PDO::FETCH_ASSOC)) {
        $fraisLivraison = (int) $lieuData['frais_livraison'];
    }

    $items = [];
    $totalCommande = $fraisLivraison;

    foreach ($cartItems as $index => $item) {
        $quantity = (int) ($item['quantity'] ?? 0);
        $unitPrice = (int) ($item['price'] ?? 0);
        $lineTotal = $quantity * $unitPrice;
        $totalCommande += $lineTotal;

        $items['item_' . $index] = [
            'name' => (string) ($item['name'] ?? ('Article ' . ($index + 1))),
            'quantity' => $quantity,
            'unit_price' => (string) $unitPrice,
            'total_price' => (string) $lineTotal,
            'description' => (string) ($item['description'] ?? ''),
        ];
    }

    $taxes = [];
    if ($fraisLivraison > 0) {
        $taxes['tax_0'] = [
            'name' => 'Frais de livraison',
            'amount' => $fraisLivraison,
        ];
    }

    $phoneNumberFormatted = $numeroTel;
    if (strpos($phoneNumberFormatted, '+221') !== 0 && ctype_digit(ltrim($phoneNumberFormatted, '+'))) {
        $phoneNumberFormatted = '+221' . ltrim($phoneNumberFormatted, '0');
    }

    $codeCommande = 'PANIER-' . strtoupper(substr(uniqid(), -6));
    $lieuLivraison = $chambre !== '' ? $chambre : ($position !== '' ? $position : $secteur);
    $baseUrl = paydunyaCurrentBaseUrl();

    $actionUrls = [
        'callback_url' => $baseUrl . '/gestionDePaiement/callback.php',
        'return_url' => $baseUrl . '/gestionDePaiement/confirm_payment.php?order_code=' . urlencode($codeCommande),
        'cancel_url' => $baseUrl . '/gestionDePaiement/confirm_payment.php?status=cancelled&order_code=' . urlencode($codeCommande),
    ];

    $extraData = [
        'customer_email' => $customerEmail,
        'description' => 'Paiement commande ' . $codeCommande,
        'items' => $items,
        'taxes' => $taxes,
        'custom_data' => [
            'order_code' => $codeCommande,
            'delivery_sector' => $secteur,
            'delivery_location' => $lieuLivraison,
        ],
        'store_name' => 'Sanarois Fast-Food',
    ];

    $paymentResult = processPaymentPayDunya(
        $phoneNumberFormatted,
        $customerName,
        $totalCommande,
        $normalizedPaymentMethod,
        $actionUrls,
        $extraData
    );

    if (!$paymentResult['success']) {
        throw new Exception($paymentResult['message'] ?? 'Erreur lors du paiement.');
    }

    $invoiceToken = $paymentResult['data']['token'];
    $paymentUrl = $paymentResult['data']['url'] ?? '';
    $providerPayload = json_encode($paymentResult['data']['payment'] ?? []);

    try {
        $sqlPending = "INSERT INTO commandes_en_attente
            (code_commande, token_paiement, user_id, panier, telephone, paiement_methode, commentaires, lieu_livraison, secteur, chambre, heure_livraison, montant_total, payment_status, payment_url, provider_response)
            VALUES
            (:code_commande, :token, :user_id, :panier, :telephone, :methode, :comments, :lieu, :secteur, :chambre, :heure, :montant, 'pending', :payment_url, :provider_response)";

        $stmtPending = $connexion->prepare($sqlPending);
        $stmtPending->execute([
            ':code_commande' => $codeCommande,
            ':token' => $invoiceToken,
            ':user_id' => $userId,
            ':panier' => $cartJson,
            ':telephone' => $numeroTel,
            ':methode' => $normalizedPaymentMethod,
            ':comments' => $comments,
            ':lieu' => $lieuLivraison,
            ':secteur' => $secteur,
            ':chambre' => $chambre !== '' ? $chambre : null,
            ':heure' => $heureLivraison !== '' ? $heureLivraison : null,
            ':montant' => $totalCommande,
            ':payment_url' => $paymentUrl,
            ':provider_response' => $providerPayload,
        ]);
    } catch (PDOException $pdoException) {
        if ((string) $pdoException->getCode() === '42S02') {
            throw new Exception('La table `commandes_en_attente` est absente. Exécutez le script SQL fourni dans `/paydumya/commandes_en_attente.sql`.');
        }

        throw $pdoException;
    }

    writeLog('Commande en attente stockée', [
        'code_commande' => $codeCommande,
        'token' => $invoiceToken,
        'payment_url' => $paymentUrl,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Commande en attente créée. Redirection vers le paiement...',
        'order_code' => $codeCommande,
        'token' => $invoiceToken,
        'redirectUrl' => $paymentUrl,
        'other_url' => $paymentResult['data']['other_url'] ?? [],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    writeLog('ERREUR store_pending_order: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
