document.addEventListener("DOMContentLoaded", () => {
  const navigation = document.querySelector("[data-mobile-navigation]");
  const drawer = navigation?.querySelector("#apexMobileDrawer");
  const backdrop = navigation?.querySelector("[data-mobile-nav-backdrop]");
  const toggle = navigation?.querySelector("[data-mobile-nav-toggle]");
  const closeButton = navigation?.querySelector("[data-mobile-nav-close]");
  const filterInput = navigation?.querySelector("[data-mobile-nav-filter]");
  const emptyMessage = navigation?.querySelector("[data-mobile-nav-empty]");
  const peopleSearchResults = navigation?.querySelector(
    "[data-mobile-people-search-results]",
  );
  const peopleSearchEndpoint = peopleSearchResults?.dataset.endpoint;
  if (!navigation || !drawer || !backdrop || !toggle) return;

  let closeTimer = 0;
  let peopleSearchTimer = 0;
  let peopleSearchController = null;
  let previousFocus = null;

  const setPeopleSearchMessage = (message) => {
    if (!peopleSearchResults) return;
    peopleSearchResults.replaceChildren();
    const status = document.createElement("p");
    status.className = "portal-people-search-message";
    status.textContent = message;
    peopleSearchResults.appendChild(status);
  };

  const focusableItems = () =>
    Array.from(
      drawer.querySelectorAll(
        'a[href]:not([hidden]), button:not([disabled]):not([hidden]), input:not([disabled]):not([hidden])',
      ),
    ).filter((item) => item.getClientRects().length > 0);

  const openDrawer = () => {
    window.clearTimeout(closeTimer);
    previousFocus = document.activeElement;
    drawer.hidden = false;
    backdrop.hidden = false;
    drawer.inert = false;
    drawer.setAttribute("aria-hidden", "false");
    drawer.setAttribute("aria-modal", "true");
    document.body.classList.add("nav-open");
    toggle.setAttribute("aria-expanded", "true");
    toggle.setAttribute("aria-label", "Close navigation");
    window.requestAnimationFrame(() => {
      drawer.classList.add("is-active");
      backdrop.classList.add("is-active");
      if (filterInput) {
        filterInput.focus();
      } else {
        focusableItems()[0]?.focus();
      }
    });
  };

  const closeDrawer = (restoreFocus = true) => {
    if (drawer.hidden) return;
    drawer.classList.remove("is-active");
    backdrop.classList.remove("is-active");
    drawer.inert = true;
    drawer.setAttribute("aria-hidden", "true");
    drawer.removeAttribute("aria-modal");
    document.body.classList.remove("nav-open");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Open navigation");
    if (filterInput) filterInput.value = "";
    updateFilter();
    window.clearTimeout(peopleSearchTimer);
    peopleSearchController?.abort();
    setPeopleSearchMessage("Enter at least 2 characters.");
    window.clearTimeout(closeTimer);
    closeTimer = window.setTimeout(() => {
      drawer.hidden = true;
      backdrop.hidden = true;
      if (restoreFocus && previousFocus instanceof HTMLElement) previousFocus.focus();
    }, 300);
  };

  toggle.addEventListener("click", () => {
    if (drawer.hidden) openDrawer();
    else closeDrawer();
  });
  closeButton?.addEventListener("click", () => closeDrawer());
  backdrop.addEventListener("click", () => closeDrawer());

  const updateFilter = () => {
    const query = filterInput?.value.trim().toLocaleLowerCase() ?? "";
    let visibleItems = 0;
    navigation.querySelectorAll("[data-mobile-nav-item]").forEach((item) => {
      item.hidden = !item.textContent.toLocaleLowerCase().includes(query);
      if (!item.hidden) visibleItems += 1;
    });
    const peopleQueryActive = Boolean(
      peopleSearchResults && (filterInput?.value.trim().length ?? 0) >= 2,
    );
    if (emptyMessage)
      emptyMessage.hidden = visibleItems > 0 || peopleQueryActive;
  };

  const renderPeopleResults = (users) => {
    if (!peopleSearchResults) return;
    peopleSearchResults.replaceChildren();
    if (!users.length) {
      setPeopleSearchMessage("No matching people found.");
      return;
    }

    users.forEach((user) => {
      const destination = new URL(
        user.target_url,
        new URL(peopleSearchEndpoint, window.location.href),
      );
      if (destination.origin !== window.location.origin) return;

      const result = document.createElement("a");
      result.className = "portal-people-search-result";
      result.setAttribute("role", "listitem");
      result.href = destination.href;

      const heading = document.createElement("div");
      heading.className = "portal-people-search-result-heading";
      const name = document.createElement("strong");
      name.textContent = `${user.first_name} ${user.last_name}`.trim();
      const status = document.createElement("span");
      const statusClass = String(user.account_status)
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, "");
      status.className = `portal-people-search-status status-${statusClass}`;
      status.textContent = user.account_status;
      heading.append(name, status);

      const identifier = document.createElement("p");
      identifier.className = "portal-people-search-identifier";
      identifier.textContent = user.id_number || "Account ID not assigned";
      const email = document.createElement("p");
      email.textContent = user.email;
      const details = document.createElement("small");
      const created = user.created_at
        ? new Date(user.created_at.replace(" ", "T")).toLocaleDateString()
        : "";
      const role = `${String(user.role).charAt(0).toUpperCase()}${String(user.role).slice(1)}`;
      details.textContent = `${role}${created ? ` · Created ${created}` : ""}`;
      result.append(heading, identifier, email, details);
      peopleSearchResults.appendChild(result);
    });
  };

  const runPeopleSearch = async (query) => {
    if (!peopleSearchResults || !peopleSearchEndpoint) return;
    peopleSearchController?.abort();
    peopleSearchController = new AbortController();
    setPeopleSearchMessage("Searching people...");

    try {
      const response = await fetch(
        `${peopleSearchEndpoint}?q=${encodeURIComponent(query)}`,
        {
          headers: { Accept: "application/json" },
          signal: peopleSearchController.signal,
        },
      );
      if (!response.ok) throw new Error("People search is unavailable.");
      const payload = await response.json();
      renderPeopleResults(
        Array.isArray(payload.results) ? payload.results : [],
      );
    } catch (error) {
      if (error.name !== "AbortError") {
        setPeopleSearchMessage("Unable to search people right now.");
      }
    }
  };

  navigation.querySelectorAll("[data-mobile-nav-item]").forEach((item) => {
    if (item instanceof HTMLAnchorElement) {
      item.addEventListener("click", () => closeDrawer(false));
    }
  });
  navigation
    .querySelector("[data-fiscal-year-open]")
    ?.addEventListener("click", () => closeDrawer(false));

  filterInput?.addEventListener("input", () => {
    updateFilter();
    if (!peopleSearchResults || !peopleSearchEndpoint) return;

    const query = filterInput.value.trim();
    window.clearTimeout(peopleSearchTimer);
    peopleSearchController?.abort();
    if (query.length < 2) {
      setPeopleSearchMessage("Enter at least 2 characters.");
      return;
    }

    setPeopleSearchMessage("Searching people...");
    peopleSearchTimer = window.setTimeout(() => runPeopleSearch(query), 180);
  });

  peopleSearchResults?.addEventListener("click", (event) => {
    if (event.target.closest("a.portal-people-search-result"))
      closeDrawer(false);
  });

  document.addEventListener("keydown", (event) => {
    if (drawer.hidden) return;
    if (event.key === "Escape") {
      event.preventDefault();
      closeDrawer();
      return;
    }
    if (event.key !== "Tab") return;

    const items = focusableItems();
    if (!items.length) return;
    const firstItem = items[0];
    const lastItem = items[items.length - 1];
    if (event.shiftKey && document.activeElement === firstItem) {
      event.preventDefault();
      lastItem.focus();
    } else if (!event.shiftKey && document.activeElement === lastItem) {
      event.preventDefault();
      firstItem.focus();
    }
  });

  window.matchMedia("(min-width: 769px)").addEventListener("change", (event) => {
    if (event.matches) closeDrawer(false);
  });
});