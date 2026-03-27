(function () {
  const APPROVE_REMOVE_DELAY_MS = 360;
  const DELETE_REMOVE_DELAY_MS = 220;
  const TOAST_HIDE_DELAY_MS = 1400;

  function init() {
    if (!window.spendingPlansPage) {
      return;
    }

    const cfg = window.spendingPlansPage;
    const i18n = {
      unableApprove: cfg?.i18n?.unableApprove || "Unable to approve suggestion.",
      unableDelete: cfg?.i18n?.unableDelete || "Unable to delete suggestion.",
      toastApproved: cfg?.i18n?.toastApproved || "Approved",
      toastDeleted: cfg?.i18n?.toastDeleted || "Deleted",
      noSuggestions: cfg?.i18n?.noSuggestions || "No suggestions for this month",
    };
    const suggestionList = document.getElementById("sp-suggestions-list");
    const existingList = document.getElementById("sp-existing-list");
    if (!suggestionList || !existingList) {
      return;
    }

    suggestionList.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const card = target.closest("[data-suggestion-id]");
      if (!(card instanceof HTMLElement)) {
        return;
      }

      if (target.matches("[data-suggestion-approve]")) {
        await approve(card);
      }

      if (target.matches("[data-suggestion-delete]")) {
        await removeSuggestion(card);
      }
    });

    const popup = document.querySelector("[data-sp-popup]");
    const closeBtn = document.querySelector("[data-sp-popup-close]");
    closeBtn?.addEventListener("click", () => {
      if (!popup) {
        return;
      }
      popup.classList.add("is-hidden");
      window.setTimeout(() => popup.remove(), 220);
    });

    async function approve(card) {
      const suggestionId = card.dataset.suggestionId;
      if (!suggestionId) {
        return;
      }

      const limitField = card.querySelector("[data-suggestion-limit]");
      const limitValue = limitField instanceof HTMLInputElement ? limitField.value : "";
      const currencyField = card.querySelector("[data-suggestion-currency]");
      const currencyCode = currencyField instanceof HTMLSelectElement
        ? currencyField.value
        : "";
      const weightField = card.querySelector("[data-suggestion-weight]");
      const weightValue = weightField instanceof HTMLInputElement
        ? weightField.value
        : "";
      const noteField = card.querySelector("[data-suggestion-note]");
      const noteValue = noteField instanceof HTMLInputElement
        ? noteField.value
        : "";
      const url = cfg.approveUrlTemplate
        .replace("MONTH", encodeURIComponent(cfg.month))
        .replace("ID", encodeURIComponent(suggestionId));

      const payload = new URLSearchParams();
      payload.set("_token", cfg.csrfToken);
      payload.set("limitAmount", limitValue);
      payload.set("currencyCode", currencyCode);
      payload.set("weight", weightValue);
      payload.set("note", noteValue);

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: payload.toString(),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        window.alert(data.error || i18n.unableApprove);
        return;
      }

      card.classList.add("is-approving");
      window.setTimeout(() => {
        card.remove();
        ensureSuggestionPlaceholder();
      }, APPROVE_REMOVE_DELAY_MS);

      const noExisting = document.getElementById("sp-no-existing");
      noExisting?.remove();
      existingList.insertAdjacentHTML("beforeend", data.existingHtml);
      showToast(i18n.toastApproved);
    }

    async function removeSuggestion(card) {
      const suggestionId = card.dataset.suggestionId;
      if (!suggestionId) {
        return;
      }

      const url = cfg.deleteUrlTemplate
        .replace("MONTH", encodeURIComponent(cfg.month))
        .replace("ID", encodeURIComponent(suggestionId));

      const payload = new URLSearchParams();
      payload.set("_token", cfg.csrfToken);

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: payload.toString(),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        window.alert(data.error || i18n.unableDelete);
        return;
      }

      card.classList.add("is-deleting");
      window.setTimeout(() => {
        card.remove();
        ensureSuggestionPlaceholder();
      }, DELETE_REMOVE_DELAY_MS);
      showToast(i18n.toastDeleted);
    }

    function ensureSuggestionPlaceholder() {
      if (suggestionList.querySelector("[data-suggestion-id]")) {
        return;
      }

      if (document.getElementById("sp-no-suggestions")) {
        return;
      }

      suggestionList.insertAdjacentHTML(
        "beforeend",
        `<div class="empty" id="sp-no-suggestions"><strong>${escapeHtml(i18n.noSuggestions)}</strong></div>`
      );
    }

    function showToast(message) {
      const toast = document.createElement("div");
      toast.className = "sp-toast";
      toast.textContent = message;
      document.body.appendChild(toast);

      window.setTimeout(() => {
        toast.classList.add("is-visible");
      }, 20);

      window.setTimeout(() => {
        toast.classList.remove("is-visible");
        window.setTimeout(() => toast.remove(), 220);
      }, TOAST_HIDE_DELAY_MS);
    }

    function escapeHtml(input) {
      return String(input)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll("\"", "&quot;")
        .replaceAll("'", "&#039;");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
    return;
  }

  init();
})();
