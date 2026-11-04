
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.student-assessment-question input[type="text"]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.toLowerCase();
        });
    });
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    const dashboardLinks = document.querySelectorAll('.student-sidebar-link[data-target]');
    const dashboardShell = document.getElementById('studentDashboardShell');
    const sidebarToggle = document.querySelector('.student-sidebar-toggle');
    const mobileToggle = document.querySelector('.student-mobile-toggle');
    const sidebarBackdrop = document.querySelector('.student-sidebar-backdrop');
    const profileMenu = document.querySelector('[data-profile-menu]');
    const profileMenuTrigger = document.querySelector('[data-profile-menu-trigger]');
    const profileMenuContent = document.querySelector('[data-profile-menu-content]');
    const fiscalYearDialog = document.getElementById('studentFiscalYearDialog');
    const fiscalYearForm = document.getElementById('studentFiscalYearForm');
    const fiscalYearStatus = document.getElementById('studentFiscalYearStatus');
    const fiscalYearSummary = document.querySelector('[data-fiscal-year-summary]');
    const academicYearSelect = document.getElementById('studentAcademicYear');
    const semesterSelect = document.getElementById('studentSemester');

    if (!document.documentElement.dataset.portalProfileBound) {
        const setProfileMenuOpen = (open) => {
            profileMenuTrigger?.setAttribute('aria-expanded', String(open));
            if (profileMenuContent) profileMenuContent.hidden = !open;
        };

        profileMenuTrigger?.addEventListener('click', () => {
            setProfileMenuOpen(profileMenuTrigger.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('click', event => {
            if (profileMenu && !profileMenu.contains(event.target)) setProfileMenuOpen(false);
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') setProfileMenuOpen(false);
        });
        document.documentElement.dataset.portalProfileBound = 'true';
    }

    const updateFiscalYear = (selection) => {
        if (!selection || typeof selection.academicYear !== 'string' || typeof selection.semester !== 'string') {
            return false;
        }
        const yearLabel = Array.from(academicYearSelect?.options || [])
            .find(option => option.value === selection.academicYear)?.textContent;
        const semesterLabel = Array.from(semesterSelect?.options || [])
            .find(option => option.value === selection.semester)?.textContent;
        if (!yearLabel || !semesterLabel) return false;

        if (fiscalYearSummary) {
            fiscalYearSummary.textContent = `${yearLabel} · ${semesterLabel}`;
        }
        if (fiscalYearStatus) {
            fiscalYearStatus.textContent = `Selected academic period: ${yearLabel} · ${semesterLabel}`;
        }
        return true;
    };

    if (fiscalYearDialog && fiscalYearForm && academicYearSelect && semesterSelect) {
        const savedSelection = localStorage.getItem('apexFiscalYearSelection');
        if (savedSelection) {
            try {
                const selection = JSON.parse(savedSelection);
                if (updateFiscalYear(selection)) {
                    academicYearSelect.value = selection.academicYear;
                    semesterSelect.value = selection.semester;
                } else {
                    console.error('Saved academic-year selection is invalid.');
                    if (fiscalYearStatus) {
                        fiscalYearStatus.textContent = 'The saved selection is invalid. Please choose the year and semester again.';
                    }
                }
            } catch (error) {
                console.error('Unable to read the saved academic-year selection.', error);
                if (fiscalYearStatus) {
                    fiscalYearStatus.textContent = 'Unable to read the saved selection. Please choose the year and semester again.';
                }
            }
        }

        document.querySelectorAll('[data-fiscal-year-open]').forEach(button => {
            button.addEventListener('click', () => fiscalYearDialog.showModal());
        });
        document.querySelectorAll('[data-fiscal-year-close]').forEach(button => {
            button.addEventListener('click', () => fiscalYearDialog.close());
        });
        fiscalYearDialog.addEventListener('click', event => {
            if (event.target === fiscalYearDialog) fiscalYearDialog.close();
        });
        fiscalYearForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!fiscalYearForm.reportValidity()) return;

            const selection = {
                academicYear: academicYearSelect.value,
                semester: semesterSelect.value,
            };
            if (!updateFiscalYear(selection)) return;

            try {
                localStorage.setItem('apexFiscalYearSelection', JSON.stringify(selection));
                fiscalYearDialog.close();
            } catch (error) {
                console.error('Unable to save the fiscal-year selection.', error);
                if (fiscalYearStatus) {
                    fiscalYearStatus.textContent = 'Unable to save your selection in this browser. Please try again.';
                }
            }
        });
    }

    const setSidebarState = (open) => {
        if (!dashboardShell) return;
        dashboardShell.classList.toggle('sidebar-open', open);
        mobileToggle?.setAttribute('aria-expanded', String(open));
        sidebarToggle?.setAttribute('aria-expanded', String(open));
        sidebarToggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        if (mobileToggle) {
            mobileToggle.setAttribute('aria-label', open ? 'Close student navigation' : 'Open student navigation');
        }
    };

    if (document.documentElement.dataset.portalSidebarBound) return;
    document.documentElement.dataset.portalSidebarBound = 'true';

    sidebarToggle?.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) {
            setSidebarState(!dashboardShell?.classList.contains('sidebar-open'));
            return;
        }

        const collapsed = dashboardShell?.classList.toggle('sidebar-collapsed') ?? false;
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
    });

    mobileToggle?.addEventListener('click', () => {
        setSidebarState(!dashboardShell?.classList.contains('sidebar-open'));
    });
    sidebarBackdrop?.addEventListener('click', () => setSidebarState(false));

    const activatePanel = (targetId) => {
        const targetPanel = document.getElementById(targetId);
        if (!targetPanel || !targetPanel.classList.contains('tab-panel')) return;

        tabPanels.forEach(panel => panel.classList.toggle('active', panel === targetPanel));
        document.querySelectorAll('.student-sidebar-link').forEach(link => {
            link.classList.toggle('active', link.dataset.target === targetId);
        });
        if (window.matchMedia('(max-width: 900px)').matches) {
            setSidebarState(false);
        }
    };

    dashboardLinks.forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            const targetId = link.dataset.target;
            if (!targetId) return;
            history.replaceState(null, '', `#${targetId}`);
            activatePanel(targetId);
        });
    });

    if (window.location.hash) {
        activatePanel(window.location.hash.slice(1));
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPanel = document.getElementById(targetId);
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
            if (window.matchMedia('(max-width: 900px)').matches) {
                setSidebarState(false);
            }
        });
    });

    const catalogSearch = document.getElementById('catalogSearch');
    if (catalogSearch) {
        catalogSearch.addEventListener('input', () => {
            const query = catalogSearch.value.toLowerCase().trim();
            const courseCards = document.querySelectorAll('.course-card');

            courseCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    const announcementSearch = document.getElementById('announcementSearch');
    const announcementItems = document.querySelectorAll('[data-announcement-item]');
    const announcementNoResults = document.getElementById('announcementNoResults');
    announcementSearch?.addEventListener('input', () => {
        const query = announcementSearch.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        announcementItems.forEach(item => {
            const matches = item.textContent.toLocaleLowerCase().includes(query);
            item.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        if (announcementNoResults) {
            announcementNoResults.hidden = visibleCount > 0;
        }
    });

});
