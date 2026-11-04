<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
$pageTitle = 'Privacy Policy | APEX Learning Hub';
$extraCss = ['../../css/admin.css?v=202610301835', '../../css/student.css?v=202610311320'];
$extraJs = ['../../js/admin.js?v=202610301355', '../../js/student.js?v=202610301835'];
$bodyClass = 'admin-dashboard student-dashboard admin-portal';
$useVisualTheme = true;
$adminPage = 'privacy';
require_once '../../includes/header.php';
?>
<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require '../../includes/admin_sidebar.php'; ?>
    <div class="container student-dashboard-content">
        <?php require '../../includes/privacy_policy_content.php'; ?>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close admin navigation"></button>
<button type="button" class="student-mobile-toggle" aria-label="Open admin navigation" aria-controls="studentSidebar" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg></button>
<?php require_once '../../includes/footer.php'; ?>
