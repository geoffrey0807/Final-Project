<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';

$studentId = (int) $_SESSION['user_id'];
$coursesStmt = $pdo->prepare("
    SELECT e.id AS enrollment_id,
           c.id AS course_id, c.course_code, c.course_name, c.section_name,
           c.meeting_days, c.start_time, c.end_time, c.room,
           u.first_name AS prof_first, u.last_name AS prof_last, u.email AS prof_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.professor_id = u.id
    WHERE e.student_id = :student_id AND e.status = 'APPROVED'
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    " . academicPeriodRecordFilter('e', 'e.applied_at') . "
    ORDER BY c.course_code ASC
");
$coursesStmt->execute(array_merge(['student_id' => $studentId], academicPeriodRecordParams()));
$courses = $coursesStmt->fetchAll();

$pageTitle = 'Assigned Classes | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611061140', '../../css/student.css?v=202611041145'];
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
            <span class="student-eyebrow">Student portal</span>
            <h1>Assigned Classes</h1>
            <p>Your currently approved classes for this academic period.</p>
        </header>

        <?php if (empty($courses)): ?>
            <section class="student-overview-card student-empty-state">
                <h2>No courses yet</h2>
                <p>You are not currently enrolled in any courses.</p>
            </section>
        <?php else: ?>
            <section class="professor-assigned-classes" aria-label="Assigned classes">
                <div class="roster-grid">
                    <?php foreach ($courses as $course): ?>
                        <?php
                        $courseDayLabels = [
                            'Monday' => 'M',
                            'Tuesday' => 'T',
                            'Wednesday' => 'W',
                            'Thursday' => 'Th',
                            'Friday' => 'F',
                            'Saturday' => 'Sa',
                        ];
                        $courseDays = array_values(array_filter(array_map(
                            'trim',
                            explode(',', (string) ($course['meeting_days'] ?? ''))
                        )));
                        $courseTime = $course['start_time'] && $course['end_time']
                            ? strtoupper(date('g:ia', strtotime($course['start_time'])))
                            . '-' . strtoupper(date('g:ia', strtotime($course['end_time'])))
                            : null;
                        ?>
                        <article class="course-roster-card professor-assigned-course-card">
                            <div class="professor-assigned-course-header">
                                <span><?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <h2><?= htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                            </div>
                            <div class="professor-assigned-schedules">
                                <h3>Schedules</h3>
                                <?php if ($courseDays && $courseTime): ?>
                                    <ul>
                                        <?php foreach ($courseDays as $courseDay): ?>
                                            <li>
                                                <?= htmlspecialchars(
                                                    ($courseDayLabels[$courseDay] ?? $courseDay)
                                                    . '|' . $courseTime
                                                    . '|' . ($course['room'] ?: 'Room not set'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">Schedule not set</p>
                                <?php endif; ?>
                                <p class="text-muted">
                                    Prof.
                                    <?= htmlspecialchars(trim(($course['prof_first'] ?? '') . ' ' . ($course['prof_last'] ?? '')) ?: 'Instructor TBA', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <div class="professor-assigned-course-footer">
                                <a class="professor-assigned-course-open"
                                    href="student_course.php?id=<?= (int) $course['course_id'] ?>">Open Class</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php for ($emptySlot = 0; $emptySlot < max(0, 3 - count($courses)); $emptySlot++): ?>
                        <div class="professor-assigned-course-placeholder" aria-hidden="true"></div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>