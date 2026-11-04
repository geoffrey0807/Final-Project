<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';

$studentId = (int) $_SESSION['user_id'];
$announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'student');
$assignmentsStmt = $pdo->prepare("
    SELECT a.id, a.title, a.description, a.due_at, c.course_code, c.course_name,
           ac.completed_at, ac.score, ac.total_questions
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = :enrolled_student_id AND e.status = 'APPROVED'
    LEFT JOIN assignment_completions ac ON ac.assignment_id = a.id AND ac.student_id = :completed_student_id
    WHERE " . studentEnrollmentCourseScopeSql('c', 'e') . "
      " . academicPeriodDateFilter('a.due_at') . "
    ORDER BY (ac.completed_at IS NOT NULL), a.due_at ASC
");
$assignmentsStmt->execute(array_merge([
    'enrolled_student_id' => $studentId,
    'completed_student_id' => $studentId,
], academicPeriodDateParams()));
$assignments = $assignmentsStmt->fetchAll();
$questionStmt = $pdo->prepare("SELECT * FROM assignment_questions WHERE assignment_id = :assignment_id ORDER BY question_order, id");
$questionsByAssignment = [];
foreach ($assignments as $assignment) {
    $questionStmt->execute(['assignment_id' => (int) $assignment['id']]);
    $questionsByAssignment[(int) $assignment['id']] = $questionStmt->fetchAll();
}
$pendingAssignments = array_values(array_filter($assignments, static fn($assignment) => !$assignment['completed_at']));
$completedAssignments = array_values(array_filter($assignments, static fn($assignment) => $assignment['completed_at']));

$applicationsStmt = $pdo->prepare("
    SELECT e.id, e.status, e.applied_at, c.course_code, c.course_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = :student_id AND e.status = 'PENDING'
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
      " . academicPeriodDateFilter('e.applied_at') . "
    ORDER BY e.applied_at ASC
");
$applicationsStmt->execute(array_merge(['student_id' => $studentId], academicPeriodDateParams()));
$pendingApplications = $applicationsStmt->fetchAll();
$studentPendingTaskCount = count($pendingAssignments) + count($pendingApplications);

$announcementCountStmt = $pdo->prepare("
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
       )) AND {$announcementAudienceFilter}" . academicPeriodDateFilter('a.created_at') . "
");
$announcementCountStmt->execute(array_merge(['student_id' => $studentId], academicPeriodDateParams()));
$studentAnnouncementCount = (int) $announcementCountStmt->fetchColumn();

$pageTitle = 'Pending Tasks | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610291355', '../../css/student.css?v=202611012000'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'tasks';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <span class="student-eyebrow">Stay on track</span>
            <h1>Pending Tasks</h1>
            <p>Assignments for your enrolled courses and enrollment decisions still in progress.</p>
        </header>

        <section class="student-page-list">
            <div class="student-list-section-heading">
                <h2>Assignments to complete <span class="tab-badge"><?= count($pendingAssignments) ?></span></h2>
            </div>
            <?php if (empty($pendingAssignments)): ?>
                <div class="student-overview-card student-empty-state">
                    <p>No assignments are currently waiting for you.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingAssignments as $assignment): ?>
                    <article class="student-overview-card student-task-full">
                        <form action="../../api/student_process.php" method="POST" class="student-assignment-form">
                            <input type="hidden" name="action" value="complete_assignment">
                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                            <div class="student-task-full-copy">
                                <span class="student-eyebrow"><?= htmlspecialchars($assignment['course_code']) ?></span>
                                <h3><?= htmlspecialchars($assignment['title']) ?></h3>
                                <?php if ($assignment['description'] !== ''): ?><p><?= nl2br(htmlspecialchars($assignment['description'])) ?></p><?php endif; ?>
                                <?php foreach ($questionsByAssignment[(int) $assignment['id']] as $question): ?>
                                    <div class="student-assessment-question">
                                        <label for="answer_<?= (int) $question['id'] ?>"><?= htmlspecialchars($question['question_text']) ?></label>
                                        <?php $choices = $question['choices'] ? json_decode($question['choices'], true) : []; ?>
                                        <?php if ($question['question_type'] === 'multiple_choice' && is_array($choices) && $choices): ?>
                                            <select id="answer_<?= (int) $question['id'] ?>" name="answers[<?= (int) $question['id'] ?>]" required>
                                                <option value="">Select an answer</option>
                                                <?php foreach ($choices as $choice): ?><option value="<?= htmlspecialchars($choice) ?>"><?= htmlspecialchars($choice) ?></option><?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input id="answer_<?= (int) $question['id'] ?>" type="text" name="answers[<?= (int) $question['id'] ?>]" required>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <time class="student-task-due<?= strtotime($assignment['due_at']) < time() ? ' overdue' : '' ?>" datetime="<?= htmlspecialchars(date('c', strtotime($assignment['due_at']))) ?>">
                                Due <?= date('M j, Y', strtotime($assignment['due_at'])) ?> &middot; <?= date('g:i A', strtotime($assignment['due_at'])) ?>
                            </time>
                            <button type="submit" class="btn btn-primary"><?= $questionsByAssignment[(int) $assignment['id']] ? 'Submit Assessment' : 'Mark Complete' ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="student-list-section-heading">
                <h2>Enrollment decisions <span class="tab-badge"><?= count($pendingApplications) ?></span></h2>
            </div>
            <?php if (empty($pendingApplications)): ?>
                <div class="student-overview-card student-empty-state">
                    <p>No enrollment applications are awaiting a decision.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingApplications as $application): ?>
                    <article class="student-overview-card student-task-full">
                        <span class="student-check-empty student-check-static" aria-hidden="true"></span>
                        <div class="student-task-full-copy">
                            <span class="student-eyebrow"><?= htmlspecialchars($application['course_code']) ?></span>
                            <h3>Enrollment decision pending</h3>
                            <p><?= htmlspecialchars($application['course_name']) ?> &middot; Applied <?= date('M j, Y', strtotime($application['applied_at'])) ?></p>
                        </div>
                        <span class="student-task-status">Awaiting review</span>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($completedAssignments)): ?>
                <details class="student-completed-tasks">
                    <summary>Completed assignments (<?= count($completedAssignments) ?>)</summary>
                    <?php foreach ($completedAssignments as $assignment): ?>
                        <div class="student-completed-task">
                            <span aria-hidden="true">&#10003;</span>
                            <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                            <small><?= htmlspecialchars($assignment['course_code']) ?></small>
                            <?php if ((int) $assignment['total_questions'] > 0): ?>
                                <small>Score: <?= (int) $assignment['score'] ?>/<?= (int) $assignment['total_questions'] ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
