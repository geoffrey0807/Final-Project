<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/admin_account_helpers.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/announcement_access.php';

$pageTitle = 'Admin Dashboard | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611052430', '../../css/student.css?v=202611020900'];
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
    WHERE {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY a.created_at DESC
    LIMIT 6
");
$dashboardAnnouncementsStmt->execute(academicPeriodRecordParams());
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

<?php require __DIR__ . '/_create_registrar_modal.php'; ?>

<?php require_once '../../includes/footer.php'; ?>