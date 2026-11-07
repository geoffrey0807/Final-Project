<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';

$professorId = (int) $_SESSION['user_id'];
$courseStmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name
    FROM courses c
    WHERE c.professor_id = :professor_id AND c.archived_at IS NULL
      AND c.academic_year = :academic_year AND c.semester = :semester
    ORDER BY c.course_code ASC
");
$courseStmt->execute([
    'professor_id' => $professorId,
    'academic_year' => $academicPeriod['academic_year'],
    'semester' => $academicPeriod['semester'],
]);
$courses = $courseStmt->fetchAll();

$pointList = [
    ['title' => 'Prelim Module', 'meta' => 'PDF · 2.4 MB'],
    ['title' => 'Midterm Review Deck', 'meta' => 'PPTX · 4.8 MB'],
    ['title' => 'Final Review Packet', 'meta' => 'DOCX · 1.4 MB'],
];

$professorView = 'lessons';
$pageTitle = 'Upload Lessons | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202611061140', '../../css/student.css?v=202611020900'];
$extraJs = ['../../js/admin.js?v=202611010115', '../../js/student.js?v=202610301825'];
$bodyClass = 'admin-dashboard student-dashboard professor-portal';
$useVisualTheme = true;
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/professor_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <header class="student-page-header">
            <h1>Upload Lessons</h1>
        </header>

        <section class="professor-course-panel">
            <div class="professor-course-panel-header">
                <h2>Course Materials</h2>
                <button type="button" class="professor-course-action-button">+ Upload Material</button>
            </div>
            <div class="professor-course-file-list">
                <?php foreach ($pointList as $item): ?>
                    <div class="professor-course-file-item">
                        <span class="professor-course-file-badge">FILE</span>
                        <div>
                            <strong><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($item['meta'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="professor-course-panel professor-announcements-page">
            <div class="professor-course-panel-header">
                <h2>Term Upload</h2>
                <span class="professor-course-scope-label">Prelim · Midterm · Final</span>
            </div>
            <div class="professor-course-term-legend">
                <span>Prelim</span>
                <span>Midterm</span>
                <span>Final</span>
            </div>
            <div class="professor-course-upload-summary">
                <div class="professor-course-summary-item">
                    <strong>Prelim</strong>
                    <small>2 files uploaded</small>
                </div>
                <div class="professor-course-summary-item">
                    <strong>Midterm</strong>
                    <small>1 file uploaded</small>
                </div>
                <div class="professor-course-summary-item">
                    <strong>Final</strong>
                    <small>0 files uploaded</small>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>