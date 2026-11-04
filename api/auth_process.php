<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db.php';
require_once '../includes/email_policy.php';
require_once '../includes/portal_toast.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'login';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }
    if (!isValidApexEmail($email)) {
        echo json_encode(['success' => false, 'message' => apexEmailPolicyMessage()]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ((int) $user['is_active'] !== 1 || $user['archived_at'] !== null) {
            echo json_encode(['success' => false, 'message' => 'This account is disabled or archived. Contact an administrator for assistance.']);
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['role']      = $user['role'];
        $_SESSION['email']     = $user['email'];
        setPortalToast('Login successful.');

        $dashboardPaths = [
            'admin' => '../views/admin/admin_dashboard.php',
            'registrar' => '../views/registrar/registrar_dashboard.php',
            'professor' => '../views/professor/professor_dashboard.php',
            'dean' => '../views/dean/dean_dashboard.php',
            'student' => '../views/student/student_dashboard.php',
        ];
        $redirect = $dashboardPaths[$user['role']] ?? '../auth/login.php';
        echo json_encode(['success' => true, 'redirect' => $redirect, 'role' => $user['role']]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit;
