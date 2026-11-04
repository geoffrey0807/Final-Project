<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

require_once 'includes/session_check.php';
checkAccess(['admin', 'registrar', 'dean', 'professor', 'student'], 'auth/login.php');

switch ($_SESSION['role']) {
    case 'admin':
        header("Location: views/admin/admin_dashboard.php");
        break;
    case 'registrar':
        header("Location: views/registrar/registrar_dashboard.php");
        break;
    case 'professor':
        header("Location: views/professor/professor_dashboard.php");
        break;
    case 'dean':
        header("Location: views/dean/dean_dashboard.php");
        break;
    case 'student':
        header("Location: views/student/student_dashboard.php");
        break;
    default:
        header("Location: auth/login.php");
        break;
}
exit;

