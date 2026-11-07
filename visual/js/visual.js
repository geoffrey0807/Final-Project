/**
 * APEX Learning Hub — Visual Core JavaScript
 * Handles Global Search, Guide Cards,
 * Mobile Navigation, and Interactive Picture Containers.
 */

// Comprehensive Search Index across all visual pages & portal
const SEARCH_INDEX = [
  {
    title: "Home — Welcome to APEX Learning Hub",
    page: "Homepage",
    url: "homepage.php",
  },
  {
    title: "Academic Programs & Offerings",
    page: "Offerings",
    url: "homepage.php#offerings",
  },
  {
    title: "Digital Library & Learning Resources",
    page: "Offerings",
    url: "homepage.php#offerings",
  },
  {
    title: "Interactive Coursework Tracker",
    page: "Offerings",
    url: "homepage.php#offerings",
  },
  {
    title: "Campus Bulletins & Announcements Preview",
    page: "Announcements",
    url: "homepage.php#announcements",
  },
  {
    title: "Campus Essentials & Student Guide (FAQ)",
    page: "FAQ & Guide",
    url: "homepage.php#essentials",
  },
  {
    title: "About APEX Learning Hub",
    page: "About",
    url: "homepage.php#about",
  },

  {
    title: "All Campus Announcements & Advisories",
    page: "Announcements",
    url: "homepage.php#announcements",
  },

  {
    title: "Student Code of Conduct",
    page: "Policies",
    url: "policies.php#code-of-conduct",
  },
  {
    title: "Academic Integrity Policy",
    page: "Policies",
    url: "policies.php#academic-integrity",
  },
  {
    title: "Attendance & Participation Rules",
    page: "Policies",
    url: "policies.php#attendance",
  },
  {
    title: "Grading System & Evaluation Scale",
    page: "Policies",
    url: "policies.php#grading",
  },
  {
    title: "Data Privacy Notice & Protection",
    page: "Policies",
    url: "policies.php#privacy",
  },
  {
    title: "Acceptable Digital Use Policy",
    page: "Policies",
    url: "policies.php#digital-use",
  },

  {
    title: "APEX Local Support Assistant",
    page: "Support",
    url: "homepage.php?chat=1",
  },
  {
    title: "Send an Inquiry to Academic Support",
    page: "Support",
    url: "homepage.php?chat=1",
  },

  {
    title: "APEX Academic Portal — Sign In",
    page: "Portal Sign In",
    url: "../auth/login.php",
  },
];

/* ── 1. Global Live Search Functionality ─────────────────────────── */
function initGlobalSearch() {
  const searchInput = document.getElementById("globalSearch");
  const resultsBox = document.getElementById("searchResults");
  const clearBtn = document.getElementById("searchClear");
  if (!searchInput || !resultsBox) return;

  let debounceTimer;

  searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    const query = searchInput.value.trim();

    if (clearBtn) {
      clearBtn.style.display = query.length > 0 ? "block" : "none";
    }

    debounceTimer = setTimeout(() => {
      renderSearchResults(query, resultsBox);
    }, 120);
  });

  searchInput.addEventListener("focus", () => {
    const query = searchInput.value.trim();
    if (query.length > 0) {
      renderSearchResults(query, resultsBox);
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      searchInput.value = "";
      clearBtn.style.display = "none";
      resultsBox.classList.remove("visible");
      searchInput.focus();
    });
  }

  // Pressing Enter navigates directly to the top match
  searchInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      const firstResult = resultsBox.querySelector(".search-result-item");
      if (firstResult) {
        window.location.href = firstResult.getAttribute("href");
      } else if (searchInput.value.trim().length > 0) {
        window.location.href = `${document.body.dataset.visualBase || ""}announcement.php`;
      }
    } else if (e.key === "Escape") {
      resultsBox.classList.remove("visible");
    }
  });

  // Close dropdown on outside click
  document.addEventListener("click", (e) => {
    if (!searchInput.closest(".sub-search").contains(e.target)) {
      resultsBox.classList.remove("visible");
    }
  });
}

function renderSearchResults(query, container) {
  if (query.length === 0) {
    container.classList.remove("visible");
    return;
  }

  const q = query.toLowerCase();
  const matches = SEARCH_INDEX.filter(
    (item) =>
      item.title.toLowerCase().includes(q) ||
      item.page.toLowerCase().includes(q),
  ).slice(0, 6);

  if (matches.length === 0) {
    container.innerHTML = `
            <div class="search-empty">
                No matching pages found for "<strong>${escapeHtml(query)}</strong>".
                <div style="margin-top:6px; font-size:12px; color:var(--text-light);">
                    Try searching for "announcements", "policies", or "portal".
                </div>
            </div>
        `;
  } else {
    const visualBase = document.body.dataset.visualBase || "";
    container.innerHTML = matches
      .map(
        (m) => `
            <a class="search-result-item" href="${visualBase}${m.url}">
                <span class="res-title">${highlightMatch(m.title, query)}</span>
                <span class="res-page">${escapeHtml(m.page)} &bull; View details &rarr;</span>
            </a>
        `,
      )
      .join("");
  }

  container.classList.add("visible");
}

function highlightMatch(text, query) {
  const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const regex = new RegExp(`(${escapedQuery})`, "gi");
  return escapeHtml(text).replace(
    regex,
    '<mark style="background:#fee2e2;color:#991b1b;padding:0 2px;border-radius:2px;">$1</mark>',
  );
}

function escapeHtml(str) {
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

/* ── 2. Homepage Guide Card Expansion ────────────────────────────── */
function refreshGuideCards() {
  document.querySelectorAll(".guide-card").forEach((card) => {
    const answer = card.querySelector(".guide-answer-copy");
    const toggle = card.querySelector(".guide-toggle");
    if (!answer || !toggle) return;

    const wasExpanded = card.classList.contains("expanded");
    card.classList.remove("expanded");
    card.classList.add("is-measuring");
    const hasOverflow = answer.scrollHeight > answer.clientHeight + 1;
    card.classList.toggle("has-overflow", hasOverflow);
    card.classList.remove("is-measuring");
    card.classList.toggle("expanded", wasExpanded);

    toggle.setAttribute("aria-expanded", wasExpanded ? "true" : "false");
    toggle.textContent = wasExpanded ? "Show less" : "Read more";
  });
}

function initGuideCards() {
  const guideCards = document.querySelectorAll(".guide-card");
  if (!guideCards.length) return;

  guideCards.forEach((card) => {
    const toggle = card.querySelector(".guide-toggle");
    if (!toggle) return;

    toggle.addEventListener("click", () => {
      const isExpanded = card.classList.toggle("expanded");
      toggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
      refreshGuideCards();
    });
  });

  refreshGuideCards();
  window.addEventListener("resize", refreshGuideCards);
}

/* ── 3. Picture Containers: Hover & Click Highlight ─────────────── */
function initMediaCards() {
  const cards = document.querySelectorAll(".media-card");

  cards.forEach((card) => {
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const viewport = card
          .closest(".media-carousel")
          ?.querySelector(".media-carousel-viewport");
        if (!viewport) return;

        const behavior = window.matchMedia("(prefers-reduced-motion: reduce)")
          .matches
          ? "auto"
          : "smooth";
        viewport.scrollTo({
          left: getCarouselCardOffset(viewport, card),
          behavior,
        });
      }
    });
  });
}

function updateCenteredCard(viewport, cards) {
  const viewportCenter =
    viewport.getBoundingClientRect().left + viewport.clientWidth / 2;
  let centeredCard = null;
  let closestDistance = Infinity;

  cards.forEach((card) => {
    const bounds = card.getBoundingClientRect();
    const distance = Math.abs(bounds.left + bounds.width / 2 - viewportCenter);
    if (distance < closestDistance) {
      closestDistance = distance;
      centeredCard = card;
    }
  });

  cards.forEach((card) =>
    card.classList.toggle("is-center", card === centeredCard),
  );
}

function getCarouselCardOffset(viewport, card) {
  const viewportBounds = viewport.getBoundingClientRect();
  const cardBounds = card.getBoundingClientRect();
  return (
    viewport.scrollLeft +
    cardBounds.left -
    viewportBounds.left -
    (viewport.clientWidth - cardBounds.width) / 2
  );
}

function initMediaCarousel() {
  const carousel = document.querySelector(".media-carousel");
  const viewport = carousel?.querySelector(".media-carousel-viewport");
  const track = carousel?.querySelector(".media-grid");
  const originalCards = track
    ? Array.from(track.querySelectorAll(".media-card"))
    : [];
  if (!carousel || !viewport || !track || !originalCards.length) return;

  const createLoopClone = (card) => {
    const clone = card.cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    clone.setAttribute("tabindex", "-1");
    return clone;
  };

  const cloneGroups = 3;
  const beforeClones = Array.from({ length: cloneGroups }, () =>
    originalCards.map(createLoopClone),
  ).flat();
  const afterClones = Array.from({ length: cloneGroups }, () =>
    originalCards.map(createLoopClone),
  ).flat();
  const beforeFragment = document.createDocumentFragment();
  beforeClones.forEach((clone) => beforeFragment.appendChild(clone));
  track.prepend(beforeFragment);
  afterClones.forEach((clone) => track.appendChild(clone));

  let loopStart = 0;
  let loopWidth = 0;
  const alignToOriginals = () => {
    loopStart = getCarouselCardOffset(viewport, originalCards[0]);
    loopWidth = getCarouselCardOffset(viewport, afterClones[0]) - loopStart;
    viewport.scrollLeft = loopStart;
    updateCenteredCard(viewport, Array.from(track.children));
  };
  alignToOriginals();

  let centerFrame = 0;
  const updateCenterOnScroll = () => {
    if (centerFrame) return;
    centerFrame = window.requestAnimationFrame(() => {
      centerFrame = 0;
      updateCenteredCard(viewport, Array.from(track.children));
    });
  };
  viewport.addEventListener("scroll", updateCenterOnScroll, { passive: true });

  const normalizeLoopPosition = () => {
    if (!loopWidth) return;

    if (viewport.scrollLeft < loopStart - loopWidth * 2) {
      viewport.scrollLeft += loopWidth * cloneGroups;
    } else if (viewport.scrollLeft >= loopStart + loopWidth * 2) {
      viewport.scrollLeft -= loopWidth * cloneGroups;
    }
    updateCenteredCard(viewport, Array.from(track.children));
  };

  if ("onscrollend" in viewport) {
    viewport.addEventListener("scrollend", normalizeLoopPosition);
  } else {
    let settleTimer;
    viewport.addEventListener(
      "scroll",
      () => {
        window.clearTimeout(settleTimer);
        settleTimer = window.setTimeout(normalizeLoopPosition, 140);
      },
      { passive: true },
    );
  }

  window.addEventListener("resize", alignToOriginals);

  carousel.querySelectorAll(".media-carousel-arrow").forEach((button) => {
    button.addEventListener("click", () => {
      const firstCard = originalCards[0];
      const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      const step = firstCard.getBoundingClientRect().width + gap;
      const behavior = window.matchMedia("(prefers-reduced-motion: reduce)")
        .matches
        ? "auto"
        : "smooth";

      const direction = button.dataset.direction === "next" ? 1 : -1;
      viewport.scrollBy({ left: direction * step, behavior });
    });
  });
}

function initHomeCardCarousels() {
  document.querySelectorAll(".home-card-carousel").forEach((carousel) => {
    const viewport = carousel.querySelector(".home-card-carousel-viewport");
    const track = carousel.querySelector(".home-card-carousel-track");
    const originalCards = track ? Array.from(track.children) : [];
    if (!viewport || !track || originalCards.length < 2) return;

    const cloneGroups = 3;
    const createLoopClone = (card) => {
      const clone = card.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      clone.querySelectorAll("a, button, [tabindex]").forEach((item) => {
        item.setAttribute("tabindex", "-1");
      });
      clone.querySelectorAll(".guide-card").forEach((guideCard) => {
        const toggle = guideCard.querySelector(".guide-toggle");
        if (!toggle) return;

        toggle.addEventListener("click", () => {
          const isExpanded = guideCard.classList.toggle("expanded");
          toggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
          refreshGuideCards();
        });
      });
      return clone;
    };
    const beforeClones = Array.from({ length: cloneGroups }, () =>
      originalCards.map(createLoopClone),
    ).flat();
    const afterClones = Array.from({ length: cloneGroups }, () =>
      originalCards.map(createLoopClone),
    ).flat();
    const beforeFragment = document.createDocumentFragment();
    beforeClones.forEach((clone) => beforeFragment.appendChild(clone));
    track.prepend(beforeFragment);
    afterClones.forEach((clone) => track.appendChild(clone));

    const allCards = Array.from(track.children);
    let loopStart = 0;
    let loopWidth = 0;
    const alignToOriginals = () => {
      loopStart = getCarouselCardOffset(viewport, originalCards[0]);
      loopWidth = getCarouselCardOffset(viewport, afterClones[0]) - loopStart;
      viewport.scrollLeft = loopStart;
      updateCenteredCard(viewport, allCards);
    };
    alignToOriginals();

    let centerFrame = 0;
    const updateCenterOnScroll = () => {
      if (centerFrame) return;
      centerFrame = window.requestAnimationFrame(() => {
        centerFrame = 0;
        updateCenteredCard(viewport, allCards);
      });
    };
    viewport.addEventListener("scroll", updateCenterOnScroll, {
      passive: true,
    });

    const normalizeLoopPosition = () => {
      if (!loopWidth) return;

      if (viewport.scrollLeft < loopStart - loopWidth * 2) {
        viewport.scrollLeft += loopWidth * cloneGroups;
      } else if (viewport.scrollLeft >= loopStart + loopWidth * 2) {
        viewport.scrollLeft -= loopWidth * cloneGroups;
      }
      updateCenteredCard(viewport, allCards);
    };

    if ("onscrollend" in viewport) {
      viewport.addEventListener("scrollend", normalizeLoopPosition);
    } else {
      let settleTimer;
      viewport.addEventListener(
        "scroll",
        () => {
          window.clearTimeout(settleTimer);
          settleTimer = window.setTimeout(normalizeLoopPosition, 140);
        },
        { passive: true },
      );
    }

    window.addEventListener("resize", alignToOriginals);

    carousel.querySelectorAll(".media-carousel-arrow").forEach((button) => {
      button.addEventListener("click", () => {
        const firstCard = originalCards[0];
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const step = firstCard.getBoundingClientRect().width + gap;
        const behavior = window.matchMedia("(prefers-reduced-motion: reduce)")
          .matches
          ? "auto"
          : "smooth";

        const direction = button.dataset.direction === "next" ? 1 : -1;
        viewport.scrollBy({ left: direction * step, behavior });
      });
    });
  });
}

/* ── 4. Mobile Navigation Menu Toggle ────────────────────────────── */
function initMobileNav() {
  const toggleBtn = document.getElementById("menuToggle");
  const mainNav = document.getElementById("mainNav");
  if (!toggleBtn || !mainNav) return;

  toggleBtn.addEventListener("click", () => {
    const isOpen = mainNav.classList.contains("open");
    mainNav.classList.toggle("open");
    toggleBtn.classList.toggle("open");
    toggleBtn.setAttribute("aria-expanded", !isOpen);
  });

  // Close when clicking any nav link
  mainNav.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", () => {
      mainNav.classList.remove("open");
      toggleBtn.classList.remove("open");
      toggleBtn.setAttribute("aria-expanded", "false");
    });
  });
}

/* ── 5. Smooth Scrolling for Internal Anchor Links ───────────────── */
function initSmoothAnchors() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const targetId = this.getAttribute("href");
      if (targetId === "#" || targetId === "") return;

      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        e.preventDefault();
        const headerOffset = 76;
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition =
          elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: "smooth",
        });
      }
    });
  });
}

/* ── 6. Back-To-Top Button ───────────────────────────────────────── */
function initBackToTop() {
  const btn = document.getElementById("backToTop");
  if (!btn) return;

  window.addEventListener("scroll", () => {
    if (window.pageYOffset > 280) {
      btn.classList.add("visible");
    } else {
      btn.classList.remove("visible");
    }
  });

  btn.addEventListener("click", () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  });
}

/* ── 7. Policies Sidebar Active Scroll (for policies.php) ────────── */
function initPolicySidebar() {
  const navItems = document.querySelectorAll(".policy-nav-item[data-target]");
  if (!navItems.length) return;

  navItems.forEach((item) => {
    item.addEventListener("click", () => {
      const targetId = item.getAttribute("data-target");
      const target = document.getElementById(targetId);
      if (target) {
        const headerOffset = 80;
        const pos =
          target.getBoundingClientRect().top +
          window.pageYOffset -
          headerOffset;
        window.scrollTo({ top: pos, behavior: "smooth" });
      }
    });
  });
}

/* ── 8. Announcement Filter Tabs (for announcement.php) ──────────── */
function initAnnouncementFilter() {
  const tabs = document.querySelectorAll(".filter-tab[data-filter]");
  const cards = document.querySelectorAll(".announcement-card[data-category]");
  if (!tabs.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("active"));
      tabs.forEach((t) => t.setAttribute("aria-selected", "false"));
      tab.classList.add("active");
      tab.setAttribute("aria-selected", "true");

      const filter = tab.getAttribute("data-filter");
      cards.forEach((card) => {
        const match =
          filter === "all" || card.getAttribute("data-category") === filter;
        card.style.display = match ? "" : "none";
      });
    });
  });
}

/* ── 9. Local Support Assistant ──────────────────────────────────── */
function initLocalAssistant() {
  const assistant = document.querySelector("[data-apex-assistant]");
  if (!assistant) return;

  const launcher = assistant.querySelector("[data-assistant-open]");
  const panel = assistant.querySelector("[data-assistant-panel]");
  const closeButton = assistant.querySelector("[data-assistant-close]");
  const messages = assistant.querySelector("[data-assistant-messages]");
  const form = assistant.querySelector("[data-assistant-form]");
  const input = assistant.querySelector("[data-assistant-input]");
  const sendButton = assistant.querySelector("[data-assistant-send]");
  const honeypot = assistant.querySelector("[data-assistant-honeypot]");
  if (!launcher || !panel || !closeButton || !messages || !form || !input || !sendButton || !honeypot) return;

  const subjects = {
    enrollment: "Enrollment Inquiry",
    policy: "Policy Question",
    technical: "Technical Support",
    announcement: "Announcement / Bulletin",
    account_access: "Account Access / Password",
    appeal: "Appeal or Review Request",
    other: "Other",
  };
  const menuText = [
    "Choose a support area:",
    "1. Enrollment inquiry",
    "2. Policy question",
    "3. Technical support",
    "4. Announcement / bulletin",
    "5. Account access / password",
    "6. Appeal or review request",
    "7. Other concern",
  ].join("\n");
  const flow = {
    step: "menu",
    subject: "",
    apexAccount: "",
    email: "",
    firstName: "",
    lastName: "",
    name: "",
    concern: "",
  };
  let welcomed = false;
  const messageQueue = [];
  let isTyping = false;
  let isSubmitting = false;
  const inputPlaceholder = input.placeholder;

  const appendMessage = (text, sender) => {
    const bubble = document.createElement("div");
    bubble.className = `apex-assistant-message apex-assistant-message--${sender}`;
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    return bubble;
  };
  const updateBusyState = () => {
    const busy = isTyping || messageQueue.length > 0 || isSubmitting;
    input.disabled = busy;
    sendButton.disabled = busy;
    input.placeholder = isTyping || messageQueue.length > 0
      ? "Assistant is typing…"
      : inputPlaceholder;
    if (!busy && !panel.hidden) input.focus();
  };
  const typeMessage = async (bubble, text) => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      bubble.textContent = text;
      return;
    }

    bubble.classList.add("is-typing");
    for (let index = 0; index < text.length; index += 1) {
      bubble.textContent += text[index];
      if (index % 3 === 0 || index === text.length - 1) {
        messages.scrollTop = messages.scrollHeight;
      }
      const character = text[index];
      const pause = /[.!?…]/.test(character) ? 28 : /[,;:]/.test(character) ? 14 : 4;
      await new Promise((resolve) => window.setTimeout(resolve, pause));
    }
    bubble.classList.remove("is-typing");
  };
  const playMessageQueue = async () => {
    if (isTyping) return;
    isTyping = true;
    updateBusyState();
    while (messageQueue.length) {
      const text = messageQueue.shift();
      const bubble = appendMessage("", "assistant");
      await typeMessage(bubble, text);
    }
    isTyping = false;
    updateBusyState();
  };
  const reply = (text) => {
    messageQueue.push(text);
    void playMessageQueue();
  };
  const resetFlow = () => {
    flow.step = "menu";
    flow.subject = "";
    flow.apexAccount = "";
    flow.email = "";
    flow.firstName = "";
    flow.lastName = "";
    flow.name = "";
    flow.concern = "";
  };
  const showMenu = () => reply(menuText);
  const sayWelcome = () => {
    if (welcomed) return;
    welcomed = true;
    reply("Hi! I’m the APEX local support assistant. Tell me what you need help with, and I’ll guide you or send your concern to the Admin team.");
    showMenu();
  };
  const setOpen = (open) => {
    panel.hidden = !open;
    launcher.setAttribute("aria-expanded", String(open));
    if (open) {
      sayWelcome();
      input.focus();
    } else {
      launcher.focus();
    }
  };
  const askAccountType = () => {
    flow.step = "account-check";
    reply("Do you have an APEX account? Reply yes or no.");
  };
  const askConcern = () => {
    flow.step = "concern";
    reply("Please tell me your concern in one message.");
  };
  const sendConcern = async () => {
    const data = new FormData();
    data.set("csrf_token", assistant.dataset.csrfToken || "");
    data.set("fax_number", honeypot.value);
    data.set("subject", flow.subject);
    data.set("apex_account", flow.apexAccount);
    data.set("email", flow.email);
    data.set("first_name", flow.firstName);
    data.set("last_name", flow.lastName);
    data.set("name", flow.name);
    data.set("message", flow.concern);

    isSubmitting = true;
    updateBusyState();
    reply("Sending your concern to the Admin team…");
    try {
      const response = await fetch(assistant.dataset.endpoint, {
        method: "POST",
        body: data,
        headers: { Accept: "application/json" },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        const requestError = new Error(result.message || "Your concern could not be sent.");
        requestError.code = result.code || "request_failed";
        throw requestError;
      }

      reply(result.ticket_code
        ? `${result.message} Your ticket reference is ${result.ticket_code}.`
        : result.message);
      resetFlow();
      showMenu();
    } catch (requestError) {
      if (requestError.code === "account_identity_mismatch") {
        flow.step = "apex-email";
        flow.email = "";
        flow.firstName = "";
        flow.lastName = "";
        reply(`${requestError.message} Please enter your registered APEX email again.`);
      } else if (requestError.code === "apex_email_required") {
        flow.step = "apex-email";
        flow.email = "";
        reply(requestError.message);
      } else if (requestError.code === "invalid_name") {
        flow.step = flow.apexAccount === "yes" ? "apex-first-name" : "public-name";
        reply(requestError.message);
      } else if (requestError.code === "invalid_input") {
        flow.step = "concern";
        reply(`${requestError.message} Please enter your concern again, using no more than 4,800 characters.`);
      } else if (requestError.code === "account_required") {
        resetFlow();
        reply(requestError.message);
        showMenu();
      } else if (requestError.code === "csrf_failed") {
        flow.step = "retry";
        reply("Your session expired. Reload the page before trying to send this request again.");
      } else {
        flow.step = "retry";
        const message = requestError.code
          ? requestError.message
          : "The support service could not be reached. Please try again in a moment.";
        reply(`${message} Reply “retry” to try again, or “menu” to start over.`);
      }
    } finally {
      isSubmitting = false;
      updateBusyState();
    }
  };
  const selectSubject = (text) => {
    const value = text.trim().toLowerCase();
    if (/^1\b/.test(value) || value.includes("enroll")) return "enrollment";
    if (/^2\b/.test(value) || value.includes("policy")) return "policy";
    if (/^3\b/.test(value) || value.includes("technical")) return "technical";
    if (/^4\b/.test(value) || value.includes("announcement") || value.includes("bulletin")) return "announcement";
    if (/^5\b/.test(value) || value.includes("account") || value.includes("password")) return "account_access";
    if (/^6\b/.test(value) || value.includes("appeal") || value.includes("review")) return "appeal";
    if (/^7\b/.test(value) || value === "other") return "other";
    return "";
  };
  const startOver = () => {
    resetFlow();
    showMenu();
  };
  const handleReply = async (value) => {
    const text = value.trim();
    if (!text) return;
    appendMessage(text, "user");
    input.value = "";

    if (/^(menu|start over|restart)$/i.test(text)) {
      startOver();
      return;
    }

    switch (flow.step) {
      case "menu": {
        const subject = selectSubject(text);
        if (!subject) {
          reply("I couldn’t match that to a support area. Reply with a menu number or topic.");
          showMenu();
          return;
        }
        flow.subject = subject;
        if (subject === "enrollment") {
          reply("For enrollment, use the Portal registration options and check Announcements for current instructions. The Admin team can clarify requirements for your case.");
          flow.step = "faq-confirm";
          reply("Would you like to send a related concern to the Admin team? Reply yes or no.");
        } else if (subject === "policy") {
          reply("You can review the Policies page for published guidelines. If your question depends on your situation, the Admin team can review the details.");
          flow.step = "faq-confirm";
          reply("Would you like to send a related concern to the Admin team? Reply yes or no.");
        } else if (subject === "technical") {
          reply("Try refreshing the page and signing in again. If the problem continues, include the page name and what happened when you send a request.");
          flow.step = "faq-confirm";
          reply("Would you like to send a related concern to the Admin team? Reply yes or no.");
        } else if (subject === "announcement") {
          reply("Open Announcements from the site navigation to view current bulletins. If an item is missing or unclear, you can ask the Admin team.");
          flow.step = "faq-confirm";
          reply("Would you like to send a related concern to the Admin team? Reply yes or no.");
        } else {
          askAccountType();
        }
        return;
      }
      case "faq-confirm":
        if (/^(y|yes)$/i.test(text)) {
          askAccountType();
        } else if (/^(n|no)$/i.test(text)) {
          reply("No problem. What else can I help with?");
          startOver();
        } else {
          reply("Please reply yes or no.");
        }
        return;
      case "account-check":
        if (/^(y|yes)$/i.test(text)) {
          flow.apexAccount = "yes";
          flow.step = "apex-email";
          reply("Please enter your APEX email address.");
        } else if (/^(n|no)$/i.test(text)) {
          if (["account_access", "appeal"].includes(flow.subject)) {
            reply("Account-access and appeal requests need the registered APEX email and matching account name. You can choose Other to send a general question.");
            startOver();
            return;
          }
          flow.apexAccount = "no";
          flow.step = "public-name";
          reply("What name should we use for your request?");
        } else {
          reply("Please reply yes or no.");
        }
        return;
      case "apex-email": {
        const email = text.trim();
        if (!/^[^\s@]+@apex\.edu\.ph$/i.test(email)) {
          reply("Please enter a valid email ending in @apex.edu.ph.");
          return;
        }
        flow.email = email;
        flow.step = "apex-first-name";
        reply("What first name is registered to that APEX account?");
        return;
      }
      case "apex-first-name":
        flow.firstName = text.trim();
        flow.step = "apex-last-name";
        reply("What last name is registered to that APEX account?");
        return;
      case "apex-last-name":
        flow.lastName = text.trim();
        askConcern();
        return;
      case "public-name":
        if (text.length > 120) {
          reply("Please keep your name to 120 characters or fewer.");
          return;
        }
        flow.name = text;
        flow.step = "public-email";
        reply("What email address can the Admin team use to contact you?");
        return;
      case "public-email":
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) || text.length > 100) {
          reply("Please enter a valid email address.");
          return;
        }
        flow.email = text;
        askConcern();
        return;
      case "concern":
        if (text.length > 4800) {
          reply("Please keep your concern to 4,800 characters or fewer.");
          return;
        }
        flow.concern = text;
        await sendConcern();
        return;
      case "retry":
        if (/^(retry|yes|y)$/i.test(text)) {
          await sendConcern();
        } else {
          reply("Reply “retry” to send the same request again, or “menu” to start over.");
        }
        return;
      default:
        startOver();
    }
  };

  launcher.addEventListener("click", () => setOpen(panel.hidden));
  closeButton.addEventListener("click", () => setOpen(false));
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    handleReply(input.value);
  });
  document.querySelectorAll("[data-open-apex-assistant]").forEach((button) => {
    button.addEventListener("click", () => setOpen(true));
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) setOpen(false);
  });
  if (new URLSearchParams(window.location.search).get("chat") === "1") {
    setOpen(true);
  }
}

/* ── Role portal clocks ──────────────────────────────────────────── */
function initPageClocks() {
  const clocks = document.querySelectorAll("[data-apex-clock]");
  if (!clocks.length) return;

  const formatter = new Intl.DateTimeFormat("en-US", {
    timeZone: "Asia/Manila",
    weekday: "short",
    month: "long",
    day: "2-digit",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    second: "2-digit",
    hour12: true,
  });

  const updateClocks = () => {
    const parts = Object.fromEntries(
      formatter
        .formatToParts(new Date())
        .map(({ type, value }) => [type, value]),
    );
    const dateText = `${parts.weekday} | ${parts.month} ${parts.day}, ${parts.year}`;
    const timeText = `${parts.hour}:${parts.minute}:${parts.second} ${parts.dayPeriod}`;

    clocks.forEach((clock) => {
      const date = clock.querySelector("[data-clock-date]");
      const time = clock.querySelector("[data-clock-time]");
      if (date) date.textContent = dateText;
      if (time) time.textContent = timeText;
      clock.dateTime = new Date().toISOString();
    });
  };

  updateClocks();
  window.setInterval(updateClocks, 1000);
}

/* ── DOM Ready Initializer ───────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  initPageClocks();
  initGlobalSearch();

  initGuideCards();
  initMediaCards();
  initMediaCarousel();
  initHomeCardCarousels();
  initMobileNav();
  initSmoothAnchors();
  initBackToTop();
  initPolicySidebar();
  initAnnouncementFilter();
  initLocalAssistant();
});
