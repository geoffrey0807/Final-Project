<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';
require_once '../../includes/assessment_workflow.php';

$studentId = (int) $_SESSION['user_id'];
$requestedAssessmentId = $_GET['assessment'] ?? null;
$assessmentId = null;
if ($requestedAssessmentId !== null) {
    $validAssessmentId = filter_var($requestedAssessmentId, FILTER_VALIDATE_INT);
    if ($validAssessmentId === false || $validAssessmentId === null || $validAssessmentId < 1) {
        http_response_code(404);
        exit('Assessment not found.');
    }
    $assessmentId = (int) $validAssessmentId;
}
$courseId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($courseId === false || $courseId === null || $courseId < 1) {
    http_response_code(404);
    exit('Class not found.');
}
$courseId = (int) $courseId;
$courseAreas = ['lessons', 'assessments', 'grades', 'announcements'];
$studentCourseArea = $_GET['area'] ?? 'lessons';
if (!is_string($studentCourseArea) || !in_array($studentCourseArea, $courseAreas, true)) {
    $studentCourseArea = 'lessons';
}
$termLabels = ['Prelim', 'Midterm', 'Final'];
$currentTerm = $_GET['term'] ?? 'Prelim';
if (!is_string($currentTerm) || !in_array($currentTerm, $termLabels, true)) {
    $currentTerm = 'Prelim';
}

$courseStmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name, c.section_name, c.academic_year, c.semester,
           e.grade_released_at, e.final_grade,
           u.first_name AS professor_first, u.last_name AS professor_last
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    LEFT JOIN users u ON u.id = c.professor_id
    WHERE c.id = :course_id
      AND e.student_id = :student_id
      AND e.status = 'APPROVED'
      AND c.archived_at IS NULL
      AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
      " . academicPeriodRecordFilter('e', 'e.applied_at') . "
");
$courseStmt->execute(array_merge([
    'course_id' => $courseId,
    'student_id' => $studentId,
], academicPeriodRecordParams()));
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Class not found.');
}
$gradesReleased = $course['grade_released_at'] !== null;
if ($assessmentId !== null && $studentCourseArea === 'assessments') {
    header('Location: student_assessment_take.php?id=' . $courseId . '&assessment=' . $assessmentId);
    exit;
}

$lessons = [];
$studentAssessments = [];
$assessmentQuestions = [];
$activeAssessment = null;
$activeSubmissionStatus = null;
$assessmentCanTake = false;
$announcements = [];
if ($studentCourseArea === 'lessons') {
    $lessonsStmt = $pdo->prepare("
        SELECT l.id, l.title, l.content, l.file_name, l.file_mime, l.created_at
        FROM lessons l
        WHERE l.course_id = :course_id
          AND l.term = :term" . academicPeriodRecordFilter('l', 'l.created_at') . "
        ORDER BY l.created_at DESC
    ");
    $lessonsStmt->execute(array_merge([
        'course_id' => $courseId,
        'term' => $currentTerm,
    ], academicPeriodRecordParams()));
    $lessons = $lessonsStmt->fetchAll();
} elseif (in_array($studentCourseArea, ['assessments', 'grades'], true)) {
    materializeExpiredAssessmentSubmissions(
        $pdo,
        $courseId,
        (string) $academicPeriod['academic_year'],
        (string) $academicPeriod['semester']
    );
    $assessmentTermFilter = $studentCourseArea === 'assessments' ? ' AND a.term = :term' : '';
    $assessmentParams = [
        'student_id' => $studentId,
        'course_id' => $courseId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ];
    if ($studentCourseArea === 'assessments') {
        $assessmentParams['term'] = $currentTerm;
    }
    $assessmentsStmt = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.term, a.assessment_type,
               a.question_format, a.total_points, a.deadline,
               submission.status AS submission_status, submission.score,
               submission.answers_json, submission.submitted_at, submission.reopened_until,
               (SELECT COUNT(*) FROM assessment_questions q WHERE q.assessment_id = a.id) AS question_count
        FROM assessments a
        LEFT JOIN student_submissions submission
          ON submission.assessment_id = a.id AND submission.student_id = :student_id
        WHERE a.course_id = :course_id
          AND a.academic_year = :academic_year
          AND a.semester = :semester{$assessmentTermFilter}
        ORDER BY a.deadline ASC, a.id ASC
    ");
    $assessmentsStmt->execute($assessmentParams);
    $studentAssessments = $assessmentsStmt->fetchAll();
    $studentAssessmentsByTerm = array_fill_keys($termLabels, []);
    foreach ($studentAssessments as $studentAssessment) {
        $studentAssessmentsByTerm[$studentAssessment['term']][] = $studentAssessment;
    }
    if ($studentCourseArea === 'grades') {
        $studentSubmissionsByAssessment = [];
        foreach ($studentAssessments as $studentAssessment) {
            $studentSubmissionsByAssessment[(int) $studentAssessment['id']] = $studentAssessment;
        }
        $studentTermContributions = [];
        foreach ($termLabels as $gradeTerm) {
            $termPercentage = assessmentTermPercentage(
                $studentAssessmentsByTerm[$gradeTerm],
                $studentSubmissionsByAssessment
            );
            $studentTermContributions[$gradeTerm] = $termPercentage === null
                ? null
                : $termPercentage * assessmentTermWeight($gradeTerm);
        }
        $studentMidtermStanding = $studentTermContributions['Prelim'] !== null
            && $studentTermContributions['Midterm'] !== null
            ? $studentTermContributions['Prelim'] + $studentTermContributions['Midterm']
            : null;
    }
    if ($assessmentId !== null && $studentCourseArea === 'assessments') {
        foreach ($studentAssessments as $studentAssessment) {
            if ((int) $studentAssessment['id'] === $assessmentId) {
                $activeAssessment = $studentAssessment;
                break;
            }
        }
        if ($activeAssessment === null) {
            http_response_code(404);
            exit('Assessment not found.');
        }
        $activeSubmissionStatus = $activeAssessment['submission_status'] ?? 'Pending';
        $effectiveDeadline = $activeAssessment['reopened_until'] ?: $activeAssessment['deadline'];
        $assessmentCanTake = $activeSubmissionStatus === 'Pending'
            && strtotime((string) $effectiveDeadline) >= time();
        $questionsStmt = $pdo->prepare("
                                 SELECT id, question_text, question_type, choices_json, points, question_order
            FROM assessment_questions
            WHERE assessment_id = :assessment_id
            ORDER BY question_order, id
        ");
        $questionsStmt->execute(['assessment_id' => $assessmentId]);
        $assessmentQuestions = $questionsStmt->fetchAll();
    }
} elseif ($studentCourseArea === 'announcements') {
    $announcementAudienceFilter = announcementAudienceVisibilitySql('a', 'student', true);
    $announcementsStmt = $pdo->prepare("
        SELECT a.id, a.headline, a.message, a.image_path, a.created_at,
               u.first_name, u.last_name, u.role
        FROM announcements a
        JOIN users u ON u.id = a.author_id
        WHERE a.course_id = :course_id
          AND {$announcementAudienceFilter}" . academicPeriodRecordFilter('a', 'a.created_at') . "
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $announcementsStmt->execute(array_merge(['course_id' => $courseId], academicPeriodRecordParams()));
    $announcements = $announcementsStmt->fetchAll();
}

$areaTitles = [
    'lessons' => 'Lessons',
    'assessments' => 'Assessments',
    'grades' => 'Grades',
    'announcements' => 'Announcements',
];
$pageTitle = $areaTitles[$studentCourseArea] . ' | ' . $course['course_code'];
$extraCss = ['../../css/admin.css?v=202611062700', '../../css/student.css?v=202611062500'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301930'];
if ($studentCourseArea === 'assessments') {
    $extraCss[] = '../../css/student-assessment-focus.css?v=202611063350';
    $extraJs[] = '../../js/student-assessment-focus.js?v=202611063350';
}
$bodyClass = 'admin-dashboard student-dashboard student-course-page';
$useVisualTheme = true;
$studentPage = 'courses';
$studentCourseNavigation = true;
$studentCourseId = $courseId;
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <main class="professor-course-main student-dashboard-content container">
        <div class="professor-course-main-panel">
            <?php if (in_array($studentCourseArea, ['lessons', 'assessments'], true)): ?>
                <div class="professor-course-header-block">
                    <div class="professor-course-badge"><?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <h1><?= htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            <?php endif; ?>

            <?php if ($studentCourseArea === 'lessons'): ?>
                <section class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <h2>Lessons</h2>
                        <form class="professor-course-term-filter" method="GET" action="student_course.php">
                            <input type="hidden" name="id" value="<?= $courseId ?>">
                            <input type="hidden" name="area" value="lessons">
                            <select name="term" aria-label="Lesson term" onchange="this.form.submit()">
                                <?php foreach ($termLabels as $termLabel): ?>
                                    <option value="<?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $currentTerm === $termLabel ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php if (!$lessons): ?>
                        <p class="student-empty-note">No lessons have been shared for this class yet.</p>
                    <?php else: ?>
                        <div class="professor-course-file-list">
                            <?php foreach ($lessons as $lesson): ?>
                                <article class="professor-course-file-item course-lesson-reader-item">
                                    <div class="course-lesson-reader-field">
                                        <strong><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="course-lesson-reader-field">
                                        <p><?= nl2br(htmlspecialchars($lesson['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                    <hr class="course-lesson-reader-divider">
                                    <?php if (!empty($lesson['file_name'])): ?>
                                        <div class="course-lesson-reader-attachment">
                                            <button class="announcement-attachment-open" type="button" data-lesson-attachment-open
                                                data-preview-src="../../api/download_lesson.php?id=<?= (int) $lesson['id'] ?>&amp;preview=1"
                                                data-download-src="../../api/download_lesson.php?id=<?= (int) $lesson['id'] ?>"
                                                data-file-name="<?= htmlspecialchars($lesson['file_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-file-mime="<?= htmlspecialchars($lesson['file_mime'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                View Attachment
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($studentCourseArea === 'assessments'): ?>
                <section class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <h2>Assessments</h2>
                        <form class="professor-course-term-filter" method="GET" action="student_course.php">
                            <input type="hidden" name="id" value="<?= $courseId ?>">
                            <input type="hidden" name="area" value="assessments">
                            <select name="term" aria-label="Assessment term" onchange="this.form.submit()">
                                <?php foreach ($termLabels as $termLabel): ?>
                                    <option value="<?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $currentTerm === $termLabel ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php if ($activeAssessment !== null): ?>
                        <article class="student-assessment-taking-view">
                            <div class="student-assessment-taking-header">
                                <div>
                                    <span class="student-eyebrow">
                                        <?= htmlspecialchars($activeAssessment['term'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($activeAssessment['assessment_type'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <h3><?= htmlspecialchars($activeAssessment['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                </div>
                                <a class="btn btn-outline btn-sm"
                                    href="?id=<?= $courseId ?>&amp;area=assessments&amp;term=<?= urlencode($currentTerm) ?>">Back</a>
                            </div>
                            <?php if ($activeAssessment['description'] !== ''): ?>
                                <p><?= nl2br(htmlspecialchars($activeAssessment['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <?php endif; ?>
                            <p class="student-assessment-deadline">
                                <?= number_format((float) $activeAssessment['total_points'], 0) ?> points · Due
                                <?= htmlspecialchars(date('M j, Y g:i A', strtotime($activeAssessment['reopened_until'] ?: $activeAssessment['deadline'])), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php if ($assessmentCanTake): ?>
                                <form action="../../api/student_process.php" method="POST" class="student-assessment-taking-form">
                                    <input type="hidden" name="action" value="submit_assessment">
                                    <input type="hidden" name="assessment_id" value="<?= (int) $activeAssessment['id'] ?>">
                                    <?php foreach ($assessmentQuestions as $question): ?>
                                        <fieldset class="student-assessment-taking-question">
                                            <legend>
                                                <?= htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8') ?>
                                                <small><?= number_format((float) $question['points'], 0) ?> pts</small>
                                            </legend>
                                            <?php if ($question['question_type'] === 'multiple_choice'):
                                                $choiceOptions = json_decode((string) $question['choices_json'], true) ?: [];
                                                foreach ($choiceOptions as $choice): ?>
                                                    <label class="student-assessment-answer-option">
                                                        <input type="radio" name="answers[<?= (int) $question['id'] ?>]"
                                                            value="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>" required>
                                                        <span><?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                                <?php foreach (['true' => 'True', 'false' => 'False'] as $answerValue => $answerLabel): ?>
                                                    <label class="student-assessment-answer-option">
                                                        <input type="radio" name="answers[<?= (int) $question['id'] ?>]"
                                                            value="<?= $answerValue ?>" required>
                                                        <span><?= $answerLabel ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-primary">Submit Assessment</button>
                                </form>
                            <?php elseif ($activeSubmissionStatus === 'Submitted'): ?>
                                <p class="student-assessment-submission-status">
                                    Submitted<?= $activeAssessment['score'] !== null
                                        ? ' · ' . number_format((float) $activeAssessment['score'], 0) . ' / ' . number_format((float) $activeAssessment['total_points'], 0)
                                        : ' · Awaiting review' ?>
                                </p>
                            <?php else: ?>
                                <p class="student-assessment-submission-status">This assessment deadline has passed.</p>
                            <?php endif; ?>
                        </article>
                    <?php elseif (!$studentAssessments): ?>
                        <p class="student-empty-note">No assessments have been assigned to this class for this term.</p>
                    <?php else: ?>
                        <div class="professor-assessment-list student-assessment-list">
                            <?php foreach ($studentAssessments as $assessment): ?>
                                <article class="professor-assessment-card">
                                    <div>
                                        <h3><?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <p><?= number_format((float) $assessment['total_points'], 0) ?> pts
                                            · DEADLINE:
                                            <?= htmlspecialchars(date('m/d/y g:i A', strtotime($assessment['reopened_until'] ?: $assessment['deadline'])), ENT_QUOTES, 'UTF-8') ?>
                                            · <?= htmlspecialchars($assessment['assessment_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <?php if ($assessment['description'] !== ''): ?>
                                            <p><?= nl2br(htmlspecialchars($assessment['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                                        <?php endif; ?>
                                        <?php if ($assessment['submission_status'] === 'Submitted'): ?>
                                            <small><?= $assessment['score'] !== null
                                                ? 'Submitted · ' . number_format((float) $assessment['score'], 0) . ' / ' . number_format((float) $assessment['total_points'], 0)
                                                : 'Submitted · Awaiting review' ?></small>
                                        <?php elseif ($assessment['submission_status'] === 'Missed'): ?>
                                            <small>Missed · 0 / <?= number_format((float) $assessment['total_points'], 0) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (($assessment['submission_status'] ?? null) === null || $assessment['submission_status'] === 'Pending'): ?>
                                        <a class="professor-assessment-view-link"
                                            href="student_assessment_take.php?id=<?= $courseId ?>&amp;assessment=<?= (int) $assessment['id'] ?>"
                                            data-assessment-take-trigger>Take</a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <dialog class="assessment-focus-confirm" id="assessmentTakeConfirm"
                    aria-labelledby="assessmentTakeConfirmTitle">
                    <form method="dialog">
                        <p id="assessmentTakeConfirmTitle">Are you sure you want to take this assessment?</p>
                        <p class="assessment-focus-confirm-countdown">Choose within
                            <strong data-assessment-take-countdown>5</strong> seconds.
                        </p>
                        <div class="assessment-focus-confirm-actions">
                            <button class="assessment-focus-confirm-primary" type="submit" value="yes">Yes, take
                                assessment</button>
                            <button type="submit" value="no">No</button>
                        </div>
                    </form>
                </dialog>
            <?php elseif ($studentCourseArea === 'grades'): ?>
                <section class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <h2>Grades</h2>
                    </div>
                    <div class="professor-grade-legend">
                        <h3>Grade Conversion Legend</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Grade</th>
                                    <th scope="col">Percentage Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (gradeConversionScale() as $gradeEquivalent => $percentageRange): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($gradeEquivalent, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($percentageRange, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="professor-grade-table-wrap">
                        <table class="professor-grade-table student-grade-matrix">
                            <thead>
                                <tr>
                                    <th rowspan="2">Student Name</th>
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <th colspan="<?= max(1, count($studentAssessmentsByTerm[$termLabel])) ?>">
                                            <?= htmlspecialchars(strtoupper($termLabel === 'Final' ? 'Finals' : $termLabel), ENT_QUOTES, 'UTF-8') ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2">Prelim Grade</th>
                                    <th rowspan="2">Midterm Grade</th>
                                    <th rowspan="2">Midterm Standing</th>
                                    <th rowspan="2">Final Grade</th>
                                    <th rowspan="2">Status</th>
                                </tr>
                                <tr>
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <?php if ($studentAssessmentsByTerm[$termLabel]): ?>
                                            <?php foreach ($studentAssessmentsByTerm[$termLabel] as $assessment): ?>
                                                <th>
                                                    <?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?>
                                                    <small><?= number_format((float) $assessment['total_points'], 0) ?> pts</small>
                                                </th>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <th>—</th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= htmlspecialchars(trim(($_SESSION['user_name'] ?? 'Student')), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <?php if ($studentAssessmentsByTerm[$termLabel]): ?>
                                            <?php foreach ($studentAssessmentsByTerm[$termLabel] as $assessment): ?>
                                                <td>
                                                    <?php if (!$gradesReleased): ?>
                                                        —
                                                    <?php elseif ($assessment['score'] !== null): ?>
                                                        <?= number_format((float) $assessment['score'], 0) ?> /
                                                        <?= number_format((float) $assessment['total_points'], 0) ?>
                                                    <?php elseif ($assessment['submission_status'] === 'Missed'): ?>
                                                        0 / <?= number_format((float) $assessment['total_points'], 0) ?>
                                                    <?php elseif ($assessment['submission_status'] === 'Submitted'): ?>
                                                        Needs review
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <td>—</td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <td><?= !$gradesReleased || $studentTermContributions['Prelim'] === null ? '—' : number_format($studentTermContributions['Prelim'], 0) . '%' ?>
                                    </td>
                                    <td><?= !$gradesReleased || $studentTermContributions['Midterm'] === null ? '—' : number_format($studentTermContributions['Midterm'], 0) . '%' ?>
                                    </td>
                                    <td><?= !$gradesReleased || $studentMidtermStanding === null ? '—' : number_format($studentMidtermStanding, 0) . '%' ?>
                                    </td>
                                    <td><?= $gradesReleased && $course['final_grade'] !== null
                                        ? number_format((float) $course['final_grade'], 2)
                                        : '—' ?></td>
                                    <td><?= $gradesReleased ? 'Released' : '—' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php else: ?>
                <?php if (!$announcements): ?>
                    <p class="student-empty-note">No announcements have been posted to this class yet.</p>
                <?php else: ?>
                    <div class="student-page-list">
                        <?php foreach ($announcements as $announcement): ?>
                            <article class="student-overview-card student-announcement-full">
                                <div class="student-announcement-full-heading">
                                    <div>
                                        <span class="student-announcement-role student-eyebrow">
                                            <?= htmlspecialchars(announcementAuthorLabel($announcement['role']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                    <time
                                        datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                </div>
                                <hr class="student-announcement-divider">
                                <h2 class="student-announcement-title">
                                    <?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?>
                                </h2>
                                <div class="student-announcement-body">
                                    <?= nl2br(htmlspecialchars($announcement['message'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                                <?php if (
                                    is_string($announcement['image_path'])
                                    && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $announcement['image_path'])
                                ): ?>
                                    <hr class="student-announcement-divider">
                                    <button class="announcement-attachment-open" type="button" data-announcement-attachment-open
                                        data-image-src="../../<?= htmlspecialchars($announcement['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-image-alt="<?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                        View Attachment
                                    </button>
                                    <a class="announcement-attachment-open"
                                        href="../../<?= htmlspecialchars($announcement['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                        download="<?= htmlspecialchars(basename($announcement['image_path']), ENT_QUOTES, 'UTF-8') ?>">
                                        Download Attachment
                                    </a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once '../../includes/footer.php'; ?>