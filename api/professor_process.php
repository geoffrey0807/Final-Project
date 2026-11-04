<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/portal_toast.php';

checkAccess(['professor'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$profId = (int)$_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/professor/professor_dashboard.php') {
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

if ($academicReadOnly) {
    respond(false, 'This past academic period is view-only.');
}

try {
    switch ($action) {
        case 'post_announcement':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                : '../views/professor/professor_dashboard.php';

            if (empty($title) || empty($message) || $courseId <= 0) {
                respond(false, "Course, Title, and Message are required.");
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid");
            $checkCourse->execute(['cid' => $courseId, 'pid' => $profId]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only post announcements to your assigned courses.");
            }

            $stmt = $pdo->prepare("INSERT INTO announcements (course_id, author_id, headline, message) VALUES (:cid, :aid, :title, :msg)");
            $stmt->execute(['cid' => $courseId, 'aid' => $profId, 'title' => $title, 'msg' => $message]);
            respond(true, "Announcement published to your class.", $courseRedirect);
            break;

        case 'create_assignment':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dueInput = trim($_POST['due_at'] ?? '');
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                : '../views/professor/professor_dashboard.php';
            $dueAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dueInput);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if ($courseId <= 0 || $title === '' || $dueAt === false
                || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
                || $dueAt->format('Y-m-d\TH:i') !== $dueInput) {
                respond(false, "Choose a course, enter a title, and provide a valid due date and time.");
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid");
            $checkCourse->execute(['cid' => $courseId, 'pid' => $profId]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only create assignments for your assigned courses.");
            }

            $assignmentStmt = $pdo->prepare("
                INSERT INTO assignments (course_id, professor_id, title, description, assessment_type, due_at)
                VALUES (:course_id, :professor_id, :title, :description, :assessment_type, :due_at)
            ");
            $assignmentStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'title' => $title,
                'description' => $description,
                'assessment_type' => in_array($_POST['assessment_type'] ?? '', ['Quiz', 'Assessment', 'Exam'], true)
                    ? $_POST['assessment_type']
                    : 'Assessment',
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
            ]);
            $assignmentId = (int) $pdo->lastInsertId();
            $questions = $_POST['questions'] ?? [];
            $savedQuestions = 0;
            if (is_array($questions)) {
                $questionStmt = $pdo->prepare("
                    INSERT INTO assignment_questions
                        (assignment_id, question_text, question_type, choices, correct_answer, question_order)
                    VALUES
                        (:assignment_id, :question_text, :question_type, :choices, :correct_answer, :question_order)
                ");
                foreach ($questions as $index => $question) {
                    if (!is_array($question)) {
                        continue;
                    }
                    $questionText = trim((string) ($question['text'] ?? ''));
                    $questionType = (string) ($question['type'] ?? '');
                    $correctAnswer = strtolower(trim((string) ($question['answer'] ?? '')));
                    $choiceLines = preg_split('/\R/', (string) ($question['choices'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    $choices = array_values(array_filter(array_map(
                        static fn(string $choice): string => strtolower(trim($choice)),
                        $choiceLines ?: []
                    )));
                    if ($questionText === '' || $correctAnswer === '') {
                        continue;
                    }
                    if (!in_array($questionType, ['multiple_choice', 'identification'], true)) {
                        respond(false, "Choose a valid question type.", $courseRedirect);
                    }
                    if ($questionType === 'multiple_choice' && $choices && !in_array($correctAnswer, $choices, true)) {
                        respond(false, "The correct answer must match one of the multiple-choice options.", $courseRedirect);
                    }
                    $questionStmt->execute([
                        'assignment_id' => $assignmentId,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'choices' => $questionType === 'multiple_choice' ? json_encode($choices, JSON_UNESCAPED_UNICODE) : null,
                        'correct_answer' => $correctAnswer,
                        'question_order' => (int) $index + 1,
                    ]);
                    $savedQuestions++;
                }
            }
            if ($savedQuestions === 0) {
                respond(false, "Add at least one question to the assessment.", $courseRedirect);
            }
            respond(true, "Assignment published to your enrolled students.", $courseRedirect);
            break;

        case 'delete_assignment':
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $deleteStmt = $pdo->prepare("DELETE FROM assignments WHERE id = :id AND professor_id = :professor_id");
            $deleteStmt->execute(['id' => $assignmentId, 'professor_id' => $profId]);
            if ($deleteStmt->rowCount() === 0) {
                respond(false, "Assignment not found or you do not have permission to delete it.");
            }
            respond(true, "Assignment deleted.");
            break;

        case 'create_lesson':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $uploadedFile = $_FILES['lesson_file'] ?? null;
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                : '../views/professor/professor_lessons.php';

            if ($courseId <= 0 || $title === '' || $content === '') {
                respond(false, "Choose a course and provide a lesson title and content.", '../views/professor/professor_lessons.php');
            }
            if (!$uploadedFile || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
                respond(false, "Please attach a lesson file before publishing.", '../views/professor/professor_lessons.php');
            }
            if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE && $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                respond(false, "The lesson file could not be uploaded.", '../views/professor/professor_lessons.php');
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid");
            $checkCourse->execute(['cid' => $courseId, 'pid' => $profId]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only create lessons for your assigned courses.", '../views/professor/professor_lessons.php');
            }

            $fileName = null;
            $filePath = null;
            $fileMime = null;
            $fileSize = null;
            $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'];
            if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
                if ((int) $uploadedFile['size'] > 10 * 1024 * 1024) {
                    respond(false, "Lesson files must be 10 MB or smaller.", '../views/professor/professor_lessons.php');
                }
                $originalName = (string) $uploadedFile['name'];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    respond(false, "That file type is not supported for lessons.", '../views/professor/professor_lessons.php');
                }
                $fileMime = (new finfo(FILEINFO_MIME_TYPE))->file($uploadedFile['tmp_name']) ?: 'application/octet-stream';
                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'lessons';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                    respond(false, "The lesson upload folder is unavailable.", '../views/professor/professor_lessons.php');
                }
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $filePath = 'uploads/lessons/' . $storedName;
                $absoluteFilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
                if (!move_uploaded_file($uploadedFile['tmp_name'], $absoluteFilePath)) {
                    respond(false, "The lesson file could not be saved.", '../views/professor/professor_lessons.php');
                }
                $fileName = mb_substr($originalName, 0, 255);
                $fileSize = (int) $uploadedFile['size'];
            }

            $lessonStmt = $pdo->prepare("
                INSERT INTO lessons (course_id, professor_id, title, content, file_name, file_path, file_mime, file_size)
                VALUES (:course_id, :professor_id, :title, :content, :file_name, :file_path, :file_mime, :file_size)
            ");
            $lessonStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'title' => $title,
                'content' => $content,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_mime' => $fileMime,
                'file_size' => $fileSize,
            ]);
            respond(true, "Lesson published to your course.", $courseRedirect);
            break;

        case 'delete_lesson':
            $lessonId = (int) ($_POST['lesson_id'] ?? 0);
            $deleteStmt = $pdo->prepare("DELETE FROM lessons WHERE id = :id AND professor_id = :professor_id");
            $deleteStmt->execute(['id' => $lessonId, 'professor_id' => $profId]);
            if ($deleteStmt->rowCount() === 0) {
                respond(false, "Lesson not found or you do not have permission to delete it.", '../views/professor/professor_lessons.php');
            }
            respond(true, "Lesson deleted.", '../views/professor/professor_lessons.php');
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
