document.addEventListener("DOMContentLoaded", () => {
  const supportRequestCheckboxes = Array.from(
    document.querySelectorAll("[data-support-request-select]"),
  );
  const deleteSupportRequestsForm = document.querySelector(
    "[data-support-delete-form]",
  );
  const deleteSupportRequestsButton = deleteSupportRequestsForm?.querySelector(
    "[data-support-delete-button]",
  );
  const selectedSupportRequestCount = deleteSupportRequestsButton?.querySelector(
    "[data-support-selected-count]",
  );
  const updateSupportRequestSelection = () => {
    const selectedCount = supportRequestCheckboxes.filter(
      (checkbox) => checkbox.checked,
    ).length;
    if (deleteSupportRequestsButton) {
      deleteSupportRequestsButton.disabled = selectedCount === 0;
      deleteSupportRequestsButton.setAttribute(
        "aria-label",
        selectedCount
          ? `Permanently delete ${selectedCount} selected requests`
          : "Delete selected requests",
      );
      deleteSupportRequestsButton.title = selectedCount
        ? `Permanently delete ${selectedCount} selected requests`
        : "Permanently delete selected requests";
    }
    if (selectedSupportRequestCount) {
      selectedSupportRequestCount.textContent = String(selectedCount);
      selectedSupportRequestCount.hidden = selectedCount === 0;
    }
  };
  supportRequestCheckboxes.forEach((checkbox) =>
    checkbox.addEventListener("change", updateSupportRequestSelection),
  );
  deleteSupportRequestsForm?.addEventListener("submit", (event) => {
    if (!supportRequestCheckboxes.some((checkbox) => checkbox.checked)) {
      event.preventDefault();
      return;
    }
    if (
      !window.confirm(
        deleteSupportRequestsForm.dataset.confirmMessage ??
          "Permanently delete the selected requests? This cannot be undone.",
      )
    ) {
      event.preventDefault();
    }
  });

  const sortValue = (cell) => {
    const rawValue = cell.dataset.sortValue ?? cell.textContent;
    const value = rawValue.trim().replace(/\s+/g, " ");
    if (value === "" || value === "—") return { type: "empty", value: "" };
    const datePattern =
      /^(?:\d{4}-\d{2}-\d{2}(?:[ T].*)?|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{1,2}\s+[A-Za-z]{3,9},?\s+\d{4})$/;
    if (datePattern.test(value)) {
      const timestamp = Date.parse(value);
      if (!Number.isNaN(timestamp)) return { type: "number", value: timestamp };
    }

    const numericValue = value.replace(/[$,%]/g, "").replace(/,/g, "");
    if (/^[-+]?\d+(?:\.\d+)?$/.test(numericValue)) {
      return { type: "number", value: Number(numericValue) };
    }
    return { type: "text", value };
  };

  const collator = new Intl.Collator(undefined, {
    numeric: true,
    sensitivity: "base",
  });
  document.querySelectorAll(".data-table").forEach((table) => {
    if (table.hasAttribute("data-no-sort")) return;
    const headerRow = table.tHead?.rows[0];
    const body = table.tBodies[0];
    if (!headerRow || !body) return;

    Array.from(headerRow.cells).forEach((header, columnIndex) => {
      const label = header.textContent.trim().replace(/\s+/g, " ");
      if (!label) return;

      const button = document.createElement("button");
      button.type = "button";
      button.className = "portal-table-sort";
      button.setAttribute("aria-label", `Sort by ${label} ascending`);
      while (header.firstChild) button.appendChild(header.firstChild);
      const indicator = document.createElement("span");
      indicator.className = "portal-table-sort-indicator";
      indicator.setAttribute("aria-hidden", "true");
      indicator.textContent = "↕";
      button.appendChild(indicator);
      header.appendChild(button);
      header.setAttribute("aria-sort", "none");

      let direction = 0;
      button.addEventListener("click", () => {
        direction = direction === 1 ? -1 : 1;
        const rows = Array.from(body.rows);
        const sortableRows = rows.filter(
          (row) => !row.querySelector("td[colspan]"),
        );
        const fixedRows = rows.filter((row) =>
          row.querySelector("td[colspan]"),
        );

        sortableRows
          .map((row, originalIndex) => ({
            row,
            originalIndex,
            value: row.cells[columnIndex]
              ? sortValue(row.cells[columnIndex])
              : { type: "empty", value: "" },
          }))
          .sort((left, right) => {
            const leftEmpty = left.value.type === "empty";
            const rightEmpty = right.value.type === "empty";
            if (leftEmpty !== rightEmpty) return leftEmpty ? 1 : -1;

            const comparison =
              left.value.type === "number" && right.value.type === "number"
                ? left.value.value - right.value.value
                : collator.compare(String(left.value.value), String(right.value.value));
            return comparison === 0
              ? left.originalIndex - right.originalIndex
              : comparison * direction;
          })
          .forEach(({ row }) => body.appendChild(row));
        fixedRows.forEach((row) => body.appendChild(row));

        Array.from(headerRow.cells).forEach((cell) => {
          cell.setAttribute("aria-sort", cell === header ? (direction === 1 ? "ascending" : "descending") : "none");
          const sortButton = cell.querySelector(".portal-table-sort");
          const sortIndicator = sortButton?.querySelector(".portal-table-sort-indicator");
          if (!sortButton || !sortIndicator) return;
          const active = cell === header;
          sortIndicator.textContent = active ? (direction === 1 ? "↑" : "↓") : "↕";
          sortButton.setAttribute(
            "aria-label",
            `Sort by ${cell.textContent.replace(/[↕↑↓]/g, "").trim()} ${active && direction === 1 ? "descending" : "ascending"}`,
          );
        });
      });
    });
  });

  const shell = document.getElementById("studentDashboardShell");
  document.querySelectorAll("[data-support-logout-link]").forEach((link) => {
    link.addEventListener("click", (event) => {
      if (!window.confirm("You need to sign out before opening public support. Continue?")) {
        event.preventDefault();
      }
    });
  });
  const navToggle = document.querySelector("[data-portal-nav-toggle]");
  const searchToggle = document.querySelector("[data-people-search-toggle]");
  const sidebar = shell?.querySelector(".student-sidebar");
  const sidebarBackdrop = document.querySelector(".student-sidebar-backdrop");
  const sidebarNav = sidebar?.querySelector(".student-sidebar-nav");
  const searchPanel = sidebar?.querySelector("[data-people-search-panel]");
  const searchInput = searchPanel?.querySelector("[data-people-search-input]");
  const searchResults = searchPanel?.querySelector(
    "[data-people-search-results]",
  );
  const searchEndpoint = searchPanel?.dataset.endpoint;
  if (!shell || !sidebar) return;

  const isMobile = () => window.matchMedia("(max-width: 900px)").matches;
  const setSidebarHidden = (hidden) => {
    sidebar.inert = hidden;
    if (hidden) {
      sidebar.setAttribute("aria-hidden", "true");
    } else {
      sidebar.removeAttribute("aria-hidden");
    }
  };

  const setMobileOpen = (open) => {
    shell.classList.toggle("sidebar-open", open);
    setSidebarHidden(!open);
    navToggle?.setAttribute("aria-expanded", String(open));
    navToggle?.setAttribute(
      "aria-label",
      open ? "Close navigation" : "Open navigation",
    );
  };

  let searchOpen = false;
  let searchTimer = 0;
  let searchController = null;

  const setSearchMessage = (message) => {
    if (!searchResults) return;
    searchResults.replaceChildren();
    const status = document.createElement("p");
    status.className = "portal-people-search-message";
    status.textContent = message;
    searchResults.appendChild(status);
  };

  const renderSearchResults = (users) => {
    if (!searchResults) return;
    searchResults.replaceChildren();
    if (!users.length) {
      setSearchMessage("No matching people found.");
      return;
    }

    users.forEach((user) => {
      const result = document.createElement("a");
      result.className = "portal-people-search-result";
      result.setAttribute("role", "listitem");
      const destination = new URL(
        user.target_url,
        new URL(searchEndpoint, window.location.href),
      );
      if (destination.origin !== window.location.origin) return;
      result.href = destination.href;

      const heading = document.createElement("div");
      heading.className = "portal-people-search-result-heading";
      const name = document.createElement("strong");
      name.textContent = `${user.first_name} ${user.last_name}`.trim();
      const status = document.createElement("span");
      status.className = `portal-people-search-status status-${String(user.account_status).toLowerCase()}`;
      status.textContent = user.account_status;
      heading.append(name, status);

      const email = document.createElement("p");
      email.textContent = user.email;
      const identifier = document.createElement("p");
      identifier.className = "portal-people-search-identifier";
      identifier.textContent = user.id_number || "Account ID not assigned";
      const details = document.createElement("small");
      const created = user.created_at
        ? new Date(user.created_at.replace(" ", "T")).toLocaleDateString()
        : "";
      details.textContent = `${String(user.role).charAt(0).toUpperCase()}${String(user.role).slice(1)}${created ? ` · Created ${created}` : ""}`;
      result.append(heading, identifier, email, details);
      searchResults.appendChild(result);
    });
  };

  const runPeopleSearch = async () => {
    if (!searchInput || !searchEndpoint) return;
    const query = searchInput.value.trim();
    if (query.length < 2) {
      setSearchMessage("Enter at least 2 characters.");
      return;
    }

    searchController?.abort();
    searchController = new AbortController();
    setSearchMessage("Searching...");

    try {
      const response = await fetch(
        `${searchEndpoint}?q=${encodeURIComponent(query)}`,
        {
          headers: { Accept: "application/json" },
          signal: searchController.signal,
        },
      );
      if (!response.ok) throw new Error("Search is unavailable.");
      const payload = await response.json();
      renderSearchResults(
        Array.isArray(payload.results) ? payload.results : [],
      );
    } catch (error) {
      if (error.name !== "AbortError")
        setSearchMessage("Unable to search right now.");
    }
  };

  const setSearchOpen = (open, focusInput = true) => {
    searchOpen = open;
    shell.classList.toggle("people-search-open", open);
    if (open) {
      shell.classList.remove("sidebar-collapsed");
      setSidebarHidden(false);
    }
    sidebar.classList.toggle("people-search-active", open);
    if (searchPanel) {
      searchPanel.hidden = !open;
      searchPanel.inert = !open;
    }
    if (sidebarNav) sidebarNav.inert = open;
    searchToggle?.setAttribute("aria-expanded", String(open));
    searchToggle?.setAttribute(
      "aria-label",
      open ? "Minimize people search" : "Search people",
    );
    navToggle?.setAttribute(
      "aria-expanded",
      String(
        !open && (!isMobile() || shell.classList.contains("sidebar-open")),
      ),
    );
    navToggle?.setAttribute(
      "aria-label",
      open ? "Return to role navigation" : "Open navigation",
    );

    if (open && isMobile()) {
      setMobileOpen(true);
      navToggle?.setAttribute("aria-expanded", "false");
      navToggle?.setAttribute("aria-label", "Return to role navigation");
    }
    if (open && focusInput) searchInput?.focus();
  };

  if (isMobile()) setMobileOpen(false);
  else {
    setSidebarHidden(false);
    navToggle?.setAttribute("aria-expanded", "true");
    navToggle?.setAttribute("aria-label", "Role navigation");
  }

  navToggle?.addEventListener("click", () => {
    if (searchToggle && searchOpen) {
      setSearchOpen(false, false);
      if (isMobile()) setMobileOpen(true);
      return;
    }

    if (isMobile()) {
      setMobileOpen(!shell.classList.contains("sidebar-open"));
      return;
    }

    const collapsed = shell.classList.toggle("sidebar-collapsed");
    setSidebarHidden(collapsed);
    navToggle.setAttribute("aria-expanded", String(!collapsed));
    navToggle.setAttribute(
      "aria-label",
      collapsed ? "Expand navigation" : "Collapse navigation",
    );
  });

  searchToggle?.addEventListener("click", () => {
    if (!searchOpen) {
      setSearchOpen(true);
      return;
    }

    setSearchOpen(false, false);
    if (isMobile()) {
      setMobileOpen(false);
      return;
    }

    shell.classList.add("sidebar-collapsed");
    setSidebarHidden(true);
    navToggle?.setAttribute("aria-expanded", "false");
    navToggle?.setAttribute("aria-label", "Expand navigation");
  });
  searchInput?.addEventListener("input", () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(runPeopleSearch, 220);
  });

  sidebarBackdrop?.addEventListener("click", () => {
    if (searchOpen) setSearchOpen(false, false);
    setMobileOpen(false);
  });
  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    if (searchOpen) setSearchOpen(false, false);
    if (isMobile()) setMobileOpen(false);
  });
});
