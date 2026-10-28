/**
 * APEX Learning Hub — Visual Core JavaScript
 * Handles Global Search, English/Filipino Language Switcher, Chevrons,
 * Accordions, Mobile Navigation, and Interactive Picture Containers.
 */

// Comprehensive Search Index across all visual pages & portal
const SEARCH_INDEX = [
    { title: "Home — Welcome to APEX Learning Hub", page: "Homepage", url: "homepage.php" },
    { title: "Academic Programs & Offerings", page: "Offerings", url: "homepage.php#offerings" },
    { title: "Digital Library & Learning Resources", page: "Offerings", url: "homepage.php#offerings" },
    { title: "Interactive Coursework Tracker", page: "Offerings", url: "homepage.php#offerings" },
    { title: "Campus Bulletins & Announcements Preview", page: "Announcements", url: "homepage.php#announcements" },
    { title: "Campus Essentials & Student Guide (FAQ)", page: "FAQ & Guide", url: "homepage.php#essentials" },
    { title: "About APEX Learning Hub", page: "About", url: "homepage.php#about" },

    { title: "All Campus Announcements & Advisories", page: "Announcements", url: "announcement.php" },
    { title: "Midterm Examination Schedule Released", page: "Announcements", url: "announcement.php" },
    { title: "Scheduled Maintenance Advisory", page: "Announcements", url: "announcement.php" },
    { title: "Second Semester Enrollment Deadline", page: "Announcements", url: "announcement.php" },
    { title: "Student Leadership Summit 2026", page: "Announcements", url: "announcement.php" },
    { title: "Updated Academic Integrity Guidelines", page: "Announcements", url: "announcement.php" },

    { title: "Student Code of Conduct", page: "Policies", url: "policies.php#code-of-conduct" },
    { title: "Academic Integrity Policy", page: "Policies", url: "policies.php#academic-integrity" },
    { title: "Attendance & Participation Rules", page: "Policies", url: "policies.php#attendance" },
    { title: "Grading System & Evaluation Scale", page: "Policies", url: "policies.php#grading" },
    { title: "Data Privacy Notice & Protection", page: "Policies", url: "policies.php#privacy" },
    { title: "Acceptable Digital Use Policy", page: "Policies", url: "policies.php#digital-use" },

    { title: "Contact Us & Administration Directory", page: "Contact Us", url: "contact.php" },
    { title: "Registrar & Admissions Office Hours", page: "Contact Us", url: "contact.php#info" },
    { title: "Send Inquiries to Academic Support", page: "Contact Us", url: "contact.php#info" },
    { title: "Frequently Asked Questions", page: "Contact Us", url: "contact.php#faq" },

    { title: "APEX Academic Portal — Sign In", page: "Portal Login", url: "../auth/login.php" },
    { title: "Student Account Registration", page: "Portal Register", url: "../auth/register.php" },
    { title: "Reset Portal Password", page: "Password Recovery", url: "../auth/forgot-password.php" }
];

/* ── 1. Global Live Search Functionality ─────────────────────────── */
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearch');
    const resultsBox  = document.getElementById('searchResults');
    const clearBtn    = document.getElementById('searchClear');
    if (!searchInput || !resultsBox) return;

    let debounceTimer;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';
        }

        debounceTimer = setTimeout(() => {
            renderSearchResults(query, resultsBox);
        }, 120);
    });

    searchInput.addEventListener('focus', () => {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            renderSearchResults(query, resultsBox);
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            resultsBox.classList.remove('visible');
            searchInput.focus();
        });
    }

    // Pressing Enter navigates directly to the top match
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstResult = resultsBox.querySelector('.search-result-item');
            if (firstResult) {
                window.location.href = firstResult.getAttribute('href');
            } else if (searchInput.value.trim().length > 0) {
                window.location.href = 'announcement.php';
            }
        } else if (e.key === 'Escape') {
            resultsBox.classList.remove('visible');
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!searchInput.closest('.sub-search').contains(e.target)) {
            resultsBox.classList.remove('visible');
        }
    });
}

function renderSearchResults(query, container) {
    if (query.length === 0) {
        container.classList.remove('visible');
        return;
    }

    const q = query.toLowerCase();
    const matches = SEARCH_INDEX.filter(item =>
        item.title.toLowerCase().includes(q) ||
        item.page.toLowerCase().includes(q)
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
        container.innerHTML = matches.map(m => `
            <a class="search-result-item" href="${m.url}">
                <span class="res-title">${highlightMatch(m.title, query)}</span>
                <span class="res-page">${escapeHtml(m.page)} &bull; View details &rarr;</span>
            </a>
        `).join('');
    }

    container.classList.add('visible');
}

function highlightMatch(text, query) {
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark style="background:#fee2e2;color:#991b1b;padding:0 2px;border-radius:2px;">$1</mark>');
}

function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ── 2. Language Switcher (Only English and Filipino) ────────────── */
function initLanguageSwitcher() {
    const langBtn   = document.getElementById('langBtn');
    const langDrop  = document.getElementById('langDropdown');
    const langLabel = document.getElementById('langLabel');
    if (!langBtn || !langDrop) return;

    // Load saved language (default: 'en')
    const currentLang = localStorage.getItem('apex_lang') || 'en';
    applyLanguage(currentLang, false);

    // Toggle dropdown
    langBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        langDrop.classList.toggle('open');
        langBtn.closest('.lang-wrapper').classList.toggle('active');
    });

    // Close on outside click
    document.addEventListener('click', () => {
        langDrop.classList.remove('open');
        langBtn.closest('.lang-wrapper').classList.remove('active');
    });

    langDrop.addEventListener('click', (e) => e.stopPropagation());

    // Option selection
    langDrop.querySelectorAll('.lang-option').forEach(opt => {
        opt.addEventListener('click', () => {
            const selectedLang = opt.getAttribute('data-lang');
            applyLanguage(selectedLang, true);
            langDrop.classList.remove('open');
            langBtn.closest('.lang-wrapper').classList.remove('active');
        });
    });
}

function applyLanguage(lang, save) {
    if (save) {
        localStorage.setItem('apex_lang', lang);
    }

    const isFilipino = (lang === 'fil');
    document.body.classList.toggle('lang-fil', isFilipino);

    const langLabel = document.getElementById('langLabel');
    if (langLabel) {
        langLabel.textContent = isFilipino ? 'Filipino' : 'English';
    }

    document.querySelectorAll('.lang-option').forEach(opt => {
        const isCurrent = opt.getAttribute('data-lang') === lang;
        opt.classList.toggle('selected', isCurrent);
    });
}

/* ── 3. Interactive Chevrons & Accordion ─────────────────────────── */
function initAccordionChevrons() {
    const accordionItems = document.querySelectorAll('.accordion-item');
    if (!accordionItems.length) return;

    accordionItems.forEach(item => {
        const header = item.querySelector('.accordion-header');
        const body   = item.querySelector('.accordion-body');
        if (!header || !body) return;

        // Set initial max-height if already open
        if (item.classList.contains('open')) {
            body.style.maxHeight = body.scrollHeight + 32 + 'px';
            header.setAttribute('aria-expanded', 'true');
        } else {
            body.style.maxHeight = '0px';
            header.setAttribute('aria-expanded', 'false');
        }

        header.addEventListener('click', () => {
            const isOpen = item.classList.contains('open');

            // Optional: Close sibling items for clean accordion UX
            accordionItems.forEach(other => {
                if (other !== item && other.classList.contains('open')) {
                    other.classList.remove('open');
                    const otherHeader = other.querySelector('.accordion-header');
                    const otherBody   = other.querySelector('.accordion-body');
                    if (otherHeader) otherHeader.setAttribute('aria-expanded', 'false');
                    if (otherBody) otherBody.style.maxHeight = '0px';
                }
            });

            if (isOpen) {
                item.classList.remove('open');
                header.setAttribute('aria-expanded', 'false');
                body.style.maxHeight = '0px';
            } else {
                item.classList.add('open');
                header.setAttribute('aria-expanded', 'true');
                body.style.maxHeight = body.scrollHeight + 32 + 'px';
            }
        });
    });
}

/* ── 4. Picture Containers: Hover & Click Highlight ─────────────── */
function initMediaCards() {
    const cards = document.querySelectorAll('.media-card');
    const track = document.querySelector('.media-grid');
    track?.addEventListener('click', event => {
        const card = event.target.closest('.media-card');
        if (!card || !track.contains(card)) return;

        const wasActive = card.classList.contains('is-active');
        track.querySelectorAll('.media-card').forEach(item => item.classList.remove('is-active'));
        if (!wasActive) card.classList.add('is-active');
    });

    cards.forEach(card => {
        // Keyboard accessibility
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.click();
            }
        });
    });
}

function initMediaCarousel() {
    const carousel = document.querySelector('.media-carousel');
    const viewport = carousel?.querySelector('.media-carousel-viewport');
    const track = carousel?.querySelector('.media-grid');
    const originalCards = track ? Array.from(track.querySelectorAll('.media-card')) : [];
    if (!carousel || !viewport || !track || !originalCards.length) return;

    const createLoopClone = card => {
        const clone = card.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
        return clone;
    };

    const cloneGroups = 3;
    const beforeClones = Array.from(
        { length: cloneGroups },
        () => originalCards.map(createLoopClone)
    ).flat();
    const afterClones = Array.from(
        { length: cloneGroups },
        () => originalCards.map(createLoopClone)
    ).flat();
    const beforeFragment = document.createDocumentFragment();
    beforeClones.forEach(clone => beforeFragment.appendChild(clone));
    track.prepend(beforeFragment);
    afterClones.forEach(clone => track.appendChild(clone));

    let loopStart = 0;
    let loopWidth = 0;
    const scrollOffsetFor = card => {
        const viewportLeft = viewport.getBoundingClientRect().left;
        const cardLeft = card.getBoundingClientRect().left;
        return viewport.scrollLeft + cardLeft - viewportLeft;
    };
    const alignToOriginals = () => {
        loopStart = scrollOffsetFor(originalCards[0]);
        loopWidth = scrollOffsetFor(afterClones[0]) - loopStart;
        viewport.scrollLeft = loopStart;
    };
    alignToOriginals();

    const normalizeLoopPosition = () => {
        if (!loopWidth) return;

        if (viewport.scrollLeft < loopStart - loopWidth * 2) {
            viewport.scrollLeft += loopWidth * cloneGroups;
        } else if (viewport.scrollLeft >= loopStart + loopWidth * 2) {
            viewport.scrollLeft -= loopWidth * cloneGroups;
        }
    };

    if ('onscrollend' in viewport) {
        viewport.addEventListener('scrollend', normalizeLoopPosition);
    } else {
        let settleTimer;
        viewport.addEventListener('scroll', () => {
            window.clearTimeout(settleTimer);
            settleTimer = window.setTimeout(normalizeLoopPosition, 140);
        }, { passive: true });
    }

    window.addEventListener('resize', alignToOriginals);

    carousel.querySelectorAll('.media-carousel-arrow').forEach(button => {
        button.addEventListener('click', () => {
            const firstCard = originalCards[0];
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            const step = firstCard.getBoundingClientRect().width + gap;
            const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'auto'
                : 'smooth';

            const direction = button.dataset.direction === 'next' ? 1 : -1;
            viewport.scrollBy({ left: direction * step, behavior });
        });
    });
}

/* ── 5. Mobile Navigation Menu Toggle ────────────────────────────── */
function initMobileNav() {
    const toggleBtn = document.getElementById('menuToggle');
    const mainNav   = document.getElementById('mainNav');
    if (!toggleBtn || !mainNav) return;

    toggleBtn.addEventListener('click', () => {
        const isOpen = mainNav.classList.contains('open');
        mainNav.classList.toggle('open');
        toggleBtn.classList.toggle('open');
        toggleBtn.setAttribute('aria-expanded', !isOpen);
    });

    // Close when clicking any nav link
    mainNav.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            mainNav.classList.remove('open');
            toggleBtn.classList.remove('open');
            toggleBtn.setAttribute('aria-expanded', 'false');
        });
    });
}

/* ── 6. Smooth Scrolling for Internal Anchor Links ───────────────── */
function initSmoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#' || targetId === '') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const headerOffset = 76;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/* ── 7. Back-To-Top Button ───────────────────────────────────────── */
function initBackToTop() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;

    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 280) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });

    btn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/* ── 8. Policies Sidebar Active Scroll (for policies.php) ────────── */
function initPolicySidebar() {
    const navItems = document.querySelectorAll('.policy-nav-item[data-target]');
    if (!navItems.length) return;

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            const targetId = item.getAttribute('data-target');
            const target = document.getElementById(targetId);
            if (target) {
                const headerOffset = 80;
                const pos = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;
                window.scrollTo({ top: pos, behavior: 'smooth' });
            }
        });
    });
}

/* ── 9. Announcement Filter Tabs (for announcement.php) ──────────── */
function initAnnouncementFilter() {
    const tabs  = document.querySelectorAll('.filter-tab[data-filter]');
    const cards = document.querySelectorAll('.announcement-card[data-category]');
    if (!tabs.length) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filter = tab.getAttribute('data-filter');
            cards.forEach(card => {
                const match = (filter === 'all' || card.getAttribute('data-category') === filter);
                card.style.display = match ? '' : 'none';
            });
        });
    });
}

/* ── 10. Contact Form Submission (for contact.php) ───────────────── */
function initContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        let valid = true;

        form.querySelectorAll('.form-error-msg').forEach(el => el.classList.remove('visible'));
        form.querySelectorAll('input, select, textarea').forEach(el => el.style.borderColor = '');

        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.style.borderColor = '#e11d48';
                const err = field.closest('.form-group')?.querySelector('.form-error-msg');
                if (err) err.classList.add('visible');
            }
        });

        const emailField = form.querySelector('input[type="email"]');
        if (emailField && emailField.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
            valid = false;
            emailField.style.borderColor = '#e11d48';
            const err = emailField.closest('.form-group')?.querySelector('.form-error-msg');
            if (err) {
                err.textContent = 'Please enter a valid email address.';
                err.classList.add('visible');
            }
        }

        if (!valid) return;

        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending Message…';
        }

        setTimeout(() => {
            form.reset();
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Message';
            }
            const success = document.getElementById('formSuccess');
            if (success) {
                success.classList.add('visible');
                setTimeout(() => success.classList.remove('visible'), 5000);
            }
        }, 800);
    });
}

/* ── DOM Ready Initializer ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initGlobalSearch();
    initLanguageSwitcher();
    initAccordionChevrons();
    initMediaCards();
    initMediaCarousel();
    initMobileNav();
    initSmoothAnchors();
    initBackToTop();
    initPolicySidebar();
    initAnnouncementFilter();
    initContactForm();
});
