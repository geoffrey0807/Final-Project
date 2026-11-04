<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';

$studentId = (int) $_SESSION['user_id'];
$lessonsStmt = $pdo->prepare("
    SELECT l.id, l.title, l.content, l.file_name, l.created_at,
           c.course_code, c.course_name, c.section_name,
           u.first_name AS professor_first, u.last_name AS professor_last
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    JOIN users u ON u.id = l.professor_id
    JOIN enrollments e ON e.course_id = l.course_id
        AND e.student_id = :student_id
        AND e.status = 'APPROVED'
        AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    WHERE 1 = 1" . academicPeriodDateFilter('l.created_at') . "
    ORDER BY l.created_at DESC
");
$lessonsStmt->execute(array_merge(['student_id' => $studentId], academicPeriodDateParams()));
$lessons = $lessonsStmt->fetchAll();

$pageTitle = 'Lessons | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611010025', '../../css/student.css?v=202610292224'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'lessons';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <span class="student-eyebrow">Your course materials</span>
            <h1>Lessons</h1>
            <p>Lessons and materials shared by the professors of your enrolled courses.</p>
        </header>

        <section class="student-page-list">
            <?php if (empty($lessons)): ?>
                <div class="student-overview-card student-empty-state">
                    <p>No lessons have been shared with your enrolled courses yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <article class="student-overview-card student-task-full">
                        <div class="student-task-full-copy">
                            <span class="student-eyebrow"><?= htmlspecialchars($lesson['course_code']) ?></span>
                            <h3><?= htmlspecialchars($lesson['title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($lesson['content'])) ?></p>
                            <small class="text-muted">
                                <?= htmlspecialchars($lesson['course_name']) ?> &middot;
                                Prof. <?= htmlspecialchars(trim($lesson['professor_first'] . ' ' . $lesson['professor_last'])) ?> &middot;
                                <?= htmlspecialchars(date('M j, Y', strtotime($lesson['created_at']))) ?>
                            </small>
                            <?php if (!empty($lesson['file_name'])): ?>
                                <p>
                                    <a class="btn btn-outline btn-sm" href="../../api/download_lesson.php?id=<?= (int) $lesson['id'] ?>">
                                        Download <?= htmlspecialchars($lesson['file_name']) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
