(function () {
  const SUCCESS_CLASS = "is-success-submit";
  const LOADING_CLASS = "is-loading";
  const DONE_CLASS = "is-done";
  const FEEDBACK_SUCCESS = "form-status-success";
  const FEEDBACK_ERROR = "form-status-error";
  const FEEDBACK_PENDING = "form-status-pending";
  const SUCCESS_RESET_MS = 1100;
  const SUCCESS_HINT_HIDE_MS = 3000;

  function init() {
    const i18n = buildI18n();
    const form = document.querySelector("[data-spend-form]");
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const submitBtn = form.querySelector("[data-spend-submit]");
    const feedback = document.querySelector("[data-spend-feedback]");
    if (!(submitBtn instanceof HTMLButtonElement)) {
      return;
    }

    const defaultLabel = (submitBtn.textContent || i18n.addSpend).trim() || i18n.addSpend;
    let feedbackHideTimer = null;

    form.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") {
        return;
      }

      const target = event.target;
      if (target instanceof HTMLTextAreaElement) {
        return;
      }

      event.preventDefault();
      if (form.dataset.pending === "1") {
        return;
      }

      submitBtn.click();
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (form.dataset.pending === "1") {
        return;
      }

      form.dataset.pending = "1";
      setLoadingState(submitBtn, true, defaultLabel);
      setFeedback(feedback, i18n.saving, FEEDBACK_PENDING, 0);

      try {
        const response = await fetch(form.action, {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          body: new FormData(form),
        });

        const data = await tryParseJson(response);
        if (!response.ok || !data.success) {
          setFeedback(feedback, data.error || i18n.unableAdd, FEEDBACK_ERROR, 0);
          setLoadingState(submitBtn, false, defaultLabel);
          form.dataset.pending = "0";
          return;
        }

        applyDefaults(form, data.defaults || {});
        playSuccessEffect(form, submitBtn);
        updateSpendWidget(data.widget || null);
        setFeedback(feedback, data.message || "Spend added.", FEEDBACK_SUCCESS, SUCCESS_HINT_HIDE_MS);
        showInlineSuccessBadge(form, i18n.saved);

        setDoneState(submitBtn, defaultLabel);
        window.setTimeout(() => {
          form.dataset.pending = "0";
        }, SUCCESS_RESET_MS);
      } catch (error) {
        setFeedback(feedback, i18n.networkError, FEEDBACK_ERROR, 0);
        setLoadingState(submitBtn, false, defaultLabel);
        form.dataset.pending = "0";
      }
    });

    function clearFeedbackTimer() {
      if (feedbackHideTimer === null) {
        return;
      }

      window.clearTimeout(feedbackHideTimer);
      feedbackHideTimer = null;
    }

    function setFeedback(node, message, cssClass, autoHideMs) {
      clearFeedbackTimer();
      setFeedbackInternal(node, message, cssClass);

      if (autoHideMs > 0 && node instanceof HTMLElement) {
        feedbackHideTimer = window.setTimeout(() => {
          node.hidden = true;
          node.textContent = "";
          node.classList.remove(FEEDBACK_SUCCESS, FEEDBACK_ERROR, FEEDBACK_PENDING);
          feedbackHideTimer = null;
        }, autoHideMs);
      }
    }
  }

  async function tryParseJson(response) {
    try {
      return await response.json();
    } catch (_error) {
      return {};
    }
  }

  function setFeedbackInternal(node, message, cssClass) {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    node.hidden = false;
    node.textContent = message;
    node.classList.remove(FEEDBACK_SUCCESS, FEEDBACK_ERROR, FEEDBACK_PENDING);
    node.classList.add(cssClass);
  }

  function applyDefaults(form, defaults) {
    form.reset();

    setFieldValue(form, "amount", defaults.amount || "");
    setFieldValue(form, "currency", defaults.currencyId || "");
    setFieldValue(form, "spendingPlan", defaults.spendingPlanId || "");
    setFieldValue(form, "spendDate", defaults.spendDate || "");
    setFieldValue(form, "comment", defaults.comment || "");

    const amountField = getField(form, "amount");
    if (amountField instanceof HTMLElement) {
      amountField.focus();
    }
  }

  function setLoadingState(button, loading, defaultLabel) {
    button.classList.remove(DONE_CLASS);
    button.classList.toggle(LOADING_CLASS, loading);
    button.disabled = loading;
    button.textContent = loading ? buildI18n().adding : defaultLabel;
  }

  function setDoneState(button, defaultLabel) {
    button.classList.remove(LOADING_CLASS);
    button.classList.add(DONE_CLASS);
    button.disabled = true;
    button.textContent = buildI18n().added;

    window.setTimeout(() => {
      button.classList.remove(DONE_CLASS);
      button.disabled = false;
      button.textContent = defaultLabel;
    }, SUCCESS_RESET_MS);
  }

  function playSuccessEffect(form, submitBtn) {
    form.classList.remove(SUCCESS_CLASS);
    void form.offsetWidth;
    form.classList.add(SUCCESS_CLASS);
    window.setTimeout(() => form.classList.remove(SUCCESS_CLASS), SUCCESS_RESET_MS);

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reducedMotion) {
      return;
    }

    spawnSparkBurst(submitBtn);
    spawnRipple(form, submitBtn);
  }

  function spawnSparkBurst(submitBtn) {
    const dots = [];
    const total = 14;

    for (let i = 0; i < total; i += 1) {
      const dot = document.createElement("span");
      dot.className = "spend-burst-dot";
      dot.style.setProperty("--hue", `${145 + Math.round(Math.random() * 40)}`);

      const angle = (Math.PI * 2 * i) / total;
      const distance = 26 + Math.random() * 28;
      dot.style.setProperty("--dx", `${Math.cos(angle) * distance}px`);
      dot.style.setProperty("--dy", `${Math.sin(angle) * distance}px`);

      submitBtn.appendChild(dot);
      dots.push(dot);
    }

    window.setTimeout(() => {
      dots.forEach((dot) => dot.remove());
    }, 700);
  }

  function spawnRipple(form, submitBtn) {
    const formRect = form.getBoundingClientRect();
    const btnRect = submitBtn.getBoundingClientRect();

    const ripple = document.createElement("span");
    ripple.className = "spend-success-ripple";
    ripple.style.left = `${btnRect.left - formRect.left + btnRect.width / 2}px`;
    ripple.style.top = `${btnRect.top - formRect.top + btnRect.height / 2}px`;
    form.appendChild(ripple);

    window.setTimeout(() => ripple.remove(), 850);
  }

  function showInlineSuccessBadge(form, text) {
    const existing = form.querySelector(".spend-inline-success");
    existing?.remove();

    const badge = document.createElement("div");
    badge.className = "spend-inline-success";
    badge.textContent = text;
    form.appendChild(badge);

    window.setTimeout(() => {
      badge.classList.add("is-visible");
    }, 20);

    window.setTimeout(() => {
      badge.classList.remove("is-visible");
      window.setTimeout(() => badge.remove(), 240);
    }, 900);
  }

  function updateSpendWidget(widget) {
    if (!widget || typeof widget !== "object") {
      return;
    }

    const card = document.querySelector("[data-spend-widget]");
    if (!(card instanceof HTMLElement)) {
      return;
    }

    const countNode = card.querySelector("[data-spend-widget-count]");
    if (countNode instanceof HTMLElement && Number.isFinite(Number(widget.count))) {
      countNode.textContent = `${widget.count} records`;
    }

    const totalNode = card.querySelector("[data-spend-widget-total]");
    if (totalNode instanceof HTMLElement && typeof widget.total === "string") {
      totalNode.textContent = widget.total;
    }

    const lastNode = card.querySelector("[data-spend-widget-last]");
    if (lastNode instanceof HTMLElement && typeof widget.lastLabel === "string") {
      lastNode.textContent = widget.lastLabel;
    }

    card.classList.remove("is-live-updated");
    void card.offsetWidth;
    card.classList.add("is-live-updated");
    window.setTimeout(() => card.classList.remove("is-live-updated"), 900);
  }

  function getField(form, fieldName) {
    return form.querySelector(`[name$="[${fieldName}]"]`);
  }

  function buildI18n() {
    const cfg = window.dashboardSpendFormI18n || {};

    return {
      saving: typeof cfg.saving === "string" ? cfg.saving : "Saving spend...",
      unableAdd: typeof cfg.unableAdd === "string" ? cfg.unableAdd : "Unable to add spend.",
      networkError: typeof cfg.networkError === "string" ? cfg.networkError : "Network error while adding spend.",
      addSpend: typeof cfg.addSpend === "string" ? cfg.addSpend : "Add spend",
      adding: typeof cfg.adding === "string" ? cfg.adding : "Adding...",
      added: typeof cfg.added === "string" ? cfg.added : "Added ✓",
      saved: typeof cfg.saved === "string" ? cfg.saved : "Saved",
    };
  }

  function setFieldValue(form, fieldName, value) {
    const field = getField(form, fieldName);
    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
      field.value = value;
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
    return;
  }

  init();
})();
