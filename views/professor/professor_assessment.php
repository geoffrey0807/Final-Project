<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';

$professorId = (int) $_SESSION['user_id'];
$courseId = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$assessmentId = filter_var($_GET['assessment_id'] ?? null, FILTER_VALIDATE_INT);
$mode = $_GET['mode'] ?? 'view';
if (
    !is_int($courseId) || $courseId < 1
    || !is_int($assessmentId) || $assessmentId < 1
    || !is_string($mode) || !in_array($mode, ['view', 'edit'], true)
) {
    http_response_code(404);
    exit('Assessment not found.');
}

$courseStmt = $pdo->prepare("
    SELECT id, course_code, course_name
    FROM courses
    WHERE id = :course_id
      AND professor_id = :professor_id
      AND academic_year = :academic_year
      AND semester = :semester
      AND archived_at IS NULL
");
$courseStmt->execute([
    'course_id' => $courseId,
    'professor_id' => $professorId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Assessment not found.');
}

$assessmentStmt = $pdo->prepare("
    SELECT id, title, description, term, assessment_type, question_format, total_points, deadline, time_limit_minutes
    FROM assessments
    WHERE id = :assessment_id
      AND course_id = :course_id
      AND academic_year = :academic_year
      AND semester = :semester
");
$assessmentStmt->execute([
    'assessment_id' => $assessmentId,
    'course_id' => $courseId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$assessment = $assessmentStmt->fetch();
if (!$assessment) {
    http_response_code(404);
    exit('Assessment not found.');
}

$deadlinePassed = new DateTimeImmutable(
    (string) $assessment['deadline'],
    new DateTimeZone('Asia/Manila')
) <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$editUnavailable = $mode === 'edit' && !$deadlinePassed;
if ($editUnavailable) {
    http_response_code(403);
    $mode = 'view';
}

$questionsStmt = $pdo->prepare("
    SELECT id, question_text, question_type, choices_json, correct_answer,
           points, question_order
    FROM assessment_questions
    WHERE assessment_id = :assessment_id
    ORDER BY question_order, id
");
$questionsStmt->execute(['assessment_id' => $assessmentId]);
$questions = $questionsStmt->fetchAll();

$pageTitle = htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') . ' | ' . $course['course_code'];
$extraCss = ['../../css/admin.css?v=202611062930', '../../css/student.css?v=202611061730'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal professor-course-page';
$useVisualTheme = true;
$professorView = 'classes';
$professorCourseNavigation = true;
require_once '../../includes/header.php';
?>
<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <main class="professor-course-main student-dashboard-content container">
        <section class="professor-assessment-detail">
            <a class="professor-assessment-back"
                href="professor_course.php?id=<?= (int) $courseId ?>&amp;area=assessments&amp;term=<?= urlencode($assessment['term']) ?>">Back to assessments</a>
            <header class="professor-assessment-detail-header">
                <div>
                    <span class="professor-course-badge"><?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                    <h1><?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($assessment['term'], ENT_QUOTES, 'UTF-8') ?> ·
                        <?= htmlspecialchars($assessment['assessment_type'], ENT_QUOTES, 'UTF-8') ?> ·
                        <?= number_format((float) $assessment['total_points'], 0) ?> points ·
                        <?= $assessment['time_limit_minutes'] === null ? 'No time limit' : (int) $assessment['time_limit_minutes'] . ' minute limit' ?> ·
                        Due <?= htmlspecialchars(date('M j, Y g:i A', strtotime($assessment['deadline'])), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php if ($mode === 'view' && $deadlinePassed): ?>
                    <a class="professor-assessment-view-link"
                        href="professor_assessment.php?course_id=<?= (int) $courseId ?>&amp;assessment_id=<?= (int) $assessmentId ?>&amp;mode=edit">Edit</a>
                <?php endif; ?>
            </header>
            <?php if ($editUnavailable): ?>
                <p class="professor-assessment-readonly-note">Editing is available after the assessment deadline.</p>
            <?php endif; ?>
            <?php if ($assessment['description'] !== ''): ?>
                <p class="professor-assessment-description"><?= nl2br(htmlspecialchars($assessment['description'], ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>

            <?php if ($mode === 'edit'): ?>
                <form action="../../api/professor_process.php" method="POST" class="professor-assessment-edit-form">
                    <input type="hidden" name="action" value="update_assessment">
                    <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                    <input type="hidden" name="assessment_id" value="<?= (int) $assessmentId ?>">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($assessment['term'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="professor-assessment-edit-metadata">
                        <label>
                            Assessment title
                            <input type="text" name="title" maxlength="150" required
                                value="<?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Description / guidelines
                            <textarea name="description" maxlength="10000" rows="3"><?= htmlspecialchars($assessment['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>
                        <label>
                            Time limit (minutes)
                            <input type="number" name="time_limit_minutes" min="1" max="1440" step="1"
                                placeholder="No limit"
                                value="<?= $assessment['time_limit_minutes'] === null ? '' : (int) $assessment['time_limit_minutes'] ?>">
                        </label>
                    </div>
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        $choices = json_decode((string) $question['choices_json'], true) ?: [];
                        $correctChoice = array_search($question['correct_answer'], $choices, true);
                        ?>
                        <fieldset class="professor-assessment-question-edit">
                            <legend>Question <?= $index + 1 ?></legend>
                            <input type="hidden" name="questions[<?= $index ?>][id]" value="<?= (int) $question['id'] ?>">
                            <label>
                                Question / problem
                                <textarea name="questions[<?= $index ?>][question_text]" maxlength="10000" rows="3" required><?= htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </label>
                            <label class="professor-assessment-points-field">
                                Points
                                <input type="number" name="questions[<?= $index ?>][points]" min="0.01" max="100000" step="0.01" required
                                    value="<?= htmlspecialchars((string) $question['points'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <fieldset class="professor-assessment-choice-edit">
                                    <legend>Choices and correct answer</legend>
                                    <?php foreach ([0, 1, 2, 3] as $choiceIndex): ?>
                                        <label>
                                            <input type="radio" name="questions[<?= $index ?>][correct_choice]" value="<?= $choiceIndex ?>" required
                                                <?= $correctChoice === $choiceIndex ? 'checked' : '' ?>>
                                            <input type="text" name="questions[<?= $index ?>][choices][]" maxlength="500" required
                                                aria-label="Choice <?= $choiceIndex + 1 ?>"
                                                value="<?= htmlspecialchars((string) ($choices[$choiceIndex] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <label>
                                    Correct answer
                                    <select name="questions[<?= $index ?>][correct_answer]" required>
                                        <option value="true" <?= strtolower((string) $question['correct_answer']) === 'true' ? 'selected' : '' ?>>True</option>
                                        <option value="false" <?= strtolower((string) $question['correct_answer']) === 'false' ? 'selected' : '' ?>>False</option>
                                    </select>
                                </label>
                            <?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <div class="professor-assessment-edit-actions">
                        <a class="professor-assessment-view-link"
                            href="professor_assessment.php?course_id=<?= (int) $courseId ?>&amp;assessment_id=<?= (int) $assessmentId ?>&amp;mode=view">Cancel</a>
                        <button class="professor-course-action-button" type="submit">Save Changes</button>
                    </div>
                </form>
            <?php else: ?>
                <?php if (!$questions): ?>
                    <p class="student-empty-note">This assessment has no questions.</p>
                <?php endif; ?>
                <div class="professor-assessment-question-list-readonly">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        $choices = json_decode((string) $question['choices_json'], true) ?: [];
                        ?>
                        <article class="professor-assessment-question-readonly">
                            <header>
                                <h2>Question <?= $index + 1 ?></h2>
                                <span><?= number_format((float) $question['points'], 0) ?> points</span>
                            </header>
                            <p><?= nl2br(htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <ul>
                                    <?php foreach ($choices as $choice): ?>
                                        <li class="<?= $choice === $question['correct_answer'] ? 'is-correct' : '' ?>">
                                            <?= htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8') ?>
                                            <?= $choice === $question['correct_answer'] ? ' · Correct answer' : '' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <p><strong>Correct answer:</strong> <?= htmlspecialchars(ucfirst((string) $question['correct_answer']), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require_once '../../includes/footer.php'; ?>
