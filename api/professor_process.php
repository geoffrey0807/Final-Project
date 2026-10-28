<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';

checkAccess(['professor'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$profId = (int)$_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/professor_dashboard.php') {
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
        case 'approve':
        case 'reject':
            $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
            $newStatus = ($action === 'approve') ? 'APPROVED' : 'REJECTED';

            $checkStmt = $pdo->prepare("
                SELECT e.id, c.course_code, u.first_name, u.last_name
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                JOIN users u ON e.student_id = u.id
                WHERE e.id = :eid AND c.professor_id = :pid
            ");
            $checkStmt->execute(['eid' => $enrollmentId, 'pid' => $profId]);
            $enrollment = $checkStmt->fetch();

            if (!$enrollment) {
                respond(false, "You do not have permission to moderate this enrollment.");
            }

            $update = $pdo->prepare("UPDATE enrollments SET status = :status, decided_at = NOW() WHERE id = :id");
            $update->execute(['status' => $newStatus, 'id' => $enrollmentId]);

            $studentName = $enrollment['first_name'] . ' ' . $enrollment['last_name'];
            respond(true, "Application for $studentName in {$enrollment['course_code']} has been $newStatus.");
            break;

        case 'post_announcement':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if (empty($title) || empty($message) || $courseId <= 0) {
                respond(false, "Course, Title, and Message are required.");
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid");
            $checkCourse->execute(['cid' => $courseId, 'pid' => $profId]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only post announcements to your assigned courses.");
            }

            $stmt = $pdo->prepare("INSERT INTO announcements (course_id, author_id, title, message) VALUES (:cid, :aid, :title, :msg)");
            $stmt->execute(['cid' => $courseId, 'aid' => $profId, 'title' => $title, 'msg' => $message]);
            respond(true, "Announcement published to your class.");
            break;

        case 'delete_announcement':
            $annId = (int)($_POST['announcement_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id AND author_id = :pid");
            $stmt->execute(['id' => $annId, 'pid' => $profId]);
            respond(true, "Announcement removed.");
            break;

        default:
            respond(false, "Unknown professor action.");
            break;
    }
} catch (Exception $e) {
    respond(false, "Action failed: " . $e->getMessage());
}


