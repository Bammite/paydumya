# Documentation des Moyens de Paiement PayDunya

Ce document résume les différences d'intégration entre les paiements **Orange Money** et **Wave** via l'API Softpay de PayDunya.

## 1. Fichiers de Traitement

- **Orange Money** : Le traitement est géré par le fichier `process_payment.php`.
- **Wave** : Le traitement est géré par le fichier `paiement_wave.php`.

Le formulaire `index.html` utilise JavaScript pour changer son attribut `action` afin de pointer vers le bon fichier en fonction du choix de l'utilisateur.

## 2. Endpoints de l'API Softpay

La principale différence technique réside dans l'URL de l'API à appeler lors de l'étape 3 (effectuer le paiement).

- **Orange Money** :
  ```
  https://app.paydunya.com/api/v1/softpay/new-orange-money-senegal
  ```

- **Wave** :
  ```
  https://app.paydunya.com/api/v1/softpay/wave-senegal
  ```

## 3. Données de Paiement (Payload)

Pour les deux services, la structure des données envoyées (le "payload") à l'API Softpay est actuellement identique. Elle inclut :
- `invoice_token` : Le token de facture obtenu à l'étape 2.
- `customer_name` : Le nom du client.
- `phone_number` : Le numéro de téléphone du client pour le paiement.
- `total_amount` : Le montant de la transaction.