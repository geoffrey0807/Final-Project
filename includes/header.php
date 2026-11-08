<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/academic_year.php';
$pageTitle = $pageTitle ?? 'APEX Learning Hub';
$activeUserRole = $_SESSION['role'] ?? 'guest';
$academicPeriod = $academicPeriod ?? academicPeriodFromValue($_SESSION['academic_period'] ?? null);
$academicReadOnly = $academicReadOnly ?? ($academicPeriod['read_only'] ?? false);
$activeUserRoleLabel = match ($activeUserRole) {
    'admin' => 'Administrator',
    'registrar' => 'Registrar',
    'dean' => 'Dean',
    default => ucfirst($activeUserRole),
};
$headerClockNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$isStudentPortal = $activeUserRole === 'student' && !empty($useVisualTheme);
$isAdminPortal = $activeUserRole === 'admin' && !empty($useVisualTheme);
$isRegistrarPortal = $activeUserRole === 'registrar' && !empty($useVisualTheme);
$isFacultyPortal = $activeUserRole === 'professor' && !empty($useVisualTheme);
$isDeanPortal = $activeUserRole === 'dean' && !empty($useVisualTheme);
$siteRootPath = $siteRootPath ?? (isset($_SERVER['SCRIPT_FILENAME'])
    && str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']), '/views/')
    ? '../../'
    : '../');
$activeUserName = $_SESSION['user_name'] ?? 'Guest User';
$userInitials = '';
if (!empty($activeUserName)) {
    $parts = explode(' ', trim($activeUserName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
$activeUserProfilePhoto = '';
$registrarAnnouncementGate = null;
$registrarAnnouncementGateCsrf = '';
if (!empty($_SESSION['user_id'])) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        global $pdo;
        require_once __DIR__ . '/../config/db.php';
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        $profilePhotoStmt = $pdo->prepare('SELECT profile_photo FROM users WHERE id = :id');
        $profilePhotoStmt->execute([
            'id' => (int) $_SESSION['user_id'],
        ]);
        $storedProfilePhoto = (string) ($profilePhotoStmt->fetchColumn() ?: '');
        if (preg_match('/\Auploads\/profiles\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $storedProfilePhoto)) {
            $photoFilePath = dirname(__DIR__) . '/' . $storedProfilePhoto;
            if (is_file($photoFilePath)) {
                $activeUserProfilePhoto = $siteRootPath . $storedProfilePhoto . '?v=' . filemtime($photoFilePath);
                $_SESSION['profile_photo'] = $storedProfilePhoto;
            }
        }
        if (!empty($useVisualTheme) && in_array($activeUserRole, ['admin', 'registrar', 'dean', 'professor', 'student'], true)) {
            require_once __DIR__ . '/registrar_announcement_gate.php';
            $pendingRegistrarAnnouncements = pendingRegistrarAnnouncements(
                $pdo,
                $activeUserRole,
                (int) $_SESSION['user_id']
            );
            if ($pendingRegistrarAnnouncements !== []) {
                $registrarAnnouncementGate = $pendingRegistrarAnnouncements[0];
                $registrarAnnouncementGateCount = count($pendingRegistrarAnnouncements);
                $registrarAnnouncementGateCsrf = $_SESSION['registrar_announcement_ack_csrf']
                    ??= bin2hex(random_bytes(32));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $siteRootPath ?>css/style.css?v=202611041145">
    <link rel="stylesheet" href="<?= htmlspecialchars($siteRootPath . 'css/portal-toast.css', ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($useVisualTheme)): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="<?= $siteRootPath ?>visual/css/visual.css?v=202611050900">
    <?php endif; ?>
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array) $extraCss as $stylesheet): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($useVisualTheme)): ?>
        <link rel="stylesheet" href="<?= $siteRootPath ?>css/portal-layout.css?v=202611071845">
        <link rel="stylesheet" href="<?= $siteRootPath ?>css/announcement-attachments.css?v=202611062030">
    <?php endif; ?>
    <?php if ($registrarAnnouncementGate !== null): ?>
        <link rel="stylesheet"
            href="<?= htmlspecialchars($siteRootPath . 'css/registrar-announcement-gate.css?v=202611041802', ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?><?= !empty($useVisualTheme) ? ' data-visual-base="' . htmlspecialchars($siteRootPath . 'visual/') . '"' : '' ?><?= !empty($academicReadOnly) ? ' data-academic-readonly="true"' : '' ?><?= $registrarAnnouncementGate !== null ? ' data-registrar-announcement-gate="open"' : '' ?>
    data-academic-period-mode="<?= htmlspecialchars($_SESSION['academic_period_mode'] ?? 'global', ENT_QUOTES, 'UTF-8') ?>"
    data-academic-period="<?= htmlspecialchars($academicPeriod === null ? '' : $academicPeriod['academic_year'] . ':' . $academicPeriod['semester'], ENT_QUOTES, 'UTF-8') ?>"
    data-academic-period-endpoint="<?= htmlspecialchars($siteRootPath . 'api/academic_period.php', ENT_QUOTES, 'UTF-8') ?>">
    <header class="app-navbar">
        <?php if (!empty($useVisualTheme)): ?>
            <div class="navbar-brand header-logo" role="img" aria-label="APEX Learning Hub">
                <svg class="header-logo-mark" viewBox="0 0 504 428" role="img" aria-hidden="true">
                    <title>APEX Brand Mark</title>
                    <path fill="var(--red-base)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                    <path fill="var(--red-base)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                    <path fill="var(--red-base)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                </svg>
                <span class="header-logo-type">
                    <span class="header-wordmark">APEX</span>
                    <span class="header-tagline">Learning Hub</span>
                </span>
            </div>
        <?php else: ?>
            <div class="navbar-brand" role="img" aria-label="APEX Learning Hub">
                <div class="logo logo-nav">
                    <svg class="logo-mark" viewBox="0 0 504 428" role="img" aria-labelledby="nav-mark-title">
                        <title id="nav-mark-title">APEX three-part geometric mark</title>
                        <path fill="var(--navy)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                        <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                        <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                    </svg>
                    <div class="logo-type">
                        <span class="wordmark">APEX</span>
                        <span class="tagline">Learning Hub</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-right">
                <time class="app-navbar-clock" data-apex-clock aria-label="Current time in Manila"
                    datetime="<?= htmlspecialchars($headerClockNow->format(DATE_ATOM), ENT_QUOTES, 'UTF-8') ?>">
                    <span
                        data-clock-date><?= htmlspecialchars($headerClockNow->format('D | F d, Y'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span
                        data-clock-time><?= htmlspecialchars($headerClockNow->format('g:i:s A'), ENT_QUOTES, 'UTF-8') ?></span>
                </time>
                <?php if (!($isStudentPortal || $isAdminPortal || $isRegistrarPortal || $isFacultyPortal || $isDeanPortal)): ?>
                    <div class="user-badge">
                        <div class="avatar-circle"><?= htmlspecialchars($userInitials) ?></div>
                        <div class="user-meta">
                            <span class="user-name"><?= htmlspecialchars($activeUserName) ?></span>
                            <span class="role-pill role-<?= htmlspecialchars($activeUserRole) ?>">
                                <?= htmlspecialchars($activeUserRoleLabel) ?>
                            </span>
                        </div>
                    </div>
                    <a href="<?= $siteRootPath ?>auth/logout.php"
                        class="btn btn-outline btn-sm<?= !empty($useVisualTheme) ? ' admin-signout' : '' ?>"
                        title="Sign out of your session">
                        Sign Out
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="navbar-right">
                <time class="app-navbar-clock" data-apex-clock aria-label="Current time in Manila"
                    datetime="<?= htmlspecialchars($headerClockNow->format(DATE_ATOM), ENT_QUOTES, 'UTF-8') ?>">
                    <span
                        data-clock-date><?= htmlspecialchars($headerClockNow->format('D | F d, Y'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span
                        data-clock-time><?= htmlspecialchars($headerClockNow->format('g:i:s A'), ENT_QUOTES, 'UTF-8') ?></span>
                </time>
                <a href="<?= $siteRootPath ?>auth/login.php" class="btn btn-primary btn-sm">Sign In</a>
            </div>
        <?php endif; ?>
    </header>
    <?php if ($registrarAnnouncementGate !== null): ?>
        <div class="registrar-announcement-gate" data-registrar-announcement-gate-overlay
            data-site-root="<?= htmlspecialchars($siteRootPath, ENT_QUOTES, 'UTF-8') ?>"
            data-announcement-count="<?= (int) $registrarAnnouncementGateCount ?>">
            <section class="registrar-announcement-dialog" role="dialog" aria-modal="true"
                aria-labelledby="registrarAnnouncementTitle" aria-describedby="registrarAnnouncementMessage">
                <header class="registrar-announcement-header">
                    <svg class="registrar-announcement-ornament" viewBox="0 0 640 32" preserveAspectRatio="none"
                        aria-hidden="true" focusable="false">
                        <path d="M13 16H294M346 16h281" />
                        <path d="M4 12c3 0 6 1 9 4-3 3-6 4-9 4m623-8c3 0 6 1 9 4-3 3-6 4-9 4" />
                        <path d="m296 16 4-3 4 3-4 3zm36 0 4-3 4 3-4 3z" />
                        <path class="registrar-announcement-ornament-fleur"
                            d="M320 3c-6 5-6 10 0 14 6-4 6-9 0-14zm-2 14c-8-7-14-4-9 3 3 4 7 4 11 2 4 2 8 2 11-2 5-7-1-10-9-3m-2 3c-1 4-4 6-8 7 4 1 7 0 10-3 3 3 6 4 10 3-4-1-7-3-8-7" />
                        <circle cx="3" cy="16" r="1.4" />
                        <circle cx="637" cy="16" r="1.4" />
                    </svg>
                    <p class="registrar-announcement-label">Office of the College Registrar</p>
                    <hr class="registrar-announcement-divider">
                </header>
                <div class="registrar-announcement-content">
                    <p class="registrar-announcement-queue-status" data-announcement-queue-status>
                        1/<?= (int) $registrarAnnouncementGateCount ?>
                    </p>
                    <h1 id="registrarAnnouncementTitle">
                        <?= htmlspecialchars($registrarAnnouncementGate['headline'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div class="registrar-announcement-message" id="registrarAnnouncementMessage">
                        <?= htmlspecialchars($registrarAnnouncementGate['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <hr class="registrar-announcement-divider">
                    <?php
                    $registrarAnnouncementImagePath = is_string($registrarAnnouncementGate['image_path'])
                        && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $registrarAnnouncementGate['image_path'])
                        ? $registrarAnnouncementGate['image_path']
                        : '';
                    ?>
                    <button class="announcement-attachment-open" type="button" data-announcement-attachment-open
                        data-image-src="<?= htmlspecialchars($registrarAnnouncementImagePath === '' ? '' : $siteRootPath . $registrarAnnouncementImagePath, ENT_QUOTES, 'UTF-8') ?>"
                        data-image-alt="<?= htmlspecialchars($registrarAnnouncementGate['headline'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $registrarAnnouncementImagePath === '' ? 'hidden' : '' ?>>
                        View Attachment
                    </button>
                </div>
                <footer class="registrar-announcement-footer">
                    <form class="registrar-announcement-acknowledgement" data-registrar-announcement-acknowledgement
                        data-endpoint="<?= htmlspecialchars($siteRootPath . 'api/registrar_announcement_acknowledge.php', ENT_QUOTES, 'UTF-8') ?>"
                        data-announcement-id="<?= (int) $registrarAnnouncementGate['id'] ?>"
                        data-csrf-token="<?= htmlspecialchars($registrarAnnouncementGateCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <p class="registrar-announcement-error" data-announcement-ack-error role="alert" hidden></p>
                        <button class="registrar-announcement-understand" type="submit"
                            data-announcement-ack-button><?= $registrarAnnouncementGateCount > 1 ? 'Next' : 'Okay' ?></button>
                    </form>
                    <svg class="registrar-announcement-ornament registrar-announcement-footer-ornament" viewBox="0 0 640 32"
                        preserveAspectRatio="none" aria-hidden="true" focusable="false">
                        <path d="M13 16H294M346 16h281" />
                        <path d="M4 12c3 0 6 1 9 4-3 3-6 4-9 4m623-8c3 0 6 1 9 4-3 3-6 4-9 4" />
                        <path d="m296 16 4-3 4 3-4 3zm36 0 4-3 4 3-4 3z" />
                        <path class="registrar-announcement-ornament-fleur"
                            d="M320 3c-6 5-6 10 0 14 6-4 6-9 0-14zm-2 14c-8-7-14-4-9 3 3 4 7 4 11 2 4 2 8 2 11-2 5-7-1-10-9-3m-2 3c-1 4-4 6-8 7 4 1 7 0 10-3 3 3 6 4 10 3-4-1-7-3-8-7" />
                        <circle cx="3" cy="16" r="1.4" />
                        <circle cx="637" cy="16" r="1.4" />
                    </svg>
                </footer>
            </section>
        </div>
    <?php endif; ?>
    <main class="app-main" <?= $registrarAnnouncementGate !== null ? ' inert' : '' ?>>