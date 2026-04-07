(function () {
  if (window.__formValidationPopupInitialized) {
    return;
  }
  window.__formValidationPopupInitialized = true;
  const AUTO_HIDE_MS = 3000;
  const FIELD_ERROR_CLASS = "validation-field-error";
  const FIELD_TIP_CLASS = "validation-field-tip";

  function isFormElement(node) {
    return node instanceof HTMLFormElement;
  }

  function firstInvalidField(form) {
    return form.querySelector(":invalid");
  }

  function ensurePopupElements() {
    let overlay = document.querySelector("[data-validation-popup-overlay]");
    if (!(overlay instanceof HTMLElement)) {
      overlay = document.createElement("div");
      overlay.className = "validation-popup-overlay";
      overlay.setAttribute("data-validation-popup-overlay", "1");

      const box = document.createElement("div");
      box.className = "validation-popup-box";
      box.setAttribute("role", "alert");
      box.setAttribute("aria-live", "assertive");

      const message = document.createElement("p");
      message.className = "validation-popup-message";
      message.setAttribute("data-validation-popup-message", "1");

      box.appendChild(message);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
    }

    const messageNode = overlay.querySelector("[data-validation-popup-message]");
    if (!(messageNode instanceof HTMLElement)) {
      return null;
    }

    return { overlay, messageNode };
  }

  function showPopup(message) {
    const text = (message || "").trim() || "Please check form fields.";
    const parts = ensurePopupElements();
    if (!parts) {
      return;
    }

    const existingTimer = Number(parts.overlay.dataset.hideTimer || "0");
    if (existingTimer > 0) {
      window.clearTimeout(existingTimer);
    }

    parts.messageNode.textContent = text;
    parts.overlay.classList.add("is-visible");

    const hideTimer = window.setTimeout(() => {
      parts.overlay.classList.remove("is-visible");
    }, AUTO_HIDE_MS);
    parts.overlay.dataset.hideTimer = String(hideTimer);
  }

  function readServerFormErrorMessage() {
    const candidates = document.querySelectorAll("form li[id*=\"_error\"], form ul[id*=\"_error\"] li");
    for (const node of candidates) {
      if (!(node instanceof HTMLElement)) {
        continue;
      }

      const text = (node.textContent || "").trim();
      if (text !== "") {
        return text;
      }
    }

    return "";
  }

  const serverErrorMessage = readServerFormErrorMessage();
  if (serverErrorMessage !== "") {
    showPopup(serverErrorMessage);
  }

  function clearFieldValidationState(form) {
    form.querySelectorAll("." + FIELD_ERROR_CLASS).forEach((node) => {
      if (node instanceof HTMLElement) {
        node.classList.remove(FIELD_ERROR_CLASS);
      }
    });

    form.querySelectorAll("." + FIELD_TIP_CLASS).forEach((node) => node.remove());
  }

  function showFieldTip(form, field, message) {
    clearFieldValidationState(form);
    if (!(field instanceof HTMLElement)) {
      return;
    }

    field.classList.add(FIELD_ERROR_CLASS);

    const tip = document.createElement("div");
    tip.className = FIELD_TIP_CLASS;
    tip.textContent = (message || "").trim() || "Please correct this field.";
    field.insertAdjacentElement("afterend", tip);

    const clear = () => {
      field.classList.remove(FIELD_ERROR_CLASS);
      tip.remove();
      field.removeEventListener("input", clear);
      field.removeEventListener("change", clear);
    };

    field.addEventListener("input", clear);
    field.addEventListener("change", clear);
  }

  function wirePopupDismiss() {
    const parts = ensurePopupElements();
    if (!parts) {
      return;
    }

    let closeBtn = parts.overlay.querySelector("[data-validation-popup-close]");
    if (!(closeBtn instanceof HTMLButtonElement)) {
      closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "validation-popup-close";
      closeBtn.setAttribute("data-validation-popup-close", "1");
      closeBtn.setAttribute("aria-label", "Close");
      closeBtn.textContent = "×";
      parts.overlay.querySelector(".validation-popup-box")?.appendChild(closeBtn);
    }

    const closeNow = () => {
      parts.overlay.classList.remove("is-visible");
      const existingTimer = Number(parts.overlay.dataset.hideTimer || "0");
      if (existingTimer > 0) {
        window.clearTimeout(existingTimer);
      }
      parts.overlay.dataset.hideTimer = "0";
    };

    closeBtn.addEventListener("click", closeNow);
    parts.overlay.addEventListener("click", (event) => {
      if (event.target === parts.overlay) {
        closeNow();
      }
    });
  }

  wirePopupDismiss();

  document.addEventListener(
    "submit",
    (event) => {
      const target = event.target;
      if (!isFormElement(target)) {
        return;
      }

      if (target.noValidate || target.dataset.skipValidationPopup === "1") {
        return;
      }

      if (target.checkValidity()) {
        return;
      }

      event.preventDefault();
      const invalidField = firstInvalidField(target);
      if (invalidField instanceof HTMLElement && typeof invalidField.reportValidity === "function") {
        invalidField.reportValidity();
      }

      if (invalidField instanceof HTMLElement) {
        showFieldTip(
          target,
          invalidField,
          invalidField instanceof HTMLInputElement || invalidField instanceof HTMLTextAreaElement || invalidField instanceof HTMLSelectElement
            ? invalidField.validationMessage
            : ""
        );
      }

      showPopup(invalidField instanceof HTMLInputElement || invalidField instanceof HTMLTextAreaElement || invalidField instanceof HTMLSelectElement
        ? invalidField.validationMessage
        : "");
    },
    true
  );
})();
