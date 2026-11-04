<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';

$statement = $pdo->prepare("
    SELECT c.course_code, c.course_name, c.section_name, c.meeting_days, c.start_time, c.end_time, c.room
    FROM courses c
    WHERE c.professor_id = :professor_id
    ORDER BY c.start_time, c.course_code
");
$statement->execute(['professor_id' => (int) $_SESSION['user_id']]);
$courses = $statement->fetchAll();
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$scheduleByDay = array_fill_keys($days, []);
foreach ($courses as $course) {
    foreach (array_filter(array_map('trim', explode(',', (string) $course['meeting_days']))) as $day) {
        if (isset($scheduleByDay[$day]) && $course['start_time'] && $course['end_time']) {
            $scheduleByDay[$day][] = $course;
        }
    }
}

$pageTitle = 'Teaching Schedule | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610310020', '../../css/student.css?v=202610292224'];
$extraJs = ['../../js/admin.js?v=202610301355', '../../js/student.js?v=202610301825'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal';
$useVisualTheme = true;
$professorView = 'schedule';
require_once '../../includes/header.php';
?>
<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <div class="student-dashboard-heading">
            <div class="student-dashboard-heading-copy">
                <h1>Teaching Schedule</h1>
            </div>
        </div>
        <section class="student-week-grid" aria-label="Weekly teaching timetable">
            <?php foreach ($days as $day): ?>
                <div class="student-week-column">
                    <h2><?= htmlspecialchars($day) ?></h2>
                    <?php if (!$scheduleByDay[$day]): ?>
                        <p class="student-week-empty">No classes</p><?php endif; ?>
                    <?php foreach ($scheduleByDay[$day] as $course): ?>
                        <article class="student-class-slot">
                            <time><?= date('g:i A', strtotime($course['start_time'])) ?> &ndash;
                                <?= date('g:i A', strtotime($course['end_time'])) ?></time>
                            <h3><?= htmlspecialchars($course['course_code']) ?></h3>
                            <p><?= htmlspecialchars($course['course_name']) ?></p>
                            <small>Section <?= htmlspecialchars($course['section_name'] ?? 'General') ?></small>
                            <?php if ($course['room']): ?><small
                                    class="student-room-label"><?= htmlspecialchars($course['room']) ?></small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>