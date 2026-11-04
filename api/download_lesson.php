<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/academic_access.php';
checkAccess(['student', 'professor'], '../auth/login.php');

$lessonId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT l.file_name, l.file_path, l.file_mime, l.file_size, l.professor_id, l.course_id
    FROM lessons l
    WHERE l.id = :lesson_id
");
$stmt->execute([
    'lesson_id' => $lessonId,
]);
$lesson = $stmt->fetch();

if ($lesson && ($_SESSION['role'] ?? '') === 'student') {
    if (!studentCanAccessCourse($pdo, $userId, (int) $lesson['course_id'])) {
        denyStudentCourseAccess();
    }
    $enrollmentStmt = $pdo->prepare("
        SELECT 1 FROM enrollments
        WHERE student_id = :student_id AND course_id = :course_id AND status = 'APPROVED'
    ");
    $enrollmentStmt->execute(['student_id' => $userId, 'course_id' => (int) $lesson['course_id']]);
    if (!$enrollmentStmt->fetchColumn()) {
        $lesson = false;
    }
} elseif ($lesson && (int) $lesson['professor_id'] !== $userId) {
    $lesson = false;
}

if (!$lesson || empty($lesson['file_path'])) {
    http_response_code(404);
    exit('Lesson file not found.');
}

$baseDirectory = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'lessons');
$file = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lesson['file_path']));
if ($baseDirectory === false || $file === false || strncmp($file, $baseDirectory . DIRECTORY_SEPARATOR, strlen($baseDirectory) + 1) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('Lesson file not found.');
}

header('Content-Type: ' . ($lesson['file_mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . (int) $lesson['file_size']);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($lesson['file_name'])) . '"');
readfile($file);
