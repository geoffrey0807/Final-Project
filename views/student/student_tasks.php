<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';

$studentId = (int) $_SESSION['user_id'];
$requestedCourseId = $_GET['course_id'] ?? null;
$courseFilterId = 0;
if ($requestedCourseId !== null) {
    $validatedCourseId = filter_var($requestedCourseId, FILTER_VALIDATE_INT);
    if ($validatedCourseId === false || $validatedCourseId === null || $validatedCourseId < 1) {
        http_response_code(404);
        exit('Class not found.');
    }
    $courseFilterId = (int) $validatedCourseId;
}
$courseFilterSql = $courseFilterId > 0 ? ' AND c.id = :course_id' : '';
$assignmentParams = [
    'enrolled_student_id' => $studentId,
    'completed_student_id' => $studentId,
];
if ($courseFilterId > 0) {
    $assignmentParams['course_id'] = $courseFilterId;
}
$assignmentsStmt = $pdo->prepare("
    SELECT a.id, a.title, a.description, a.assessment_type, a.due_at,
           c.id AS course_id, c.course_code, c.course_name,
           ac.completed_at, ac.score, ac.total_questions
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = :enrolled_student_id AND e.status = 'APPROVED'
    LEFT JOIN assignment_completions ac ON ac.assignment_id = a.id AND ac.student_id = :completed_student_id
    WHERE " . studentEnrollmentCourseScopeSql('c', 'e') . "
      {$courseFilterSql}
    " . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY (ac.completed_at IS NOT NULL), a.due_at ASC
");
$assignmentsStmt->execute(array_merge($assignmentParams, academicPeriodRecordParams()));
$assignments = $assignmentsStmt->fetchAll();
$assessmentParams = [
    'enrolled_student_id' => $studentId,
    'submission_student_id' => $studentId,
];
if ($courseFilterId > 0) {
    $assessmentParams['course_id'] = $courseFilterId;
}
$assessmentsStmt = $pdo->prepare("
    SELECT a.id, a.title, a.description, a.term, a.assessment_type,
           a.deadline, submission.status AS submission_status,
           submission.reopened_until, c.id AS course_id, c.course_code, c.course_name
    FROM assessments a
    JOIN courses c ON c.id = a.course_id
    JOIN enrollments e ON e.course_id = c.id
        AND e.student_id = :enrolled_student_id
        AND e.status = 'APPROVED'
    LEFT JOIN student_submissions submission
        ON submission.assessment_id = a.id
        AND submission.student_id = :submission_student_id
    WHERE (submission.status IS NULL OR submission.status IN ('Pending', 'Missed'))
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
      {$courseFilterSql}
    " . academicPeriodRecordFilter('a', 'a.created_at') . "
    ORDER BY COALESCE(submission.reopened_until, a.deadline) ASC
");
$assessmentsStmt->execute(array_merge($assessmentParams, academicPeriodRecordParams()));
$assessments = $assessmentsStmt->fetchAll();
$questionStmt = $pdo->prepare("SELECT * FROM assignment_questions WHERE assignment_id = :assignment_id ORDER BY question_order, id");
$questionsByAssignment = [];
foreach ($assignments as $assignment) {
    $questionStmt->execute(['assignment_id' => (int) $assignment['id']]);
    $questionsByAssignment[(int) $assignment['id']] = $questionStmt->fetchAll();
}
$manilaTimezone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $manilaTimezone);
$todoGroups = [
    'all' => [],
    'assigned' => [],
    'due_today' => [],
    'missed' => [],
];
$todoLabels = [
    'all' => 'All',
    'assigned' => 'Assigned',
    'due_today' => 'Due Today',
    'missed' => 'Missed',
];
$todoStatuses = array_keys($todoLabels);
$selectedTodoStatus = $_GET['status'] ?? 'all';
if (!is_string($selectedTodoStatus) || !in_array($selectedTodoStatus, $todoStatuses, true)) {
    $selectedTodoStatus = 'all';
}
$today = $now->format('Y-m-d');
$classifyTodoStatus = static function (string $dueAt, DateTimeImmutable $now, bool $alreadyMissed = false) use ($today): string {
    if ($alreadyMissed) {
        return 'missed';
    }
    $deadline = new DateTimeImmutable($dueAt, new DateTimeZone('Asia/Manila'));
    if ($deadline < $now) {
        return 'missed';
    }
    return $deadline->format('Y-m-d') === $today ? 'due_today' : 'assigned';
};
foreach ($assignments as $assignment) {
    if ($assignment['completed_at']) {
        continue;
    }
    $assignment['task_kind'] = 'assignment';
    $assignment['task_key'] = 'assignment-' . (int) $assignment['id'];
    $assignment['todo_status'] = $classifyTodoStatus((string) $assignment['due_at'], $now);
    $todoGroups['all'][] = $assignment;
    $todoGroups[$assignment['todo_status']][] = $assignment;
}
$manilaTimezone = new DateTimeZone('Asia/Manila');
foreach ($assessments as $assessment) {
    $assessment['due_at'] = $assessment['reopened_until'] ?: $assessment['deadline'];
    $assessment['task_kind'] = 'assessment';
    $assessment['task_key'] = 'assessment-' . (int) $assessment['id'];
    $assessment['todo_status'] = $classifyTodoStatus(
        (string) $assessment['due_at'],
        $now,
        $assessment['submission_status'] === 'Missed'
    );
    $todoGroups['all'][] = $assessment;
    $todoGroups[$assessment['todo_status']][] = $assessment;
}
foreach ($todoGroups as &$groupTasks) {
    usort($groupTasks, static fn(array $left, array $right): int => strtotime((string) $left['due_at']) <=> strtotime((string) $right['due_at']));
}
unset($groupTasks);
$studentPendingTaskCount = count($todoGroups['all']);
$emptyMessages = [
    'all' => 'There are no tasks for this class period.',
    'assigned' => 'There is no current assignment to do',
    'due_today' => 'There is no due to do',
    'missed' => 'There is no missed assignment',
];

$todoAcademicParams = academicPeriodRecordParams();
if (isset($todoAcademicParams['academic_year'], $todoAcademicParams['academic_semester'])) {
    $markTodoViewedStmt = $pdo->prepare("
        INSERT INTO student_todo_views (student_id, academic_year, semester, last_viewed_at)
        VALUES (:student_id, :academic_year, :semester, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE last_viewed_at = CURRENT_TIMESTAMP
    ");
    $markTodoViewedStmt->execute([
        'student_id' => $studentId,
        'academic_year' => $todoAcademicParams['academic_year'],
        'semester' => $todoAcademicParams['academic_semester'],
    ]);
}

$pageTitle = 'To-do List | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611061140', '../../css/student.css?v=202611061610'];
$extraJs = '../../js/student.js?v=202611041145';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'tasks';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-todo-header">
            <form class="student-todo-filter" method="GET" action="student_tasks.php">
                <?php if ($courseFilterId > 0): ?>
                    <input type="hidden" name="course_id" value="<?= $courseFilterId ?>">
                <?php endif; ?>
                <label for="studentTodoStatus">Status</label>
                <select id="studentTodoStatus" name="status" onchange="this.form.submit()">
                    <?php foreach ($todoLabels as $statusValue => $statusLabel): ?>
                        <option value="<?= htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $selectedTodoStatus === $statusValue ? ' selected' : '' ?>>
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <section class="student-page-list student-todo-panel">
            <?php $groupTasks = $todoGroups[$selectedTodoStatus]; ?>
            <?php if (!$groupTasks): ?>
                <p class="student-todo-empty">
                    <?= htmlspecialchars($emptyMessages[$selectedTodoStatus], ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php else: ?>
                <?php foreach ($groupTasks as $task): ?>
                    <?php $taskQuestions = $task['task_kind'] === 'assignment' ? ($questionsByAssignment[(int) $task['id']] ?? []) : []; ?>
                    <article class="student-todo-item" id="<?= htmlspecialchars($task['task_key'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($task['task_kind'] === 'assessment'): ?>
                            <div class="student-assignment-form">
                                <div class="student-task-full-copy">
                                    <span class="student-eyebrow">
                                        <?= htmlspecialchars($task['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($task['assessment_type'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($todoLabels[$task['todo_status']], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <h3><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if ($task['description'] !== ''): ?>
                                        <p><?= nl2br(htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                </div>
                                <time class="student-task-due<?= $task['todo_status'] === 'missed' ? ' overdue' : '' ?>"
                                    datetime="<?= htmlspecialchars(date('c', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                    Due <?= htmlspecialchars(date('M j, Y', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars(date('g:i A', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </time>
                                <a class="btn btn-primary"
                                    href="student_course.php?id=<?= (int) $task['course_id'] ?>&amp;area=assessments&amp;term=<?= urlencode($task['term']) ?>">
                                    Open Class Assessments
                                </a>
                            </div>
                        <?php else: ?>
                            <form action="../../api/student_process.php" method="POST" class="student-assignment-form">
                                <input type="hidden" name="action" value="complete_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $task['id'] ?>">
                                <div class="student-task-full-copy">
                                    <span class="student-eyebrow">
                                        <?= htmlspecialchars($task['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($task['assessment_type'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($todoLabels[$task['todo_status']], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <h3><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if ($task['description'] !== ''): ?>
                                        <p><?= nl2br(htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php foreach ($taskQuestions as $question): ?>
                                        <div class="student-assessment-question">
                                            <label for="answer_<?= (int) $question['id'] ?>">
                                                <?= htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                            <?php $choices = $question['choices'] ? json_decode($question['choices'], true) : []; ?>
                                            <?php if ($question['question_type'] === 'multiple_choice' && is_array($choices) && $choices): ?>
                                                <select id="answer_<?= (int) $question['id'] ?>"
                                                    name="answers[<?= (int) $question['id'] ?>]" required>
                                                    <option value="">Select an answer</option>
                                                    <?php foreach ($choices as $choice): ?>
                                                        <option value="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input id="answer_<?= (int) $question['id'] ?>" type="text"
                                                    name="answers[<?= (int) $question['id'] ?>]" required>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <time class="student-task-due<?= $task['todo_status'] === 'missed' ? ' overdue' : '' ?>"
                                    datetime="<?= htmlspecialchars(date('c', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                    Due <?= htmlspecialchars(date('M j, Y', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars(date('g:i A', strtotime($task['due_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </time>
                                <button type="submit" class="btn btn-primary">
                                    <?= $taskQuestions ? 'Submit Assessment' : 'Mark Complete' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>