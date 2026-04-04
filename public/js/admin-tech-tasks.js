(function () {
  function init() {
    if (!window.adminTechTasksBoard) {
      return;
    }

    const config = window.adminTechTasksBoard;
    const board = document.querySelector("[data-tech-board]");
    if (!(board instanceof HTMLElement)) {
      return;
    }

    const columns = Array.from(board.querySelectorAll("[data-tech-status]"));
    if (columns.length === 0) {
      return;
    }

    let draggedCard = null;

    board.addEventListener("dragstart", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const card = target.closest("[data-tech-task-id]");
      if (!(card instanceof HTMLElement)) {
        return;
      }

      draggedCard = card;
      card.classList.add("is-dragging");
      event.dataTransfer?.setData("text/plain", card.dataset.techTaskId || "");
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
      }
    });

    board.addEventListener("dragend", () => {
      if (!(draggedCard instanceof HTMLElement)) {
        return;
      }

      draggedCard.classList.remove("is-dragging");
      draggedCard = null;
    });

    columns.forEach((column) => {
      const list = column.querySelector("[data-tech-column-list]");
      if (!(list instanceof HTMLElement)) {
        return;
      }

      list.addEventListener("dragover", (event) => {
        if (!(draggedCard instanceof HTMLElement)) {
          return;
        }

        event.preventDefault();
        const afterElement = getDragAfterElement(list, event.clientY);
        if (!afterElement) {
          list.appendChild(draggedCard);
        } else {
          list.insertBefore(draggedCard, afterElement);
        }
        refreshEmptyAndCounts(board);
      });

      list.addEventListener("drop", async (event) => {
        if (!(draggedCard instanceof HTMLElement)) {
          return;
        }

        event.preventDefault();
        const status = column.dataset.techStatus || "";
        await persistMove(config, status, draggedCard, list);
        refreshEmptyAndCounts(board);
      });
    });

    refreshEmptyAndCounts(board);
  }

  async function persistMove(config, status, card, list) {
    const taskId = card.dataset.techTaskId;
    if (!taskId || !status) {
      return;
    }

    const orderedIds = Array.from(list.querySelectorAll("[data-tech-task-id]"))
      .map((element) => element.dataset.techTaskId)
      .filter(Boolean);

    const payload = new URLSearchParams();
    payload.set("_token", config.csrfToken || "");
    payload.set("status", status);
    orderedIds.forEach((id) => payload.append("orderedIds[]", id));

    const url = String(config.moveUrlTemplate || "").replace(
      "TASK_ID",
      encodeURIComponent(taskId)
    );

    try {
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
        window.alert(
          (data && data.error) ||
            config?.i18n?.unableMove ||
            "Unable to move task."
        );
        window.location.reload();
      }
    } catch (error) {
      window.alert(config?.i18n?.networkError || "Network error.");
      window.location.reload();
    }
  }

  function getDragAfterElement(container, y) {
    const cards = Array.from(
      container.querySelectorAll("[data-tech-task-id]:not(.is-dragging)")
    );

    let closest = {
      offset: Number.NEGATIVE_INFINITY,
      element: null,
    };

    cards.forEach((card) => {
      const box = card.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset, element: card };
      }
    });

    return closest.element;
  }

  function refreshEmptyAndCounts(board) {
    const columns = Array.from(board.querySelectorAll("[data-tech-status]"));
    columns.forEach((column) => {
      const list = column.querySelector("[data-tech-column-list]");
      const empty = column.querySelector("[data-tech-empty]");
      const count = column.querySelector("[data-tech-count]");
      if (!(list instanceof HTMLElement)) {
        return;
      }

      const cardsCount = list.querySelectorAll("[data-tech-task-id]").length;
      if (empty instanceof HTMLElement) {
        empty.hidden = cardsCount > 0;
      }
      if (count instanceof HTMLElement) {
        count.textContent = String(cardsCount);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
    return;
  }

  init();
})();
