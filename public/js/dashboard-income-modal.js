(function () {
  const FEEDBACK_SUCCESS = "form-status-success";
  const FEEDBACK_ERROR = "form-status-error";
  const FEEDBACK_PENDING = "form-status-pending";
  const LOADING_CLASS = "is-loading";

  function init() {
    const modal = document.querySelector("[data-income-modal]");
    if (!(modal instanceof HTMLElement)) {
      return;
    }

    const form = modal.querySelector("[data-income-form]");
    const closeBtn = modal.querySelector("[data-income-modal-close]");
    const submitBtn = modal.querySelector("[data-income-submit]");
    const feedback = modal.querySelector("[data-income-feedback]");

    if (!(form instanceof HTMLFormElement) || !(submitBtn instanceof HTMLButtonElement)) {
      return;
    }

    const defaultLabel = (submitBtn.textContent || buildI18n().addIncome).trim() || buildI18n().addIncome;

    document.querySelectorAll("[data-income-modal-open]").forEach((trigger) => {
      trigger.addEventListener("click", (event) => {
        event.preventDefault();
        openModal(modal, form);
      });
    });

    if (closeBtn instanceof HTMLElement) {
      closeBtn.addEventListener("click", () => closeModal(modal, feedback));
    }

    modal.addEventListener("click", (event) => {
      if (event.target !== modal) {
        return;
      }
      closeModal(modal, feedback);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape" || modal.hidden) {
        return;
      }
      closeModal(modal, feedback);
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (form.dataset.pending === "1") {
        return;
      }

      form.dataset.pending = "1";
      setLoadingState(submitBtn, true, defaultLabel);
      setFeedback(feedback, buildI18n().saving, FEEDBACK_PENDING);

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
          setFeedback(feedback, data.error || buildI18n().unableAdd, FEEDBACK_ERROR);
          setLoadingState(submitBtn, false, defaultLabel);
          form.dataset.pending = "0";
          return;
        }

        setFeedback(feedback, data.message || buildI18n().added, FEEDBACK_SUCCESS);
        submitBtn.textContent = buildI18n().added;
        window.setTimeout(() => {
          window.location.reload();
        }, 220);
      } catch (_error) {
        setFeedback(feedback, buildI18n().networkError, FEEDBACK_ERROR);
        setLoadingState(submitBtn, false, defaultLabel);
        form.dataset.pending = "0";
      }
    });
  }

  function openModal(modal, form) {
    modal.hidden = false;
    const firstField = form.querySelector('input[name$="[amount]"]');
    if (firstField instanceof HTMLElement) {
      window.setTimeout(() => firstField.focus(), 20);
    }
  }

  function closeModal(modal, feedback) {
    modal.hidden = true;
    clearFeedback(feedback);
  }

  function setLoadingState(button, loading, defaultLabel) {
    button.classList.toggle(LOADING_CLASS, loading);
    button.disabled = loading;
    button.textContent = loading ? buildI18n().adding : defaultLabel;
  }

  function setFeedback(node, message, cssClass) {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    node.hidden = false;
    node.textContent = message;
    node.classList.remove(FEEDBACK_SUCCESS, FEEDBACK_ERROR, FEEDBACK_PENDING);
    node.classList.add(cssClass);
  }

  function clearFeedback(node) {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    node.hidden = true;
    node.textContent = "";
    node.classList.remove(FEEDBACK_SUCCESS, FEEDBACK_ERROR, FEEDBACK_PENDING);
  }

  async function tryParseJson(response) {
    try {
      return await response.json();
    } catch (_error) {
      return {};
    }
  }

  function buildI18n() {
    const configured = window.dashboardIncomeFormI18n || {};
    return {
      saving: configured.saving || "Saving income...",
      unableAdd: configured.unableAdd || "Unable to add income.",
      networkError: configured.networkError || "Network error while adding income.",
      addIncome: configured.addIncome || "Add income",
      adding: configured.adding || "Adding...",
      added: configured.added || "Added",
    };
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
