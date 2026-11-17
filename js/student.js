document.addEventListener("DOMContentLoaded", () => {
  document
    .querySelectorAll('.student-assessment-question input[type="text"]')
    .forEach((input) => {
      input.addEventListener("input", () => {
        input.value = input.value.toLowerCase();
      });
    });
  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabPanels = document.querySelectorAll(".tab-panel");
  const dashboardLinks = document.querySelectorAll(
    ".student-sidebar-link[data-target]",
  );
  const dashboardShell = document.getElementById("studentDashboardShell");
  const sidebarToggle = document.querySelector(".student-sidebar-toggle");
  const mobileToggle = document.querySelector(".student-mobile-toggle");
  const sidebarBackdrop = document.querySelector(".student-sidebar-backdrop");
  const profileMenu = document.querySelector("[data-profile-menu]");
  const profileMenuTrigger = document.querySelector(
    "[data-profile-menu-trigger]",
  );
  const profileMenuContent = document.querySelector(
    "[data-profile-menu-content]",
  );
  if (!document.documentElement.dataset.portalProfileBound) {
    const setProfileMenuOpen = (open) => {
      profileMenuTrigger?.setAttribute("aria-expanded", String(open));
      if (profileMenuContent) profileMenuContent.hidden = !open;
    };

    profileMenuTrigger?.addEventListener("click", () => {
      setProfileMenuOpen(
        profileMenuTrigger.getAttribute("aria-expanded") !== "true",
      );
    });
    document.addEventListener("click", (event) => {
      if (profileMenu && !profileMenu.contains(event.target))
        setProfileMenuOpen(false);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setProfileMenuOpen(false);
    });
    document.documentElement.dataset.portalProfileBound = "true";
  }

  const setSidebarState = (open) => {
    if (!dashboardShell) return;
    dashboardShell.classList.toggle("sidebar-open", open);
    mobileToggle?.setAttribute("aria-expanded", String(open));
    sidebarToggle?.setAttribute("aria-expanded", String(open));
    sidebarToggle?.setAttribute(
      "aria-label",
      open ? "Close navigation" : "Open navigation",
    );
    if (mobileToggle) {
      mobileToggle.setAttribute(
        "aria-label",
        open ? "Close student navigation" : "Open student navigation",
      );
    }
  };

  if (document.documentElement.dataset.portalSidebarBound) return;
  document.documentElement.dataset.portalSidebarBound = "true";

  sidebarToggle?.addEventListener("click", () => {
    if (window.matchMedia("(max-width: 900px)").matches) {
      setSidebarState(!dashboardShell?.classList.contains("sidebar-open"));
      return;
    }

    const collapsed =
      dashboardShell?.classList.toggle("sidebar-collapsed") ?? false;
    sidebarToggle.setAttribute("aria-expanded", String(!collapsed));
    sidebarToggle.setAttribute(
      "aria-label",
      collapsed ? "Expand navigation" : "Collapse navigation",
    );
  });

  mobileToggle?.addEventListener("click", () => {
    setSidebarState(!dashboardShell?.classList.contains("sidebar-open"));
  });
  sidebarBackdrop?.addEventListener("click", () => setSidebarState(false));

  const activatePanel = (targetId) => {
    const targetPanel = document.getElementById(targetId);
    if (!targetPanel || !targetPanel.classList.contains("tab-panel")) return;

    tabPanels.forEach((panel) =>
      panel.classList.toggle("active", panel === targetPanel),
    );
    document.querySelectorAll(".student-sidebar-link").forEach((link) => {
      link.classList.toggle("active", link.dataset.target === targetId);
    });
    if (window.matchMedia("(max-width: 900px)").matches) {
      setSidebarState(false);
    }
  };

  dashboardLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      const targetId = link.dataset.target;
      if (!targetId) return;
      history.replaceState(null, "", `#${targetId}`);
      activatePanel(targetId);
    });
  });

  if (window.location.hash) {
    activatePanel(window.location.hash.slice(1));
  }

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-target");

      tabBtns.forEach((b) => b.classList.remove("active"));
      tabPanels.forEach((p) => p.classList.remove("active"));

      btn.classList.add("active");
      const targetPanel = document.getElementById(targetId);
      if (targetPanel) {
        targetPanel.classList.add("active");
      }
      if (window.matchMedia("(max-width: 900px)").matches) {
        setSidebarState(false);
      }
    });
  });

  const catalogSearch = document.getElementById("catalogSearch");
  if (catalogSearch) {
    catalogSearch.addEventListener("input", () => {
      const query = catalogSearch.value.toLowerCase().trim();
      const courseCards = document.querySelectorAll(".course-card");

      courseCards.forEach((card) => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
          card.style.display = "flex";
        } else {
          card.style.display = "none";
        }
      });
    });
  }

  const announcementSearch = document.getElementById("announcementSearch");
  const announcementItems = document.querySelectorAll(
    "[data-announcement-item]",
  );
  const announcementNoResults = document.getElementById(
    "announcementNoResults",
  );
  announcementSearch?.addEventListener("input", () => {
    const query = announcementSearch.value.trim().toLocaleLowerCase();
    let visibleCount = 0;

    announcementItems.forEach((item) => {
      const matches = item.textContent.toLocaleLowerCase().includes(query);
      item.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    if (announcementNoResults) {
      announcementNoResults.hidden = visibleCount > 0;
    }
  });
});
