<?php
require_once __DIR__ . '/portal_notification_counts.php';
$portalNotificationCounts = portalNotificationCounts(
    $pdo,
    (int) $_SESSION['user_id'],
    (string) $_SESSION['role']
);
$portalProfileHref = match ($activeUserRole) {
    'admin' => ($portalViewBase ?? '') . 'admin_profile.php',
    'registrar' => ($portalViewBase ?? '') . 'registrar_profile.php',
    'dean' => ($portalViewBase ?? '') . 'dean_profile.php',
    'professor' => ($portalViewBase ?? '') . 'professor_profile.php',
    'student' => ($portalViewBase ?? '') . 'student_profile.php',
    default => 'index.php',
};
?>
<aside class="portal-icon-rail" aria-label="Portal shortcuts">
    <button class="portal-icon-button portal-nav-toggle" type="button" data-portal-nav-toggle
        aria-label="Open navigation" aria-controls="studentSidebar" aria-expanded="false" title="Navigation">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    <?php if ($activeUserRole !== 'student'): ?>
        <button class="portal-icon-button portal-people-search-toggle" type="button" data-people-search-toggle
            aria-label="Search people" aria-controls="portalPeopleSearchPanel" aria-expanded="false" title="Search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" />
                <path d="m16 16 5 5" />
            </svg>
        </button>
    <?php endif; ?>
    <div class="student-profile-menu portal-profile-menu" data-profile-menu>
        <button class="portal-icon-button portal-profile-trigger student-profile-trigger" type="button"
            aria-haspopup="true" aria-expanded="false" aria-controls="profileDropdown" data-profile-menu-trigger
            aria-label="Open profile menu" title="Profile">
            <span class="profile-avatar-wrap">
                <?php if ($activeUserProfilePhoto !== ''): ?>
                    <img class="portal-avatar-photo"
                        src="<?= htmlspecialchars($activeUserProfilePhoto, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                    <span class="avatar-circle"><?= htmlspecialchars($userInitials) ?></span>
                <?php endif; ?>
            </span>
        </button>
        <div class="student-profile-dropdown" id="profileDropdown" data-profile-menu-content hidden>
            <a class="student-profile-dropdown-link"
                href="<?= htmlspecialchars($portalProfileHref, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 21a8 8 0 0 1 16 0"></path>
                </svg>
                <span>My Profile</span>
            </a>
            <a href="<?= htmlspecialchars($siteRootPath . 'auth/logout.php', ENT_QUOTES, 'UTF-8') ?>"
                class="student-profile-dropdown-link student-profile-signout" title="Sign out of your session">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 17l5-5-5-5"></path>
                    <path d="M15 12H3"></path>
                    <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
                </svg>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</aside>
<?php if (!empty($showDashboardAnnouncements)): ?>
    <?php require __DIR__ . '/dashboard_announcements.php'; ?>
<?php endif; ?>