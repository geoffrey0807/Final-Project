<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';

$professorId = (int) $_SESSION['user_id'];
$courseStmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name
    FROM courses c
    WHERE c.professor_id = :professor_id AND c.archived_at IS NULL
      AND c.academic_year = :academic_year AND c.semester = :semester
    ORDER BY c.course_code ASC
");
$courseStmt->execute([
    'professor_id' => $professorId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$courses = $courseStmt->fetchAll();

$announcementStmt = $pdo->prepare("
    SELECT a.*, c.course_code, c.course_name
    FROM announcements a
    JOIN courses c ON c.id = a.course_id
    WHERE a.author_id = :professor_id
      AND a.academic_year = :academic_year AND a.semester = :semester
    ORDER BY a.created_at DESC
");
$announcementStmt->execute([
    'professor_id' => $professorId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$announcements = $announcementStmt->fetchAll();

$professorView = 'announcements';
$pageTitle = 'Class Announcements | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611061140', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301825'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal';
$useVisualTheme = true;
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <h1>Class Announcements</h1>
        </header>

        <section class="professor-course-announcement-panel professor-announcements-page">
            <div class="professor-course-panel-header">
                <h2>Post to a Course</h2>
                <span class="professor-course-scope-label">Visible only to selected students</span>
            </div>

            <form class="professor-course-announcement-form" action="../../api/professor_process.php" method="POST">
                <input type="hidden" name="action" value="post_announcement">
                <div class="form-group">
                    <label for="announcementCourseId">Course</label>
                    <select id="announcementCourseId" class="form-control" name="course_id" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>">
                                <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="announcementTitle">Title</label>
                    <input id="announcementTitle" class="form-control" type="text" name="title"
                        placeholder="Reminder on assignment deadline" required>
                </div>
                <div class="form-group">
                    <label for="announcementMessage">Message</label>
                    <textarea id="announcementMessage" class="form-control" name="message" rows="6"
                        placeholder="Post only to students enrolled in the selected course..." required></textarea>
                </div>
                <div class="professor-course-form-actions">
                    <button type="reset" class="btn btn-outline">Clear</button>
                    <button type="submit" class="btn btn-primary">Publish Announcement</button>
                </div>
            </form>
        </section>

        <section class="professor-course-panel professor-announcements-list">
            <div class="professor-course-panel-header">
                <h2>Recent Posts</h2>
            </div>
            <?php if (empty($announcements)): ?>
                <div class="card course-empty">No class announcements yet.</div>
            <?php else: ?>
                <?php foreach ($announcements as $item): ?>
                    <article class="card course-content-card">
                        <div class="professor-announcement-header">
                            <span
                                class="professor-course-file-badge"><?= htmlspecialchars($item['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                            <small><?= htmlspecialchars(date('M j, Y g:i A', strtotime($item['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <h3><?= htmlspecialchars($item['headline'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= nl2br(htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>