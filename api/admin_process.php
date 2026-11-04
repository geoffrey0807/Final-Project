<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/password_policy.php';
require_once '../includes/email_policy.php';
require_once '../includes/portal_toast.php';

checkAccess(['registrar'], '../auth/login.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$adminId = $_SESSION['user_id'];

function respond($success, $message, $redirect = null) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    setPortalToast($message, $success ? 'success' : 'error');
    if ($redirect === null) {
        $redirect = '../views/registrar/registrar_dashboard.php';
    }
    header("Location: $redirect");
    exit;
}

if ($academicReadOnly && $action !== 'add_user') {
    respond(false, 'This past academic period is view-only.');
}

function getCourseScheduleInput(): ?array {
    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $submittedDays = $_POST['meeting_days'] ?? [];
    if (!is_array($submittedDays)) {
        return null;
    }

    foreach ($submittedDays as $day) {
        if (!in_array($day, $validDays, true)) {
            return null;
        }
    }

    $days = array_values(array_unique($submittedDays));
    $startInput = trim($_POST['start_time'] ?? '');
    $endInput = trim($_POST['end_time'] ?? '');
    $startTime = $startInput === '' ? null : DateTimeImmutable::createFromFormat('!H:i', $startInput);
    $endTime = $endInput === '' ? null : DateTimeImmutable::createFromFormat('!H:i', $endInput);
    $startErrors = DateTimeImmutable::getLastErrors();
    $endErrors = DateTimeImmutable::getLastErrors();

    if (($startInput === '') !== ($endInput === '')
        || ($startInput !== '' && (
            $startTime === false
            || $endTime === false
            || ($startErrors !== false && ($startErrors['warning_count'] || $startErrors['error_count']))
            || ($endErrors !== false && ($endErrors['warning_count'] || $endErrors['error_count']))
            || $startTime->format('H:i') !== $startInput
            || $endTime->format('H:i') !== $endInput
            || $endTime <= $startTime
        ))
        || (!empty($days) && $startInput === '')) {
        return null;
    }

    return [
        'meeting_days' => $days ? implode(',', $days) : null,
        'start_time' => $startTime ? $startTime->format('H:i:s') : null,
        'end_time' => $endTime ? $endTime->format('H:i:s') : null,
        'room' => trim($_POST['room'] ?? '') ?: null,
    ];
}

try {
    switch ($action) {
        case 'add_course':
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $name = trim($_POST['course_name'] ?? '');
            $sectionName = trim($_POST['section_name'] ?? 'General');
            $profId = !empty($_POST['professor_id']) ? (int)$_POST['professor_id'] : null;
            $capacity = !empty($_POST['max_capacity']) ? (int)$_POST['max_capacity'] : 30;
            $programIdsInput = $_POST['program_ids'] ?? [];
            $programIds = is_array($programIdsInput)
                ? array_values(array_unique(array_filter(array_map('intval', $programIdsInput), static fn(int $id): bool => $id > 0)))
                : [];
            $schedule = getCourseScheduleInput();

            if (empty($code) || empty($name) || empty($sectionName) || strlen($sectionName) > 50
                || !$programIds || $schedule === null) {
                respond(false, "Course Code, Name, Section, and at least one degree program are required. Enter a valid schedule or leave schedule fields blank.");
            }

            $programPlaceholders = implode(',', array_fill(0, count($programIds), '?'));
            $programCheck = $pdo->prepare("
                SELECT COUNT(DISTINCT p.id) AS program_count,
                       COUNT(DISTINCT p.department_id) AS department_count,
                       MIN(p.department_id) AS department_id
                FROM programs p
                WHERE p.is_active = 1 AND p.id IN ($programPlaceholders)
            ");
            $programCheck->execute($programIds);
            $programSelection = $programCheck->fetch();
            if ((int) $programSelection['program_count'] !== count($programIds)) {
                respond(false, "Choose active degree programs for this course.");
            }

            if ($profId !== null) {
                $professorCheck = $pdo->prepare("SELECT department_id FROM users WHERE id = :id AND role = 'professor' AND is_active = 1 AND archived_at IS NULL");
                $professorCheck->execute(['id' => $profId]);
                $professorDepartmentId = $professorCheck->fetchColumn();
                if (!$professorDepartmentId
                    || (int) $programSelection['department_count'] !== 1
                    || (int) $programSelection['department_id'] !== (int) $professorDepartmentId) {
                    respond(false, "The selected course programs must belong to the assigned professor's teaching department.");
                }
            }

            $check = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code");
            $check->execute(['code' => $code]);
            if ($check->fetch()) {
                respond(false, "A course with code '$code' already exists.");
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO courses
                    (course_code, course_name, section_name, professor_id, max_capacity, meeting_days, start_time, end_time, room)
                VALUES (:code, :name, :section_name, :prof, :cap, :meeting_days, :start_time, :end_time, :room)
            ");
            $stmt->execute([
                'code' => $code,
                'name' => $name,
                'section_name' => $sectionName,
                'prof' => $profId,
                'cap' => $capacity,
                'meeting_days' => $schedule['meeting_days'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'room' => $schedule['room'],
            ]);
            $courseId = (int) $pdo->lastInsertId();
            $mappingStmt = $pdo->prepare('INSERT INTO course_programs (course_id, program_id) VALUES (:course_id, :program_id)');
            foreach ($programIds as $programId) {
                $mappingStmt->execute(['course_id' => $courseId, 'program_id' => $programId]);
            }
            $pdo->commit();
            respond(true, "Course $code created successfully.");
            break;

        case 'update_course_schedule':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $programIdsInput = $_POST['program_ids'] ?? [];
            $programIds = is_array($programIdsInput)
                ? array_values(array_unique(array_filter(array_map('intval', $programIdsInput), static fn(int $id): bool => $id > 0)))
                : [];
            $schedule = getCourseScheduleInput();
            if ($courseId <= 0 || $schedule === null || !$programIds) {
                respond(false, "Choose at least one degree program, valid meeting days, and a valid start and end time.");
            }

            $courseOwnerStmt = $pdo->prepare('SELECT professor_id FROM courses WHERE id = :course_id');
            $courseOwnerStmt->execute(['course_id' => $courseId]);
            $courseOwner = $courseOwnerStmt->fetch();
            if (!$courseOwner) {
                respond(false, 'The selected course could not be found.');
            }

            $programPlaceholders = implode(',', array_fill(0, count($programIds), '?'));
            $programCheck = $pdo->prepare("
                SELECT COUNT(DISTINCT p.id) AS program_count,
                       COUNT(DISTINCT p.department_id) AS department_count,
                       MIN(p.department_id) AS department_id
                FROM programs p
                WHERE p.is_active = 1 AND p.id IN ($programPlaceholders)
            ");
            $programCheck->execute($programIds);
            $programSelection = $programCheck->fetch();
            if ((int) $programSelection['program_count'] !== count($programIds)) {
                respond(false, 'Choose active degree programs for this course.');
            }

            if ($courseOwner['professor_id'] !== null) {
                $professorCheck = $pdo->prepare("SELECT department_id FROM users WHERE id = :id AND role = 'professor' AND is_active = 1 AND archived_at IS NULL");
                $professorCheck->execute(['id' => (int) $courseOwner['professor_id']]);
                $professorDepartmentId = $professorCheck->fetchColumn();
                if (!$professorDepartmentId
                    || (int) $programSelection['department_count'] !== 1
                    || (int) $programSelection['department_id'] !== (int) $professorDepartmentId) {
                    respond(false, "The selected course programs must belong to the assigned professor's teaching department.");
                }
            }

            $pdo->beginTransaction();
            $scheduleStmt = $pdo->prepare("
                UPDATE courses
                SET meeting_days = :meeting_days, start_time = :start_time,
                    end_time = :end_time, room = :room
                WHERE id = :course_id
            ");
            $scheduleStmt->execute([
                'meeting_days' => $schedule['meeting_days'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'room' => $schedule['room'],
                'course_id' => $courseId,
            ]);
            $pdo->prepare('DELETE FROM course_programs WHERE course_id = :course_id')
                ->execute(['course_id' => $courseId]);
            $mappingStmt = $pdo->prepare('INSERT INTO course_programs (course_id, program_id) VALUES (:course_id, :program_id)');
            foreach ($programIds as $programId) {
                $mappingStmt->execute(['course_id' => $courseId, 'program_id' => $programId]);
            }
            $pdo->commit();
            respond(true, "Course schedule updated.");
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
            $userPage = '../views/registrar/registrar_users.php';
            $getTextInput = static function (string $name): string {
                $value = $_POST[$name] ?? '';
                return is_string($value) ? trim($value) : '';
            };
            $fn = $getTextInput('first_name');
            $ln = $getTextInput('last_name');
            $middleName = $getTextInput('middle_name');
            $suffix = $getTextInput('suffix');
            $idNumber = strtoupper($getTextInput('id_number'));
            $programId = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);
            $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
            $email = $getTextInput('email');
            $password = $_POST['password'] ?? '';
            $password = is_string($password) ? $password : '';
            $role = $_POST['role'] ?? '';
            $submittedToken = $_POST['registrar_user_csrf'] ?? '';

            if ($_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($submittedToken)
                || empty($_SESSION['registrar_user_csrf'])
                || !is_string($_SESSION['registrar_user_csrf'])
                || !hash_equals($_SESSION['registrar_user_csrf'], $submittedToken)) {
                respond(false, 'Your session expired. Please reload the page and try again.', $userPage);
            }

            if ($fn === '' || $ln === '' || $idNumber === '' || $email === '' || $password === '') {
                respond(false, 'Complete all required fields to create the account.', $userPage);
            }
            if (!in_array($role, ['dean', 'professor', 'student'], true)) {
                respond(false, 'Choose Dean, Professor, or Student as the user role.', $userPage);
            }
            if (($role === 'student' && !$programId)
                || (in_array($role, ['dean', 'professor'], true) && !$departmentId)) {
                respond(false, 'Choose a department for Dean or Professor accounts, or a degree program for Student accounts.', $userPage);
            }
            if (strlen($fn) > 50 || strlen($ln) > 50 || strlen($middleName) > 50
                || strlen($suffix) > 20 || strlen($idNumber) > 30
                || strlen($email) > 100) {
                respond(false, 'One or more fields exceed the allowed length.', $userPage);
            }
            if (!isValidApexEmail($email)) {
                respond(false, apexEmailPolicyMessage(), $userPage);
            }
            if (!isValidPassword($password)) {
                respond(false, passwordPolicyMessage(), $userPage);
            }

            $rolePrefixes = ['dean' => 'D', 'professor' => 'EDU', 'student' => 'STU'];
            $expectedIdPattern = '/^' . $rolePrefixes[$role] . '-[0-9]{4}-[0-9]{4}$/D';
            if (!preg_match($expectedIdPattern, $idNumber)) {
                respond(false, 'Enter an ID number in the required format for the selected role ('
                    . $rolePrefixes[$role] . '-YYYY-NNNN).', $userPage);
            }

            $accountDepartmentId = null;
            if ($role === 'student') {
                $programCheck = $pdo->prepare("
                    SELECT id, department_id
                    FROM programs
                    WHERE id = :id AND is_active = 1
                      AND code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
                ");
                $programCheck->execute(['id' => $programId]);
                $selectedProgram = $programCheck->fetch();
                if (!$selectedProgram) {
                    respond(false, 'Choose an active degree program from the list.', $userPage);
                }
                $accountDepartmentId = (int) $selectedProgram['department_id'];
            } else {
                $departmentCheck = $pdo->prepare("
                    SELECT id FROM departments
                    WHERE id = :id AND is_active = 1 AND code IN ('DSEAD', 'DSCS', 'DISWT')
                ");
                $departmentCheck->execute(['id' => $departmentId]);
                if (!$departmentCheck->fetch()) {
                    respond(false, 'Choose an active department from the list.', $userPage);
                }
                $accountDepartmentId = (int) $departmentId;
            }

            $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email OR id_number = :id_number LIMIT 1');
            $duplicate->execute(['email' => $email, 'id_number' => $idNumber]);
            if ($duplicate->fetch()) {
                respond(false, 'That email address or ID number is already assigned to an account.', $userPage);
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (first_name, middle_name, last_name, suffix, id_number, program_id, department_id, email, password, role, created_by)
                VALUES
                    (:first_name, :middle_name, :last_name, :suffix, :id_number, :program_id, :department_id, :email, :password, :role, :created_by)
            ");
            try {
                $stmt->execute([
                    'first_name' => $fn,
                    'middle_name' => $middleName !== '' ? $middleName : null,
                    'last_name' => $ln,
                    'suffix' => $suffix !== '' ? $suffix : null,
                    'id_number' => $idNumber,
                    'program_id' => $role === 'student' ? $programId : null,
                    'department_id' => $accountDepartmentId,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'role' => $role,
                    'created_by' => (int) $_SESSION['user_id'],
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    respond(false, 'That email address or ID number is already assigned to an account.', $userPage);
                }
                error_log('Registrar user account creation failed: ' . $exception->getMessage());
                respond(false, 'The account could not be saved. Please try again.', $userPage);
            }

            respond(true, ucfirst($role) . ' account created successfully.', $userPage);
            break;

        case 'update_enrollment':
            $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
            $status = strtoupper(trim($_POST['status'] ?? ''));

            if (!in_array($status, ['APPROVED', 'REJECTED', 'PENDING'], true)) {
                respond(false, "Invalid enrollment decision.");
            }

            if ($status === 'APPROVED') {
                $eligibilityStmt = $pdo->prepare("
                    SELECT 1
                    FROM enrollments e
                    JOIN users student ON student.id = e.student_id AND student.role = 'student'
                    JOIN course_programs allowed ON allowed.course_id = e.course_id
                        AND allowed.program_id = student.program_id
                    JOIN programs program ON program.id = allowed.program_id AND program.is_active = 1
                    WHERE e.id = :enrollment_id
                    LIMIT 1
                ");
                $eligibilityStmt->execute(['enrollment_id' => $enrollmentId]);
                if (!$eligibilityStmt->fetchColumn()) {
                    respond(false, 'This student is not eligible for the selected course degree track.');
                }
            }

            $stmt = $pdo->prepare("UPDATE enrollments SET status = :status, decided_at = NOW() WHERE id = :id");
            $stmt->execute(['status' => $status, 'id' => $enrollmentId]);
            respond(true, "Enrollment status updated to $status.");
            break;

        default:
            respond(false, "Invalid action specified.");
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(false, "Operation failed: " . $e->getMessage());
}
