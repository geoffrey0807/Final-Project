<?php
if (!isset($portalNotificationCounts)) {
    require_once __DIR__ . '/portal_notification_counts.php';
    $portalNotificationCounts = portalNotificationCounts(
        $pdo,
        (int) $_SESSION['user_id'],
        (string) $_SESSION['role']
    );
}
$mobilePortalBase = $siteRootPath . 'views/' . $activeUserRole . '/';
$mobileAnnouncementHref = $siteRootPath . 'views/announcements.php';
$mobileSupportHref = $siteRootPath . 'views/support/my_requests.php';
$mobileHomeHref = $siteRootPath . 'visual/homepage.php';
$mobileProfessorCourseArea = $currentArea ?? match (basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))) {
    'professor_assessment.php' => 'assessments',
    'professor_student_grade.php' => 'grades',
    default => '',
};
$mobileProfessorCourseTerm = (string) ($currentTerm ?? ($assessment['term'] ?? ($_GET['term'] ?? 'Prelim')));
$mobileProfessorCourseId = (int) ($courseId ?? 0);
$mobilePeopleSearchEnabled = in_array($activeUserRole, ['admin', 'registrar', 'dean', 'professor'], true);
$mobilePeopleSearchScope = match ($activeUserRole) {
    'admin' => 'All roles and account states',
    'registrar' => 'All school accounts',
    'dean' => 'Professors only',
    'professor' => 'Students in your classes',
    default => '',
};
$mobileNavigationItems = match ($activeUserRole) {
    'student' => !empty($studentCourseNavigation)
        ? [
            ['label' => 'Go back', 'href' => $mobilePortalBase . 'student_courses.php', 'icon' => 'back'],
            ['label' => 'Lessons', 'href' => $mobilePortalBase . 'student_course.php?id=' . (int) $studentCourseId . '&area=lessons', 'icon' => 'book', 'active' => $studentCourseArea === 'lessons'],
            ['label' => 'Assessments', 'href' => $mobilePortalBase . 'student_course.php?id=' . (int) $studentCourseId . '&area=assessments', 'icon' => 'checklist', 'active' => $studentCourseArea === 'assessments'],
            ['label' => 'Grades', 'href' => $mobilePortalBase . 'student_course.php?id=' . (int) $studentCourseId . '&area=grades', 'icon' => 'chart', 'active' => $studentCourseArea === 'grades'],
            ['label' => 'Announcements', 'href' => $mobilePortalBase . 'student_course.php?id=' . (int) $studentCourseId . '&area=announcements', 'icon' => 'announcement', 'active' => $studentCourseArea === 'announcements'],
            ['type' => 'academic-year'],
        ]
        : [
            ['label' => 'Dashboard', 'href' => $mobilePortalBase . 'student_dashboard.php', 'icon' => 'home', 'active' => ($studentPage ?? '') === 'dashboard'],
            ['label' => 'Assigned Classes', 'href' => $mobilePortalBase . 'student_courses.php', 'icon' => 'book', 'active' => ($studentPage ?? '') === 'courses'],
            ['label' => 'To-do List', 'href' => $mobilePortalBase . 'student_tasks.php', 'icon' => 'checklist', 'active' => ($studentPage ?? '') === 'tasks', 'badge' => $portalNotificationCounts['todo'] ?? 0],
            ['label' => 'Announcements', 'href' => $mobileAnnouncementHref, 'icon' => 'announcement', 'active' => ($studentPage ?? '') === 'announcements' || !empty($portalAnnouncementsActive), 'badge' => $portalNotificationCounts['announcements'] ?? 0],
            ['label' => 'My Support Requests', 'href' => $mobileSupportHref, 'icon' => 'message', 'active' => ($studentPage ?? '') === 'support', 'badge' => $portalNotificationCounts['messages'] ?? 0],
            ['type' => 'academic-year'],
            ['label' => 'Privacy Policy', 'href' => $mobilePortalBase . 'student_privacy.php', 'icon' => 'privacy', 'active' => ($studentPage ?? '') === 'privacy'],
        ],
    'professor' => !empty($professorCourseNavigation)
        ? [
            ['label' => 'Go back', 'href' => $mobilePortalBase . 'professor_classes.php', 'icon' => 'back'],
            ['label' => 'Upload Lessons', 'href' => $mobilePortalBase . 'professor_course.php?id=' . $mobileProfessorCourseId . '&area=lessons&term=' . rawurlencode($mobileProfessorCourseTerm), 'icon' => 'book', 'active' => $mobileProfessorCourseArea === 'lessons'],
            ['label' => 'Upload Assessments', 'href' => $mobilePortalBase . 'professor_course.php?id=' . $mobileProfessorCourseId . '&area=assessments&term=' . rawurlencode($mobileProfessorCourseTerm), 'icon' => 'checklist', 'active' => $mobileProfessorCourseArea === 'assessments'],
            ['label' => 'Release Grade', 'href' => $mobilePortalBase . 'professor_course.php?id=' . $mobileProfessorCourseId . '&area=grades&term=' . rawurlencode($mobileProfessorCourseTerm), 'icon' => 'chart', 'active' => $mobileProfessorCourseArea === 'grades'],
            ['label' => 'Announcements', 'href' => $mobilePortalBase . 'professor_course.php?id=' . $mobileProfessorCourseId . '&area=announcements&term=' . rawurlencode($mobileProfessorCourseTerm), 'icon' => 'announcement', 'active' => $mobileProfessorCourseArea === 'announcements'],
            ['type' => 'academic-year'],
        ]
        : [
            ['label' => 'Dashboard', 'href' => $mobilePortalBase . 'professor_dashboard.php', 'icon' => 'home', 'active' => ($professorView ?? '') === 'dashboard'],
            ['label' => 'Assigned Classes', 'href' => $mobilePortalBase . 'professor_classes.php', 'icon' => 'book', 'active' => ($professorView ?? '') === 'classes'],
            ['label' => 'Announcements', 'href' => $mobileAnnouncementHref, 'icon' => 'announcement', 'active' => !empty($portalAnnouncementsActive), 'badge' => $portalNotificationCounts['announcements'] ?? 0],
            ['label' => 'My Support Requests', 'href' => $mobileSupportHref, 'icon' => 'message', 'active' => ($professorView ?? '') === 'support', 'badge' => $portalNotificationCounts['messages'] ?? 0],
            ['type' => 'academic-year'],
            ['label' => 'Privacy Policy', 'href' => $mobilePortalBase . 'professor_privacy.php', 'icon' => 'privacy', 'active' => ($professorView ?? '') === 'privacy'],
        ],
    'admin' => [
        ['label' => 'Dashboard', 'href' => $mobilePortalBase . 'admin_dashboard.php', 'icon' => 'home', 'active' => ($adminPage ?? '') === 'dashboard'],
        ['label' => 'User Management', 'href' => $mobilePortalBase . 'admin_users.php', 'icon' => 'people', 'active' => ($adminPage ?? '') === 'users'],
        ['label' => 'Message Requests', 'href' => $mobilePortalBase . 'admin_message_requests.php', 'icon' => 'message', 'active' => ($adminPage ?? '') === 'messages', 'badge' => $portalNotificationCounts['messages'] ?? 0],
        ['label' => 'Announcements', 'href' => $mobileAnnouncementHref, 'icon' => 'announcement', 'active' => !empty($portalAnnouncementsActive), 'badge' => $portalNotificationCounts['announcements'] ?? 0],
        ['type' => 'academic-year'],
        ['label' => 'Privacy Policy', 'href' => $mobilePortalBase . 'admin_privacy.php', 'icon' => 'privacy', 'active' => ($adminPage ?? '') === 'privacy'],
    ],
    'dean' => [
        ['label' => 'Dashboard', 'href' => $mobilePortalBase . 'dean_dashboard.php', 'icon' => 'home', 'active' => ($deanPage ?? 'dashboard') === 'dashboard'],
        ['label' => 'My Professors', 'href' => $mobilePortalBase . 'dean_professors.php', 'icon' => 'people', 'active' => ($deanPage ?? '') === 'professors'],
        ['label' => 'Announcements', 'href' => $mobileAnnouncementHref, 'icon' => 'announcement', 'active' => !empty($portalAnnouncementsActive), 'badge' => $portalNotificationCounts['announcements'] ?? 0],
        ['label' => 'My Support Requests', 'href' => $mobileSupportHref, 'icon' => 'message', 'active' => ($deanPage ?? '') === 'support', 'badge' => $portalNotificationCounts['messages'] ?? 0],
        ['type' => 'academic-year'],
        ['label' => 'Privacy Policy', 'href' => $mobilePortalBase . 'dean_privacy.php', 'icon' => 'privacy', 'active' => ($deanPage ?? '') === 'privacy'],
    ],
    'registrar' => [
        ['label' => 'Dashboard', 'href' => $mobilePortalBase . 'registrar_dashboard.php', 'icon' => 'home', 'active' => ($registrarView ?? 'dashboard') === 'dashboard'],
        ['label' => 'User Accounts', 'href' => $mobilePortalBase . 'registrar_users.php', 'icon' => 'people', 'active' => ($registrarView ?? '') === 'users'],
        ['label' => 'Assigned Courses', 'href' => $mobilePortalBase . 'registrar_courses.php', 'icon' => 'book', 'active' => ($registrarView ?? '') === 'courses'],
        ['label' => 'Student Placements', 'href' => $mobilePortalBase . 'registrar_placements.php', 'icon' => 'placements', 'active' => ($registrarView ?? '') === 'placements'],
        ['label' => 'Manage Announcements', 'href' => $mobilePortalBase . 'registrar_announcements.php', 'icon' => 'announcement-manage', 'active' => ($registrarView ?? '') === 'announcements'],
        ['label' => 'Announcements', 'href' => $mobileAnnouncementHref, 'icon' => 'announcement', 'active' => !empty($portalAnnouncementsActive), 'badge' => $portalNotificationCounts['announcements'] ?? 0],
        ['label' => 'My Support Requests', 'href' => $mobileSupportHref, 'icon' => 'message', 'active' => ($registrarView ?? '') === 'support', 'badge' => $portalNotificationCounts['messages'] ?? 0],
        ['type' => 'academic-year'],
        ['label' => 'Privacy Policy', 'href' => $mobilePortalBase . 'registrar_privacy.php', 'icon' => 'privacy', 'active' => ($registrarView ?? '') === 'privacy'],
    ],
    default => [],
};
$mobileProfileHref = $mobilePortalBase . $activeUserRole . '_profile.php';
$mobileIconPaths = [
    'back' => 'M19 12H5M12 19l-7-7 7-7',
    'home' => 'M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5M9 21v-7h6v7',
    'book' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z',
    'checklist' => 'M9 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4M9 5a3 3 0 0 0 6 0M8 12h8M8 16h6',
    'chart' => 'M4 19V5M4 19h17M8 15l4-4 3 2 5-6M17 7h3v3',
    'announcement' => 'M4 14V9a1 1 0 0 1 1-1h3l9-4v14l-9-4H5a1 1 0 0 1-1-1ZM8 14l2 6h4l-2.5-7.1M20 8l2-1M20 12h2M20 16l2 1',
    'people' => 'M9 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20',
    'message' => 'M3 5h18v14H3zM4 7l8 6 8-6',
    'calendar' => 'M3 5h18v16H3zM16 3v4M8 3v4M3 10h18',
    'privacy' => 'M12 3 19 6v5c0 4.5-2.8 7.8-7 10-4.2-2.2-7-5.5-7-10V6l7-3ZM9 12l2 2 4-4',
    'placements' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20v-1a5.5 5.5 0 0 1 11 0v1M16 5h5M18.5 2.5v5M16 14h5M16 18h5',
    'announcement-manage' => 'M4 5h16v14H4zM8 9h8M8 13h8M8 17h5',
    'logout' => 'M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6',
];
?>
<div class="apex-mobile-navigation" data-mobile-navigation>
    <div class="apex-mobile-topbar">
        <a class="apex-mobile-brand" href="<?= htmlspecialchars($mobileHomeHref, ENT_QUOTES, 'UTF-8') ?>"
            aria-label="APEX Learning Hub home">
            <svg viewBox="0 0 504 428" aria-hidden="true">
                <path d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                <path d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                <path d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
            </svg>
            <span class="apex-mobile-brand-copy"><strong>APEX</strong><small>Learning Hub</small></span>
        </a>
        <div class="apex-mobile-top-actions">
            <button class="apex-mobile-action" type="button" data-mobile-nav-toggle aria-label="Open navigation"
                aria-controls="apexMobileDrawer" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <button class="apex-mobile-backdrop" type="button" data-mobile-nav-backdrop aria-label="Close navigation"
        hidden></button>
    <aside class="apex-mobile-drawer" id="apexMobileDrawer" role="dialog"
        aria-label="<?= htmlspecialchars($activeUserRoleLabel, ENT_QUOTES, 'UTF-8') ?> navigation" aria-hidden="true"
        inert hidden>
        <div class="apex-mobile-drawer-heading">
            <a class="apex-mobile-user" href="<?= htmlspecialchars($mobileProfileHref, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($activeUserProfilePhoto !== ''): ?>
                    <img class="apex-mobile-avatar"
                        src="<?= htmlspecialchars($activeUserProfilePhoto, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                    <span
                        class="apex-mobile-avatar apex-mobile-avatar-initials"><?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="apex-mobile-user-copy">
                    <strong><?= htmlspecialchars($activeUserName, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($activeUserRoleLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </span>
            </a>
            <button class="apex-mobile-action apex-mobile-close" type="button" data-mobile-nav-close
                aria-label="Close navigation">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>
        <label class="apex-mobile-search-field">
            <span class="visually-hidden">Find a page</span>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" />
                <path d="m16 16 5 5" />
            </svg>
            <input type="search"
                placeholder="<?= $mobilePeopleSearchEnabled ? 'Search people or pages' : 'Find a page' ?>"
                aria-label="<?= $mobilePeopleSearchEnabled ? 'Search people by name, ID number, email, role, or status, or find a page' : 'Find a page' ?>"
                data-mobile-nav-filter autocomplete="off">
        </label>
        <?php if ($mobilePeopleSearchEnabled): ?>
            <p class="apex-mobile-search-scope"><?= htmlspecialchars($mobilePeopleSearchScope, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="apex-mobile-people-results" data-mobile-people-search-results
                data-endpoint="<?= htmlspecialchars($siteRootPath . 'api/portal_people_search.php', ENT_QUOTES, 'UTF-8') ?>"
                role="list" aria-live="polite">
                <p class="portal-people-search-message" data-mobile-people-search-message>Enter at least 2 characters.</p>
            </div>
        <?php endif; ?>
        <nav class="apex-mobile-links" aria-label="Portal pages" data-mobile-nav-links>
            <?php foreach ($mobileNavigationItems as $item): ?>
                <?php if (($item['type'] ?? '') === 'academic-year'): ?>
                    <button class="apex-mobile-link" type="button" data-fiscal-year-open aria-haspopup="dialog"
                        aria-controls="studentFiscalYearDialog" data-mobile-nav-item>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="<?= htmlspecialchars($mobileIconPaths['calendar'], ENT_QUOTES, 'UTF-8') ?>" />
                        </svg>
                        <span class="apex-mobile-link-copy"><span>Academic Year</span><small data-fiscal-year-summary>Not
                                set</small></span>
                    </button>
                <?php else: ?>
                    <a class="apex-mobile-link" <?= !empty($item['active']) ? ' aria-current="page"' : '' ?>
                        href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" data-mobile-nav-item>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="<?= htmlspecialchars($mobileIconPaths[$item['icon']], ENT_QUOTES, 'UTF-8') ?>" />
                        </svg>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <small class="apex-mobile-badge"><?= (int) $item['badge'] ?></small>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <p class="apex-mobile-no-results" data-mobile-nav-empty hidden>No matching pages</p>
        <a class="apex-mobile-signout"
            href="<?= htmlspecialchars($siteRootPath . 'auth/logout.php', ENT_QUOTES, 'UTF-8') ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="<?= htmlspecialchars($mobileIconPaths['logout'], ENT_QUOTES, 'UTF-8') ?>" />
            </svg>
            <span>Logout</span>
        </a>
    </aside>
</div>