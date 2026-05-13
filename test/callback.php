<?php
/**
 * URL de Callback (IPN) pour les notifications de paiement PayDunya.
 * Ce script est appelé par les serveurs de PayDunya, pas par un utilisateur.
 */

// --- Configuration ---
define('LOG_FILE_CALLBACK', 'callback_log.txt');

// Mettez ici votre clé privée PayDunya pour la vérification du hash
$paydunyaPrivateKey = 'live_private_cEnLlYLrnmoaRzINT93yEtN0fFa';

/**
 * Fonction utilitaire pour écrire dans un fichier de log.
 */
function writeCallbackLog($message, $data = null) {
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\nDATA: " . print_r($data, true);
    }
    $logEntry .= "\n-----------------------------------\n";
    file_put_contents(LOG_FILE_CALLBACK, $logEntry, FILE_APPEND);
}

writeCallbackLog("Nouvelle notification de callback reçue.");

// --- 1. Récupérer les données POST ---
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    writeCallbackLog("Erreur: Impossible de décoder le payload JSON.");
    http_response_code(400); // Bad Request
    exit;
}

writeCallbackLog("Payload JSON décodé avec succès.", $data);

// --- 2. Vérification de sécurité (Hash) ---
if (isset($data['hash'])) {
    $receivedHash = $data['hash'];
    $invoiceToken = $data['invoice']['token'];

    // Recalculer le hash localement
    $calculatedHash = hash_hmac('sha512', $invoiceToken, $paydunyaPrivateKey);

    if ($receivedHash !== $calculatedHash) {
        writeCallbackLog("ALERTE SÉCURITÉ: Hash invalide !", [
            'reçu' => $receivedHash,
            'calculé' => $calculatedHash
        ]);
        http_response_code(401); // Unauthorized
        exit;
    }
    writeCallbackLog("Vérification du hash réussie.");
} else {
    writeCallbackLog("ALERTE SÉCURITÉ: Hash manquant dans la requête.");
    http_response_code(400);
    exit;
}

// --- 3. Traiter la notification ---
$status = $data['status'] ?? 'unknown';
$invoiceToken = $data['invoice']['token'] ?? 'no-token';
$customerEmail = $data['customer']['email'] ?? 'no-email';

writeCallbackLog("Traitement du statut '$status' pour la facture '$invoiceToken'.");

if ($status === 'completed') {
    // ✅ PAIEMENT RÉUSSI
    // C'est ici que vous mettez à jour votre base de données (ex: UPDATE orders SET status='paid' WHERE token=...)
    // Vous pouvez aussi envoyer un email de confirmation au client.
    writeCallbackLog("SUCCÈS: Paiement pour $customerEmail validé.");
} else {
    // ❌ PAIEMENT ÉCHOUÉ ('failed') OU ANNULÉ ('cancelled')
    // Mettez à jour votre base de données en conséquence.
    writeCallbackLog("ÉCHEC/ANNULATION: Le paiement pour $customerEmail n'a pas abouti (Statut: $status).");
}

// --- 4. Répondre à PayDunya ---
// Il est crucial de répondre avec un code 200 pour que PayDunya sache que vous avez bien reçu la notification.
http_response_code(200);
echo "Notification reçue.";
exit;