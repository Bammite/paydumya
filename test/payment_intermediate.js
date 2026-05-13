(() => {
  const form = document.getElementById("paymentForm");
  const output = document.getElementById("output");
  const submitButton = form ? form.querySelector('button[type="submit"]') : null;
  let isSubmitting = false;

  if (!form || !output) return;

  function cleanPhone(value) {
    return (value || "").replace(/\D+/g, "");
  }

  function normalizeSnPhone(value) {
    let phone = cleanPhone(value);
    if (phone.startsWith("221") && phone.length === 12) {
      phone = phone.slice(3);
    }
    return phone;
  }

  function isSnMethod(method) {
    return ["wave", "orange_money", "free_money", "expresso", "wizall"].includes(method);
  }

  function printResult(data, isError = false) {
    output.style.color = isError ? "#b42318" : "#0f5132";
    output.textContent = JSON.stringify(data, null, 2);
  }

  function resetSubmitState() {
    isSubmitting = false;
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = "PayDunya Test Payment";
    }
  }

  if (window.location.protocol === "file:") {
    printResult(
      {
        success: false,
        message:
          "Page ouverte en file://. Ouvrez cette page via Apache (http://localhost/...) pour executer les scripts PHP.",
      },
      true
    );
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true;
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Traitement...";
    }
    output.style.color = "#333";
    output.textContent = "Traitement en cours...";

    const formData = new FormData(form);
    const method = (formData.get("payment_method") || "").toString().trim();
    const rawPhone = (formData.get("phone_number") || "").toString();
    const amount = Number(formData.get("amount") || 0);
    const customerName = (formData.get("name") || "Client Test").toString().trim();
    const baseUrlFromForm = (formData.get("base_url") || "").toString().trim();
    const callbackUrl = (formData.get("callback_url") || "").toString().trim();
    const returnUrl = (formData.get("return_url") || "").toString().trim();
    const cancelUrl = (formData.get("cancel_url") || "").toString().trim();

    const phoneForApi = isSnMethod(method) ? normalizeSnPhone(rawPhone) : cleanPhone(rawPhone);

    if (!phoneForApi) {
      printResult(
        {
          success: false,
          message:
            "Numero invalide. Pour Senegal, utilisez un numero comme 781234567 ou +221781234567.",
        },
        true
      );
      resetSubmitState();
      return;
    }

    if (isSnMethod(method) && !/^7\d{8}$/.test(phoneForApi)) {
      printResult(
        {
          success: false,
          message:
            "Numero Senegal invalide pour cette methode. Il faut 9 chiffres et commencer par 7.",
        },
        true
      );
      resetSubmitState();
      return;
    }

    try {
      if (typeof window.lancerPaiementPartenairePayDunya !== "function") {
        throw new Error("Fonction JS lancerPaiementPartenairePayDunya introuvable.");
      }

      const baseUrl = baseUrlFromForm || (
        window.location.protocol === "file:"
          ? ""
          : `${window.location.origin}/paydumya`
      );

      const data = await window.lancerPaiementPartenairePayDunya(
        customerName,
        phoneForApi,
        amount,
        method,
        baseUrl,
        callbackUrl,
        returnUrl,
        cancelUrl,
        {
          description: `Test ${method} - ${customerName}`,
          throwOnFailure: false,
        }
      );

      printResult(data, !data.success);

      if (data.success && data.data && data.data.url) {
        const shouldRedirect = window.confirm("Paiement initialise. Voulez-vous ouvrir la page de paiement ?");
        if (shouldRedirect) {
          window.location.href = data.data.url;
        }
      }
    } catch (error) {
      printResult(
        {
          success: false,
          message: "Erreur reseau pendant le test",
          details: String(error),
        },
        true
      );
    } finally {
      resetSubmitState();
    }
  });
})();
