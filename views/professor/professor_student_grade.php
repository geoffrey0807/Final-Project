<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/assessment_workflow.php';

$professorId = (int) $_SESSION['user_id'];
$courseId = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$studentId = filter_var($_GET['student_id'] ?? null, FILTER_VALIDATE_INT);
$selectedAssessmentId = filter_var($_GET['assessment_id'] ?? null, FILTER_VALIDATE_INT);
if (
    !is_int($courseId) || $courseId < 1
    || !is_int($studentId) || $studentId < 1
    || !is_int($selectedAssessmentId) || $selectedAssessmentId < 1
) {
    http_response_code(404);
    exit('Student grade not found.');
}

$studentStmt = $pdo->prepare("
    SELECT c.id AS course_id, c.course_code, c.course_name,
           e.grade_released_at, e.final_grade,
           u.first_name, u.last_name, u.id_number
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
        AND e.status = 'APPROVED'
        AND e.academic_year = c.academic_year
        AND e.semester = c.semester
    JOIN users u ON u.id = e.student_id AND u.role = 'student'
    WHERE c.id = :course_id
      AND e.student_id = :student_id
      AND c.professor_id = :professor_id
      AND c.archived_at IS NULL
      AND c.academic_year = :academic_year
      AND c.semester = :semester
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
");
$studentStmt->execute([
    'course_id' => $courseId,
    'student_id' => $studentId,
    'professor_id' => $professorId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$student = $studentStmt->fetch();
if (!$student) {
    http_response_code(404);
    exit('Student grade not found.');
}

$assessmentsStmt = $pdo->prepare("
    SELECT id, title, term, assessment_type, total_points, deadline
    FROM assessments
    WHERE course_id = :course_id
      AND academic_year = :academic_year
      AND semester = :semester
    ORDER BY FIELD(term, 'Prelim', 'Midterm', 'Final'), deadline, id
");
$assessmentsStmt->execute([
    'course_id' => $courseId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$assessments = $assessmentsStmt->fetchAll();
$reviewAssessment = null;
foreach ($assessments as $assessment) {
    if ((int) $assessment['id'] === $selectedAssessmentId) {
        $reviewAssessment = $assessment;
        break;
    }
}
if (!$reviewAssessment) {
    http_response_code(404);
    exit('Assessment not found for this student.');
}
$assessmentIds = array_map(static fn(array $assessment): int => (int) $assessment['id'], $assessments);
$questionsByAssessment = [];
if ($assessmentIds) {
    $questionsStmt = $pdo->prepare("
        SELECT q.id, q.assessment_id, q.question_text, q.question_type,
             q.choices_json, q.correct_answer,
               q.points, q.question_order
        FROM assessment_questions q
        JOIN assessments a ON a.id = q.assessment_id
        WHERE a.course_id = :course_id
          AND a.academic_year = :academic_year
          AND a.semester = :semester
        ORDER BY a.id, q.question_order, q.id
    ");
    $questionsStmt->execute([
        'course_id' => $courseId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    foreach ($questionsStmt->fetchAll() as $question) {
        $questionsByAssessment[(int) $question['assessment_id']][] = $question;
    }
}

$submissionsStmt = $pdo->prepare("
    SELECT s.assessment_id, s.score, s.status, s.answers_json, s.submitted_at
    FROM student_submissions s
    JOIN assessments a ON a.id = s.assessment_id
    WHERE a.course_id = :course_id
      AND a.academic_year = :academic_year
      AND a.semester = :semester
      AND s.student_id = :student_id
");
$submissionsStmt->execute([
    'course_id' => $courseId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
    'student_id' => $studentId,
]);
$submissionsByAssessment = [];
foreach ($submissionsStmt->fetchAll() as $submission) {
    $assessmentId = (int) $submission['assessment_id'];
    $submission['answer_records'] = json_decode((string) $submission['answers_json'], true) ?: [];
    $submission['answers_by_question'] = [];
    foreach ($submission['answer_records'] as $answerRecord) {
        if (is_array($answerRecord) && isset($answerRecord['question_id'])) {
            $submission['answers_by_question'][(int) $answerRecord['question_id']] = $answerRecord;
        }
    }
    $submissionsByAssessment[$assessmentId] = $submission;
}

$assessmentsByTerm = array_fill_keys(['Prelim', 'Midterm', 'Final'], []);
foreach ($assessments as $assessment) {
    $assessmentsByTerm[$assessment['term']][] = $assessment;
}
$termContributions = [];
foreach ($assessmentsByTerm as $term => $termAssessments) {
    $percentage = assessmentTermPercentage($termAssessments, $submissionsByAssessment);
    $termContributions[$term] = $percentage === null ? null : $percentage * assessmentTermWeight($term);
}
$midtermStanding = $termContributions['Prelim'] !== null && $termContributions['Midterm'] !== null
    ? $termContributions['Prelim'] + $termContributions['Midterm']
    : null;
$overallGradePercent = $midtermStanding !== null && $termContributions['Final'] !== null
    ? $midtermStanding + $termContributions['Final']
    : null;
$reviewAssessmentId = (int) $reviewAssessment['id'];
$reviewSubmission = $submissionsByAssessment[$reviewAssessmentId] ?? null;
$hasReviewableSubmission = $reviewSubmission
    && $reviewSubmission['status'] === 'Submitted'
    && !empty($reviewSubmission['answer_records']);

$pageTitle = htmlspecialchars($reviewAssessment['title'], ENT_QUOTES, 'UTF-8') . ' | ' . $student['first_name'];
$extraCss = ['../../css/admin.css?v=202611063140', '../../css/student.css?v=202611061730'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal professor-course-page';
$useVisualTheme = true;
$professorView = 'classes';
$professorCourseNavigation = true;
require_once '../../includes/header.php';
?>
<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <main class="professor-course-main student-dashboard-content container">
        <div class="professor-course-main-panel">
            <div class="professor-course-header-block">
                <div class="professor-course-badge"><?= htmlspecialchars($student['course_code'], ENT_QUOTES, 'UTF-8') ?></div>
                <h1><?= htmlspecialchars($student['course_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <section class="professor-course-content-box">
                <div class="professor-course-content-header">
                    <div>
                        <h2>Student Grade Review</h2>
                        <p><?= htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name']), ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars((string) ($student['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a class="professor-assessment-view-link"
                        href="professor_course.php?id=<?= (int) $courseId ?>&amp;area=grades">Back to grade table</a>
                </div>

            <div class="professor-student-grade-summary" aria-label="Course grade summary">
                <div><span>Prelim Grade</span><strong><?= $termContributions['Prelim'] === null ? '—' : number_format($termContributions['Prelim'], 0) . '%' ?></strong></div>
                <div><span>Midterm Grade</span><strong><?= $termContributions['Midterm'] === null ? '—' : number_format($termContributions['Midterm'], 0) . '%' ?></strong></div>
                <div><span>Midterm Standing</span><strong><?= $midtermStanding === null ? '—' : number_format($midtermStanding, 0) . '%' ?></strong></div>
                <div><span>Overall Grade</span><strong><?= $overallGradePercent === null ? '—' : number_format($overallGradePercent, 0) . '%' ?></strong></div>
                <div><span>Final Grade</span><strong><?= $student['final_grade'] === null ? '—' : number_format((float) $student['final_grade'], 0) ?></strong></div>
            </div>

            <?php if (!$assessments): ?>
                <p class="student-empty-note">No assessments are available for this class.</p>
            <?php else: ?>
                <?php if ($hasReviewableSubmission): ?>
                    <form action="../../api/professor_process.php" method="POST" class="professor-student-grade-review-form">
                        <input type="hidden" name="action" value="recheck_student_grade">
                        <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                        <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
                        <input type="hidden" name="assessment_id" value="<?= $reviewAssessmentId ?>">
                <?php endif; ?>

                <?php foreach (['Prelim', 'Midterm', 'Final'] as $term): ?>
                    <?php if ($term === $reviewAssessment['term']): ?>
                        <section class="professor-student-grade-term">
                            <h2><?= htmlspecialchars($term === 'Final' ? 'Finals' : $term, ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php foreach ($assessmentsByTerm[$term] as $assessment): ?>
                                <?php if ((int) $assessment['id'] !== $reviewAssessmentId) { continue; } ?>
                                <?php
                                $assessmentId = (int) $assessment['id'];
                                $submission = $submissionsByAssessment[$assessmentId] ?? null;
                                $questions = $questionsByAssessment[$assessmentId] ?? [];
                                ?>
                                <article class="professor-student-grade-assessment">
                                    <header>
                                        <div>
                                            <h3><?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                            <p><?= number_format((float) $assessment['total_points'], 0) ?> points
                                                · <?= htmlspecialchars($assessment['assessment_type'], ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <strong class="professor-student-grade-score">
                                            <?php if (!$submission): ?>
                                                —
                                            <?php elseif ($submission['status'] === 'Missed'): ?>
                                                0 / <?= number_format((float) $assessment['total_points'], 0) ?>
                                            <?php elseif ($submission['score'] === null): ?>
                                                Needs review
                                            <?php else: ?>
                                                <?= number_format((float) $submission['score'], 0) ?> / <?= number_format((float) $assessment['total_points'], 0) ?>
                                            <?php endif; ?>
                                        </strong>
                                    </header>
                                    <?php if (!$submission): ?>
                                        <p class="professor-student-grade-muted">No submission recorded.</p>
                                    <?php elseif ($submission['status'] === 'Missed'): ?>
                                        <p class="professor-student-grade-muted">Missed</p>
                                    <?php elseif (!$submission['answer_records']): ?>
                                        <p class="professor-student-grade-muted">No saved answers to review.</p>
                                    <?php else: ?>
                                        <div class="professor-student-grade-questions">
                                            <?php foreach ($questions as $question): ?>
                                                <?php
                                                $questionId = (int) $question['id'];
                                                $answerRecord = $submission['answers_by_question'][$questionId] ?? null;
                                                $studentAnswer = is_array($answerRecord) ? (string) ($answerRecord['answer'] ?? '') : '';
                                                $isCurrentlyCorrect = is_array($answerRecord)
                                                    && in_array($answerRecord['is_correct'] ?? null, [true, 1, '1'], true);
                                                $answerChoices = json_decode((string) $question['choices_json'], true) ?: [];
                                                ?>
                                                <article class="professor-student-grade-question">
                                                    <div class="professor-student-grade-question-copy">
                                                        <h4>Question <?= (int) $question['question_order'] ?>
                                                            <small><?= number_format((float) $question['points'], 0) ?> pts</small>
                                                        </h4>
                                                        <p><?= nl2br(htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8')) ?></p>
                                                        <div class="professor-student-grade-answer">
                                                            <strong>Student answer:</strong>
                                                            <span><?= htmlspecialchars($studentAnswer, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </div>
                                                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                                            <p><strong>Correct answer:</strong>
                                                                <?= htmlspecialchars((string) $question['correct_answer'], ENT_QUOTES, 'UTF-8') ?></p>
                                                            <ul class="professor-student-grade-choices">
                                                                <?php foreach ($answerChoices as $choice): ?>
                                                                    <li class="<?= $choice === $question['correct_answer'] ? 'is-correct' : '' ?><?= $choice === $studentAnswer ? ' is-student-answer' : '' ?>">
                                                                        <?= htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8') ?>
                                                                        <?php if ($choice === $studentAnswer): ?> · Student answer<?php endif; ?>
                                                                        <?php if ($choice === $question['correct_answer']): ?> · Correct<?php endif; ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                                                            <p><strong>Correct answer:</strong>
                                                                <?= htmlspecialchars((string) $question['correct_answer'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (is_array($answerRecord) && $hasReviewableSubmission): ?>
                                                        <fieldset class="professor-student-grade-mark">
                                                            <legend>Mark answer</legend>
                                                            <label>
                                                                <input type="radio" name="review[<?= $assessmentId ?>][<?= $questionId ?>]" value="correct" required
                                                                    <?= $isCurrentlyCorrect ? 'checked' : '' ?>>
                                                                Correct
                                                            </label>
                                                            <label>
                                                                <input type="radio" name="review[<?= $assessmentId ?>][<?= $questionId ?>]" value="incorrect" required
                                                                    <?= !$isCurrentlyCorrect ? 'checked' : '' ?>>
                                                                Incorrect
                                                            </label>
                                                        </fieldset>
                                                    <?php endif; ?>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($hasReviewableSubmission): ?>
                        <div class="professor-student-grade-review-actions">
                            <button type="submit" class="professor-course-action-button">Save Rechecked Grades</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<?php require_once '../../includes/footer.php'; ?>
