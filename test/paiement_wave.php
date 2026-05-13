<?php

// ❌ ENLEVÉ : header('Content-Type: application/json');

// Configuration
define('LOG_FILE', 'debug_log.txt');

/**
 * Fonction utilitaire pour écrire dans le fichier de log (inchangée)
 */
function writeLog($message, $data = null) {
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        if (is_array($data) && isset($data['password'])) {
            $data['password'] = '****** (MASQUÉ)';
        }
        $logEntry .= "\nDATA: " . print_r($data, true);
    }
    
    $logEntry .= "\n-----------------------------------\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// --- DÉBUT DU TRAITEMENT ---
writeLog("Nouvelle requête reçue.");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog("Erreur: Méthode non autorisée (" . $_SERVER['REQUEST_METHOD'] . ")");
    http_response_code(405);
    echo "<h1>ERREUR 405</h1><p>Méthode non autorisée. Utilisez POST.</p>";
    exit;
}

// --- Étape 1: Récupérer les données du formulaire ---
$customerName = $_POST['name'] ?? 'Client Test';
$phoneNumber = $_POST['phone_number'] ?? '';
$amount = $_POST['amount'] ?? 0;

writeLog("Étape 1: Données reçues du formulaire", $_POST);

// Valider les entrées
if (empty($phoneNumber) || empty($amount) || !is_numeric($amount)) {
    writeLog("Erreur: Validation échouée (téléphone ou montant vide/invalide).");
    http_response_code(400);
    echo "<h1>ERREUR 400</h1><p>Données du formulaire invalides.</p>";
    exit;
}

// --- Étape 2: Créer la facture (Checkout Invoice) ---
writeLog("Étape 2: Tentative de création de facture PayDunya...");

$invoiceData = [
    'invoice' => [
        'total_amount' => (int)$amount,
        'description' => 'Achat de test pour ' . $customerName,
    ],
    'store' => [
        'name' => 'Magasin de Test',
    ],
];

// Tes clés API
$apiKeysHeader = [
    'Content-Type: application/json',
    'PAYDUNYA-MASTER-KEY: BKOjYyKf-IVMY-r9lv-Oewv-xGmzZk8lWlze',
    'PAYDUNYA-PRIVATE-KEY: live_private_cEnLlYLrnmoaRzINT93yEtN0fFa',
    'PAYDUNYA-TOKEN: X1ZXceMk3x0HPBoTfSIK',
];

$ch = curl_init('https://app.paydunya.com/api/v1/checkout-invoice/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $apiKeysHeader);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$invoiceResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    writeLog("Erreur CRITIQUE cURL (Facture): " . $error);
    http_response_code(500);
    echo "<h1>ERREUR 500</h1><p>Erreur cURL (création facture): " . htmlspecialchars($error) . "</p>";
    curl_close($ch);
    exit;
}
else{
    echo "<h1>Facture créée avec succès (Debug)</h1>";
    echo "<pre>" . htmlspecialchars($invoiceResponse) . "</pre>";
}
curl_close($ch);

$invoiceResult = json_decode($invoiceResponse, true);

echo "<h2>Réponse de création de facture (Debug)</h2> <pre>" . print_r($invoiceResult, true) . "</pre>";

writeLog("Réponse PayDunya (Création Facture) - Code HTTP: $httpCode", $invoiceResult);

if ($httpCode !== 200 || !isset($invoiceResult['token'])) {
    writeLog("Erreur: Impossible de créer la facture.");
    http_response_code($httpCode);
    echo "<h1>ERREUR PayDunya: Échec de la création de la facture (Code $httpCode)</h1>";
    echo "<pre>" . htmlspecialchars($invoiceResponse) . "</pre>";
    exit;
}

$invoiceToken = $invoiceResult['token'];
echo "<h2>Token de la facture créée (Debug): " .$invoiceToken. "</h2>";

// --- Étape 3: Effectuer le paiement (Softpay) ---
writeLog("Étape 3: Tentative de paiement Softpay...");

$paymentData = [
    'wave_senegal_payment_token' => $invoiceToken,
    "wave_senegal_email"  => "test@gmail.com",
    'wave_senegal_fullName' => $customerName,
    'wave_senegal_phone' => '781941351',
    'total_amount' => (int)$amount,
];

echo("Envoi des données de paiement (Softpay)". print_r($paymentData, true));

// UTILISATION DE L'URL CORRIGÉE (si tu l'as déjà mise à jour)
$ch = curl_init('https://app.paydunya.com/api/v1/softpay/wave-senegal'); // OU sandbox-api
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $apiKeysHeader); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$paymentResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    writeLog("Erreur CRITIQUE cURL (Paiement): " . $error);
    http_response_code(500);
    echo "<h1>ERREUR 500</h1><p>Erreur cURL (paiement): " . htmlspecialchars($error) . "</p>";
    curl_close($ch);
    exit;
}
curl_close($ch);

$paymentResult = json_decode($paymentResponse, true);

writeLog("Réponse PayDunya (Paiement Softpay) - Code HTTP: $httpCode", $paymentResult ?? $paymentResponse);

// Affichage final pour le débogage
echo "<!DOCTYPE html><html><head><title>Résultat PayDunya Softpay</title>        <!-- raccourci -->
    <link rel="apple-touch-icon" sizes="180x180" href="./assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/favicon-16x16.png">
    <link rel="manifest" href="./manifest.json">
  </head>
  <body><body>";
echo "<h1>💳 Résultat de la Transaction PayDunya (Mode Débogage) </h1>";
echo "<h2>Code HTTP Softpay: $httpCode</h2>";

if ($httpCode === 200 && isset($paymentResult['success']) && $paymentResult['success'] === true) {
    writeLog("SUCCÈS FINAL: Paiement accepté.");
    echo "<h3>✅ PAIEMENT RÉUSSI !</h3>";
    echo "<h4>Détails de la réponse JSON :</h4>";
    echo "<pre>" . print_r($paymentResult, true) . "</pre>";

} else {
    writeLog("ÉCHEC FINAL: Le paiement a été refusé ou la réponse API est invalide.");
    echo "<h3>❌ ÉCHEC DU PAIEMENT OU DE L'API</h3>";

    if ($paymentResult !== null) {
        // C'est du JSON décodable (ex: erreur de mot de passe)
        echo "<h4>Réponse JSON (Erreur Décodable) :</h4>";
        echo "<pre>" . print_r($paymentResult, true) . "</pre>";
    } else {
        // C'est probablement du HTML (comme le 404) ou du texte brut
        echo "<h4>Réponse BRUTE du Serveur (Affichage HTML/Erreur Page) :</h4>";
        echo "<hr>";
        // Afficher la réponse brute pour voir la page d'erreur HTML (404)
        echo $paymentResponse; 
    }
}
echo "</body></html>";
?>