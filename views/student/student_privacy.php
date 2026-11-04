<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');

$pageTitle = 'Privacy Policy | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610291355', '../../css/student.css?v=202610311320'];
$extraJs = '../../js/student.js?v=202610291930';
$bodyClass = 'admin-dashboard student-dashboard';
$useVisualTheme = true;
$studentPage = 'privacy';
require_once '../../includes/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/student_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <?php require '../../includes/privacy_policy_content.php'; ?>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
