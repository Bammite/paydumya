# Collab Externe PayDunya

Ce dossier expose une interface JavaScript simple pour permettre a un site partenaire de lancer un paiement PayDunya sans integration complexe.

Fichier principal:
- `collabexterne.js`

## Objectif

Un site externe peut appeler une seule fonction en envoyant:
- nom client
- numero client
- montant
- moyen de paiement
- base URL
- callback URL
- return URL
- cancel URL

Le script appelle ensuite:
- `POST {baseUrl}/gestionDePaiement/simple_payment.php`

---

## Integration rapide

```html
<script src="https://pay.bammite.com/paydumya/collab/collabexterne.js"></script>
<script>
  async function payer() {
    const result = await window.lancerPaiementPartenairePayDunya(
      "test001",
      "+221781941351",
      1000,
      "Orange Money",
      "https://pay.bammite.com/paydumya",
      "https://pay.bammite.com/paydumya",
      "https://pay.bammite.com/paydumya",
      "https://pay.bammite.com/paydumya"
    );

    console.log(result);
    if (result.success && result.data && result.data.url) {
      window.location.href = result.data.url;
    }
  }
</script>
```

Note:
- si callback/return/cancel sont donnes a la racine (ex: `https://pay.bammite.com/paydumya`), le script complete automatiquement:
  - `/gestionDePaiement/callback.php`
  - `/gestionDePaiement/confirm_payment.php`
  - `/gestionDePaiement/confirm_payment.php?status=cancelled`

---

## API exposee

### 1) Fonction positionnelle

```js
window.lancerPaiementPartenairePayDunya(
  customerName,
  phoneNumber,
  amount,
  paymentMethod,
  baseUrl,
  callbackUrl,
  returnUrl,
  cancelUrl,
  options // facultatif
)
```

Alias equivalent:
- `window.PaydunyaCollab.lancerPaiement(...)`

### 2) Fonction objet

```js
window.PaydunyaCollab.lancerPaiementAvecObjet({
  customer_name: "test001",
  phone_number: "+221781941351",
  amount: 1000,
  payment_method: "Orange Money",
  base_url: "https://pay.bammite.com/paydumya",
  callback_url: "https://pay.bammite.com/paydumya",
  return_url: "https://pay.bammite.com/paydumya",
  cancel_url: "https://pay.bammite.com/paydumya",
  options: {
    autoRedirect: true
  }
});
```

---

## Parametres

- `customerName` / `customer_name`: obligatoire
- `phoneNumber` / `phone_number`: obligatoire
- `amount`: obligatoire, nombre > 0
- `paymentMethod` / `payment_method`: obligatoire
  - alias acceptes: `Orange Money`, `orange_money`, `wave`, `free_money`, `expresso`, `wizall`
- `baseUrl` / `base_url`: obligatoire (ex: `https://pay.bammite.com/paydumya`)
- `callbackUrl`, `returnUrl`, `cancelUrl`: optionnels mais recommandes
- `options` (facultatif):
  - `autoRedirect` (bool): redirige auto vers `result.data.url` si success
  - `throwOnFailure` (bool, defaut true): si false, ne throw pas sur `success:false`
  - `customer_email` (string)
  - `description` (string)
  - `partner_ref` (string)
  - `fetchOptions` (objet fetch supplementaire)

---

## Format de reponse

### Success

```json
{
  "success": true,
  "message": "....",
  "data": {
    "url": "https://....",
    "token": "...."
  }
}
```

### Echec

```json
{
  "success": false,
  "message": "....",
  "api_result": {
    "http_code": 422,
    "message": "...."
  }
}
```

---

## Prerequis serveur

Verifier que ces fichiers existent et sont accessibles:
- `/paydumya/collab/collabexterne.js`
- `/paydumya/gestionDePaiement/simple_payment.php`
- `/paydumya/gestionDePaiement/callback.php`
- `/paydumya/gestionDePaiement/confirm_payment.php`

Variables d'environnement importantes:
- `PAYDUNYA_MASTER_KEY`
- `PAYDUNYA_PRIVATE_KEY`
- `PAYDUNYA_TOKEN`
- `PAYDUNYA_BASE_URL`
- `PAYDUNYA_CHECKOUT_ENDPOINT`
- `PAYDUNYA_CORS_ORIGINS`
- `PAYDUNYA_ALLOWED_HOSTS`

Exemple:

```env
PAYDUNYA_CORS_ORIGINS="https://pay.bammite.com,https://sanarois.com,http://localhost,http://127.0.0.1"
```

---

## Debug rapide

Si erreur `422`:
1. verifier le JSON envoye (champ manquant, montant <= 0, methode invalide)
2. verifier le numero pour SN (`+22178...` ou `78...`)
3. verifier la casse du chemin (`/paydumya` vs `/Paydumya`)
4. verifier CORS (`PAYDUNYA_CORS_ORIGINS`)
5. verifier endpoint/cles PayDunya (test vs live)

Si `api_result.http_code = 500` avec HTML:
- le serveur PayDunya a retourne une page erreur (probleme de mode, cles ou endpoint).

---

## Notes pour assistant de code

Si tu modifies cette integration:
- garde la signature positionnelle stable (retrocompatibilite partenaire)
- ne casse pas `window.lancerPaiementPartenairePayDunya`
- ne retire pas les alias de methode (`Orange Money` -> `orange_money`)
- conserver la normalisation automatique des URLs callback/return/cancel
- conserver la validation telephone Senegal pour methodes SN
