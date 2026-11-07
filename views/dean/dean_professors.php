<?php
require_once '../../includes/session_check.php';
checkAccess(['dean'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';

$deanDepartmentId = requireDeanDepartmentId($pdo, (int) $_SESSION['user_id']);
if (isset($_GET['user_id'])) {
    assertDeanCanAccessUser($pdo, (int) $_SESSION['user_id'], (int) $_GET['user_id']);
}
$professorAssignmentFilter = academicPeriodRecordFilter('a', 'a.created_at', 'assignments');
$professorLessonFilter = academicPeriodRecordFilter('l', 'l.created_at', 'lessons');
$professorAnnouncementFilter = academicPeriodRecordFilter('an', 'an.created_at', 'announcements');
$deanAnnouncementAudienceFilter = announcementAudienceVisibilitySql('an', 'dean');
$professorsStmt = $pdo->prepare("
    SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, u.is_active, u.archived_at,
           COUNT(DISTINCT c.id) AS assigned_courses,
           COUNT(DISTINCT a.id) AS assignment_count,
           COUNT(DISTINCT l.id) AS lesson_count,
           COUNT(DISTINCT an.id) AS announcement_count
    FROM users u
    LEFT JOIN courses c ON c.professor_id = u.id" . academicPeriodRecordFilter('c', 'c.created_at', 'courses') . "
    LEFT JOIN assignments a ON a.professor_id = u.id AND 1 = 1$professorAssignmentFilter
    LEFT JOIN lessons l ON l.professor_id = u.id AND 1 = 1$professorLessonFilter
    LEFT JOIN announcements an ON an.author_id = u.id AND 1 = 1$professorAnnouncementFilter
        AND $deanAnnouncementAudienceFilter
    WHERE u.role = 'professor' AND u.department_id = :department_id
    GROUP BY u.id, u.id_number, u.first_name, u.last_name, u.email, u.is_active, u.archived_at
    ORDER BY u.is_active DESC, u.last_name ASC, u.first_name ASC
");
$professorsStmt->execute(array_merge(
    academicPeriodRecordParams('courses'),
    academicPeriodRecordParams('assignments'),
    academicPeriodRecordParams('lessons'),
    academicPeriodRecordParams('announcements'),
    ['department_id' => $deanDepartmentId]
));
$professors = $professorsStmt->fetchAll();

$pageTitle = 'My Professors | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611070003', '../../css/student.css?v=202610292224'];
$extraJs = ['../../js/admin.js?v=202610301355', '../../js/student.js?v=202610301835'];
$bodyClass = 'admin-dashboard student-dashboard dean-portal';
$useVisualTheme = true;
$deanPage = 'professors';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/dean_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <span class="student-eyebrow">Faculty overview</span>
            <h1>My Professors</h1>
            <p>Review the professors assigned to your department and their current portal activity.</p>
        </header>

        <?php if (empty($professors)): ?>
            <section class="student-overview-card student-empty-state">
                <h2>No professors yet</h2>
                <p>No professor accounts have been created or assigned to this Dean portal.</p>
            </section>
        <?php else: ?>
            <section class="student-course-grid dean-professor-card-grid" aria-label="Professors">
                <?php foreach ($professors as $professor): ?>
                    <?php $fullName = trim($professor['first_name'] . ' ' . $professor['last_name']); ?>
                    <article class="course-card dean-professor-card" id="user-<?= (int) $professor['id'] ?>">
                        <div class="course-card-top">
                            <div class="dean-professor-card-heading">
                                <h2 class="course-title"><?= htmlspecialchars($fullName) ?></h2>
                            </div>
                            <p class="dean-professor-id"><strong>ID
                                    Number</strong><span><?= htmlspecialchars($professor['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></span>
                            </p>
                            <p class="student-course-email"><?= htmlspecialchars($professor['email']) ?></p>
                        </div>
                        <div class="student-course-details dean-professor-card-details">
                            <p><strong>Assigned courses</strong><span><?= (int) $professor['assigned_courses'] ?></span></p>
                            <p><strong>Assignments</strong><span><?= (int) $professor['assignment_count'] ?></span></p>
                            <p><strong>Lessons</strong><span><?= (int) $professor['lesson_count'] ?></span></p>
                            <p><strong>Announcements</strong><span><?= (int) $professor['announcement_count'] ?></span></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close dean navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open dean navigation" aria-controls="studentSidebar"
    aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
    </svg></button>
<?php require_once '../../includes/footer.php'; ?>