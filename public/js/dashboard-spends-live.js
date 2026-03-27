(function () {
  const POLL_INTERVAL_MS = 5000;

  function init() {
    const cfg = window.dashboardSpendsPage;
    if (!cfg || typeof cfg.versionUrl !== "string" || typeof cfg.month !== "string") {
      return;
    }

    let knownLatestId = null;
    let knownTotal = null;
    let pending = false;

    const indicator = ensureIndicator();

    async function poll() {
      if (pending) {
        return;
      }
      pending = true;

      try {
        const url = new URL(cfg.versionUrl, window.location.origin);
        url.searchParams.set("month", cfg.month);

        const response = await fetch(url.toString(), {
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          cache: "no-store",
        });

        if (!response.ok) {
          return;
        }

        const data = await response.json();
        const latestId = Number(data.latestId || 0);
        const total = Number(data.total || 0);

        if (knownLatestId === null || knownTotal === null) {
          knownLatestId = latestId;
          knownTotal = total;
          return;
        }

        if (latestId !== knownLatestId || total !== knownTotal) {
          indicator.textContent = typeof cfg.updateMessage === "string" && cfg.updateMessage !== ""
            ? cfg.updateMessage
            : "New spends detected. Updating...";
          indicator.classList.add("is-visible");
          window.setTimeout(() => window.location.reload(), 450);
        }
      } catch (_error) {
        // Silent retry on next tick.
      } finally {
        pending = false;
      }
    }

    poll();
    window.setInterval(poll, POLL_INTERVAL_MS);
  }

  function ensureIndicator() {
    const existing = document.querySelector(".spend-live-indicator");
    if (existing instanceof HTMLElement) {
      return existing;
    }

    const node = document.createElement("div");
    node.className = "spend-live-indicator";
    node.setAttribute("aria-live", "polite");
    document.body.appendChild(node);

    return node;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
    return;
  }

  init();
})();
