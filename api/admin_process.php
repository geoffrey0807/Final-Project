<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/password_policy.php';

checkAccess(['admin'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$adminId = $_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/admin_dashboard.php') {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    $query = $success ? "msg=" . urlencode($message) : "error=" . urlencode($message);
    header("Location: $redirect?$query");
    exit;
}

try {
    switch ($action) {
        case 'add_course':
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $name = trim($_POST['course_name'] ?? '');
            $profId = !empty($_POST['professor_id']) ? (int)$_POST['professor_id'] : null;
            $capacity = !empty($_POST['max_capacity']) ? (int)$_POST['max_capacity'] : 30;

            if (empty($code) || empty($name)) {
                respond(false, "Course Code and Name are required.");
            }

            $check = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code");
            $check->execute(['code' => $code]);
            if ($check->fetch()) {
                respond(false, "A course with code '$code' already exists.");
            }

            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, professor_id, max_capacity) VALUES (:code, :name, :prof, :cap)");
            $stmt->execute(['code' => $code, 'name' => $name, 'prof' => $profId, 'cap' => $capacity]);
            respond(true, "Course $code created successfully.");
            break;

        case 'delete_course':
            $courseId = (int)($_POST['course_id'] ?? 0);
            if ($courseId <= 0) {
                respond(false, "Invalid course selected.");
            }
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
            $stmt->execute(['id' => $courseId]);
            respond(true, "Course removed successfully.");
            break;

        case 'add_user':
            $fn = trim($_POST['first_name'] ?? '');
            $ln = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password = is_string($password) ? $password : '';
            $role = $_POST['role'] ?? 'student';

            if (empty($fn) || empty($ln) || empty($email) || empty($password)) {
                respond(false, "All user fields are required.");
            }
            if (!in_array($role, ['admin', 'professor', 'student'], true)) {
                respond(false, "Invalid role specified.");
            }
            if (!isValidPassword($password)) {
                respond(false, passwordPolicyMessage());
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                respond(false, "User email '$email' is already registered.");
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (:fn, :ln, :email, :pass, :role)");
            $stmt->execute(['fn' => $fn, 'ln' => $ln, 'email' => $email, 'pass' => $hash, 'role' => $role]);
            respond(true, "New $role account created for $fn $ln.");
            break;

        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === $adminId) {
                respond(false, "You cannot delete your own active administrator account.");
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            respond(true, "User deleted successfully.");
            break;

        case 'update_enrollment':
            $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
            $status = strtoupper(trim($_POST['status'] ?? ''));

            if (!in_array($status, ['APPROVED', 'REJECTED', 'PENDING'], true)) {
                respond(false, "Invalid enrollment decision.");
            }

            $stmt = $pdo->prepare("UPDATE enrollments SET status = :status, decided_at = NOW() WHERE id = :id");
            $stmt->execute(['status' => $status, 'id' => $enrollmentId]);
            respond(true, "Enrollment status updated to $status.");
            break;

        case 'post_announcement':
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;

            if (empty($title) || empty($message)) {
                respond(false, "Title and announcement content are required.");
            }

            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, course_id, author_id) VALUES (:title, :msg, :cid, :aid)");
            $stmt->execute(['title' => $title, 'msg' => $message, 'cid' => $courseId, 'aid' => $adminId]);
            respond(true, "Announcement published successfully.");
            break;

        case 'delete_announcement':
            $annId = (int)($_POST['announcement_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
            $stmt->execute(['id' => $annId]);
            respond(true, "Announcement deleted.");
            break;

        default:
            respond(false, "Invalid action specified.");
            break;
    }
} catch (Exception $e) {
    respond(false, "Operation failed: " . $e->getMessage());
}
