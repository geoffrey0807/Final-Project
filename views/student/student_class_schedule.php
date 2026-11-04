<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';

$studentId = (int) $_SESSION['user_id'];
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'student');
$coursesStmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name, c.section_name, c.meeting_days, c.start_time, c.end_time, c.room,
           u.first_name AS professor_first, u.last_name AS professor_last
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    LEFT JOIN users u ON u.id = c.professor_id
    WHERE e.student_id = :student_id AND e.status = 'APPROVED'
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
      " . academicPeriodDateFilter('COALESCE(e.decided_at, e.applied_at)') . "
    ORDER BY c.start_time, c.course_code
");
$coursesStmt->execute(array_merge(['student_id' => $studentId], academicPeriodDateParams()));
$courses = $coursesStmt->fetchAll();
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$scheduleByDay = array_fill_keys($days, []);
$unscheduledCourses = [];
foreach ($courses as $course) {
    $meetingDays = array_filter(array_map('trim', explode(',', (string) $course['meeting_days'])));
    if (!$meetingDays || !$course['start_time'] || !$course['end_time']) {
        $unscheduledCourses[] = $course;
        continue;
    }
    foreach ($meetingDays as $day) {
        if (array_key_exists($day, $scheduleByDay)) {
            $scheduleByDay[$day][] = $course;
        }
    }
}

$studentAnnouncementCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    WHERE (a.course_id IS NULL
       OR EXISTS (
            SELECT 1 FROM enrollments e
            WHERE e.course_id = a.course_id
              AND e.student_id = :student_id
              AND e.status = 'APPROVED'
              AND EXISTS (
                  SELECT 1 FROM course_programs allowed_program
                  JOIN programs allowed_track ON allowed_track.id = allowed_program.program_id AND allowed_track.is_active = 1
                  JOIN users eligible_student ON eligible_student.program_id = allowed_track.id
                      AND eligible_student.role = 'student'
                  WHERE allowed_program.course_id = a.course_id AND eligible_student.id = e.student_id
              )
       )) AND {$announcementAudienceFilter}
");
$studentAnnouncementCountStmt->execute(['student_id' => $studentId]);
$studentAnnouncementCount = (int) $studentAnnouncementCountStmt->fetchColumn();
$taskCountStmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*)
         FROM enrollments pending_enrollment
         JOIN courses pending_course ON pending_course.id = pending_enrollment.course_id
         WHERE pending_enrollment.student_id = :pending_student_id
           AND pending_enrollment.status = 'PENDING'
           AND " . studentEnrollmentCourseScopeSql('pending_course', 'pending_enrollment') . ")
        +
        (SELECT COUNT(*)
         FROM assignments a
         JOIN enrollments e ON e.course_id = a.course_id
         JOIN courses c ON c.id = a.course_id
         LEFT JOIN assignment_completions ac
           ON ac.assignment_id = a.id AND ac.student_id = :completion_student_id
         WHERE e.student_id = :enrolled_student_id
           AND e.status = 'APPROVED'
           AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
           AND ac.assignment_id IS NULL) AS pending_count
");
$taskCountStmt->execute([
    'pending_student_id' => $studentId,
    'completion_student_id' => $studentId,
    'enrolled_student_id' => $studentId,
]);
$studentPendingTaskCount = (int) $taskCountStmt->fetchColumn();

$pageTitle = 'Class Schedule | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610310020', '../../css/student.css?v=202610292118'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'schedule';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <span class="student-eyebrow">Your weekly timetable</span>
            <h1>Class Schedule</h1>
            <p>Meeting times for your approved courses. Courses without published meeting details appear below as TBA.</p>
        </header>

        <section class="student-week-grid" aria-label="Weekly class timetable">
            <?php foreach ($days as $day): ?>
                <div class="student-week-column">
                    <h2><?= htmlspecialchars($day) ?></h2>
                    <?php if (empty($scheduleByDay[$day])): ?>
                        <p class="student-week-empty">No classes</p>
                    <?php else: ?>
                        <?php foreach ($scheduleByDay[$day] as $course): ?>
                            <article class="student-class-slot">
                                <time>
                                    <?= date('g:i A', strtotime($course['start_time'])) ?>
                                    &ndash;
                                    <?= date('g:i A', strtotime($course['end_time'])) ?>
                                </time>
                                <h3><?= htmlspecialchars($course['course_code']) ?></h3>
                                <p><?= htmlspecialchars($course['course_name']) ?></p>
                                <small>Section <?= htmlspecialchars($course['section_name'] ?? 'General') ?></small>
                                <small>
                                    <?= htmlspecialchars(trim(($course['professor_first'] ?? '') . ' ' . ($course['professor_last'] ?? '')) ?: 'Instructor TBA') ?>
                                </small>
                                <?php if (!empty($course['room'])): ?>
                                    <small class="student-room-label"><?= htmlspecialchars($course['room']) ?></small>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if (!empty($unscheduledCourses)): ?>
            <section class="student-overview-card student-unscheduled">
                <div class="student-overview-card-heading">
                    <div>
                        <h2>Schedule to be announced</h2>
                        <p>These courses are enrolled, but meeting details have not been published yet.</p>
                    </div>
                </div>
                <div class="student-unscheduled-list">
                    <?php foreach ($unscheduledCourses as $course): ?>
                        <div>
                            <strong><?= htmlspecialchars($course['course_code']) ?></strong>
                            <span><?= htmlspecialchars($course['course_name']) ?></span>
                            <small>
                                <?= htmlspecialchars(trim(($course['professor_first'] ?? '') . ' ' . ($course['professor_last'] ?? '')) ?: 'Instructor TBA') ?>
                                <?= $course['room'] ? ' &middot; ' . htmlspecialchars($course['room']) : '' ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
