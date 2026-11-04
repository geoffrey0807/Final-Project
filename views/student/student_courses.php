<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';

$studentId = (int) $_SESSION['user_id'];
$coursesStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id, e.decided_at,
           c.course_code, c.course_name, c.meeting_days, c.start_time, c.end_time, c.room,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :student_id AND e.status = 'APPROVED'
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
      " . academicPeriodDateFilter('COALESCE(e.decided_at, e.applied_at)') . "
    ORDER BY c.course_code ASC
");
$coursesStmt->execute(array_merge(['student_id' => $studentId], academicPeriodDateParams()));
$courses = $coursesStmt->fetchAll();

$pageTitle = 'My Courses | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610291355', '../../css/student.css?v=202610292118'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'courses';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <span class="student-eyebrow">Your learning</span>
            <h1>My Courses</h1>
            <p>Your currently approved course enrollments and class details.</p>
        </header>

        <?php if (empty($courses)): ?>
            <section class="student-overview-card student-empty-state">
                <h2>No courses yet</h2>
                <p>You are not currently enrolled in any courses.</p>
                <a class="btn btn-primary" href="student_dashboard.php#tabCatalog">Browse the course catalog</a>
            </section>
        <?php else: ?>
            <section class="student-course-grid" aria-label="My enrolled courses">
                <?php foreach ($courses as $course): ?>
                    <article class="course-card student-course-card">
                        <div class="course-card-top">
                            <div class="course-badge-row">
                                <span class="course-code-tag"><?= htmlspecialchars($course['course_code']) ?></span>
                                <span class="badge badge-approved">Enrolled</span>
                            </div>
                            <h2 class="course-title"><?= htmlspecialchars($course['course_name']) ?></h2>
                            <div class="course-instructor">
                                <div class="instructor-avatar" aria-hidden="true">
                                    <?= htmlspecialchars(strtoupper(substr($course['prof_first'] ?? 'P', 0, 1))) ?>
                                </div>
                                <span>
                                    <?php if ($course['prof_first'] || $course['prof_last']): ?>
                                        Prof. <?= htmlspecialchars(trim(($course['prof_first'] ?? '') . ' ' . ($course['prof_last'] ?? ''))) ?>
                                    <?php else: ?>
                                        Instructor to be announced
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($course['prof_email'])): ?>
                                <p class="student-course-email"><?= htmlspecialchars($course['prof_email']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="student-course-details">
                            <p>
                                <strong>Class days</strong>
                                <span><?= htmlspecialchars($course['meeting_days'] ? str_replace(',', ' · ', $course['meeting_days']) : 'To be announced') ?></span>
                            </p>
                            <p>
                                <strong>Time</strong>
                                <span>
                                    <?php if ($course['start_time'] && $course['end_time']): ?>
                                        <?= date('g:i A', strtotime($course['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($course['end_time'])) ?>
                                    <?php else: ?>
                                        To be announced
                                    <?php endif; ?>
                                </span>
                            </p>
                            <?php if (!empty($course['room'])): ?>
                                <p><strong>Room</strong><span><?= htmlspecialchars($course['room']) ?></span></p>
                            <?php endif; ?>
                            <p><strong>Enrolled</strong><span><?= date('M j, Y', strtotime($course['decided_at'] ?? 'now')) ?></span></p>
                        </div>

                        <div class="course-card-bottom student-course-actions">
                            <a class="btn btn-outline btn-sm" href="student_class_schedule.php">View schedule</a>
                            <form action="../../api/student_process.php" method="POST" onsubmit="return confirm('Are you sure you want to drop this enrolled course?');">
                                <input type="hidden" name="action" value="drop_course">
                                <input type="hidden" name="enrollment_id" value="<?= (int) $course['enrollment_id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm student-drop-course">Drop course</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
