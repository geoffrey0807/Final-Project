<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/academic_access.php';
require_once '../includes/portal_toast.php';

checkAccess(['student'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$studentId = (int)$_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/student/student_dashboard.php') {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    setPortalToast($message, $success ? 'success' : 'error');
    header("Location: $redirect");
    exit;
}

if ($academicReadOnly && in_array($action, ['apply_course', 'drop_course', 'complete_assignment'], true)) {
    respond(false, 'This past academic period is view-only.');
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
            if (!studentCanAccessCourse($pdo, $studentId, $courseId)) {
                denyStudentCourseAccess($isAjax);
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

        case 'complete_assignment':
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                respond(false, "Invalid assignment selected.", '../views/student/student_tasks.php');
            }

            $assignmentCourseStmt = $pdo->prepare('SELECT course_id FROM assignments WHERE id = :assignment_id');
            $assignmentCourseStmt->execute(['assignment_id' => $assignmentId]);
            $assignmentCourseId = $assignmentCourseStmt->fetchColumn();
            if ($assignmentCourseId && !studentCanAccessCourse($pdo, $studentId, (int) $assignmentCourseId)) {
                denyStudentCourseAccess($isAjax);
            }

            $assignmentStmt = $pdo->prepare("
                SELECT a.id, a.course_id, COUNT(q.id) AS total_questions
                FROM assignments a
                JOIN enrollments e ON e.course_id = a.course_id
                JOIN courses c ON c.id = a.course_id
                LEFT JOIN assignment_questions q ON q.assignment_id = a.id
                WHERE a.id = :assignment_id
                  AND e.student_id = :student_id
                  AND e.status = 'APPROVED'
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
                GROUP BY a.id
            ");
            $assignmentStmt->execute([
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
            ]);
            $assignment = $assignmentStmt->fetch();
            if (!$assignment) {
                respond(false, "That assignment is not available in your enrolled courses.", '../views/student/student_tasks.php');
            }

            $totalQuestions = (int) $assignment['total_questions'];
            $score = 0;
            if ($totalQuestions > 0) {
                $questionStmt = $pdo->prepare("SELECT id, correct_answer FROM assignment_questions WHERE assignment_id = :assignment_id ORDER BY question_order, id");
                $questionStmt->execute(['assignment_id' => $assignmentId]);
                $answers = $_POST['answers'] ?? [];
                foreach ($questionStmt->fetchAll() as $question) {
                    $submittedAnswer = is_array($answers) ? strtolower(trim((string) ($answers[$question['id']] ?? ''))) : '';
                    $correctAnswer = strtolower(trim((string) $question['correct_answer']));
                    if ($submittedAnswer !== '' && $submittedAnswer === $correctAnswer) {
                        $score++;
                    }
                }
            }
            $completionStmt = $pdo->prepare("
                INSERT INTO assignment_completions (assignment_id, student_id, score, total_questions)
                VALUES (:assignment_id, :student_id, :score, :total_questions)
                ON DUPLICATE KEY UPDATE score = VALUES(score), total_questions = VALUES(total_questions), completed_at = CURRENT_TIMESTAMP
            ");
            $completionStmt->execute([
                'assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'score' => $score,
                'total_questions' => $totalQuestions,
            ]);
            respond(true, $totalQuestions > 0
                ? "Assessment submitted. Score: {$score}/{$totalQuestions}."
                : "Assignment marked complete.", '../views/student/student_tasks.php');
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
