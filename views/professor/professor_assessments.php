<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
require_once '../../config/db.php';

$professorId = (int) $_SESSION['user_id'];
$assessmentRows = [
    ['type' => 'Multiple Choice', 'title' => 'Prelim Quiz 1', 'deadline' => '2026-11-12 18:00', 'status' => 'Open'],
    ['type' => 'True or False', 'title' => 'Midterm Recap', 'deadline' => '2026-12-01 18:00', 'status' => 'Open'],
    ['type' => 'Multiple Choice', 'title' => 'Final Examination', 'deadline' => '2026-12-20 18:00', 'status' => 'Scheduled'],
];

$professorView = 'assessments';
$pageTitle = 'Upload Assessments | APEX Learning Hub';
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
            <h1>Upload Assessments</h1>
        </header>

        <section class="professor-course-panel">
            <div class="professor-course-panel-header">
                <h2>Assessment Types</h2>
                <button type="button" class="professor-course-action-button">+ New Assessment</button>
            </div>
            <div class="professor-course-file-list">
                <?php foreach ($assessmentRows as $assessment): ?>
                    <div class="professor-course-file-item professor-course-assessment-item">
                        <span
                            class="professor-course-file-badge"><?= strtoupper(htmlspecialchars($assessment['type'], ENT_QUOTES, 'UTF-8')) ?></span>
                        <div>
                            <strong><?= htmlspecialchars($assessment['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($assessment['deadline'], ENT_QUOTES, 'UTF-8') ?> ·
                                <?= htmlspecialchars($assessment['status'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="professor-course-announcement-panel">
            <div class="professor-course-panel-header">
                <h2>Assessment Builder</h2>
            </div>
            <form class="professor-course-announcement-form">
                <div class="form-group">
                    <label for="assessmentType">Type</label>
                    <select id="assessmentType" class="form-control">
                        <option>Multiple Choice</option>
                        <option>True or False</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assessmentQuestion">Question</label>
                    <textarea id="assessmentQuestion" class="form-control" rows="4"
                        placeholder="Type your question here"></textarea>
                </div>
                <div class="form-group">
                    <label for="assessmentAnswer">Correct Answer</label>
                    <input id="assessmentAnswer" class="form-control" type="text" placeholder="A / True / 42">
                </div>
                <div class="professor-course-form-actions">
                    <button type="button" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>