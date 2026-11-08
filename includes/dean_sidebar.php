<?php
$portalViewBase ??= '';
require __DIR__ . '/portal_icon_rail.php';
?>
<aside class="student-sidebar" id="studentSidebar" aria-label="Dean dashboard navigation">
    <div class="student-sidebar-heading">
        <span class="student-sidebar-title">Dean Portal</span>
    </div>
    <?php require __DIR__ . '/portal_people_search.php'; ?>
    <nav class="student-sidebar-nav" aria-label="Dean sections">
        <a class="student-sidebar-link<?= ($deanPage ?? 'dashboard') === 'dashboard' ? ' active' : '' ?>"
            href="<?= htmlspecialchars($portalViewBase . 'dean_dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <path d="m3 10 9-7 9 7M5 9v11h14V9M9 20v-7h6v7" />
                </svg></span>
            <span class="student-nav-label">Dashboard</span>
        </a>
        <a class="student-sidebar-link<?= ($deanPage ?? '') === 'professors' ? ' active' : '' ?>"
            href="<?= htmlspecialchars($portalViewBase . 'dean_professors.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <circle cx="9" cy="8" r="3" />
                    <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 0 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                </svg></span>
            <span class="student-nav-label">My Professors</span>
        </a>
        <a class="student-sidebar-link<?= !empty($portalAnnouncementsActive) ? ' active' : '' ?>"
            href="<?= htmlspecialchars($portalAnnouncementsHref ?? '../announcements.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <path
                        d="M4 14V9a1 1 0 0 1 1-1h3l9-4v14l-9-4H5a1 1 0 0 1-1-1ZM8 14l2 6h4l-2.5-7.1M20 8l2-1M20 12h2M20 16l2 1" />
                </svg></span>
            <span class="student-nav-label">Announcements</span>
            <?= portalNotificationBadge($portalNotificationCounts['announcements'], 'announcement') ?>
        </a>
        <a class="student-sidebar-link<?= ($deanPage ?? '') === 'support' ? ' active' : '' ?>"
            href="<?= htmlspecialchars($portalSupportHref ?? '../support/my_requests.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="m4 7 8 6 8-6" />
                </svg></span>
            <span class="student-nav-label">My Support Requests</span>
            <?= portalNotificationBadge($portalNotificationCounts['messages'], 'message') ?>
        </a>
        <button type="button" class="student-sidebar-link student-fiscal-year-trigger" data-fiscal-year-open
            aria-haspopup="dialog" aria-controls="studentFiscalYearDialog">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="17" rx="2" />
                    <path d="M16 2v4M8 2v4M3 9h18M8 13h3M13 13h3M8 17h3" />
                </svg></span>
            <span class="student-nav-label student-fiscal-year-copy"><span>Academic Year</span><small
                    class="student-fiscal-year-summary" data-fiscal-year-summary>Not set</small></span>
        </button>
        <a class="student-sidebar-link<?= ($deanPage ?? '') === 'privacy' ? ' active' : '' ?>"
            href="<?= htmlspecialchars($portalViewBase . 'dean_privacy.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="student-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                    <path d="M12 3 19 6v5c0 4.5-2.8 7.8-7 10-4.2-2.2-7-5.5-7-10V6l7-3Z" />
                    <path d="m9 12 2 2 4-4" />
                </svg></span>
            <span class="student-nav-label">Privacy Policy</span>
        </a>
    </nav>
</aside>
<dialog class="student-fiscal-year-dialog" id="studentFiscalYearDialog" aria-labelledby="studentFiscalYearTitle">
    <form id="studentFiscalYearForm">
        <div class="student-fiscal-year-heading"><span class="student-eyebrow">Dean portal</span><button type="button"
                class="student-fiscal-year-close" data-fiscal-year-close aria-label="Close dialog">&times;</button>
        </div>
        <h2 id="studentFiscalYearTitle">Set an academic year</h2>
        <p>Choose the academic year and semester you want to view.</p>
        <label for="studentAcademicYear">Academic year</label>
        <select id="studentAcademicYear" name="academicYear" required>
            <option value="">Select an academic year</option>
            <?php foreach (academicYearStartOptions() as $year): ?>
                <option value="<?= $year ?>-<?= $year + 1 ?>">AY <?= $year ?>–<?= $year + 1 ?></option><?php endforeach; ?>
        </select>
        <label for="studentSemester">Semester</label>
        <select id="studentSemester" name="semester" required>
            <option value="">Select a semester</option>
            <option value="first">First Semester</option>
            <option value="second">Second Semester</option>
        </select>
        <p class="student-fiscal-year-status" id="studentFiscalYearStatus" aria-live="polite"></p>
        <div class="student-fiscal-year-actions">
            <button type="button" class="btn btn-outline" data-academic-clear>Current period</button>
            <button type="button" class="btn btn-outline" data-fiscal-year-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save selection</button>
        </div>
    </form>
    <dialog class="student-fiscal-year-confirm" id="studentFiscalYearConfirm"
        aria-labelledby="studentFiscalYearConfirmTitle" aria-describedby="studentFiscalYearConfirmDescription">
        <div class="student-fiscal-year-confirm-content">
            <h2 id="studentFiscalYearConfirmTitle">Save this period selection?</h2>
            <p id="studentFiscalYearConfirmDescription">The selected academic year and semester will be applied.</p>
            <div class="student-fiscal-year-confirm-actions">
                <button type="button" class="btn btn-outline" data-fiscal-year-save-cancel>No</button>
                <button type="button" class="btn btn-primary" data-fiscal-year-save-confirm>Yes, save</button>
            </div>
        </div>
    </dialog>
</dialog>