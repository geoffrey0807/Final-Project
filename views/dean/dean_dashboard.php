<?php
require_once '../../includes/session_check.php';
checkAccess(['dean'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/dashboard_greeting.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';

$pageTitle = 'Dean Dashboard | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611052030', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202610301355', '../../js/student.js?v=202610301835'];
$bodyClass = 'admin-dashboard student-dashboard dean-portal';
$useVisualTheme = true;
$deanPage = 'dashboard';
$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$greeting = dashboardGreeting($manilaNow);
$deanDepartmentId = requireDeanDepartmentId($pdo, (int) $_SESSION['user_id']);
if (isset($_GET['user_id'])) {
    assertDeanCanAccessUser($pdo, (int) $_SESSION['user_id'], (int) $_GET['user_id']);
}

$professorAssignmentFilter = academicPeriodRecordFilter('a', 'a.created_at', 'assignments');
$professorAnnouncementFilter = academicPeriodRecordFilter('an', 'an.created_at', 'announcements');
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'dean');
$professorsStmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.is_active, u.archived_at,
           COUNT(DISTINCT c.id) AS assigned_courses,
           COUNT(DISTINCT a.id) AS assessment_count,
           COUNT(DISTINCT an.id) AS announcement_count,
           GREATEST(
               COALESCE(MAX(a.created_at), '1970-01-01'),
               COALESCE(MAX(an.created_at), '1970-01-01')
           ) AS latest_activity
    FROM users u
    LEFT JOIN courses c ON c.professor_id = u.id" . academicPeriodRecordFilter('c', 'c.created_at', 'courses') . "
    LEFT JOIN assignments a ON a.professor_id = u.id AND 1 = 1$professorAssignmentFilter
    LEFT JOIN announcements an ON an.author_id = u.id AND 1 = 1$professorAnnouncementFilter
    WHERE u.role = 'professor' AND u.department_id = :department_id
    GROUP BY u.id, u.first_name, u.last_name, u.email, u.is_active, u.archived_at
    ORDER BY latest_activity DESC, u.is_active DESC, u.last_name ASC
");
$professorsStmt->execute(array_merge(
    academicPeriodRecordParams('courses'),
    academicPeriodRecordParams('assignments'),
    academicPeriodRecordParams('announcements'),
    ['department_id' => $deanDepartmentId]
));
$professors = $professorsStmt->fetchAll();
$activeProfessorCount = count(array_filter($professors, static fn(array $professor): bool => (int) $professor['is_active'] === 1));
$assignedCourseCount = array_sum(array_map(
    static fn(array $professor): int => (int) $professor['assigned_courses'],
    $professors
));
$announcementCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    WHERE u.role = 'professor' AND u.department_id = :department_id
    AND {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
");
$announcementCountStmt->execute(array_merge(['department_id' => $deanDepartmentId], academicPeriodRecordParams()));
$announcementCount = (int) $announcementCountStmt->fetchColumn();
$latestAnnouncementsStmt = $pdo->prepare("
    SELECT a.headline AS title, a.message, a.created_at, c.course_code, u.first_name, u.last_name, u.role
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE (
        (u.role = 'registrar' AND a.course_id IS NULL)
        OR (u.role = 'professor' AND u.department_id = :department_id)
    )
    AND {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 3
");
$latestAnnouncementsStmt->execute(array_merge(['department_id' => $deanDepartmentId], academicPeriodRecordParams()));
$latestAnnouncements = $latestAnnouncementsStmt->fetchAll();
$dashboardAnnouncements = $latestAnnouncements;
$dashboardAnnouncementsHref = '../announcements.php';
$showDashboardAnnouncements = true;
require_once '../../includes/header.php';
?>
<div class="student-dashboard-shell has-dashboard-announcements" id="studentDashboardShell">
    <?php require '../../includes/dean_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <div class="student-dashboard-heading">
            <div class="student-dashboard-heading-copy">
                <h1>Dashboard</h1>
            </div>
        </div>
        <div class="dean-dashboard-layout">
            <div class="dean-dashboard-main-column">
                <section class="student-welcome-card dean-welcome-card">
                    <div class="student-welcome-copy">
                        <h1><?= $greeting ?>, Dean <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
                        <?php require '../../includes/role_identifier_summary.php'; ?>
                        <?php require '../../includes/academic_period_summary.php'; ?>
                    </div>
                </section>
                <section class="metrics-grid dean-summary-grid" aria-label="Department overview">
                    <article class="metric-card">
                        <div class="metric-icon navy" aria-hidden="true"><svg viewBox="0 0 24 24">
                                <circle cx="9" cy="8" r="3" />
                                <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 0 0 5.8M18 14a5 5 0 0 1 3 4.5V20" />
                            </svg></div>
                        <div class="metric-data">
                            <h3><?= $activeProfessorCount ?></h3>
                            <p>Active Professors</p>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon gold" aria-hidden="true"><svg viewBox="0 0 24 24">
                                <path
                                    d="M4 14V9a1 1 0 0 1 1-1h3l9-4v14l-9-4H5a1 1 0 0 1-1-1ZM8 14l2 6h4l-2.5-7.1M20 8l2-1M20 12h2M20 16l2 1" />
                            </svg></div>
                        <div class="metric-data">
                            <h3><?= $announcementCount ?></h3>
                            <p>Professor Announcements</p>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon coral" aria-hidden="true"><svg viewBox="0 0 24 24">
                                <path d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h4" />
                            </svg></div>
                        <div class="metric-data">
                            <h3><?= $assignedCourseCount ?></h3>
                            <p>Assigned Courses</p>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close dean navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open dean navigation" aria-controls="studentSidebar"
    aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg></button>
<?php require_once '../../includes/footer.php'; ?>