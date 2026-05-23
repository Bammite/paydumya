# PayDunya Integration Guide

## Objectif
Cette documentation explique comment intégrer le moyen de paiement PayDunya fourni par `pay.bammite.com/paydumya`. Elle s'adresse aux développeurs et aux assistants de code (IA) qui doivent intégrer un paiement sur un domaine public.

La documentation utilise uniquement les éléments de la librairie collab publique. Le formulaire de test présent dans `test/index.html` est optionnel pour debug, mais l'intégration réelle se fait avec votre propre interface.

L'exemple d'intégration fourni ici utilise le script JavaScript public :
- `https://pay.bammite.com/paydumya/test/payment_intermediate.js`

Une page de test est disponible pour développement et debug :
- `https://pay.bammite.com/paydumya/test/index.html`

## 1. Présentation du fonctionnement

Le flux d'intégration est le suivant :
1. Un administrateur ajoute un domaine et enregistre les informations de paiement dans l'admin PayDunya.
2. Le site marchand inclut le script `payment_intermediate.js` depuis `pay.bammite.com`.
3. Le formulaire du marchand envoie les données au script public.
4. Le script construit et transmet la requête de paiement à l'API PayDunya via le backend de `pay.bammite.com`.
5. L'utilisateur est redirigé sur la page de paiement ou reçoit le résultat de la transaction.

## 2. Intégration du script public

Pour un domaine public, incluez la librairie JavaScript fournie par PayDunya :

```html
<script src="https://pay.bammite.com/paydumya/test/payment_intermediate.js"></script>
```

Cette librairie expose deux fonctions JavaScript principales :
- `window.PaydunyaCollab.lancerPaiement(...)`
- `window.PaydunyaCollab.lancerPaiementAvecObjet(...)`

Elles sont aussi disponibles via la fonction globale :
- `window.lancerPaiementPartenairePayDunya(...)`

## 3. Utilisation sans formulaire de test

Le développeur peut créer son propre formulaire ou interface utilisateur. L'important est de passer les données attendues à la fonction JavaScript, par exemple :

```html
<form id="paymentForm" method="post">
  <!-- votre UI personnalisée -->
  <input type="text" name="name" id="name" placeholder="Nom du client">
  <input type="text" name="phone_number" id="phone_number" placeholder="Téléphone">
  <input type="number" name="amount" id="amount" placeholder="Montant">
  <select name="payment_method" id="payment_method">
    <option value="orange_money">Orange Money</option>
    <option value="wave">Wave</option>
    <option value="free_money">Free Money</option>
    <option value="expresso">Expresso</option>
    <option value="wizall">Wizall</option>
  </select>
  <button type="submit">Payer</button>
</form>

<script src="https://pay.bammite.com/paydumya/test/payment_intermediate.js"></script>
<script>
  document.getElementById('paymentForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const result = await window.PaydunyaCollab.lancerPaiement(
      document.getElementById('name').value,
      document.getElementById('phone_number').value,
      parseInt(document.getElementById('amount').value, 10),
      document.getElementById('payment_method').value,
      'https://pay.bammite.com/paydumya',
      'https://votredomaine.com/callback',
      'https://votredomaine.com/return',
      'https://votredomaine.com/cancel',
      {
        description: 'Paiement client',
        throwOnFailure: false,
      }
    );

    console.log(result);
  });
</script>
```

> Le formulaire peut être complètement personnalisé. Il n'est pas nécessaire de copier le modèle de test de `test/index.html`.

## 4. Champs attendus

### Champs obligatoires
- `name` / `customer_name` : nom du client
- `phone_number` : numéro de téléphone au format international (`+221781234567`) ou local sénégalais (`781234567`)
- `amount` : montant en CFA
- `payment_method` : code du moyen de paiement PayDunya
- `base_url` : base URL du backend PayDunya, ici `https://pay.bammite.com/paydumya`

### Champs de callback
- `callback_url` : URL de notification serveur
- `return_url` : URL de redirection après paiement réussi
- `cancel_url` : URL de redirection après annulation

Ces URLs doivent être configurées et autorisées côté admin dans la table `domaine_autorise`.

## 5. Exemple pour les IA assistants de code

Une IA peut générer le bloc HTML suivant pour intégrer le paiement :

```html
<form id="paymentForm" method="post">
    <input type="hidden" name="name" value="{{client_name}}">
    <input type="hidden" name="phone_number" value="{{phone_number}}">
    <input type="hidden" name="amount" value="{{amount}}">
    <input type="hidden" name="payment_method" value="{{payment_method}}">
    <button type="submit">Payer</button>
</form>

<script src="https://pay.bammite.com/paydumya/test/payment_intermediate.js"></script>
<script>
  document.getElementById('paymentForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const result = await window.PaydunyaCollab.lancerPaiementAvecObjet({
      name: '{{client_name}}',
      phone_number: '{{phone_number}}',
      amount: {{amount}},
      payment_method: '{{payment_method}}',
      base_url: 'https://pay.bammite.com/paydumya',
      callback_url: '{{callback_url}}',
      return_url: '{{return_url}}',
      cancel_url: '{{cancel_url}}',
    });

    console.log(result);
  });
</script>
```

> L'IA ou le script générateur remplit ici les variables métiers et laisse la mise en forme au développeur.

## 6. Intégration du script public

Pour un domaine public, incluez le script JavaScript centralisé :

```html
<script src="https://pay.bammite.com/paydumya/test/payment_intermediate.js"></script>
```

Le script fournit l'API client qui :
- valide le format du numéro de téléphone
- normalise le code méthode de paiement
- calcule les URLs `callback`, `return`, `cancel` si besoin
- appelle le endpoint `gestionDePaiement/simple_payment.php`
- retourne le résultat JSON à l'application cliente

## 7. Gestion du domaine autorisé

Avant de pouvoir utiliser ce moyen de paiement :
1. L'administrateur doit enregistrer le domaine dans l'interface admin.
2. Le champ `domaine` doit contenir le nom du site marchand, par exemple `sms.bammite.com`.
3. Le domaine doit être marqué `actif` et `allow_actions = 1`.

### Exemple d'enregistrement admin
- `domaine` : `sms.bammite.com`
- `partenaire_id` : l'ID du partenaire configuré
- `require_https` : `1`
- `allow_cors` : `1`
- `allow_actions` : `1`
- `actif` : `1`
- `callback_url`, `return_url`, `cancel_url` : URLs du site marchand ou du service de notification

## 8. Bonnes pratiques

- Toujours utiliser `https://pay.bammite.com/paydumya` comme `base_url` public.
- Vérifier que le domaine est autorisé dans l'admin avant de lancer des transactions.
- Utiliser des URL `callback`, `return` et `cancel` valides et accessibles.
- Pour un site marchand, activer `require_https` afin de sécuriser les échanges.
- Ne pas exposer de secrets PayDunya côté client.

## 9. Test rapide

1. Ouvrez `https://pay.bammite.com/paydumya/test/index.html`.
2. Remplissez `name`, `phone_number`, `amount`, `payment_method`.
3. Vérifiez que `base_url`, `callback_url`, `return_url` et `cancel_url` pointent vers `https://pay.bammite.com/paydumya` ou vers vos URLs autorisées.
4. Envoyez le formulaire.
5. Consultez la sortie de test dans la zone `#output`.

## 10. Éléments de support

- `admin_domains_api.php` : API interne pour ajouter / modifier / supprimer les domaines autorisés.
- `gestionDePaiement/paydunya_domain_registry.php` : logique d'accès et d'enregistrement des domaines.
- `test/index.html` : exemple client de paiement.
- `test/payment_intermediate.js` : script public de relais de paiement.

---

Si vous avez besoin d'intégrer un nouveau moyen de paiement sur un site public, utilisez ce guide comme modèle et adaptez les champs en fonction du `payment_method` disponible dans PayDunya.