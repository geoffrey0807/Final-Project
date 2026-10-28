<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';

checkAccess(['student'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$studentId = (int)$_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/student_dashboard.php') {
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
        case 'apply_course':
            $courseId = (int)($_POST['course_id'] ?? 0);
            if ($courseId <= 0) {
                respond(false, "Invalid course selection.");
            }

            $courseStmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'APPROVED') AS approved_count
                FROM courses c 
                WHERE c.id = :cid
            ");
            $courseStmt->execute(['cid' => $courseId]);
            $course = $courseStmt->fetch();

            if (!$course) {
                respond(false, "Course not found.");
            }

            $checkEnroll = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = :sid AND course_id = :cid");
            $checkEnroll->execute(['sid' => $studentId, 'cid' => $courseId]);
            $existing = $checkEnroll->fetch();

            if ($existing) {
                if ($existing['status'] === 'APPROVED') {
                    respond(false, "You are already enrolled in this course.");
                } elseif ($existing['status'] === 'PENDING') {
                    respond(false, "Your enrollment application is currently pending professor approval.");
                } else {
                    $reapply = $pdo->prepare("UPDATE enrollments SET status = 'PENDING', applied_at = NOW(), decided_at = NULL WHERE id = :id");
                    $reapply->execute(['id' => $existing['id']]);
                    respond(true, "Application re-submitted for {$course['course_code']}!");
                }
            }

            if ($course['approved_count'] >= $course['max_capacity']) {
                respond(false, "This course has reached its maximum student capacity.");
            }

            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (:sid, :cid, 'PENDING')");
            $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);

            respond(true, "Application submitted for {$course['course_code']}! Awaiting professor approval.");
            break;

        case 'drop_course':
            $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = :eid AND student_id = :sid");
            $stmt->execute(['eid' => $enrollmentId, 'sid' => $studentId]);
            respond(true, "Course application or enrollment has been dropped.");
            break;

        case 'update_profile':
            $fn = trim($_POST['first_name'] ?? '');
            $ln = trim($_POST['last_name'] ?? '');

            if (empty($fn) || empty($ln)) {
                respond(false, "First and last name cannot be empty.");
            }

            $update = $pdo->prepare("UPDATE users SET first_name = :fn, last_name = :ln WHERE id = :id");
            $update->execute(['fn' => $fn, 'ln' => $ln, 'id' => $studentId]);

            $_SESSION['user_name'] = "$fn $ln";
            respond(true, "Profile details updated successfully.");
            break;

        default:
            respond(false, "Unknown student action.");
            break;
    }
} catch (Exception $e) {
    respond(false, "Error: " . $e->getMessage());
}

