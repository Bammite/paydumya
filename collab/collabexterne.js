(function (global) {
  "use strict";

  function normalizeBaseUrl(rawBaseUrl) {
    const value = String(rawBaseUrl || "").trim();
    if (!value) return "";
    return value.replace(/\/+$/, "");
  }

  function joinUrl(baseUrl, path) {
    if (!baseUrl) return "";
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

  function normalizePaymentMethod(rawMethod) {
    const method = String(rawMethod || "").trim().toLowerCase();
    const aliases = {
      "orange money": "orange_money",
      orange_money: "orange_money",
      om: "orange_money",
      wave: "wave",
      free_money: "free_money",
      "free money": "free_money",
      expresso: "expresso",
      wizall: "wizall",
    };
    return aliases[method] || method.replace(/\s+/g, "_");
  }

  function isSnMethod(method) {
    return ["wave", "orange_money", "free_money", "expresso", "wizall"].includes(method);
  }

  function isOriginOnly(urlValue) {
    const value = String(urlValue || "").trim();
    if (!value) return false;
    try {
      const parsed = new URL(value);
      return parsed.pathname === "/" || parsed.pathname === "";
    } catch (error) {
      return false;
    }
  }

  function inferBaseUrlFromLocation(candidate) {
    if (typeof window === "undefined" || !window.location) {
      return String(candidate || "").trim();
    }

    const provided = String(candidate || "").trim();
    if (provided && !isOriginOnly(provided)) {
      return normalizeBaseUrl(provided);
    }

    const origin = window.location.origin;
    const path = String(window.location.pathname || "").replace(/\/test\/.*$/, "");
    const cleaned = path.replace(/\/+$/, "");
    return origin + (cleaned === "" ? "" : cleaned);
  }

  function resolveUrls(baseUrl, callbackUrl, returnUrl, cancelUrl) {
    const resolvedBaseUrl = inferBaseUrlFromLocation(baseUrl);

    const callbackCandidate = String(callbackUrl || "").trim();
    const returnCandidate = String(returnUrl || "").trim();
    const cancelCandidate = String(cancelUrl || "").trim();

    const callback = hasMeaningfulPath(callbackCandidate)
      ? callbackCandidate
      : joinUrl(resolvedBaseUrl, "gestionDePaiement/callback.php");
    const returnUrlResolved = hasMeaningfulPath(returnCandidate)
      ? returnCandidate
      : joinUrl(resolvedBaseUrl, "gestionDePaiement/confirm_payment.php");
    const cancelUrlResolved = hasMeaningfulPath(cancelCandidate)
      ? cancelCandidate
      : joinUrl(resolvedBaseUrl, "gestionDePaiement/confirm_payment.php?status=cancelled");

    return {
      baseUrl: normalizeBaseUrl(resolvedBaseUrl),
      callbackUrl: callback,
      returnUrl: returnUrlResolved,
      cancelUrl: cancelUrlResolved,
      endpoint: joinUrl(normalizeBaseUrl(resolvedBaseUrl), "gestionDePaiement/simple_payment.php"),
    };
  }

  async function callSimplePaymentEndpoint(endpoint, payload, fetchOptions) {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
      ...fetchOptions,
    });

    const rawText = await response.text();
    if (!rawText) {
      throw new Error("Reponse vide du serveur (HTTP " + response.status + ").");
    }

    let data;
    try {
      data = JSON.parse(rawText);
    } catch (error) {
      throw new Error("JSON invalide (HTTP " + response.status + "): " + rawText.slice(0, 180));
    }

    return data;
  }

  async function lancerPaiementPartenaire(
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
    const method = normalizePaymentMethod(paymentMethod);
    const amountValue = Number(amount || 0);

    if (!name) throw new Error("Le nom du client est obligatoire.");
    if (!method) throw new Error("Le moyen de paiement est obligatoire.");
    if (!Number.isFinite(amountValue) || amountValue <= 0) {
      throw new Error("Le montant doit etre superieur a 0.");
    }

    const normalizedPhone = isSnMethod(method) ? normalizeSnPhone(phoneNumber) : cleanPhone(phoneNumber);
    if (!normalizedPhone) throw new Error("Numero de telephone invalide.");
    if (isSnMethod(method) && !/^7\d{8}$/.test(normalizedPhone)) {
      throw new Error("Le numero Senegal doit etre au format 7XXXXXXXX.");
    }

    const urls = resolveUrls(baseUrl, callbackUrl, returnUrl, cancelUrl);
    if (!urls.baseUrl) throw new Error("baseUrl est obligatoire.");
    if (!urls.endpoint) throw new Error("Impossible de construire l'endpoint simple_payment.php.");

    const payload = {
      customer_name: name,
      name: name,
      phone_number: normalizedPhone,
      amount: amountValue,
      payment_method: method,
      base_url: urls.baseUrl,
      callback_url: urls.callbackUrl,
      return_url: urls.returnUrl,
      cancel_url: urls.cancelUrl,
      customer_email: String(opts.customer_email || "client@sanarois.com"),
      description: String(opts.description || ("Paiement " + method + " - " + name)),
      partner_ref: String(opts.partner_ref || ""),
    };

    const result = await callSimplePaymentEndpoint(urls.endpoint, payload, opts.fetchOptions || {});

    if (!result.success && opts.throwOnFailure !== false) {
      throw new Error(result.message || "Paiement refuse.");
    }

    if (result.success && opts.autoRedirect && result.data && result.data.url) {
      window.location.href = result.data.url;
    }

    return result;
  }

  async function lancerPaiementPartenaireAvecObjet(config) {
    const data = config || {};
    return lancerPaiementPartenaire(
      data.customer_name ?? data.name ?? "",
      data.phone_number ?? data.phone ?? "",
      data.amount ?? data.total_amount ?? 0,
      data.payment_method ?? data.method ?? "",
      data.base_url ?? data.baseUrl ?? "",
      data.callback_url ?? data.callbackUrl ?? "",
      data.return_url ?? data.returnUrl ?? "",
      data.cancel_url ?? data.cancelUrl ?? "",
      data.options || {}
    );
  }

  global.PaydunyaCollab = {
    version: "1.0.0",
    lancerPaiement: lancerPaiementPartenaire,
    lancerPaiementAvecObjet: lancerPaiementPartenaireAvecObjet,
  };

  global.lancerPaiementPartenairePayDunya = lancerPaiementPartenaire;
})(typeof window !== "undefined" ? window : globalThis);
