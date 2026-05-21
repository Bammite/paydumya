# Intégration PayDunya

Ce sous-domaine contient maintenant une base API PayDunya plus complète.

## Fichiers utiles

- `gestionDePaiement/process_payment.php`
  - `mode=full_checkout` : crée la facture puis lance le paiement.
  - `mode=create_invoice` : crée uniquement la facture.
  - `mode=initiate_payment` : lance un paiement à partir d'un token existant.
  - `mode=confirm_wizall` : confirme un paiement Wizall.

- `gestionDePaiement/store_pending_order.php`
  - crée une commande en attente dans la base avant redirection vers PayDunya.

- `gestionDePaiement/callback.php`
  - reçoit l'IPN PayDunya, vérifie le hash, marque le paiement, puis finalise la commande.

- `gestionDePaiement/confirm_payment.php`
  - point de retour côté navigateur.
  - ne valide pas à l'aveugle : il attend que le callback ait confirmé le paiement.

- `gestionDePaiement/privileged_payment.php`
  - endpoint simplifié pour utilisateurs autorisés (nom, téléphone, montant, méthode).
  - nécessite `auth_key` (body) ou header `X-PAYDUNYA-AUTH`.

- `gestionDePaiement/simple_payment.php`
  - endpoint public simplifié pour intégration frontend.
  - prend directement: `customer_name`, `phone_number`, `amount`, `payment_method`, `base_url`, `callback_url`, `return_url`, `cancel_url`.

## Méthodes supportées

- `wave`
- `orange_money`
- `free_money`
- `expresso`
- `wizall`
- `card`
- `orange_money_ci`
- `mtn_ci`
- `moov_ci`
- `wave_ci`
- `orange_money_burkina`
- `moov_burkina`
- `moov_benin`
- `mtn_benin`
- `t_money_togo`
- `moov_togo`
- `orange_money_mali`
- `moov_mali`
- `mtn_cameroun`
- `paydunya_account`

## Pré-requis

1. Définir les clés PayDunya uniquement via variables d'environnement (`PAYDUNYA_MASTER_KEY`, `PAYDUNYA_PRIVATE_KEY`, `PAYDUNYA_TOKEN`).
2. Exécuter `commandes_en_attente.sql` dans la base.
3. Exécuter `sql/collab_partenaires_schema.sql` pour activer la gestion partenaires via base (domaines, méthodes, logs, transactions).
4. Exposer publiquement les URLs suivantes :
   - `/paydumya/gestionDePaiement/callback.php`
   - `/paydumya/gestionDePaiement/confirm_payment.php`

## Variables .env utiles

- `PAYDUNYA_BASE_URL`
- `PAYDUNYA_CHECKOUT_ENDPOINT`
- `PAYDUNYA_PUBLIC_BASE_URL`
- `PAYDUNYA_CALLBACK_URL`
- `PAYDUNYA_RETURN_URL`
- `PAYDUNYA_CANCEL_URL`
- `PAYDUNYA_AUTH_KEYS`
- `PAYDUNYA_ALLOWED_HOSTS`
- `PAYDUNYA_REQUIRE_HTTPS`
- `PAYDUNYA_PRIVILEGED_ALLOWED_HOSTS`
- `PAYDUNYA_PRIVILEGED_REQUIRE_HTTPS`
- `PAYDUNYA_PRIVILEGED_METHODS`
- `PAYDUNYA_CORS_ORIGINS` (liste d'origines autorisées pour les appels navigateur externes)

Note: `PAYDUNYA_AUTH_KEYS` doit contenir au moins une clé (séparées par virgule) pour activer `privileged_payment.php`.

## Exemple minimal

```http
POST /paydumya/gestionDePaiement/process_payment.php
Content-Type: application/json

{
  "mode": "full_checkout",
  "payment_method": "wave",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "phone_number": "778001122",
  "amount": 5000,
  "description": "Commande test"
}
```

## Fonction JS simplifiée

Fichier: `js/paydunya_simple_payment.js`

```html
<script src="/paydumya/js/paydunya_simple_payment.js"></script>
<script>
  async function lancerPaiement() {
    const result = await window.lancerPaiementPayDunya(
      "Client Test",
      "+221781234567",
      1000,
      "wave",
      "https://sanarois.com/paydumya",
      "https://sanarois.com/paydumya/gestionDePaiement/callback.php",
      "https://sanarois.com/paydumya/gestionDePaiement/confirm_payment.php",
      "https://sanarois.com/paydumya/gestionDePaiement/confirm_payment.php?status=cancelled"
    );
    console.log(result);
  }
</script>
```

## Intégration partenaire externe

Fichier: `collab/collabexterne.js`

Fonction positionnelle:

```js
const result = await window.lancerPaiementPartenairePayDunya(
  "test001",
  "+221781941351",
  1000,
  "Orange Money",
  "https://statuesque-fox-e68842.netlify.app/paydumya",
  "https://statuesque-fox-e68842.netlify.app/paydumya/gestionDePaiement/callback.php",
  "https://statuesque-fox-e68842.netlify.app/paydumya/gestionDePaiement/confirm_payment.php",
  "https://statuesque-fox-e68842.netlify.app/paydumya/gestionDePaiement/confirm_payment.php?status=cancelled"
);
```
