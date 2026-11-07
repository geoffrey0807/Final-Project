<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/assessment_workflow.php';

$studentId = (int) $_SESSION['user_id'];
$courseId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$assessmentId = filter_var($_GET['assessment'] ?? null, FILTER_VALIDATE_INT);
if (
    !is_int($courseId) || $courseId < 1
    || !is_int($assessmentId) || $assessmentId < 1
) {
    http_response_code(404);
    exit('Assessment not found.');
}

$assessmentStmt = $pdo->prepare("
    SELECT a.id, a.course_id, a.title, a.description, a.term, a.assessment_type,
            a.total_points, a.deadline, a.time_limit_minutes, a.academic_year, a.semester,
           c.course_code, c.course_name
    FROM assessments a
    JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
    JOIN enrollments e ON e.course_id = c.id
        AND e.student_id = :student_id
        AND e.status = 'APPROVED'
        AND e.academic_year = c.academic_year
        AND e.semester = c.semester
    WHERE a.id = :assessment_id
      AND a.course_id = :course_id
      AND a.academic_year = :academic_year
      AND a.semester = :semester
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
    LIMIT 1
");
$assessmentStmt->execute([
    'student_id' => $studentId,
    'assessment_id' => $assessmentId,
    'course_id' => $courseId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$assessment = $assessmentStmt->fetch();
if (!$assessment) {
    http_response_code(404);
    exit('Assessment not found in your enrolled classes.');
}

materializeExpiredAssessmentSubmissions(
    $pdo,
    (int) $assessment['course_id'],
    (string) $assessment['academic_year'],
    (string) $assessment['semester']
);
$submissionStmt = $pdo->prepare("
    SELECT status, score, reopened_until, assessment_started_at
    FROM student_submissions
    WHERE assessment_id = :assessment_id AND student_id = :student_id
");
$submissionStmt->execute([
    'assessment_id' => $assessmentId,
    'student_id' => $studentId,
]);
$submission = $submissionStmt->fetch() ?: null;
$effectiveDeadline = $submission['reopened_until'] ?? $assessment['deadline'];
$manilaTimezone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $manilaTimezone);
$courseRedirect = 'student_course.php?id=' . (int) $courseId
    . '&area=assessments&term=' . rawurlencode((string) $assessment['term']);
$submissionStatus = $submission['status'] ?? null;
$assessmentDeadline = new DateTimeImmutable((string) $effectiveDeadline, $manilaTimezone);
$attemptDeadline = null;
$timeLimitMinutes = $assessment['time_limit_minutes'] === null
    ? null
    : (int) $assessment['time_limit_minutes'];

if (
    $timeLimitMinutes !== null
    && in_array($submissionStatus, [null, 'Pending'], true)
    && $assessmentDeadline > $now
    && empty($submission['assessment_started_at'])
) {
    $startedAt = $now->format('Y-m-d H:i:s');
    $startAttemptStmt = $pdo->prepare("
        INSERT IGNORE INTO student_submissions
            (assessment_id, student_id, score, status, assessment_started_at)
        VALUES (:assessment_id, :student_id, NULL, 'Pending', :started_at)
    ");
    $startAttemptStmt->execute([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
        'started_at' => $startedAt,
    ]);
    $setAttemptStartStmt = $pdo->prepare("
        UPDATE student_submissions
        SET assessment_started_at = :started_at
        WHERE assessment_id = :assessment_id
          AND student_id = :student_id
          AND status = 'Pending'
          AND assessment_started_at IS NULL
    ");
    $setAttemptStartStmt->execute([
        'started_at' => $startedAt,
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
    ]);
    $submissionStmt->execute([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
    ]);
    $submission = $submissionStmt->fetch() ?: null;
    $submissionStatus = $submission['status'] ?? null;
}

if ($timeLimitMinutes !== null && !empty($submission['assessment_started_at'])) {
    $attemptStartedAt = new DateTimeImmutable((string) $submission['assessment_started_at'], $manilaTimezone);
    $attemptDeadline = $attemptStartedAt->modify('+' . $timeLimitMinutes . ' minutes');
    if ($attemptDeadline < $assessmentDeadline) {
        $effectiveDeadline = $attemptDeadline->format('Y-m-d H:i:s');
    }
}

$effectiveDeadlineDate = new DateTimeImmutable((string) $effectiveDeadline, $manilaTimezone);
$deadlinePassed = $effectiveDeadlineDate <= $now;
if ($deadlinePassed && $submissionStatus === 'Pending') {
    $expireAttemptStmt = $pdo->prepare("
        UPDATE student_submissions
        SET score = 0, status = 'Missed', answers_json = NULL,
            submitted_at = NULL, reopened_until = NULL
        WHERE assessment_id = :assessment_id
          AND student_id = :student_id
          AND status = 'Pending'
    ");
    $expireAttemptStmt->execute([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
    ]);
    $submissionStmt->execute([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
    ]);
    $submission = $submissionStmt->fetch() ?: null;
    $submissionStatus = $submission['status'] ?? null;
}

$canTakeAssessment = in_array($submissionStatus, [null, 'Pending'], true) && !$deadlinePassed;
$timerRemainingSeconds = $canTakeAssessment && $attemptDeadline !== null
    ? max(0, $attemptDeadline->getTimestamp() - $now->getTimestamp())
    : null;

$questionsStmt = $pdo->prepare("
    SELECT id, question_text, question_type, choices_json, points, question_order
    FROM assessment_questions
    WHERE assessment_id = :assessment_id
    ORDER BY question_order, id
");
$questionsStmt->execute(['assessment_id' => $assessmentId]);
$questions = $questionsStmt->fetchAll();
$canTakeAssessment = $canTakeAssessment && count($questions) > 0;
$backUrl = $courseRedirect;
$semesterLabel = $assessment['semester'] === 'first' ? '1st Semester' : '2nd Semester';
$pageTitle = $assessment['title'] . ' | Take Assessment';
$extraCss = ['../../css/student-assessment-focus.css?v=202611070004'];
$extraJs = ['../../js/student-assessment-focus.js?v=202611070003'];
$bodyClass = 'student-assessment-focus-page';
$useVisualTheme = true;
require_once '../../includes/header.php';
?>
<div class="assessment-focus-shell">
    <p class="assessment-focus-course-context">
        <strong><?= htmlspecialchars($assessment['course_code'], ENT_QUOTES, 'UTF-8') ?></strong>
        <span><?= htmlspecialchars($assessment['course_name'], ENT_QUOTES, 'UTF-8') ?></span>
    </p>
    <div class="assessment-focus-toolbar">
        <p class="assessment-focus-eyebrow assessment-focus-metadata">
            <?= htmlspecialchars((string) $assessment['academic_year'], ENT_QUOTES, 'UTF-8') ?>
            <span aria-hidden="true">|</span>
            <?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?>
            <span aria-hidden="true">|</span>
            <?= htmlspecialchars($assessment['term'], ENT_QUOTES, 'UTF-8') ?>
            <span aria-hidden="true">|</span>
            <?= htmlspecialchars($assessment['assessment_type'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="assessment-focus-header-actions">
            <?php if ($canTakeAssessment): ?>
                <button class="assessment-focus-back" type="button" data-assessment-exit-trigger>Exit assessment</button>
            <?php else: ?>
                <a class="assessment-focus-back" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Return to
                    class</a>
            <?php endif; ?>
            <?php if ($timerRemainingSeconds !== null): ?>
                <div class="assessment-focus-timer" role="timer" aria-label="Time remaining" data-assessment-timer
                    data-remaining-seconds="<?= (int) $timerRemainingSeconds ?>">
                    <span>TIME LEFT</span>
                    <strong data-assessment-time-display>
                        <?= sprintf('%02d:%02d', intdiv($timerRemainingSeconds, 60), $timerRemainingSeconds % 60) ?>
                    </strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="assessment-focus-deadline">
        <span>DUE</span>
        <strong><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $effectiveDeadline)), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <section class="assessment-focus-content" aria-label="Assessment questions">
        <?php if ($assessment['description'] !== ''): ?>
            <p class="assessment-focus-description">
                <?= nl2br(htmlspecialchars($assessment['description'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
        <?php endif; ?>

        <?php if ($canTakeAssessment): ?>
            <form action="../../api/student_process.php" method="POST" class="assessment-focus-form"
                data-assessment-submit-form>
                <input type="hidden" name="action" value="submit_assessment">
                <input type="hidden" name="assessment_id" value="<?= (int) $assessmentId ?>">
                <p class="assessment-focus-question-count" data-assessment-question-count aria-live="polite">
                    Question 1 of <?= count($questions) ?>
                </p>
                <?php foreach ($questions as $questionIndex => $question): ?>
                    <fieldset class="assessment-focus-question"
                        aria-labelledby="assessmentQuestionPrompt<?= (int) $question['id'] ?>" data-assessment-question-step
                        <?= $questionIndex > 0 ? 'hidden' : '' ?>>
                        <div class="assessment-focus-question-heading">
                            <h3 id="assessmentQuestionPrompt<?= (int) $question['id'] ?>">
                                <?= $questionIndex + 1 ?>.
                                <?= htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <small><?= number_format((float) $question['points'], 0) ?> pts</small>
                        </div>
                        <?php if ($question['question_type'] === 'multiple_choice'):
                            $choiceOptions = json_decode((string) $question['choices_json'], true) ?: [];
                            foreach ($choiceOptions as $choice): ?>
                                <label class="assessment-focus-option">
                                    <input type="radio" name="answers[<?= (int) $question['id'] ?>]"
                                        value="<?= htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8') ?>" required>
                                    <span><?= htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <?php foreach (['true' => 'True', 'false' => 'False'] as $answerValue => $answerLabel): ?>
                                <label class="assessment-focus-option">
                                    <input type="radio" name="answers[<?= (int) $question['id'] ?>]" value="<?= $answerValue ?>"
                                        required>
                                    <span><?= $answerLabel ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
                <div class="assessment-focus-submit-row">
                    <button class="assessment-focus-submit" type="button" data-assessment-next>Next</button>
                </div>
            </form>
        <?php elseif ($submissionStatus === 'Submitted'): ?>
            <div class="assessment-focus-result" role="status">
                <h3>Assessment submitted</h3>
                <p><?= $submission['score'] !== null
                    ? number_format((float) $submission['score'], 0) . ' / ' . number_format((float) $assessment['total_points'], 0) . ' points'
                    : 'Your submission is awaiting review.' ?></p>
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Return to class</a>
            </div>
        <?php elseif ($submissionStatus === 'Missed' || $deadlinePassed): ?>
            <div class="assessment-focus-result" role="status">
                <h3>Assessment closed</h3>
                <p>The deadline for this assessment has passed.</p>
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Return to class</a>
            </div>
        <?php else: ?>
            <div class="assessment-focus-result" role="status">
                <p>This assessment is not available yet.</p>
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Return to class</a>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php if ($canTakeAssessment): ?>
    <dialog class="assessment-focus-confirm" id="assessmentExitConfirm" aria-labelledby="assessmentExitConfirmTitle">
        <form action="../../api/student_process.php" method="POST" data-assessment-forfeit-form>
            <input type="hidden" name="action" value="forfeit_assessment">
            <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
            <input type="hidden" name="assessment_id" value="<?= (int) $assessmentId ?>">
            <p id="assessmentExitConfirmTitle">Are you sure you want to exit this assessment?</p>
            <p class="assessment-focus-confirm-warning">
                Leaving now records a score of 0. Only your professor can reopen this assessment.
            </p>
            <div class="assessment-focus-confirm-actions">
                <button class="assessment-focus-confirm-secondary" type="button" data-assessment-exit-cancel>Continue
                    assessment</button>
                <button class="assessment-focus-confirm-danger" type="submit">Exit and record 0</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>