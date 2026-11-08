<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/academic_access.php';
checkAccess(['student', 'professor'], '../auth/login.php');

$lessonId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("
        SELECT l.file_name, l.file_path, l.file_mime, l.file_size, l.professor_id, l.course_id,
            l.academic_year, l.semester
    FROM lessons l
    WHERE l.id = :lesson_id
");
$stmt->execute([
    'lesson_id' => $lessonId,
]);
$lesson = $stmt->fetch();

if ($lesson && $lesson['academic_year'] !== $academicPeriod['academic_year']) {
    $lesson = false;
}
if ($lesson && ($_SESSION['role'] ?? '') === 'student') {
    if ($lesson['semester'] !== $academicPeriod['semester']) {
        $lesson = false;
    }
}

if ($lesson && ($_SESSION['role'] ?? '') === 'student') {
    if (!studentCanAccessCourse($pdo, $userId, (int) $lesson['course_id'])) {
        denyStudentCourseAccess();
    }
    $enrollmentStmt = $pdo->prepare("
        SELECT 1 FROM enrollments
        WHERE student_id = :student_id AND course_id = :course_id AND status = 'APPROVED'
          AND academic_year = :academic_year AND semester = :semester
    ");
    $enrollmentStmt->execute([
        'student_id' => $userId,
        'course_id' => (int) $lesson['course_id'],
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    if (!$enrollmentStmt->fetchColumn()) {
        $lesson = false;
    }
} elseif (
    $lesson
    && ((int) $lesson['professor_id'] !== $userId
        || $lesson['semester'] !== $academicPeriod['semester'])
) {
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

$fileMime = (string) ($lesson['file_mime'] ?: 'application/octet-stream');
$inlinePreview = ($_GET['preview'] ?? '') === '1';
$previewable = $fileMime === 'application/pdf' || str_starts_with($fileMime, 'image/');
if ($inlinePreview && !$previewable) {
    http_response_code(415);
    exit('This attachment cannot be previewed in the browser.');
}

$safeFileName = str_replace(["\r", "\n", '"'], '', basename((string) $lesson['file_name']));
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $fileMime);
header('Content-Length: ' . (int) $lesson['file_size']);
header('Content-Disposition: ' . ($inlinePreview ? 'inline' : 'attachment') . '; filename="' . $safeFileName . '"');
readfile($file);
