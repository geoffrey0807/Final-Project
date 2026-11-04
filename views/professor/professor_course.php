<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';

$professorId = (int) $_SESSION['user_id'];
$courseId = (int) ($_GET['id'] ?? 0);
$courseStmt = $pdo->prepare("
    SELECT c.*, COUNT(DISTINCT CASE WHEN e.status = 'APPROVED' THEN e.student_id END) AS approved_students
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND 1 = 1
        " . academicPeriodDateFilter('COALESCE(e.decided_at, e.applied_at)') . "
    WHERE c.id = :course_id AND c.professor_id = :professor_id
    GROUP BY c.id
");
$courseStmt->execute(array_merge(
    ['course_id' => $courseId, 'professor_id' => $professorId],
    academicPeriodDateParams()
));
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$studentsStmt = $pdo->prepare("
    SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, u.is_active, u.archived_at,
           e.status AS enrollment_status
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = :course_id AND u.role = 'student'" . academicPeriodDateFilter('COALESCE(e.decided_at, e.applied_at)') . "
    ORDER BY u.last_name, u.first_name
");
$studentsStmt->execute(array_merge(['course_id' => $courseId], academicPeriodDateParams()));
$students = $studentsStmt->fetchAll();

$announcementsStmt = $pdo->prepare(
    "SELECT *, headline AS title FROM announcements WHERE course_id = :course_id" . academicPeriodDateFilter('created_at') . " ORDER BY created_at DESC"
);
$announcementsStmt->execute(array_merge(['course_id' => $courseId], academicPeriodDateParams()));
$announcements = $announcementsStmt->fetchAll();

$assignmentsStmt = $pdo->prepare(
    "SELECT * FROM assignments WHERE course_id = :course_id AND professor_id = :professor_id"
        . academicPeriodDateFilter('due_at')
        . " ORDER BY due_at ASC"
);
$assignmentsStmt->execute(array_merge(
    ['course_id' => $courseId, 'professor_id' => $professorId],
    academicPeriodDateParams()
));
$assignments = $assignmentsStmt->fetchAll();
$questionStmt = $pdo->prepare("SELECT * FROM assignment_questions WHERE assignment_id = :assignment_id ORDER BY question_order, id");
$questionsByAssignment = [];
foreach ($assignments as $assignment) {
    $questionStmt->execute(['assignment_id' => (int) $assignment['id']]);
    $questionsByAssignment[(int) $assignment['id']] = $questionStmt->fetchAll();
}

$lessonsStmt = $pdo->prepare(
    "SELECT * FROM lessons WHERE course_id = :course_id AND professor_id = :professor_id"
        . academicPeriodDateFilter('created_at')
        . " ORDER BY created_at DESC"
);
$lessonsStmt->execute(array_merge(
    ['course_id' => $courseId, 'professor_id' => $professorId],
    academicPeriodDateParams()
));
$lessons = $lessonsStmt->fetchAll();

$pageTitle = $course['course_code'] . ' | Professor Course';
$extraCss = ['../../css/admin.css?v=202611011955', '../../css/student.css?v=202611011955'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301825'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal professor-course-page';
$useVisualTheme = true;
$professorView = 'classes';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <a class="course-back-link" href="professor_classes.php">&larr; Back to Assigned Classes</a>
        <header class="course-overview-header">
            <span class="context-pill context-pill-admin"><?= htmlspecialchars($course['course_code']) ?></span>
            <h1><?= htmlspecialchars($course['course_name']) ?></h1>
            <p>Section <?= htmlspecialchars($course['section_name'] ?: 'General') ?></p>
            <small><?= htmlspecialchars($course['meeting_days'] ?: 'Schedule not set') ?>
                <?php if ($course['start_time'] && $course['end_time']): ?>
                    &middot; <?= date('g:i A', strtotime($course['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($course['end_time'])) ?>
                <?php endif; ?>
                <?php if ($course['room']): ?> &middot; <?= htmlspecialchars($course['room']) ?><?php endif; ?>
            </small>
        </header>

        <nav class="course-workspace-nav" aria-label="Course navigation">
            <a href="#overview">Course Overview</a>
            <a href="#students">Enrolled Students</a>
            <a href="#lessons">Lessons</a>
            <a href="#assessments">Assessments</a>
            <a href="#announcements">Announcements</a>
        </nav>

        <section class="course-workspace-section" id="overview">
            <div class="course-section-heading"><h2>Course Overview</h2></div>
            <div class="metrics-grid professor-summary-grid">
                <div class="metric-card"><div class="metric-data"><h3><?= (int) $course['approved_students'] ?></h3><p>Enrolled Students</p></div></div>
                <div class="metric-card"><div class="metric-data"><h3><?= count($lessons) ?></h3><p>Lessons</p></div></div>
                <div class="metric-card"><div class="metric-data"><h3><?= count($assignments) ?></h3><p>Assessments</p></div></div>
            </div>
        </section>

        <section class="course-workspace-section" id="students">
            <div class="course-section-heading"><h2>Enrolled Students</h2></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>ID Number</th><th>Name</th><th>Email</th><th>Enrollment</th><th>Account status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$students): ?>
                            <tr><td colspan="5">No students are enrolled in this course yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $studentAccountStatus = $student['archived_at'] !== null
                                    ? 'Archived'
                                    : ((int) $student['is_active'] === 1 ? 'Active' : 'Disabled');
                                ?>
                                <tr id="student-<?= (int) $student['id'] ?>">
                                    <td class="account-id-cell"><?= htmlspecialchars($student['id_number'] ?? '', ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                    <td><?= htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student['enrollment_status'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($studentAccountStatus, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="course-workspace-section" id="lessons">
            <div class="course-section-heading">
                <h2>Lessons</h2>
                <button class="btn btn-primary btn-sm" data-open-modal="courseLessonModal">+ New Lesson</button>
            </div>
            <?php if (!$lessons): ?><div class="card course-empty">No lessons have been added to this course yet.</div><?php endif; ?>
            <?php foreach ($lessons as $lesson): ?>
                <article class="card course-content-card">
                    <div><span class="student-eyebrow">Lesson</span><h3><?= htmlspecialchars($lesson['title']) ?></h3></div>
                    <p><?= nl2br(htmlspecialchars($lesson['content'])) ?></p>
                    <?php if ($lesson['file_name']): ?><a class="btn btn-outline btn-sm" href="../../api/download_lesson.php?id=<?= (int) $lesson['id'] ?>">Download <?= htmlspecialchars($lesson['file_name']) ?></a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="course-workspace-section" id="assessments">
            <div class="course-section-heading"><h2>Assessments</h2><button class="btn btn-primary btn-sm" data-open-modal="courseAssignmentModal">+ New Assessment</button></div>
            <?php if (!$assignments): ?><div class="card course-empty">No assessments have been added to this course yet.</div><?php endif; ?>
            <?php foreach ($assignments as $assignment): ?>
                <article class="card course-content-card"><span class="student-eyebrow"><?= htmlspecialchars($assignment['assessment_type']) ?></span><h3><?= htmlspecialchars($assignment['title']) ?></h3><p><?= nl2br(htmlspecialchars($assignment['description'])) ?></p><small>Due <?= date('M j, Y g:i A', strtotime($assignment['due_at'])) ?></small>
                    <?php if (!empty($questionsByAssignment[(int) $assignment['id']])): ?>
                        <div class="assessment-question-list">
                            <?php foreach ($questionsByAssignment[(int) $assignment['id']] as $question): ?>
                                <div class="assessment-question">
                                    <strong><?= htmlspecialchars($question['question_text']) ?></strong>
                                    <small><?= $question['question_type'] === 'multiple_choice' ? 'Multiple choice' : 'Identification' ?> &middot; Correct answer: <?= htmlspecialchars($question['correct_answer']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="course-workspace-section" id="announcements">
            <div class="course-section-heading"><h2>Announcements</h2><button class="btn btn-coral btn-sm" data-open-modal="courseAnnModal">+ New Announcement</button></div>
            <?php if (!$announcements): ?><div class="card course-empty">No announcements have been posted for this course yet.</div><?php endif; ?>
            <?php foreach ($announcements as $announcement): ?>
                <article class="card course-content-card"><span class="student-eyebrow">Announcement</span><h3><?= htmlspecialchars($announcement['title']) ?></h3><p><?= nl2br(htmlspecialchars($announcement['message'])) ?></p></article>
            <?php endforeach; ?>
        </section>
    </div>
</div>

<div class="modal-backdrop lesson-modal-backdrop" id="courseLessonModal">
    <div class="modal-dialog lesson-modal-dialog">
        <form action="../../api/professor_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_lesson">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <div class="modal-header">
                <div><h3 class="modal-title">Create Course Lesson</h3></div>
                <button type="button" class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="lesson_title">Lesson Title *</label>
                    <input type="text" id="lesson_title" name="title" class="form-control" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label for="lesson_content">Description *</label>
                    <textarea id="lesson_content" name="content" class="form-control" rows="6" maxlength="10000" required></textarea>
                </div>
                <div class="form-group">
                    <label for="lesson_file">Lesson File *</label>
                    <label class="lesson-upload-box" for="lesson_file">
                        <span class="lesson-upload-main">
                            <span class="lesson-upload-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>
                            </span>
                            <strong class="lesson-upload-title">Upload your file</strong>
                        </span>
                        <small class="lesson-upload-types" id="lessonFileName">PDF, Word, PowerPoint, Excel, text, or ZIP up to 10 MB</small>
                        <input type="file" id="lesson_file" name="lesson_file"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip" required>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary lesson-submit-btn">
                    <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                    Publish Lesson
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="courseAnnModal"><div class="modal-dialog"><form action="../../api/professor_process.php" method="POST">
    <input type="hidden" name="action" value="post_announcement"><input type="hidden" name="course_id" value="<?= $courseId ?>">
    <div class="modal-header"><h3 class="modal-title">New Announcement</h3><button type="button" class="modal-close" data-close-modal>&times;</button></div>
    <div class="modal-body"><div class="form-group"><label for="announcement_title">Title *</label><input id="announcement_title" name="title" class="form-control" maxlength="150" required></div><div class="form-group"><label for="announcement_message">Message *</label><textarea id="announcement_message" name="message" class="form-control" rows="6" required></textarea></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline" data-close-modal>Cancel</button><button class="btn btn-coral" type="submit">Post Announcement</button></div>
</form></div></div>

<div class="modal-backdrop" id="courseAssignmentModal">
    <div class="modal-dialog assessment-modal-dialog">
        <form action="../../api/professor_process.php" method="POST">
            <input type="hidden" name="action" value="create_assignment">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <div class="modal-header"><h3 class="modal-title">New Assessment</h3><button type="button" class="modal-close" data-close-modal>&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label for="assessment_title">Title *</label><input id="assessment_title" name="title" class="form-control" maxlength="150" required></div>
                <div class="form-group"><label for="assessment_type">Assessment type *</label><select id="assessment_type" name="assessment_type" class="form-control" required><option value="Assessment">Activity</option><option value="Quiz">Quiz</option><option value="Exam">Test</option></select></div>
                <div class="form-group"><label for="assessment_description">Instructions</label><textarea id="assessment_description" name="description" class="form-control" rows="4"></textarea></div>
                <div class="form-group"><label for="assessment_due_at">Due date and time *</label><input id="assessment_due_at" type="datetime-local" name="due_at" class="form-control" required></div>
                <div class="assessment-builder">
                    <div class="assessment-builder-heading"><h4>Questions</h4><button type="button" class="btn btn-outline btn-sm" data-add-question>Add question</button></div>
                    <div id="assessmentQuestions">
                        <div class="assessment-question-editor">
                            <div class="form-group"><label>Question *</label><textarea name="questions[0][text]" class="form-control" rows="2" required></textarea></div>
                            <div class="form-group"><label>Question type *</label><select name="questions[0][type]" class="form-control assessment-question-type"><option value="multiple_choice">Multiple choice</option><option value="identification">Identification</option></select></div>
                            <div class="form-group assessment-choices-group"><label>Choices (one per line, optional)</label><textarea name="questions[0][choices]" class="form-control" rows="3" placeholder="Choice A&#10;Choice B&#10;Choice C"></textarea></div>
                            <div class="form-group"><label>Correct answer *</label><input name="questions[0][answer]" class="form-control assessment-answer" required></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" data-close-modal>Cancel</button><button class="btn btn-primary" type="submit">Publish Assessment</button></div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
