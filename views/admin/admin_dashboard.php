<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/admin_account_helpers.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/announcement_access.php';

$pageTitle = 'Admin Dashboard | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610301232', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202610292245', '../../js/student.js?v=202610301835'];
$bodyClass = 'admin-dashboard student-dashboard admin-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$adminPage = 'dashboard';
$csrfToken = adminCsrfToken();

$summaryStmt = $pdo->query("
    SELECT
        SUM(role IN ('dean', 'professor', 'student') AND is_active = 1 AND archived_at IS NULL) AS active_users,
        SUM(role = 'registrar' AND is_active = 1 AND archived_at IS NULL) AS active_registrars,
        SUM(role IN ('registrar', 'dean', 'professor', 'student') AND is_active = 0 AND archived_at IS NULL) AS disabled_users,
        SUM(role IN ('registrar', 'dean', 'professor', 'student') AND archived_at IS NOT NULL) AS archived_users
    FROM users
");
$summary = $summaryStmt->fetch();
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'admin');
$dashboardAnnouncementsStmt = $pdo->prepare("
    SELECT a.headline AS title, a.message, c.course_code, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE {$announcementAudienceFilter}" . academicPeriodDateFilter('a.created_at') . "
    ORDER BY a.created_at DESC
    LIMIT 6
");
$dashboardAnnouncementsStmt->execute(academicPeriodDateParams());
$dashboardAnnouncements = $dashboardAnnouncementsStmt->fetchAll();
$dashboardAnnouncementsHref = '../announcements.php';
$showDashboardAnnouncements = true;
$openCreateRegistrarModal = ($_GET['modal'] ?? '') === 'create-registrar';
$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$greeting = dashboardGreeting($manilaNow);

require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell has-dashboard-announcements" id="studentDashboardShell">
    <?php require '../../includes/admin_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <div class="student-dashboard-heading">
            <div class="student-dashboard-heading-copy">
                <h1>Dashboard</h1>
            </div>
        </div>

        <div class="student-dashboard-layout admin-dashboard-layout">
            <div class="student-dashboard-main-column student-dashboard-intro">
                <section class="student-welcome-card">
                    <div class="student-welcome-copy">
                        <h1><?= $greeting ?>, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php require '../../includes/role_identifier_summary.php'; ?>
                        <?php require '../../includes/academic_period_summary.php'; ?>
                    </div>
                </section>
            </div>

            <div class="student-dashboard-main-column student-dashboard-details">
                <section class="metrics-grid admin-summary-grid" aria-label="Account overview">
                    <article class="metric-card">
                        <div class="metric-icon navy" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="9" cy="8" r="3" />
                                <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 0 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= number_format((int) ($summary['active_users'] ?? 0)) ?></h3>
                            <p>ACTIVE ACCOUNT</p>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6M23 11h-6" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= number_format((int) ($summary['active_registrars'] ?? 0)) ?></h3>
                            <p>REGISTRAR COUNT</p>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon coral" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="9" cy="8" r="3" />
                                <path d="M3 20v-1a6 6 0 0 1 12 0v1M17 8h4M19 6v4" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= number_format((int) ($summary['disabled_users'] ?? 0)) ?></h3>
                            <p>DISABLED ACCOUNT</p>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon gold" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 7h16v13H4zM3 4h18v3H3zM9 11h6" />
                            </svg>
                        </div>
                        <div class="metric-data">
                            <h3><?= number_format((int) ($summary['archived_users'] ?? 0)) ?></h3>
                            <p>ARCHIVED ACCOUNT</p>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close admin navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open admin navigation" aria-controls="studentSidebar"
    aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<div class="modal-backdrop<?= $openCreateRegistrarModal ? ' active' : '' ?>" id="createRegistrarModal">
    <div class="modal-dialog">
        <form action="../../api/admin_accounts_process.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create_registrar">
            <div class="modal-header">
                <h2 class="modal-title">Create Registrar Account</h2>
                <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted">A maximum of two registrar accounts can be active at once. Disabled and archived registrars do not count toward this limit.</p>
                <div class="admin-form-name-grid">
                    <div class="form-group">
                        <label for="registrarLastName">Last Name *</label>
                        <input type="text" class="form-control" id="registrarLastName" name="last_name"
                            placeholder="e.g. Dela Cruz" maxlength="50" autocomplete="family-name" required>
                    </div>
                    <div class="form-group">
                        <label for="registrarFirstName">First Name *</label>
                        <input type="text" class="form-control" id="registrarFirstName" name="first_name"
                            placeholder="e.g. Juan" maxlength="50" autocomplete="given-name" required>
                    </div>
                </div>
                <div class="admin-form-name-grid">
                    <div class="form-group">
                        <label for="registrarMiddleName">Middle Name</label>
                        <input type="text" class="form-control" id="registrarMiddleName" name="middle_name"
                            placeholder="Optional" maxlength="50" autocomplete="additional-name">
                    </div>
                    <div class="form-group">
                        <label for="registrarSuffix">Suffix</label>
                        <input type="text" class="form-control" id="registrarSuffix" name="suffix"
                            placeholder="Optional (e.g. Jr.)" maxlength="20" autocomplete="honorific-suffix">
                    </div>
                </div>
                <div class="form-group">
                    <label for="registrarIdNumber">ID Number *</label>
                    <input type="text" id="registrarIdNumber" name="id_number" class="form-control"
                        placeholder="REG-<?= htmlspecialchars($manilaNow->format('Y'), ENT_QUOTES, 'UTF-8') ?>-0001"
                        maxlength="13" pattern="[Rr][Ee][Gg]-<?= htmlspecialchars($manilaNow->format('Y'), ENT_QUOTES, 'UTF-8') ?>-[0-9]{4}"
                        title="Enter REG-<?= htmlspecialchars($manilaNow->format('Y'), ENT_QUOTES, 'UTF-8') ?>- followed by four digits."
                        autocomplete="off" required>
                    <small class="text-muted">Format: REG-<?= htmlspecialchars($manilaNow->format('Y'), ENT_QUOTES, 'UTF-8') ?>-####</small>
                </div>
                <div class="form-group">
                    <label for="registrarEmail">APEX Email *</label>
                    <input type="email" id="registrarEmail" name="email" class="form-control"
                        placeholder="user@apex.edu.ph" maxlength="100" autocomplete="email"
                        pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph" title="Use an APEX Email ending in @apex.edu.ph."
                        required>
                </div>
                <div class="form-group">
                    <label for="registrarPassword">Initial Password *</label>
                    <input type="password" id="registrarPassword" name="password" class="form-control"
                        placeholder="8+ characters, upper/lowercase, number, symbol" minlength="8" maxlength="72"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                        title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                        autocomplete="new-password" required>
                    <small class="text-muted">Use at least 8 characters, including uppercase and lowercase letters, a
                        number, and a special character.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add User Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>