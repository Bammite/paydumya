(function (global) {
  "use strict";

  function normalizeBaseUrl(rawBaseUrl) {
    const value = String(rawBaseUrl || "").trim();
    if (!value) return "";
    return value.replace(/\/+$/, "");
  }

  function joinUrl(baseUrl, path) {
    if (!baseUrl) return path;
    return baseUrl.replace(/\/+$/, "") + "/" + String(path || "").replace(/^\/+/, "");
  }

  function hasMeaningfulPath(urlValue) {
    const value = String(urlValue || "").trim();
    if (!value) return false;
    try {
      const parsed = new URL(value);
      return parsed.pathname && parsed.pathname !== "/";
    } catch (error) {
      return false;
    }
  }

  function cleanPhone(rawPhone) {
    return String(rawPhone || "").replace(/\D+/g, "");
  }

  function normalizeSnPhone(rawPhone) {
    let phone = cleanPhone(rawPhone);
    if (phone.startsWith("221") && phone.length === 12) {
      phone = phone.slice(3);
    }
    return phone;
  }

  function isSnMethod(method) {
    return ["wave", "orange_money", "free_money", "expresso", "wizall"].includes(String(method || "").trim());
  }

  function resolveDefaultBaseUrl() {
    if (typeof window === "undefined") return "";
    if (window.location.protocol === "file:") return "";
    return window.location.origin + "/paydumya";
  }

  async function lancerPaiementPayDunya(
    customerName,
    phoneNumber,
    amount,
    paymentMethod,
    baseUrl,
    callbackUrl,
    returnUrl,
    cancelUrl,
    options
  ) {
    const opts = options || {};
    const name = String(customerName || "").trim();
    const method = String(paymentMethod || "").trim();
    const valueAmount = Number(amount || 0);

    if (!name) {
      throw new Error("Le nom du client est obligatoire.");
    }
    if (!method) {
      throw new Error("Le moyen de paiement est obligatoire.");
    }
    if (!Number.isFinite(valueAmount) || valueAmount <= 0) {
      throw new Error("Le montant doit etre superieur a 0.");
    }

    const cleanedPhone = isSnMethod(method) ? normalizeSnPhone(phoneNumber) : cleanPhone(phoneNumber);
    if (!cleanedPhone) {
      throw new Error("Numero de telephone invalide.");
    }
    if (isSnMethod(method) && !/^7\d{8}$/.test(cleanedPhone)) {
      throw new Error("Pour ce moyen, le numero doit etre Senegalais au format 7XXXXXXXX.");
    }

    const resolvedBaseUrl = normalizeBaseUrl(baseUrl || resolveDefaultBaseUrl());
    const callbackCandidate = String(callbackUrl || "").trim();
    const returnCandidate = String(returnUrl || "").trim();
    const cancelCandidate = String(cancelUrl || "").trim();

    const resolvedCallback = hasMeaningfulPath(callbackCandidate)
      ? callbackCandidate
      : (resolvedBaseUrl ? joinUrl(resolvedBaseUrl, "gestionDePaiement/callback.php") : "");
    const resolvedReturn = hasMeaningfulPath(returnCandidate)
      ? returnCandidate
      : (resolvedBaseUrl ? joinUrl(resolvedBaseUrl, "gestionDePaiement/confirm_payment.php") : "");
    const resolvedCancel = hasMeaningfulPath(cancelCandidate)
      ? cancelCandidate
      : (resolvedBaseUrl ? joinUrl(resolvedBaseUrl, "gestionDePaiement/confirm_payment.php?status=cancelled") : "");

    const endpoint = String(opts.endpoint || "").trim() || (resolvedBaseUrl ? joinUrl(resolvedBaseUrl, "gestionDePaiement/simple_payment.php") : "../gestionDePaiement/simple_payment.php");

    const payload = {
      customer_name: name,
      name: name,
      phone_number: cleanedPhone,
      amount: valueAmount,
      payment_method: method,
      base_url: resolvedBaseUrl,
      callback_url: resolvedCallback,
      return_url: resolvedReturn,
      cancel_url: resolvedCancel,
      description: String(opts.description || ("Paiement " + method + " - " + name)),
      customer_email: String(opts.customer_email || "client@sanarois.com"),
    };

    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const rawText = await response.text();
    if (!rawText) {
      throw new Error("Reponse vide du serveur (HTTP " + response.status + ").");
    }

    let json;
    try {
      json = JSON.parse(rawText);
    } catch (error) {
      throw new Error("JSON invalide (HTTP " + response.status + "): " + rawText.slice(0, 180));
    }

    if (!json.success && opts.throwOnFailure !== false) {
      throw new Error(json.message || "Le paiement a echoue.");
    }

    if (json.success && opts.autoRedirect && json.data && json.data.url) {
      window.location.href = json.data.url;
    }

    return json;
  }

  global.lancerPaiementPayDunya = lancerPaiementPayDunya;
})(typeof window !== "undefined" ? window : globalThis);
