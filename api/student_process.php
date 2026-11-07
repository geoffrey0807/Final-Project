<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/academic_access.php';
require_once '../includes/assessment_workflow.php';
require_once '../includes/portal_toast.php';

checkAccess(['student'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$studentId = (int) $_SESSION['user_id'];

function respond($success, $message, $redirect = '../views/student/student_dashboard.php')
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

if ($academicReadOnly && in_array($action, ['apply_course', 'drop_course', 'complete_assignment', 'submit_assessment', 'forfeit_assessment'], true)) {
    respond(false, 'This past academic period is view-only.');
}

try {
    switch ($action) {
        case 'apply_course':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if ($courseId <= 0) {
                respond(false, "Invalid course selection.");
            }

            $courseStmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'APPROVED') AS approved_count
                FROM courses c 
                WHERE c.id = :cid AND c.archived_at IS NULL
            ");
            $courseStmt->execute(['cid' => $courseId]);
            $course = $courseStmt->fetch();

            if (!$course) {
                respond(false, "Course not found.");
            }
            if (
                $course['academic_year'] !== $academicPeriod['academic_year']
                || $course['semester'] !== $academicPeriod['semester']
            ) {
                respond(false, 'This course is not part of the selected academic period.');
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

            $otherSectionStmt = $pdo->prepare("
                SELECT c.section_name, e.status
                FROM enrollments e
                JOIN courses c ON c.id = e.course_id
                WHERE e.student_id = :student_id
                  AND c.course_code = :course_code
                  AND e.academic_year = :academic_year AND e.semester = :semester
                  AND c.id <> :course_id
                LIMIT 1
            ");
            $otherSectionStmt->execute([
                'student_id' => $studentId,
                'course_code' => $course['course_code'],
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
                'course_id' => $courseId,
            ]);
            $otherSection = $otherSectionStmt->fetch();
            if ($otherSection) {
                respond(false, "You already have an application or enrollment for {$course['course_code']} in Section {$otherSection['section_name']}. A course code can only be taken in one section.");
            }

            if ($course['approved_count'] >= $course['max_capacity']) {
                respond(false, "This course has reached its maximum student capacity.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO enrollments
                    (student_id, course_id, course_code, academic_year, semester, status)
                VALUES (:sid, :cid, :course_code, :academic_year, :semester, 'PENDING')
            ");
            try {
                $stmt->execute([
                    'sid' => $studentId,
                    'cid' => $courseId,
                    'course_code' => $course['course_code'],
                    'academic_year' => $course['academic_year'],
                    'semester' => $course['semester'],
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    respond(false, "You already have an application or enrollment for {$course['course_code']} in another section.");
                }
                throw $exception;
            }

            respond(true, "Application submitted for {$course['course_code']}! Awaiting professor approval.");
            break;

        case 'drop_course':
            $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
            $stmt = $pdo->prepare("
                DELETE FROM enrollments
                WHERE id = :eid AND student_id = :sid
                  AND academic_year = :academic_year AND semester = :semester
            ");
            $stmt->execute([
                'eid' => $enrollmentId,
                'sid' => $studentId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            respond(true, "Course application or enrollment has been dropped.");
            break;

        case 'complete_assignment':
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                respond(false, "Invalid assignment selected.", '../views/student/student_tasks.php');
            }

            $assignmentCourseStmt = $pdo->prepare("
                SELECT course_id FROM assignments
                WHERE id = :assignment_id AND academic_year = :academic_year AND semester = :semester
            ");
            $assignmentCourseStmt->execute([
                'assignment_id' => $assignmentId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
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

        case 'forfeit_assessment':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $assessmentId = filter_var($_POST['assessment_id'] ?? null, FILTER_VALIDATE_INT);
            $courseRedirect = is_int($courseId) && $courseId > 0
                ? '../views/student/student_course.php?id=' . $courseId . '&area=assessments'
                : '../views/student/student_dashboard.php';
            if (!is_int($courseId) || $courseId < 1 || !is_int($assessmentId) || $assessmentId < 1) {
                respond(false, 'Choose a valid assessment.', $courseRedirect);
            }

            $forfeitAssessmentStmt = $pdo->prepare("
                SELECT a.id, a.course_id, a.term, a.academic_year, a.semester
                FROM assessments a
                JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
                JOIN enrollments e ON e.course_id = c.id
                    AND e.student_id = :student_id
                    AND e.status = 'APPROVED'
                    AND e.academic_year = c.academic_year
                    AND e.semester = c.semester
                WHERE a.id = :assessment_id
                  AND a.course_id = :course_id
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
                LIMIT 1
            ");
            $forfeitAssessmentStmt->execute([
                'student_id' => $studentId,
                'assessment_id' => $assessmentId,
                'course_id' => $courseId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $forfeitAssessment = $forfeitAssessmentStmt->fetch();
            if (!$forfeitAssessment) {
                respond(false, 'This assessment is not available in your enrolled classes.', $courseRedirect);
            }
            $courseRedirect .= '&term=' . rawurlencode((string) $forfeitAssessment['term']);

            materializeExpiredAssessmentSubmissions(
                $pdo,
                (int) $forfeitAssessment['course_id'],
                (string) $forfeitAssessment['academic_year'],
                (string) $forfeitAssessment['semester']
            );

            try {
                $pdo->beginTransaction();
                $existingSubmissionStmt = $pdo->prepare("
                    SELECT status
                    FROM student_submissions
                    WHERE assessment_id = :assessment_id AND student_id = :student_id
                    FOR UPDATE
                ");
                $existingSubmissionStmt->execute([
                    'assessment_id' => $assessmentId,
                    'student_id' => $studentId,
                ]);
                $existingStatus = $existingSubmissionStmt->fetchColumn();
                if ($existingStatus !== false && $existingStatus !== 'Pending') {
                    $pdo->rollBack();
                    respond(false, 'This assessment can no longer be forfeited.', $courseRedirect);
                }

                if ($existingStatus === false) {
                    $forfeitStmt = $pdo->prepare("
                        INSERT INTO student_submissions
                            (assessment_id, student_id, score, status, answers_json, submitted_at, reopened_until)
                        VALUES
                            (:assessment_id, :student_id, 0, 'Missed', NULL, NULL, NULL)
                    ");
                } else {
                    $forfeitStmt = $pdo->prepare("
                        UPDATE student_submissions
                        SET score = 0, status = 'Missed', answers_json = NULL,
                            submitted_at = NULL, reopened_until = NULL
                        WHERE assessment_id = :assessment_id
                          AND student_id = :student_id
                          AND status = 'Pending'
                    ");
                }
                $forfeitStmt->execute([
                    'assessment_id' => $assessmentId,
                    'student_id' => $studentId,
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            respond(true, 'Assessment exited. The score is 0 until your professor reopens it.', $courseRedirect);
            break;

        case 'submit_assessment':
            $assessmentId = filter_var($_POST['assessment_id'] ?? null, FILTER_VALIDATE_INT);
            if ($assessmentId === false || $assessmentId === null || $assessmentId < 1) {
                respond(false, 'Choose a valid assessment.');
            }
            $assessmentId = (int) $assessmentId;
            $assessmentStmt = $pdo->prepare("
                SELECT a.id, a.course_id, a.term, a.total_points, a.deadline,
                       a.time_limit_minutes,
                       a.assessment_type, a.academic_year, a.semester
                FROM assessments a
                JOIN courses c ON c.id = a.course_id AND c.archived_at IS NULL
                JOIN enrollments e ON e.course_id = c.id
                    AND e.student_id = :student_id
                    AND e.status = 'APPROVED'
                    AND e.academic_year = a.academic_year
                    AND e.semester = a.semester
                WHERE a.id = :assessment_id
                  AND a.academic_year = :academic_year
                  AND a.semester = :semester
                  AND " . studentEnrollmentCourseScopeSql('c', 'e') . "
                LIMIT 1
            ");
            $assessmentStmt->execute([
                'student_id' => $studentId,
                'assessment_id' => $assessmentId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $assessment = $assessmentStmt->fetch();
            if (!$assessment) {
                respond(false, 'This assessment is not available in your enrolled classes.');
            }
            $courseRedirect = '../views/student/student_course.php?id=' . (int) $assessment['course_id']
                . '&area=assessments&term=' . rawurlencode((string) $assessment['term']);

            materializeExpiredAssessmentSubmissions(
                $pdo,
                (int) $assessment['course_id'],
                (string) $assessment['academic_year'],
                (string) $assessment['semester']
            );
            $submissionStmt = $pdo->prepare("
                SELECT status, reopened_until, assessment_started_at
                FROM student_submissions
                WHERE assessment_id = :assessment_id AND student_id = :student_id
            ");
            $submissionStmt->execute([
                'assessment_id' => $assessmentId,
                'student_id' => $studentId,
            ]);
            $existingSubmission = $submissionStmt->fetch() ?: null;
            if ($existingSubmission && $existingSubmission['status'] !== 'Pending') {
                respond(false, 'This assessment has already been submitted or missed.');
            }
            $effectiveDeadline = $existingSubmission['reopened_until'] ?? $assessment['deadline'];
            $manilaTimezone = new DateTimeZone('Asia/Manila');
            $now = new DateTimeImmutable('now', $manilaTimezone);
            if (new DateTimeImmutable((string) $effectiveDeadline, $manilaTimezone) < $now) {
                materializeExpiredAssessmentSubmissions(
                    $pdo,
                    (int) $assessment['course_id'],
                    (string) $assessment['academic_year'],
                    (string) $assessment['semester']
                );
                respond(false, 'The deadline for this assessment has passed.', $courseRedirect);
            }
            if ($assessment['time_limit_minutes'] !== null) {
                if (!$existingSubmission || empty($existingSubmission['assessment_started_at'])) {
                    respond(false, 'Open the assessment using its Take button to start your timed attempt.', $courseRedirect);
                }
                $attemptStartedAt = new DateTimeImmutable(
                    (string) $existingSubmission['assessment_started_at'],
                    $manilaTimezone
                );
                $attemptDeadline = $attemptStartedAt->modify('+' . (int) $assessment['time_limit_minutes'] . ' minutes');
                if ($now >= $attemptDeadline) {
                    $expireAttemptStmt = $pdo->prepare("
                        UPDATE student_submissions
                        SET score = 0, status = 'Missed', answers_json = NULL,
                            submitted_at = NULL, reopened_until = NULL
                        WHERE assessment_id = :assessment_id
                          AND student_id = :student_id
                          AND status = 'Pending'
                    ");
                    $expireAttemptStmt->execute([
                        'assessment_id' => $assessmentId,
                        'student_id' => $studentId,
                    ]);
                    respond(false, 'The assessment time limit expired. Your attempt was marked missed.', $courseRedirect);
                }
            }

            $questionStmt = $pdo->prepare("
                SELECT id, question_text, question_type, choices_json, correct_answer, points
                FROM assessment_questions
                WHERE assessment_id = :assessment_id
                ORDER BY question_order, id
            ");
            $questionStmt->execute(['assessment_id' => $assessmentId]);
            $questions = $questionStmt->fetchAll();
            if (!$questions) {
                respond(false, 'This assessment has no questions to submit.');
            }
            $submittedAnswers = $_POST['answers'] ?? [];
            if (!is_array($submittedAnswers)) {
                respond(false, 'Submit an answer for each question.');
            }

            $score = 0.0;
            $answerRecords = [];
            foreach ($questions as $question) {
                $questionId = (int) $question['id'];
                $answer = trim((string) ($submittedAnswers[$questionId] ?? ''));
                if ($answer === '') {
                    respond(false, 'Answer every question before submitting.');
                }
                $isCorrect = strtolower($answer) === strtolower(trim((string) $question['correct_answer']));
                if ($isCorrect) {
                    $score += (float) $question['points'];
                }
                $answerRecords[] = [
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                ];
            }

            $scoreValue = min($score, (float) $assessment['total_points']);
            $saveSubmission = $pdo->prepare("
                INSERT INTO student_submissions
                    (assessment_id, student_id, score, status, answers_json, submitted_at, reopened_until)
                VALUES
                    (:assessment_id, :student_id, :score, 'Submitted', :answers_json, NOW(), NULL)
                ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    status = VALUES(status),
                    answers_json = VALUES(answers_json),
                    submitted_at = VALUES(submitted_at),
                    reopened_until = NULL
            ");
            $saveSubmission->execute([
                'assessment_id' => $assessmentId,
                'student_id' => $studentId,
                'score' => $scoreValue,
                'answers_json' => json_encode($answerRecords, JSON_UNESCAPED_UNICODE),
            ]);
            respond(
                true,
                'Assessment submitted. Score: ' . number_format($scoreValue, 0) . '/' . number_format((float) $assessment['total_points'], 0) . '.',
                $courseRedirect
            );
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
