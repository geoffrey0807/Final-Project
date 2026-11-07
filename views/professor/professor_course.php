<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';
require_once '../../includes/academic_access.php';
require_once '../../includes/announcement_access.php';
require_once '../../includes/assessment_workflow.php';

$professorId = (int) $_SESSION['user_id'];
$courseId = (int) ($_GET['id'] ?? 0);
$courseStmt = $pdo->prepare("
    SELECT c.*
    FROM courses c
    WHERE c.id = :course_id AND c.professor_id = :professor_id
      AND c.academic_year = :course_academic_year AND c.semester = :course_semester
      AND c.archived_at IS NULL
");
$courseStmt->execute([
    'course_id' => $courseId,
    'professor_id' => $professorId,
    'course_academic_year' => $academicPeriod['academic_year'],
    'course_semester' => $academicPeriod['semester'],
]);
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$currentTerm = $_GET['term'] ?? 'Prelim';
$termLabels = ['Prelim', 'Midterm', 'Final'];
if (!in_array($currentTerm, $termLabels, true)) {
    $currentTerm = 'Prelim';
}
$courseAreas = ['lessons', 'assessments', 'grades', 'announcements'];
$currentArea = $_GET['area'] ?? 'lessons';
if (!in_array($currentArea, $courseAreas, true)) {
    $currentArea = 'lessons';
}
$lessonsStmt = $pdo->prepare("
    SELECT l.id, l.title, l.content, l.file_name, l.file_mime, l.file_size, l.created_at
    FROM lessons l
    WHERE l.course_id = :course_id
      AND l.professor_id = :professor_id
      AND l.term = :term" . academicPeriodRecordFilter('l', 'l.created_at') . "
    ORDER BY l.created_at DESC, l.id DESC
");
$lessonsStmt->execute(array_merge([
    'course_id' => $courseId,
    'professor_id' => $professorId,
    'term' => $currentTerm,
], academicPeriodRecordParams()));
$lessons = $lessonsStmt->fetchAll();
if (in_array($currentArea, ['assessments', 'grades'], true)) {
    materializeExpiredAssessmentSubmissions(
        $pdo,
        $courseId,
        (string) $academicPeriod['academic_year'],
        (string) $academicPeriod['semester']
    );
}
$assessmentRows = [];
$gradeStudents = [];
$submissionsByStudent = [];
$assessmentSql = "
        SELECT a.id, a.title, a.description, a.term, a.assessment_type,
            a.question_format, a.total_points, a.deadline, a.time_limit_minutes,
           COUNT(q.id) AS question_count
    FROM assessments a
    LEFT JOIN assessment_questions q ON q.assessment_id = a.id
    WHERE a.course_id = :course_id
      AND a.academic_year = :academic_year
      AND a.semester = :semester
";
$assessmentParams = [
    'course_id' => $courseId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
];
if ($currentArea === 'assessments') {
    $assessmentSql .= ' AND a.term = :term';
    $assessmentParams['term'] = $currentTerm;
}
$assessmentSql .= ' GROUP BY a.id ORDER BY FIELD(a.term, \'Prelim\', \'Midterm\', \'Final\'), a.deadline, a.id';
$assessmentStmt = $pdo->prepare($assessmentSql);
$assessmentStmt->execute($assessmentParams);
$assessmentRows = $assessmentStmt->fetchAll();
$courseAnnouncements = [];
if ($currentArea === 'announcements') {
    $courseAnnouncementAudienceFilter = announcementAudienceVisibilitySql('a', 'professor', true);
    $courseAnnouncementsStmt = $pdo->prepare("
        SELECT a.id, a.headline, a.message, a.image_path, a.created_at
        FROM announcements a
        WHERE a.course_id = :course_id
          AND a.author_id = :professor_id
          AND a.academic_year = :academic_year
          AND a.semester = :semester
          AND {$courseAnnouncementAudienceFilter}
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $courseAnnouncementsStmt->execute([
        'course_id' => $courseId,
        'professor_id' => $professorId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    $courseAnnouncements = $courseAnnouncementsStmt->fetchAll();
}

$reopenableStudentsByAssessment = [];
if ($currentArea === 'assessments') {
    $reopenableStudentsStmt = $pdo->prepare("
        SELECT s.assessment_id, s.student_id, u.first_name, u.last_name
        FROM student_submissions s
        JOIN assessments a ON a.id = s.assessment_id
        JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
        JOIN enrollments e ON e.course_id = c.id
            AND e.student_id = s.student_id
            AND e.status = 'APPROVED'
            AND e.academic_year = a.academic_year
            AND e.semester = a.semester
        JOIN users u ON u.id = s.student_id AND u.role = 'student'
        WHERE a.course_id = :course_id
          AND a.academic_year = :academic_year
          AND a.semester = :semester
          AND a.term = :term
          AND s.status IN ('Submitted', 'Missed')
          AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
        ORDER BY s.assessment_id, u.last_name, u.first_name
    ");
    $reopenableStudentsStmt->execute([
        'course_id' => $courseId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
        'term' => $currentTerm,
    ]);
    foreach ($reopenableStudentsStmt->fetchAll() as $reopenableStudent) {
        $reopenableStudentsByAssessment[(int) $reopenableStudent['assessment_id']][] = $reopenableStudent;
    }
}

if ($currentArea === 'grades') {
    $studentsStmt = $pdo->prepare("
         SELECT e.student_id, u.first_name, u.last_name, u.id_number,
             e.grade_released_at, e.final_grade
        FROM enrollments e
        JOIN users u ON u.id = e.student_id AND u.role = 'student'
        JOIN courses c ON c.id = e.course_id
        WHERE e.course_id = :course_id
          AND e.status = 'APPROVED'
          AND e.academic_year = :academic_year
          AND e.semester = :semester
          AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
        ORDER BY u.last_name, u.first_name, u.id
    ");
    $studentsStmt->execute([
        'course_id' => $courseId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    $gradeStudents = $studentsStmt->fetchAll();

    $submissionsStmt = $pdo->prepare("
        SELECT s.assessment_id, s.student_id, s.score, s.status,
               s.answers_json, s.submitted_at, s.reopened_until
        FROM student_submissions s
        JOIN assessments a ON a.id = s.assessment_id
        WHERE a.course_id = :course_id
          AND a.academic_year = :academic_year
          AND a.semester = :semester
    ");
    $submissionsStmt->execute([
        'course_id' => $courseId,
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    foreach ($submissionsStmt->fetchAll() as $submission) {
        $submissionsByStudent[(int) $submission['student_id']][(int) $submission['assessment_id']] = $submission;
    }
}
$assessmentsByTerm = array_fill_keys($termLabels, []);
foreach ($assessmentRows as $assessmentRow) {
    $assessmentsByTerm[$assessmentRow['term']][] = $assessmentRow;
}
$gradeMatrixColumnCount = 7;
foreach ($assessmentsByTerm as $termAssessments) {
    $gradeMatrixColumnCount += max(1, count($termAssessments));
}

$courseAreaTitles = [
    'lessons' => 'Upload Lessons',
    'assessments' => 'Upload Assessments',
    'grades' => 'Release Grade',
    'announcements' => 'Announcements',
];
$pageTitle = $courseAreaTitles[$currentArea] . ' | ' . $course['course_code'];
$extraCss = ['../../css/admin.css?v=202611063230', '../../css/student.css?v=202611061730'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301825'];
if (in_array($currentArea, ['assessments', 'grades'], true)) {
    $extraJs[] = '../../js/professor-assessment-builder.js?v=202611070001';
}
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
            <?php if (in_array($currentArea, ['lessons', 'assessments'], true)): ?>
                <div class="professor-course-header-block">
                    <div class="professor-course-badge"><?= htmlspecialchars($course['course_code'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <h1><?= htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            <?php endif; ?>

            <?php if ($currentArea === 'lessons'): ?>
                <section id="upload-lessons" class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <div class="professor-course-header-actions">
                            <form class="professor-course-term-filter" method="GET" action="professor_course.php">
                                <input type="hidden" name="id" value="<?= (int) $courseId ?>">
                                <input type="hidden" name="area" value="lessons">
                                <select id="professorLessonTerm" name="term" aria-label="Lesson term"
                                    onchange="this.form.submit()">
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <option value="<?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $currentTerm === $termLabel ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <button type="button" class="professor-course-action-button"
                                data-open-modal="courseLessonModal">
                                + Upload Lesson
                            </button>
                        </div>
                    </div>
                    <?php if (!$lessons): ?>
                        <p class="student-empty-note">No lessons have been uploaded for this term yet.</p>
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
            <?php endif; ?>

            <?php if ($currentArea === 'assessments'): ?>
                <section id="upload-assessments" class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <h2>Upload Assessments</h2>
                        <div class="professor-course-header-actions">
                            <form class="professor-course-term-filter" method="GET" action="professor_course.php">
                                <input type="hidden" name="id" value="<?= (int) $courseId ?>">
                                <input type="hidden" name="area" value="assessments">
                                <select id="professorAssessmentTerm" name="term" aria-label="Assessment term"
                                    onchange="this.form.submit()">
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <option value="<?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $currentTerm === $termLabel ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <button type="button" class="professor-course-action-button"
                                data-open-modal="courseAssessmentModal">+ Upload Assessments</button>
                        </div>
                    </div>
                    <?php if (!$assessmentRows): ?>
                        <p class="student-empty-note">No assessments have been created for this term yet.</p>
                    <?php else: ?>
                        <div class="professor-assessment-list">
                            <?php foreach ($assessmentRows as $assessment): ?>
                                <?php
                                $assessmentDeadlinePassed = new DateTimeImmutable(
                                    (string) $assessment['deadline'],
                                    new DateTimeZone('Asia/Manila')
                                ) <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
                                ?>
                                <article class="professor-assessment-card" id="assessment-<?= (int) $assessment['id'] ?>">
                                    <div>
                                        <h3><?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <p><?= number_format((float) $assessment['total_points'], 0) ?> pts
                                            · DEADLINE:
                                            <?= htmlspecialchars(date('m/d/y g:i A', strtotime($assessment['deadline'])), ENT_QUOTES, 'UTF-8') ?>
                                            · <?= (int) $assessment['question_count'] ?> question(s)
                                            <?php if ($assessment['time_limit_minutes'] !== null): ?>
                                                · <?= (int) $assessment['time_limit_minutes'] ?> min time limit
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="professor-assessment-actions">
                                        <?php $reopenableStudents = $reopenableStudentsByAssessment[(int) $assessment['id']] ?? []; ?>
                                        <?php if ($reopenableStudents): ?>
                                            <div class="professor-assessment-reopen">
                                                <button class="professor-assessment-action-button" type="button"
                                                    data-assessment-reopen-trigger>Reopen</button>
                                                <div class="professor-assessment-reopen-panel" hidden data-assessment-reopen-panel>
                                                    <form action="../../api/professor_process.php" method="POST">
                                                        <input type="hidden" name="action" value="reopen_student_submission">
                                                        <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                                                        <input type="hidden" name="assessment_id"
                                                            value="<?= (int) $assessment['id'] ?>">
                                                        <input type="hidden" name="term"
                                                            value="<?= htmlspecialchars($currentTerm, ENT_QUOTES, 'UTF-8') ?>">
                                                        <label>
                                                            Student
                                                            <select name="student_id" required>
                                                                <option value="">Select a student</option>
                                                                <?php foreach ($reopenableStudents as $reopenableStudent): ?>
                                                                    <option value="<?= (int) $reopenableStudent['student_id'] ?>">
                                                                        <?= htmlspecialchars(trim($reopenableStudent['first_name'] . ' ' . $reopenableStudent['last_name']), ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>
                                                            New deadline
                                                            <input type="datetime-local" name="extended_deadline" required>
                                                        </label>
                                                        <button type="submit" class="professor-grade-reopen">Set Deadline &amp;
                                                            Reopen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <a class="professor-assessment-view-link"
                                            href="professor_assessment.php?course_id=<?= (int) $courseId ?>&amp;assessment_id=<?= (int) $assessment['id'] ?>&amp;mode=view">View</a>
                                        <?php if ($assessmentDeadlinePassed): ?>
                                            <a class="professor-assessment-view-link"
                                                href="professor_assessment.php?course_id=<?= (int) $courseId ?>&amp;assessment_id=<?= (int) $assessment['id'] ?>&amp;mode=edit">Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <dialog class="professor-assessment-confirm" id="assessmentReopenConfirm"
                    aria-labelledby="assessmentReopenConfirmTitle">
                    <form method="dialog">
                        <p id="assessmentReopenConfirmTitle">Do you want to reopen the assessment?</p>
                        <div class="professor-assessment-confirm-actions">
                            <button type="submit" value="yes">Yes</button>
                            <button type="submit" value="no">No</button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>

            <?php if ($currentArea === 'grades'): ?>
                <section id="release-grade" class="professor-course-content-box">
                    <div class="professor-course-content-header">
                        <h2>Release Grade</h2>
                        <div class="professor-grade-release-actions">
                            <form action="../../api/professor_process.php" method="POST" data-grade-confirm-form
                                data-grade-confirm-message="Release grades to all approved students in this course?">
                                <input type="hidden" name="action" value="release_course_grades">
                                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                                <button type="submit" class="professor-course-action-button">Release Grades</button>
                            </form>
                            <form action="../../api/professor_process.php" method="POST" data-grade-confirm-form
                                data-grade-confirm-message="Unrelease grades from all approved students in this course?">
                                <input type="hidden" name="action" value="unrelease_course_grades">
                                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                                <button type="submit" class="professor-course-action-button">Unrelease Grades</button>
                            </form>
                        </div>
                    </div>
                    <dialog class="professor-assessment-confirm" id="courseGradeReleaseConfirm"
                        aria-labelledby="courseGradeReleaseConfirmTitle">
                        <form method="dialog">
                            <p id="courseGradeReleaseConfirmTitle">Are you sure?</p>
                            <div class="professor-assessment-confirm-actions">
                                <button type="submit" value="yes">Yes</button>
                                <button type="submit" value="no">No</button>
                            </div>
                        </form>
                    </dialog>
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
                                        <td><?= $gradeEquivalent ?></td>
                                        <td><?= $percentageRange ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="professor-grade-table-wrap">
                        <table class="professor-grade-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">Student Name</th>
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <th colspan="<?= max(1, count($assessmentsByTerm[$termLabel])) ?>">
                                            <?= htmlspecialchars(strtoupper($termLabel === 'Final' ? 'Finals' : $termLabel), ENT_QUOTES, 'UTF-8') ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2">Prelim Contribution (30%)</th>
                                    <th rowspan="2">Midterm Contribution (30%)</th>
                                    <th rowspan="2">Midterm Standing (60%)</th>
                                    <th rowspan="2">Final Contribution (40%)</th>
                                    <th rowspan="2">Overall Grade (100%)</th>
                                    <th rowspan="2">Final Grade</th>
                                    <th rowspan="2">Status</th>
                                </tr>
                                <tr>
                                    <?php foreach ($termLabels as $termLabel): ?>
                                        <?php if ($assessmentsByTerm[$termLabel]): ?>
                                            <?php foreach ($assessmentsByTerm[$termLabel] as $assessment): ?>
                                                <th id="assessment-<?= (int) $assessment['id'] ?>">
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
                                <?php if (!$gradeStudents): ?>
                                    <tr>
                                        <td colspan="<?= $gradeMatrixColumnCount ?>">No approved students are enrolled in this
                                            class.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($gradeStudents as $student): ?>
                                    <?php
                                    $studentId = (int) $student['student_id'];
                                    $studentSubmissions = $submissionsByStudent[$studentId] ?? [];
                                    $termPercentages = [];
                                    $termContributions = [];
                                    foreach ($termLabels as $gradeTerm) {
                                        $termPercentages[$gradeTerm] = assessmentTermPercentage(
                                            $assessmentsByTerm[$gradeTerm],
                                            $studentSubmissions
                                        );
                                        $termContributions[$gradeTerm] = $termPercentages[$gradeTerm] === null
                                            ? null
                                            : $termPercentages[$gradeTerm] * assessmentTermWeight($gradeTerm);
                                    }
                                    $midtermStanding = $termContributions['Prelim'] !== null
                                        && $termContributions['Midterm'] !== null
                                        ? $termContributions['Prelim'] + $termContributions['Midterm']
                                        : null;
                                    $overallGradePercent = $midtermStanding !== null
                                        && $termContributions['Final'] !== null
                                        ? $midtermStanding + $termContributions['Final']
                                        : null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name']), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <?php foreach ($termLabels as $termLabel): ?>
                                            <?php if ($assessmentsByTerm[$termLabel]): ?>
                                                <?php foreach ($assessmentsByTerm[$termLabel] as $gradeAssessment): ?>
                                                    <?php $gradeSubmission = $submissionsByStudent[$studentId][(int) $gradeAssessment['id']] ?? null; ?>
                                                    <td>
                                                        <?php if ($gradeSubmission && $gradeSubmission['score'] !== null): ?>
                                                            <?= number_format((float) $gradeSubmission['score'], 0) ?> /
                                                            <?= number_format((float) $gradeAssessment['total_points'], 0) ?>
                                                        <?php elseif ($gradeSubmission && $gradeSubmission['status'] === 'Submitted'): ?>
                                                            Needs review
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                        <?php if ($gradeSubmission && !empty($gradeSubmission['answers_json'])): ?>
                                                            <a class="professor-student-grade-view-link"
                                                                href="professor_student_grade.php?course_id=<?= (int) $courseId ?>&amp;student_id=<?= $studentId ?>&amp;assessment_id=<?= (int) $gradeAssessment['id'] ?>">View</a>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <td>—</td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <td><?= $termContributions['Prelim'] !== null ? number_format($termContributions['Prelim'], 0) . '%' : '—' ?>
                                        </td>
                                        <td><?= $termContributions['Midterm'] !== null ? number_format($termContributions['Midterm'], 0) . '%' : '—' ?>
                                        </td>
                                        <td><?= $midtermStanding !== null ? number_format($midtermStanding, 0) . '%' : '—' ?>
                                        </td>
                                        <td><?= $termContributions['Final'] !== null ? number_format($termContributions['Final'], 0) . '%' : '—' ?>
                                        </td>
                                        <td><?= $overallGradePercent !== null ? number_format($overallGradePercent, 0) . '%' : '—' ?>
                                        </td>
                                        <td>
                                            <form action="../../api/professor_process.php" method="POST"
                                                class="professor-final-grade-form">
                                                <input type="hidden" name="action" value="update_final_grade">
                                                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                                                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                                                <input type="text" name="final_grade" inputmode="decimal"
                                                    pattern="(?:[12](?:\.(?:00|25|50|75))?|3(?:\.00)?|5(?:\.00)?)"
                                                    maxlength="5" title="Enter a grade from the conversion legend."
                                                    value="<?= $student['final_grade'] !== null ? htmlspecialchars(number_format((float) $student['final_grade'], 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>"
                                                    aria-label="Final grade from the conversion scale" required>
                                                <button type="submit" class="professor-grade-reopen">Save</button>
                                            </form>
                                        </td>
                                        <td><?= $student['grade_released_at'] !== null ? 'Released' : 'Pending' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($currentArea === 'announcements'): ?>
                <section class="professor-course-content-box announcement-box professor-course-announcements">
                    <div class="table-toolbar">
                        <div class="card-title" style="margin:0;">Class Announcements</div>
                        <div class="support-request-tools registrar-announcement-tools">
                            <button class="btn btn-coral btn-sm registrar-announcement-post-button" type="button"
                                data-open-modal="professorCourseAnnouncementModal">
                                <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                Post Announcement
                            </button>
                            <?php if ($courseAnnouncements): ?>
                                <form class="support-request-delete-form" id="deleteProfessorCourseAnnouncementsForm"
                                    action="../../api/professor_process.php" method="post" data-support-delete-form
                                    data-confirm-message="Permanently delete the selected course announcements? This cannot be undone.">
                                    <input type="hidden" name="action" value="delete_course_announcements">
                                    <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                                    <button class="support-request-delete-button" type="submit" data-support-delete-button
                                        aria-label="Delete selected announcements"
                                        title="Permanently delete selected announcements" disabled>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M4 7h16M9 7V4h6v3m3 0-.8 13H6.8L6 7m4 4v5m4-5v5" />
                                        </svg>
                                        <span class="support-request-delete-count" data-support-selected-count hidden></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table registrar-announcement-table professor-course-announcement-table"
                            data-no-sort>
                            <thead>
                                <tr>
                                    <th class="registrar-announcement-select-heading"><span class="sr-only">Select</span>
                                    </th>
                                    <th scope="col">Headline</th>
                                    <th scope="col">Date Uploaded</th>
                                    <th scope="col">Attachment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$courseAnnouncements): ?>
                                    <tr>
                                        <td colspan="4" class="registrar-user-table-empty">No announcements have been posted to
                                            this class.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courseAnnouncements as $announcement): ?>
                                        <tr>
                                            <td class="registrar-announcement-select-cell">
                                                <label class="support-request-select registrar-announcement-select">
                                                    <input type="checkbox" name="announcement_ids[]"
                                                        value="<?= (int) $announcement['id'] ?>"
                                                        form="deleteProfessorCourseAnnouncementsForm" data-support-request-select>
                                                    <span class="sr-only">Select announcement
                                                        <?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </label>
                                            </td>
                                            <td><strong><?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </td>
                                            <td class="registrar-announcement-date">
                                                <?= htmlspecialchars(date('M d, Y h:i A', strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td>
                                                <?php if (
                                                    is_string($announcement['image_path'])
                                                    && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $announcement['image_path'])
                                                ): ?>
                                                    <button class="announcement-attachment-open" type="button"
                                                        data-announcement-attachment-open
                                                        data-image-src="../../<?= htmlspecialchars($announcement['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-image-alt="<?= htmlspecialchars($announcement['headline'], ENT_QUOTES, 'UTF-8') ?>">
                                                        View Attachment
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($currentArea === 'lessons'): ?>
    <div class="modal-backdrop lesson-modal-backdrop" id="courseLessonModal">
        <div class="modal-dialog lesson-modal-dialog" role="dialog" aria-modal="true"
            aria-labelledby="courseLessonModalTitle">
            <form action="../../api/professor_process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_lesson">
                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                <input type="hidden" name="term" value="<?= htmlspecialchars($currentTerm, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header">
                    <h2 class="modal-title" id="courseLessonModalTitle">Upload Lesson</h2>
                    <button type="button" class="modal-close" data-close-modal aria-label="Close dialog">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="courseLessonTitle">Title</label>
                        <input type="text" id="courseLessonTitle" name="title" class="form-control" maxlength="150"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="courseLessonDescription">Description</label>
                        <textarea id="courseLessonDescription" name="content" class="form-control" rows="4"
                            maxlength="10000" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="lesson_file">File Upload</label>
                        <label class="lesson-upload-box" for="lesson_file">
                            <span class="lesson-upload-main">
                                <span class="lesson-upload-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 16V4M7 9l5-5 5 5M5 20h14" />
                                    </svg>
                                </span>
                                <strong class="lesson-upload-title">Choose a lesson file</strong>
                            </span>
                            <small class="lesson-upload-types" id="lessonFileName">PDF, Word, PowerPoint, Excel, images,
                                text, or ZIP · up to 10 MB</small>
                            <input type="file" id="lesson_file" name="lesson_file"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.jpg,.jpeg,.png,.gif,.webp"
                                required>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary lesson-submit-btn">Upload Lesson</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($currentArea === 'announcements'): ?>
    <div class="modal-backdrop" id="professorCourseAnnouncementModal">
        <div class="modal-dialog">
            <form action="../../api/professor_process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="post_announcement">
                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                <div class="modal-header">
                    <h3 class="modal-title">Post Announcement</h3>
                    <button type="button" class="modal-close" data-close-modal aria-label="Close dialog">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="professorCourseAnnouncementHeadline">Headline *</label>
                        <input type="text" id="professorCourseAnnouncementHeadline" name="title" class="form-control"
                            maxlength="150" placeholder="e.g. Midterm Schedule Advisory" required>
                    </div>
                    <div class="form-group">
                        <label for="professorCourseAnnouncementMessage">Message *</label>
                        <textarea id="professorCourseAnnouncementMessage" name="message" class="form-control" rows="5"
                            placeholder="Write the announcement message..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="professorCourseAnnouncementImage">Image (optional)</label>
                        <input type="file" id="professorCourseAnnouncementImage" name="image" class="form-control"
                            accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG, or WebP up to 5 MB. Without an image, the announcement is shown as text
                            only.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-coral">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($currentArea === 'assessments'): ?>
    <div class="modal-backdrop" id="courseAssessmentModal">
        <div class="modal-dialog course-assessment-dialog" role="dialog" aria-modal="true"
            aria-labelledby="courseAssessmentModalTitle">
            <form action="../../api/professor_process.php" method="POST" id="courseAssessmentForm">
                <input type="hidden" name="action" value="create_assessment">
                <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
                <div class="modal-header">
                    <h2 class="modal-title" id="courseAssessmentModalTitle">Create Assessment</h2>
                    <button type="button" class="modal-close" data-close-modal aria-label="Close dialog">&times;</button>
                </div>
                <div class="modal-body course-assessment-modal-body">
                    <div class="course-assessment-form-grid">
                        <div class="form-group">
                            <label for="courseAssessmentTitle">Title</label>
                            <input id="courseAssessmentTitle" type="text" name="title" class="form-control" maxlength="150"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="courseAssessmentTerm">Term</label>
                            <select id="courseAssessmentTerm" name="term" class="form-control" required>
                                <?php foreach ($termLabels as $termLabel): ?>
                                    <option value="<?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $currentTerm === $termLabel ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="courseAssessmentType">Assessment Type</label>
                            <select id="courseAssessmentType" name="assessment_type" class="form-control" required>
                                <option value="Quiz">Quiz</option>
                                <option value="Exam">Exam</option>
                            </select>
                        </div>
                        <div class="form-group" data-question-format-field>
                            <label for="courseQuestionFormat">Question Format</label>
                            <select id="courseQuestionFormat" name="question_format" class="form-control" required>
                                <option value="Multiple Choice">Multiple Choice</option>
                                <option value="True or False">True or False</option>
                                <option value="Mixed">Mixed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="courseAssessmentPoints">Total Points</label>
                            <input id="courseAssessmentPoints" type="number" name="total_points" class="form-control"
                                min="0.01" max="100000" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="courseAssessmentDeadline">Deadline</label>
                            <input id="courseAssessmentDeadline" type="datetime-local" name="deadline" class="form-control"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="courseAssessmentTimeLimit">Time Limit (minutes)</label>
                            <input id="courseAssessmentTimeLimit" type="number" name="time_limit_minutes"
                                class="form-control" min="1" max="1440" step="1" placeholder="No limit">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="courseAssessmentDescription">Description / Guidelines</label>
                        <textarea id="courseAssessmentDescription" name="description" class="form-control" rows="3"
                            maxlength="10000"></textarea>
                    </div>
                    <section class="course-assessment-question-builder" aria-labelledby="courseAssessmentQuestionsTitle">
                        <div class="course-assessment-question-builder-header">
                            <h3 id="courseAssessmentQuestionsTitle">Questions</h3>
                            <button type="button" class="btn btn-outline btn-sm" data-assessment-add-question>
                                + Add Question
                            </button>
                        </div>
                        <div class="course-assessment-question-list" data-assessment-question-list></div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Assessment</button>
                </div>
            </form>
        </div>
    </div>
    <template id="courseAssessmentQuestionTemplate">
        <article class="course-assessment-question" data-assessment-question>
            <div class="course-assessment-question-heading">
                <strong data-assessment-question-title>Question</strong>
                <button type="button" class="course-assessment-remove-question" data-assessment-remove-question
                    aria-label="Remove question">&times;</button>
            </div>
            <div class="course-assessment-question-grid">
                <div class="form-group course-assessment-question-type-field" data-question-type-field>
                    <label>Question Type</label>
                    <select class="form-control" name="questions[__INDEX__][question_type]" data-question-type>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True or False</option>
                    </select>
                </div>
                <div class="form-group course-assessment-question-points">
                    <label>Points</label>
                    <input type="number" class="form-control" name="questions[__INDEX__][points]" min="0.01" max="100000"
                        step="0.01" value="1" required>
                </div>
            </div>
            <div class="form-group">
                <label>Question / Problem Statement</label>
                <textarea class="form-control" name="questions[__INDEX__][question_text]" rows="2" maxlength="10000"
                    required></textarea>
            </div>
            <div class="course-assessment-question-mode" data-multiple-choice-fields>
                <fieldset class="course-assessment-choices">
                    <legend>Four Choices · Select the Correct Answer</legend>
                    <?php foreach ([0, 1, 2, 3] as $choiceIndex): ?>
                        <label class="course-assessment-choice">
                            <input type="radio" name="questions[__INDEX__][correct_choice]" value="<?= $choiceIndex ?>"
                                required>
                            <input type="text" class="form-control" name="questions[__INDEX__][choices][]"
                                aria-label="Choice <?= $choiceIndex + 1 ?>" maxlength="500" required>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <div class="course-assessment-question-mode" data-true-false-fields hidden>
                <div class="form-group">
                    <label>Correct Answer</label>
                    <select class="form-control" name="questions[__INDEX__][correct_answer]" disabled>
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>
                </div>
            </div>
        </article>
    </template>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>