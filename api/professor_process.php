<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/academic_access.php';
require_once '../includes/assessment_workflow.php';
require_once '../includes/portal_toast.php';

checkAccess(['professor'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$profId = (int) $_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/professor/professor_dashboard.php')
{
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
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=announcements'
                : '../views/professor/professor_dashboard.php';

            if ($title === '' || $message === '' || $courseId <= 0 || mb_strlen($title) > 150 || mb_strlen($message) > 65000) {
                respond(false, 'Course, Title, and Message are required.', $courseRedirect);
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid AND academic_year = :academic_year AND semester = :semester AND archived_at IS NULL");
            $checkCourse->execute([
                'cid' => $courseId,
                'pid' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$checkCourse->fetch()) {
                respond(false, 'You can only post announcements to your assigned courses.', $courseRedirect);
            }

            $announcementDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';
            $imagePath = null;
            $uploadedFile = $_FILES['image'] ?? null;
            if (isset($_FILES['image']) && !is_array($uploadedFile)) {
                respond(false, 'Choose a valid attachment.', $courseRedirect);
            }
            if (is_array($uploadedFile) && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadError = $uploadedFile['error'] ?? null;
                $temporaryPath = $uploadedFile['tmp_name'] ?? null;
                $imageSize = (int) ($uploadedFile['size'] ?? 0);
                if (
                    !is_int($uploadError) || $uploadError !== UPLOAD_ERR_OK
                    || !is_string($temporaryPath) || !is_uploaded_file($temporaryPath)
                    || $imageSize < 1 || $imageSize > 5 * 1024 * 1024
                ) {
                    respond(false, 'Choose an image smaller than 5 MB.', $courseRedirect);
                }

                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
                $imageExtensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($imageExtensions[$mimeType]) || getimagesize($temporaryPath) === false) {
                    respond(false, 'Use a valid JPG, PNG, or WebP attachment.', $courseRedirect);
                }
                if (
                    !is_dir($announcementDirectory)
                    && !mkdir($announcementDirectory, 0750, true)
                    && !is_dir($announcementDirectory)
                ) {
                    respond(false, 'Attachment storage is unavailable.', $courseRedirect);
                }

                $imageName = bin2hex(random_bytes(16)) . '.' . $imageExtensions[$mimeType];
                $destination = $announcementDirectory . DIRECTORY_SEPARATOR . $imageName;
                if (!move_uploaded_file($temporaryPath, $destination)) {
                    respond(false, 'The attachment could not be saved.', $courseRedirect);
                }
                $imagePath = 'uploads/announcements/' . $imageName;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (course_id, author_id, headline, message, image_path, academic_year, semester) VALUES (:cid, :aid, :title, :msg, :image_path, :academic_year, :semester)");
                $stmt->execute([
                    'cid' => $courseId,
                    'aid' => $profId,
                    'title' => $title,
                    'msg' => $message,
                    'image_path' => $imagePath,
                    'academic_year' => $academicPeriod['academic_year'],
                    'semester' => $academicPeriod['semester'],
                ]);
                $announcementId = (int) $pdo->lastInsertId();
                $audienceStmt = $pdo->prepare("
                    INSERT INTO announcement_audience_roles (announcement_id, role)
                    VALUES (:announcement_id, :role)
                ");
                foreach (['professor', 'student'] as $audienceRole) {
                    $audienceStmt->execute([
                        'announcement_id' => $announcementId,
                        'role' => $audienceRole,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($imagePath !== null && is_file($announcementDirectory . DIRECTORY_SEPARATOR . basename($imagePath))) {
                    unlink($announcementDirectory . DIRECTORY_SEPARATOR . basename($imagePath));
                }
                throw $exception;
            }
            respond(true, "Announcement published to your class.", $courseRedirect);
            break;

        case 'delete_course_announcements':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $submittedIds = $_POST['announcement_ids'] ?? [];
            $courseRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=announcements'
                : '../views/professor/professor_dashboard.php';
            if (
                !is_int($courseId) || $courseId < 1
                || !is_array($submittedIds) || !$submittedIds || count($submittedIds) > 100
            ) {
                respond(false, 'Select one or more announcements to delete.', $courseRedirect);
            }

            $announcementIds = [];
            foreach ($submittedIds as $submittedId) {
                $announcementId = filter_var($submittedId, FILTER_VALIDATE_INT);
                if (!is_int($announcementId) || $announcementId < 1) {
                    respond(false, 'The selected announcements are invalid.', $courseRedirect);
                }
                $announcementIds[] = $announcementId;
            }
            $announcementIds = array_values(array_unique($announcementIds));

            $courseCheck = $pdo->prepare("
                SELECT id
                FROM courses
                WHERE id = :course_id
                  AND professor_id = :professor_id
                  AND academic_year = :academic_year
                  AND semester = :semester
                  AND archived_at IS NULL
            ");
            $courseCheck->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$courseCheck->fetchColumn()) {
                respond(false, 'You can only manage announcements in your assigned classes.', $courseRedirect);
            }

            $placeholders = implode(', ', array_fill(0, count($announcementIds), '?'));
            $deleteParams = array_merge($announcementIds, [
                $courseId,
                $profId,
                $academicPeriod['academic_year'],
                $academicPeriod['semester'],
            ]);
            try {
                $pdo->beginTransaction();
                $selectAnnouncements = $pdo->prepare(
                    "SELECT id, image_path
                     FROM announcements
                     WHERE id IN ($placeholders)
                       AND course_id = ?
                       AND author_id = ?
                       AND academic_year = ?
                       AND semester = ?
                     FOR UPDATE"
                );
                $selectAnnouncements->execute($deleteParams);
                $selectedAnnouncements = $selectAnnouncements->fetchAll();
                if (count($selectedAnnouncements) !== count($announcementIds)) {
                    $pdo->rollBack();
                    respond(false, 'One or more selected announcements are unavailable.', $courseRedirect);
                }

                $deleteAnnouncements = $pdo->prepare(
                    "DELETE FROM announcements
                     WHERE id IN ($placeholders)
                       AND course_id = ?
                       AND author_id = ?
                       AND academic_year = ?
                       AND semester = ?"
                );
                $deleteAnnouncements->execute($deleteParams);
                $deletedCount = $deleteAnnouncements->rowCount();
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            $announcementDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';
            foreach (array_column($selectedAnnouncements, 'image_path') as $imagePath) {
                if (
                    is_string($imagePath)
                    && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $imagePath)
                ) {
                    $imageFile = $announcementDirectory . DIRECTORY_SEPARATOR . basename($imagePath);
                    if (is_file($imageFile) && !unlink($imageFile)) {
                        error_log('Professor announcement attachment cleanup failed: ' . $imagePath);
                    }
                }
            }
            respond(true, $deletedCount . ' course announcement(s) deleted.', $courseRedirect);
            break;

        case 'create_assignment':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dueInput = trim($_POST['due_at'] ?? '');
            $requestedTerm = $_POST['term'] ?? 'Prelim';
            $validTerms = ['Prelim', 'Midterm', 'Final'];
            $termIsValid = is_string($requestedTerm) && in_array($requestedTerm, $validTerms, true);
            $term = $termIsValid ? $requestedTerm : 'Prelim';
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                . '&area=assessments&term=' . rawurlencode($term)
                : '../views/professor/professor_dashboard.php';
            $dueAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dueInput);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                $courseId <= 0 || $title === '' || $dueAt === false || !$termIsValid
                || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
                || $dueAt->format('Y-m-d\TH:i') !== $dueInput
            ) {
                respond(false, "Choose a course, enter a title, and provide a valid due date and time.");
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid AND academic_year = :academic_year AND semester = :semester AND archived_at IS NULL");
            $checkCourse->execute([
                'cid' => $courseId,
                'pid' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only create assignments for your assigned courses.");
            }

            $assignmentStmt = $pdo->prepare("
                INSERT INTO assignments
                    (course_id, professor_id, title, description, term, assessment_type, due_at, academic_year, semester)
                VALUES
                    (:course_id, :professor_id, :title, :description, :term, :assessment_type, :due_at, :academic_year, :semester)
            ");
            $assignmentStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'title' => $title,
                'description' => $description,
                'term' => $term,
                'assessment_type' => in_array($_POST['assessment_type'] ?? '', ['Quiz', 'Assessment', 'Exam'], true)
                    ? $_POST['assessment_type']
                    : 'Assessment',
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
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
            $deleteStmt = $pdo->prepare("DELETE FROM assignments WHERE id = :id AND professor_id = :professor_id AND academic_year = :academic_year AND semester = :semester");
            $deleteStmt->execute([
                'id' => $assignmentId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($deleteStmt->rowCount() === 0) {
                respond(false, "Assignment not found or you do not have permission to delete it.");
            }
            respond(true, "Assignment deleted.");
            break;

        case 'create_assessment':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $term = $_POST['term'] ?? '';
            $assessmentType = $_POST['assessment_type'] ?? '';
            $requestedFormat = $_POST['question_format'] ?? '';
            $questionFormat = $requestedFormat;
            $totalPoints = is_numeric($_POST['total_points'] ?? null) ? (float) $_POST['total_points'] : 0.0;
            $timeLimitInput = $_POST['time_limit_minutes'] ?? '';
            $timeLimitMinutes = $timeLimitInput === ''
                ? null
                : (is_string($timeLimitInput) || is_int($timeLimitInput)
                    ? filter_var($timeLimitInput, FILTER_VALIDATE_INT)
                    : false);
            $deadlineInput = trim((string) ($_POST['deadline'] ?? ''));
            $deadline = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $deadlineInput);
            $deadlineErrors = DateTimeImmutable::getLastErrors();
            $validTerms = ['Prelim', 'Midterm', 'Final'];
            $validTypes = ['Quiz', 'Exam'];
            $validFormats = ['Multiple Choice', 'True or False', 'Mixed'];
            $courseRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                . '&area=assessments&term=' . rawurlencode(is_string($term) ? $term : 'Prelim')
                : '../views/professor/professor_dashboard.php';

            if (
                !is_int($courseId) || $courseId < 1
                || $title === '' || mb_strlen($title) > 150
                || !is_string($description) || mb_strlen($description) > 10000
                || !is_string($term) || !in_array($term, $validTerms, true)
                || !is_string($assessmentType) || !in_array($assessmentType, $validTypes, true)
                || !in_array($questionFormat, $validFormats, true)
                || ($timeLimitMinutes !== null && ($timeLimitMinutes < 1 || $timeLimitMinutes > 1440))
                || $totalPoints <= 0 || $totalPoints > 100000
                || $deadline === false
                || ($deadlineErrors !== false && ($deadlineErrors['warning_count'] || $deadlineErrors['error_count']))
                || $deadline->format('Y-m-d\TH:i') !== $deadlineInput
            ) {
                respond(false, 'Check the assessment details and deadline, then try again.', $courseRedirect);
            }
            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :course_id AND professor_id = :professor_id AND academic_year = :academic_year AND semester = :semester AND archived_at IS NULL");
            $checkCourse->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$checkCourse->fetchColumn()) {
                respond(false, 'You can only create assessments for your assigned classes.', $courseRedirect);
            }

            $submittedQuestions = $_POST['questions'] ?? null;
            if (!is_array($submittedQuestions) || count($submittedQuestions) < 1 || count($submittedQuestions) > 60) {
                respond(false, 'Add at least one question (up to 60).', $courseRedirect);
            }
            $questionPayloads = [];
            $questionPoints = 0.0;
            foreach ($submittedQuestions as $index => $question) {
                if (!is_array($question)) {
                    respond(false, 'One of the questions is invalid.', $courseRedirect);
                }
                $questionText = trim((string) ($question['question_text'] ?? ''));
                $points = is_numeric($question['points'] ?? null) ? (float) $question['points'] : 0.0;
                if ($questionText === '' || mb_strlen($questionText) > 10000 || $points <= 0 || $points > 100000) {
                    respond(false, 'Every question needs text and a positive point value.', $courseRedirect);
                }

                if ($questionFormat === 'Multiple Choice') {
                    $questionType = 'multiple_choice';
                } elseif ($questionFormat === 'True or False') {
                    $questionType = 'true_false';
                } else {
                    $questionType = $question['question_type'] ?? '';
                    if (!in_array($questionType, ['multiple_choice', 'true_false'], true)) {
                        respond(false, 'Choose a supported question type.', $courseRedirect);
                    }
                }

                $choices = null;
                $correctAnswer = null;
                if ($questionType === 'multiple_choice') {
                    $choices = $question['choices'] ?? null;
                    if (!is_array($choices)) {
                        respond(false, 'Multiple-choice questions need four choices.', $courseRedirect);
                    }
                    $choices = array_map(static fn($choice): string => trim((string) $choice), array_values($choices));
                    if (count($choices) !== 4 || in_array('', $choices, true)) {
                        respond(false, 'Multiple-choice questions need four non-empty choices.', $courseRedirect);
                    }
                    $correctChoice = filter_var($question['correct_choice'] ?? null, FILTER_VALIDATE_INT);
                    if ($correctChoice === false || $correctChoice < 0 || $correctChoice > 3) {
                        respond(false, 'Choose the correct multiple-choice answer.', $courseRedirect);
                    }
                    $correctAnswer = $choices[$correctChoice];
                } elseif ($questionType === 'true_false') {
                    $correctAnswer = strtolower(trim((string) ($question['correct_answer'] ?? '')));
                    if (!in_array($correctAnswer, ['true', 'false'], true)) {
                        respond(false, 'Choose True or False as the correct answer.', $courseRedirect);
                    }
                } else {
                    respond(false, 'Choose a supported question format.', $courseRedirect);
                }

                $questionPayloads[] = [
                    'question_text' => $questionText,
                    'question_type' => $questionType,
                    'choices_json' => $choices === null ? null : json_encode($choices, JSON_UNESCAPED_UNICODE),
                    'correct_answer' => $correctAnswer,
                    'points' => $points,
                    'question_order' => (int) $index + 1,
                ];
                $questionPoints += $points;
            }

            if (abs($questionPoints - $totalPoints) > 0.01) {
                respond(false, 'The question points must add up to the assessment total points.', $courseRedirect);
            }

            $pdo->beginTransaction();
            try {
                $assessmentInsert = $pdo->prepare("
                    INSERT INTO assessments
                        (course_id, professor_id, title, description, term, assessment_type,
                         question_format, total_points, deadline, time_limit_minutes, academic_year, semester)
                    VALUES
                        (:course_id, :professor_id, :title, :description, :term, :assessment_type,
                         :question_format, :total_points, :deadline, :time_limit_minutes, :academic_year, :semester)
                ");
                $assessmentInsert->execute([
                    'course_id' => $courseId,
                    'professor_id' => $profId,
                    'title' => $title,
                    'description' => $description,
                    'term' => $term,
                    'assessment_type' => $assessmentType,
                    'question_format' => $questionFormat,
                    'total_points' => $totalPoints,
                    'deadline' => $deadline->format('Y-m-d H:i:s'),
                    'time_limit_minutes' => $timeLimitMinutes,
                    'academic_year' => $academicPeriod['academic_year'],
                    'semester' => $academicPeriod['semester'],
                ]);
                $assessmentId = (int) $pdo->lastInsertId();
                $questionInsert = $pdo->prepare("
                    INSERT INTO assessment_questions
                        (assessment_id, question_text, question_type, choices_json, correct_answer, points, question_order)
                    VALUES
                        (:assessment_id, :question_text, :question_type, :choices_json, :correct_answer, :points, :question_order)
                ");
                foreach ($questionPayloads as $questionPayload) {
                    $questionInsert->execute(array_merge(
                        ['assessment_id' => $assessmentId],
                        $questionPayload
                    ));
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            respond(true, 'Assessment created for your class.', $courseRedirect);
            break;

        case 'update_assessment':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $assessmentId = filter_var($_POST['assessment_id'] ?? null, FILTER_VALIDATE_INT);
            $requestedTerm = $_POST['term'] ?? '';
            $validTerms = ['Prelim', 'Midterm', 'Final'];
            $termIsValid = is_string($requestedTerm) && in_array($requestedTerm, $validTerms, true);
            $term = $termIsValid ? $requestedTerm : 'Prelim';
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $timeLimitInput = $_POST['time_limit_minutes'] ?? '';
            $timeLimitMinutes = $timeLimitInput === ''
                ? null
                : (is_string($timeLimitInput) || is_int($timeLimitInput)
                    ? filter_var($timeLimitInput, FILTER_VALIDATE_INT)
                    : false);
            $editRedirect = is_int($courseId) && $courseId > 0 && is_int($assessmentId) && $assessmentId > 0
                ? '../views/professor/professor_assessment.php?course_id=' . $courseId
                . '&assessment_id=' . $assessmentId . '&mode=edit'
                : '../views/professor/professor_dashboard.php';
            $viewRedirect = is_int($courseId) && $courseId > 0 && is_int($assessmentId) && $assessmentId > 0
                ? '../views/professor/professor_assessment.php?course_id=' . $courseId
                . '&assessment_id=' . $assessmentId . '&mode=view'
                : '../views/professor/professor_dashboard.php';

            if (
                !is_int($courseId) || $courseId < 1
                || !is_int($assessmentId) || $assessmentId < 1
                || !$termIsValid
                || $title === '' || mb_strlen($title) > 150
                || mb_strlen($description) > 10000
                || ($timeLimitMinutes !== null && ($timeLimitMinutes < 1 || $timeLimitMinutes > 1440))
            ) {
                respond(false, 'Check the assessment title and description, then try again.', $editRedirect);
            }

            $editableAssessmentStmt = $pdo->prepare("
                SELECT a.id, a.deadline
                FROM assessments a
                JOIN courses c ON c.id = a.course_id
                WHERE a.id = :assessment_id
                  AND a.course_id = :course_id
                  AND a.term = :term
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND c.academic_year = :course_academic_year
                  AND c.semester = :course_semester
            ");
            $editableAssessmentStmt->execute([
                'assessment_id' => $assessmentId,
                'course_id' => $courseId,
                'term' => $term,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
                'professor_id' => $profId,
                'course_academic_year' => $academicPeriod['academic_year'],
                'course_semester' => $academicPeriod['semester'],
            ]);
            $editableAssessment = $editableAssessmentStmt->fetch();
            if (!$editableAssessment) {
                respond(false, 'Assessment not found in your assigned classes.', $editRedirect);
            }
            if (new DateTimeImmutable((string) $editableAssessment['deadline'], new DateTimeZone('Asia/Manila')) > new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'))) {
                respond(false, 'Assessment questions can only be edited after the deadline.', $editRedirect);
            }

            $existingQuestionsStmt = $pdo->prepare("
                SELECT id, question_type
                FROM assessment_questions
                WHERE assessment_id = :assessment_id
                ORDER BY question_order, id
            ");
            $existingQuestionsStmt->execute(['assessment_id' => $assessmentId]);
            $existingQuestions = [];
            foreach ($existingQuestionsStmt->fetchAll() as $existingQuestion) {
                $existingQuestions[(int) $existingQuestion['id']] = $existingQuestion['question_type'];
            }

            $submittedQuestions = $_POST['questions'] ?? null;
            if (
                !is_array($submittedQuestions) || count($submittedQuestions) < 1
                || count($submittedQuestions) !== count($existingQuestions)
            ) {
                respond(false, 'The question list changed. Reload the editor and try again.', $editRedirect);
            }

            $questionPayloads = [];
            $submittedQuestionIds = [];
            $totalPoints = 0.0;
            foreach ($submittedQuestions as $index => $question) {
                if (!is_array($question)) {
                    respond(false, 'One of the questions is invalid.', $editRedirect);
                }
                $questionId = filter_var($question['id'] ?? null, FILTER_VALIDATE_INT);
                $questionText = is_string($question['question_text'] ?? null)
                    ? trim($question['question_text'])
                    : '';
                $points = is_numeric($question['points'] ?? null) ? (float) $question['points'] : 0.0;
                if (
                    !is_int($questionId) || !isset($existingQuestions[$questionId])
                    || isset($submittedQuestionIds[$questionId])
                    || $questionText === '' || mb_strlen($questionText) > 10000
                    || $points <= 0 || $points > 100000
                ) {
                    respond(false, 'Every question needs valid text and a positive point value.', $editRedirect);
                }

                $questionType = $existingQuestions[$questionId];
                $choices = null;
                $correctAnswer = null;
                if ($questionType === 'multiple_choice') {
                    $submittedChoices = $question['choices'] ?? null;
                    if (!is_array($submittedChoices)) {
                        respond(false, 'Multiple-choice questions need four choices.', $editRedirect);
                    }
                    $choices = array_map(
                        static fn($choice): string => is_string($choice) ? trim($choice) : '',
                        array_values($submittedChoices)
                    );
                    $correctChoice = filter_var($question['correct_choice'] ?? null, FILTER_VALIDATE_INT);
                    if (
                        count($choices) !== 4 || in_array('', $choices, true)
                        || array_filter($choices, static fn(string $choice): bool => mb_strlen($choice) > 500)
                        || $correctChoice === false || $correctChoice < 0 || $correctChoice > 3
                    ) {
                        respond(false, 'Choose four valid options and select the correct answer.', $editRedirect);
                    }
                    $correctAnswer = $choices[$correctChoice];
                } elseif ($questionType === 'true_false') {
                    $correctAnswer = strtolower(trim((string) ($question['correct_answer'] ?? '')));
                    if (!in_array($correctAnswer, ['true', 'false'], true)) {
                        respond(false, 'Choose True or False as the correct answer.', $editRedirect);
                    }
                } else {
                    respond(false, 'This question type is no longer supported.', $editRedirect);
                }

                $submittedQuestionIds[$questionId] = true;
                $questionPayloads[] = [
                    'id' => $questionId,
                    'question_text' => $questionText,
                    'choices_json' => $choices === null ? null : json_encode($choices, JSON_UNESCAPED_UNICODE),
                    'correct_answer' => $correctAnswer,
                    'points' => $points,
                    'question_order' => (int) $index + 1,
                ];
                $totalPoints += $points;
            }

            if (count($submittedQuestionIds) !== count($existingQuestions) || $totalPoints > 100000) {
                respond(false, 'The question list or total points are invalid.', $editRedirect);
            }

            $pdo->beginTransaction();
            try {
                $updateAssessmentStmt = $pdo->prepare("
                    UPDATE assessments
                    SET title = :title, description = :description, total_points = :total_points,
                        time_limit_minutes = :time_limit_minutes
                    WHERE id = :assessment_id AND course_id = :course_id
                ");
                $updateAssessmentStmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'total_points' => $totalPoints,
                    'time_limit_minutes' => $timeLimitMinutes,
                    'assessment_id' => $assessmentId,
                    'course_id' => $courseId,
                ]);
                $updateQuestionStmt = $pdo->prepare("
                    UPDATE assessment_questions
                    SET question_text = :question_text,
                        choices_json = :choices_json,
                        correct_answer = :correct_answer,
                        points = :points,
                        question_order = :question_order
                    WHERE id = :question_id AND assessment_id = :assessment_id
                ");
                foreach ($questionPayloads as $questionPayload) {
                    $updateQuestionStmt->execute([
                        'question_text' => $questionPayload['question_text'],
                        'choices_json' => $questionPayload['choices_json'],
                        'correct_answer' => $questionPayload['correct_answer'],
                        'points' => $questionPayload['points'],
                        'question_order' => $questionPayload['question_order'],
                        'question_id' => $questionPayload['id'],
                        'assessment_id' => $assessmentId,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            respond(true, 'Assessment changes saved.', $viewRedirect);
            break;

        case 'reopen_student_submission':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $assessmentId = filter_var($_POST['assessment_id'] ?? null, FILTER_VALIDATE_INT);
            $requestedTerm = $_POST['term'] ?? '';
            $validTerms = ['Prelim', 'Midterm', 'Final'];
            $termIsValid = is_string($requestedTerm) && in_array($requestedTerm, $validTerms, true);
            $term = $termIsValid ? $requestedTerm : 'Prelim';
            $extendedInput = trim((string) ($_POST['extended_deadline'] ?? ''));
            $extendedDeadline = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $extendedInput);
            $extendedErrors = DateTimeImmutable::getLastErrors();
            $gradeRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=assessments&term=' . rawurlencode($term)
                : '../views/professor/professor_dashboard.php';
            if (
                !is_int($courseId) || $courseId < 1
                || !is_int($studentId) || $studentId < 1
                || !is_int($assessmentId) || $assessmentId < 1
                || !$termIsValid
                || $extendedDeadline === false
                || ($extendedErrors !== false && ($extendedErrors['warning_count'] || $extendedErrors['error_count']))
                || $extendedDeadline->format('Y-m-d\TH:i') !== $extendedInput
                || $extendedDeadline <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'))
            ) {
                respond(false, 'Choose a future extended deadline.', $gradeRedirect);
            }
            $reopenStmt = $pdo->prepare("
                UPDATE student_submissions s
                JOIN assessments a ON a.id = s.assessment_id
                JOIN enrollments e ON e.course_id = a.course_id
                    AND e.student_id = s.student_id
                    AND e.status = 'APPROVED'
                    AND e.academic_year = a.academic_year
                    AND e.semester = a.semester
                SET s.score = NULL,
                    s.status = 'Pending',
                    s.answers_json = NULL,
                    s.submitted_at = NULL,
                    s.reopened_until = :extended_deadline,
                    s.assessment_started_at = NULL
                WHERE s.assessment_id = :assessment_id
                  AND s.student_id = :student_id
                  AND a.course_id = :course_id
                  AND a.professor_id = :professor_id
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND s.status IN ('Submitted', 'Missed')
            ");
            $reopenStmt->execute([
                'extended_deadline' => $extendedDeadline->format('Y-m-d H:i:s'),
                'assessment_id' => $assessmentId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($reopenStmt->rowCount() < 1) {
                respond(false, 'That student submission cannot be reopened.', $gradeRedirect);
            }
            respond(true, 'Submission reopened until ' . $extendedDeadline->format('M j, Y g:i A') . '.', $gradeRedirect);
            break;

        case 'recheck_student_grade':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $assessmentId = filter_var($_POST['assessment_id'] ?? null, FILTER_VALIDATE_INT);
            $gradeRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_student_grade.php?course_id=' . $courseId
                . '&student_id=' . (is_int($studentId) && $studentId > 0 ? $studentId : 0)
                . '&assessment_id=' . (is_int($assessmentId) && $assessmentId > 0 ? $assessmentId : 0)
                : '../views/professor/professor_dashboard.php';
            if (
                !is_int($courseId) || $courseId < 1
                || !is_int($studentId) || $studentId < 1
                || !is_int($assessmentId) || $assessmentId < 1
            ) {
                respond(false, 'Choose a valid student.', $gradeRedirect);
            }

            $eligibleStudentStmt = $pdo->prepare("
                SELECT e.id
                FROM enrollments e
                JOIN courses c ON c.id = e.course_id
                WHERE e.course_id = :course_id
                  AND e.student_id = :student_id
                  AND e.status = 'APPROVED'
                  AND e.academic_year = :academic_year
                  AND e.semester = :semester
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND c.academic_year = :course_academic_year
                  AND c.semester = :course_semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
            ");
            $eligibleStudentStmt->execute([
                'course_id' => $courseId,
                'student_id' => $studentId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
                'professor_id' => $profId,
                'course_academic_year' => $academicPeriod['academic_year'],
                'course_semester' => $academicPeriod['semester'],
            ]);
            if (!$eligibleStudentStmt->fetchColumn()) {
                respond(false, 'That student is not enrolled in your assigned class.', $gradeRedirect);
            }

            $review = $_POST['review'] ?? [];
            if (!is_array($review)) {
                respond(false, 'The submitted grade review is invalid.', $gradeRedirect);
            }

            $reviewDataStmt = $pdo->prepare("
                SELECT a.id AS assessment_id, a.total_points,
                       s.answers_json
                FROM assessments a
                JOIN student_submissions s ON s.assessment_id = a.id
                WHERE a.course_id = :course_id
                  AND a.professor_id = :professor_id
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND a.id = :assessment_id
                  AND s.student_id = :student_id
                  AND s.status = 'Submitted'
            ");
            $reviewDataStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
                'assessment_id' => $assessmentId,
                'student_id' => $studentId,
            ]);

            $questionsStmt = $pdo->prepare("
                SELECT q.id, q.assessment_id, q.question_type, q.points
                FROM assessment_questions q
                JOIN assessments a ON a.id = q.assessment_id
                WHERE a.course_id = :course_id
                  AND a.professor_id = :professor_id
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND a.id = :assessment_id
                ORDER BY q.assessment_id, q.question_order, q.id
            ");
            $questionsStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
                'assessment_id' => $assessmentId,
            ]);
            $questionsByAssessment = [];
            foreach ($questionsStmt->fetchAll() as $question) {
                $questionsByAssessment[(int) $question['assessment_id']][] = $question;
            }

            $gradeUpdates = [];
            foreach ($reviewDataStmt->fetchAll() as $submission) {
                $assessmentId = (int) $submission['assessment_id'];
                $questions = $questionsByAssessment[$assessmentId] ?? [];
                $answerRecords = json_decode((string) $submission['answers_json'], true);
                if (!$questions || !is_array($answerRecords)) {
                    continue;
                }

                $answersByQuestion = [];
                foreach ($answerRecords as $recordIndex => $answerRecord) {
                    if (is_array($answerRecord) && isset($answerRecord['question_id'])) {
                        $answersByQuestion[(int) $answerRecord['question_id']] = $recordIndex;
                    }
                }

                if (!$answerRecords) {
                    continue;
                }
                $assessmentReview = $review[$assessmentId] ?? null;
                if (!is_array($assessmentReview)) {
                    respond(false, 'Mark every submitted answer as correct or incorrect.', $gradeRedirect);
                }
                $score = 0.0;
                foreach ($questions as $question) {
                    $questionId = (int) $question['id'];
                    $recordIndex = $answersByQuestion[$questionId] ?? null;
                    $mark = $assessmentReview[$questionId] ?? null;
                    if (
                        $recordIndex === null || !isset($answerRecords[$recordIndex])
                        || !is_array($answerRecords[$recordIndex])
                        || !in_array($mark, ['correct', 'incorrect'], true)
                    ) {
                        respond(false, 'Mark every submitted answer as correct or incorrect.', $gradeRedirect);
                    }
                    $isCorrect = $mark === 'correct';
                    $answerRecords[$recordIndex]['is_correct'] = $isCorrect;
                    if ($isCorrect) {
                        $score += (float) $question['points'];
                    }
                }
                $gradeUpdates[] = [
                    'assessment_id' => $assessmentId,
                    'score' => min($score, (float) $submission['total_points']),
                    'answers_json' => json_encode($answerRecords, JSON_UNESCAPED_UNICODE),
                ];
            }

            if (!$gradeUpdates) {
                respond(false, 'There are no submitted answers to recheck.', $gradeRedirect);
            }

            $pdo->beginTransaction();
            try {
                $saveReviewStmt = $pdo->prepare("
                    UPDATE student_submissions
                    SET score = :score, answers_json = :answers_json
                    WHERE assessment_id = :assessment_id
                      AND student_id = :student_id
                      AND status = 'Submitted'
                ");
                foreach ($gradeUpdates as $gradeUpdate) {
                    $saveReviewStmt->execute([
                        'score' => $gradeUpdate['score'],
                        'answers_json' => $gradeUpdate['answers_json'],
                        'assessment_id' => $gradeUpdate['assessment_id'],
                        'student_id' => $studentId,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            respond(true, 'Student assessment grades were rechecked and saved.', $gradeRedirect);
            break;

        case 'update_final_grade':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $finalGradeInput = $_POST['final_grade'] ?? null;
            $finalGrade = is_string($finalGradeInput)
                && preg_match('/\A(?:[12](?:\.(?:00|25|50|75))?|3(?:\.00)?|5(?:\.00)?)\z/', trim($finalGradeInput))
                ? number_format((float) trim($finalGradeInput), 2, '.', '')
                : null;
            $gradeRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=grades'
                : '../views/professor/professor_dashboard.php';

            if (
                !is_int($courseId) || $courseId < 1 || !is_int($studentId) || $studentId < 1
                || $finalGrade === null || !array_key_exists($finalGrade, gradeConversionScale())
            ) {
                respond(false, 'Enter a final grade listed in the conversion scale.', $gradeRedirect);
            }

            $enrollmentStmt = $pdo->prepare("
                SELECT e.student_id
                FROM enrollments e
                JOIN courses c ON c.id = e.course_id
                WHERE e.course_id = :course_id
                  AND e.student_id = :student_id
                  AND e.status = 'APPROVED'
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND e.academic_year = :academic_year
                  AND e.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
            ");
            $enrollmentStmt->execute([
                'course_id' => $courseId,
                'student_id' => $studentId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$enrollmentStmt->fetchColumn()) {
                respond(false, 'Student enrollment not found for your class.', $gradeRedirect);
            }

            $saveGradeStmt = $pdo->prepare("
                UPDATE enrollments e
                JOIN courses c ON c.id = e.course_id
                SET e.final_grade = :final_grade
                WHERE e.course_id = :course_id
                  AND e.student_id = :student_id
                  AND e.status = 'APPROVED'
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND e.academic_year = :academic_year
                  AND e.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
            ");
            $saveGradeStmt->execute([
                'final_grade' => $finalGrade,
                'course_id' => $courseId,
                'student_id' => $studentId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, 'Final grade saved.', $gradeRedirect);
            break;

        case 'release_course_grades':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $gradeRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=grades'
                : '../views/professor/professor_dashboard.php';
            if (!is_int($courseId) || $courseId < 1) {
                respond(false, 'Choose a valid class.', $gradeRedirect);
            }
            $releaseStmt = $pdo->prepare("
                UPDATE enrollments e
                JOIN courses c ON c.id = e.course_id
                SET e.grade_released_at = NOW()
                WHERE e.course_id = :course_id
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND e.status = 'APPROVED'
                  AND e.academic_year = :academic_year
                  AND e.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
            ");
            $releaseStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, 'Grades released to enrolled students.', $gradeRedirect);
            break;

        case 'unrelease_course_grades':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $gradeRedirect = is_int($courseId) && $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId . '&area=grades'
                : '../views/professor/professor_dashboard.php';
            if (!is_int($courseId) || $courseId < 1) {
                respond(false, 'Choose a valid class.', $gradeRedirect);
            }
            $unreleaseStmt = $pdo->prepare("
                UPDATE enrollments e
                JOIN courses c ON c.id = e.course_id
                SET e.grade_released_at = NULL
                WHERE e.course_id = :course_id
                  AND c.professor_id = :professor_id
                  AND c.archived_at IS NULL
                  AND e.status = 'APPROVED'
                  AND e.academic_year = :academic_year
                  AND e.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
            ");
            $unreleaseStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, 'Grades unreleased from enrolled students.', $gradeRedirect);
            break;

        case 'create_lesson':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $requestedTerm = $_POST['term'] ?? 'Prelim';
            $validTerms = ['Prelim', 'Midterm', 'Final'];
            $termIsValid = is_string($requestedTerm) && in_array($requestedTerm, $validTerms, true);
            $term = $termIsValid ? $requestedTerm : 'Prelim';
            $uploadedFile = $_FILES['lesson_file'] ?? null;
            $courseRedirect = $courseId > 0
                ? '../views/professor/professor_course.php?id=' . $courseId
                . '&area=lessons&term=' . rawurlencode($term)
                : '../views/professor/professor_lessons.php';

            if (
                $courseId <= 0 || $title === '' || strlen($title) > 150
                || $content === '' || mb_strlen($content) > 10000 || !$termIsValid
            ) {
                respond(false, "Choose a valid term and provide a lesson title and description.", $courseRedirect);
            }
            if (!$uploadedFile || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
                respond(false, "Please attach a lesson file before publishing.", $courseRedirect);
            }
            if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE && $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                respond(false, "The lesson file could not be uploaded.", $courseRedirect);
            }

            $checkCourse = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND professor_id = :pid AND academic_year = :academic_year AND semester = :semester AND archived_at IS NULL");
            $checkCourse->execute([
                'cid' => $courseId,
                'pid' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!$checkCourse->fetch()) {
                respond(false, "You can only create lessons for your assigned courses.", $courseRedirect);
            }

            $fileName = null;
            $filePath = null;
            $fileMime = null;
            $fileSize = null;
            $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
                if ((int) $uploadedFile['size'] > 10 * 1024 * 1024) {
                    respond(false, "Lesson files must be 10 MB or smaller.", $courseRedirect);
                }
                $originalName = (string) $uploadedFile['name'];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    respond(false, "That file type is not supported for lessons.", $courseRedirect);
                }
                $fileMime = (new finfo(FILEINFO_MIME_TYPE))->file($uploadedFile['tmp_name']) ?: 'application/octet-stream';
                $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'lessons';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                    respond(false, "The lesson upload folder is unavailable.", $courseRedirect);
                }
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $filePath = 'uploads/lessons/' . $storedName;
                $absoluteFilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
                if (!move_uploaded_file($uploadedFile['tmp_name'], $absoluteFilePath)) {
                    respond(false, "The lesson file could not be saved.", $courseRedirect);
                }
                $fileName = mb_substr($originalName, 0, 255);
                $fileSize = (int) $uploadedFile['size'];
            }

            $lessonStmt = $pdo->prepare("
                INSERT INTO lessons
                    (course_id, professor_id, title, content, term, file_name, file_path, file_mime, file_size, academic_year, semester)
                VALUES
                    (:course_id, :professor_id, :title, :content, :term, :file_name, :file_path, :file_mime, :file_size, :academic_year, :semester)
            ");
            $lessonStmt->execute([
                'course_id' => $courseId,
                'professor_id' => $profId,
                'title' => $title,
                'content' => $content,
                'term' => $term,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_mime' => $fileMime,
                'file_size' => $fileSize,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, "Lesson published to your course.", $courseRedirect);
            break;

        case 'delete_lesson':
            $lessonId = (int) ($_POST['lesson_id'] ?? 0);
            $deleteStmt = $pdo->prepare("DELETE FROM lessons WHERE id = :id AND professor_id = :professor_id AND academic_year = :academic_year AND semester = :semester");
            $deleteStmt->execute([
                'id' => $lessonId,
                'professor_id' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($deleteStmt->rowCount() === 0) {
                respond(false, "Lesson not found or you do not have permission to delete it.", '../views/professor/professor_lessons.php');
            }
            respond(true, "Lesson deleted.", '../views/professor/professor_lessons.php');
            break;

        case 'delete_announcement':
            $annId = (int) ($_POST['announcement_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id AND author_id = :pid AND academic_year = :academic_year AND semester = :semester");
            $stmt->execute([
                'id' => $annId,
                'pid' => $profId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, "Announcement removed.");
            break;

        default:
            respond(false, "Unknown professor action.");
            break;
    }
} catch (Exception $e) {
    respond(false, "Action failed: " . $e->getMessage());
}
