<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

switch ($_SESSION['role']) {
    case 'admin':
        header("Location: views/admin_dashboard.php");
        break;
    case 'professor':
        header("Location: views/professor_dashboard.php");
        break;
    case 'student':
        header("Location: views/student_dashboard.php");
        break;
    default:
        header("Location: auth/login.php");
        break;
}
exit;
