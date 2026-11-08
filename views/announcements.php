<?php
require_once '../includes/session_check.php';
checkAccess(['admin', 'registrar', 'dean', 'professor', 'student'], '../auth/login.php');
require_once '../config/db.php';
require_once '../includes/academic_access.php';
require_once '../includes/academic_year.php';
require_once '../includes/announcement_access.php';
require_once '../includes/portal_notification_counts.php';
require_once '../includes/portal_pagination.php';

$role = $_SESSION['role'];
$audienceVisibilitySql = announcementAudienceVisibilitySql('a', $role);
$userId = (int) $_SESSION['user_id'];
$academicPeriod = academicPeriodFromValue($_SESSION['academic_period'] ?? null);
$siteRootPath = '../';
$visibilitySql = '1 = 1';
$queryParams = [];

if ($role === 'student') {
    $visibilitySql = "(
        a.course_id IS NULL
        OR EXISTS (
            SELECT 1
            FROM enrollments e
            JOIN courses scoped_course ON scoped_course.id = e.course_id
            WHERE e.course_id = a.course_id
              AND e.student_id = :student_id
              AND e.status = 'APPROVED'
              AND " . studentEnrollmentCourseScopeSql('scoped_course', 'e') . "
        )
    )";
    $queryParams['student_id'] = $userId;
} elseif ($role === 'professor') {
    $visibilitySql = "(
        a.course_id IS NULL
        OR EXISTS (
            SELECT 1
            FROM courses professor_course
            WHERE professor_course.id = a.course_id
              AND professor_course.professor_id = :professor_id
        )
    )";
    $queryParams['professor_id'] = $userId;
} elseif ($role === 'dean') {
    $departmentId = requireDeanDepartmentId($pdo, $userId);
    $visibilitySql = "(a.course_id IS NULL OR (u.role = 'professor' AND u.department_id = :department_id))";
    $queryParams['department_id'] = $departmentId;
}

$dateFilter = academicPeriodRecordFilter('a', 'a.created_at');
$itemsPerPage = 10;
$announcementCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE {$visibilitySql} AND {$audienceVisibilitySql}{$dateFilter}
");
$announcementCountStmt->execute(array_merge($queryParams, academicPeriodRecordParams()));
$totalAnnouncements = (int) $announcementCountStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalAnnouncements / $itemsPerPage));
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$currentPage = max(1, min($requestedPage === false || $requestedPage === null ? 1 : $requestedPage, $totalPages));
$announcementOffset = ($currentPage - 1) * $itemsPerPage;
$query = "
    SELECT a.id, a.headline, a.message, a.image_path, a.created_at, c.course_code, c.course_name,
           u.first_name, u.last_name, u.role AS author_role
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE {$visibilitySql} AND {$audienceVisibilitySql}{$dateFilter}
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT :limit OFFSET :offset
";
$announcementsStmt = $pdo->prepare($query);
$announcementParams = array_merge($queryParams, academicPeriodRecordParams());
foreach ($announcementParams as $parameter => $value) {
    $announcementsStmt->bindValue(':' . $parameter, $value);
}
$announcementsStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$announcementsStmt->bindValue(':offset', $announcementOffset, PDO::PARAM_INT);
$announcementsStmt->execute();
$announcements = $announcementsStmt->fetchAll();

if ($announcements) {
    $markRead = $pdo->prepare("
        INSERT IGNORE INTO user_announcement_reads (announcement_id, user_id)
        VALUES (:announcement_id, :user_id)
    ");
    foreach ($announcements as $announcement) {
        $markRead->execute([
            'announcement_id' => (int) $announcement['id'],
            'user_id' => $userId,
        ]);
    }
}

$pageTitle = 'Announcements | APEX Learning Hub';
$extraCss = ['../css/admin.css?v=202611031950', '../css/student.css?v=202611041602'];
$bodyClass = 'admin-dashboard student-dashboard announcements-portal';
$useVisualTheme = true;
$hideSharedSearch = true;
$portalViewBase = $role . '/';
$portalAnnouncementsHref = 'announcements.php';
$portalSupportHref = 'support/my_requests.php';
$portalAnnouncementsActive = true;
$adminPage = 'announcements';
$studentPage = 'announcements';
$professorView = '';
$deanPage = 'announcements';
$registrarView = '';
$extraJs = match ($role) {
    'student' => '../js/student.js?v=202610291930',
    default => ['../js/admin.js?v=202611031901', '../js/student.js?v=202610301835'],
};

require_once '../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php
    match ($role) {
        'admin' => require '../includes/admin_sidebar.php',
        'registrar' => require '../includes/registrar_sidebar.php',
        'dean' => require '../includes/dean_sidebar.php',
        'professor' => require '../includes/professor_sidebar.php',
        'student' => require '../includes/student_sidebar.php',
    };
    ?>
    <div class="student-dashboard-content container">
        <header class="student-page-header">
            <span class="student-eyebrow">Updates from your community</span>
            <h1>Announcements</h1>
            <p>Browse announcements you have access to, with the full message, author, and posting time.</p>
        </header>

        <section class="student-page-list" aria-label="Announcements">
            <?php if (!$announcements): ?>
                <div class="student-overview-card student-empty-state">
                    <h2>No Announcements Available</h2>
                    <p>There are no announcements for your account and selected academic period.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <article class="student-overview-card student-announcement-full">
                        <div class="student-announcement-full-heading">
                            <div>
                                <span class="student-announcement-role student-eyebrow">
                                    <?= htmlspecialchars(announcementAuthorLabel($announcement['author_role']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <time
                                datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </div>
                        <hr class="student-announcement-divider">
                        <h2 class="student-announcement-title">
                            <?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="student-announcement-body">
                            <?= nl2br(htmlspecialchars($announcement['message'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                        <?php if (
                            is_string($announcement['image_path'])
                            && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $announcement['image_path'])
                        ): ?>
                            <hr class="student-announcement-divider">
                            <button class="announcement-attachment-open" type="button" data-announcement-attachment-open
                                data-image-src="../<?= htmlspecialchars($announcement['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                data-image-alt="<?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                View Attachment
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php renderPortalPagination($currentPage, $totalAnnouncements, $itemsPerPage); ?>
        </section>
    </div>
</div>
<?php if (!in_array($role, ['student', 'registrar'], true)): ?>
    <button type="button" class="student-sidebar-backdrop" aria-label="Close portal navigation"></button>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>