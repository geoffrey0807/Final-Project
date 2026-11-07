<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/password_policy.php';
require_once '../includes/email_policy.php';
require_once '../includes/portal_toast.php';
require_once '../includes/student_progression.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
checkAccess(['registrar'], '../auth/login.php', $isAjax);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$errorRedirect = $action === 'save_student_placements'
    ? '../views/registrar/registrar_placements.php'
    : null;
$adminId = $_SESSION['user_id'];

function respond($success, $message, $redirect = null)
{
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

function getCourseScheduleInput(?array $input = null): ?array
{
    $input ??= $_POST;
    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $submittedDays = $input['meeting_days'] ?? [];
    if (!is_array($submittedDays)) {
        return null;
    }

    foreach ($submittedDays as $day) {
        if (!in_array($day, $validDays, true)) {
            return null;
        }
    }

    $days = array_values(array_unique($submittedDays));
    $startValue = $input['start_time'] ?? '';
    $endValue = $input['end_time'] ?? '';
    $roomValue = $input['room'] ?? '';
    if (!is_string($startValue) || !is_string($endValue) || !is_string($roomValue)) {
        return null;
    }
    $startInput = trim($startValue);
    $endInput = trim($endValue);
    $startTime = $startInput === '' ? null : DateTimeImmutable::createFromFormat('!H:i', $startInput);
    $endTime = $endInput === '' ? null : DateTimeImmutable::createFromFormat('!H:i', $endInput);
    $startErrors = DateTimeImmutable::getLastErrors();
    $endErrors = DateTimeImmutable::getLastErrors();

    if (
        ($startInput === '') !== ($endInput === '')
        || ($startInput !== '' && (
            $startTime === false
            || $endTime === false
            || ($startErrors !== false && ($startErrors['warning_count'] || $startErrors['error_count']))
            || ($endErrors !== false && ($endErrors['warning_count'] || $endErrors['error_count']))
            || $startTime->format('H:i') !== $startInput
            || $endTime->format('H:i') !== $endInput
            || $endTime <= $startTime
        ))
        || (!empty($days) && $startInput === '')
    ) {
        return null;
    }

    return [
        'meeting_days' => $days ? implode(',', $days) : null,
        'start_time' => $startTime ? $startTime->format('H:i:s') : null,
        'end_time' => $endTime ? $endTime->format('H:i:s') : null,
        'room' => trim($roomValue) ?: null,
    ];
}

function getDepartmentProgramIds(PDO $pdo, int $departmentId): array
{
    $whereClause = '1 = 1';
    $params = [];
    if ($departmentId > 0) {
        $whereClause = 'p.department_id = :department_id';
        $params['department_id'] = $departmentId;
    }

    $stmt = $pdo->prepare("
        SELECT p.id
        FROM programs p
        JOIN departments d ON d.id = p.department_id
        WHERE $whereClause
          AND p.is_active = 1 AND d.is_active = 1
          AND p.code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
        ORDER BY p.id
    ");
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function findProfessorScheduleConflict(PDO $pdo, int $professorId, array $schedule, ?int $excludeCourseId = null): ?array
{
    if ($schedule['meeting_days'] === null || $schedule['start_time'] === null || $schedule['end_time'] === null) {
        return null;
    }

    $sql = "
        SELECT c.id, c.course_code, c.section_name, c.meeting_days, c.start_time, c.end_time
        FROM courses c
        WHERE c.professor_id = :professor_id
          AND c.meeting_days IS NOT NULL AND c.start_time IS NOT NULL AND c.end_time IS NOT NULL
    " . academicPeriodRecordFilter('c', 'c.created_at', 'professor_schedule');
    $params = ['professor_id' => $professorId];
    $params = array_merge($params, academicPeriodRecordParams('professor_schedule'));
    if ($excludeCourseId !== null) {
        $sql .= ' AND id <> :exclude_course_id';
        $params['exclude_course_id'] = $excludeCourseId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submittedDays = explode(',', $schedule['meeting_days']);
    foreach ($stmt->fetchAll() as $existing) {
        $existingDays = explode(',', (string) $existing['meeting_days']);
        if (!array_intersect($submittedDays, $existingDays)) {
            continue;
        }
        if (
            $schedule['start_time'] < $existing['end_time']
            && $existing['start_time'] < $schedule['end_time']
        ) {
            return $existing;
        }
    }
    return null;
}

function schedulesOverlap(array $first, array $second): bool
{
    if (
        $first['meeting_days'] === null || $first['start_time'] === null || $first['end_time'] === null
        || $second['meeting_days'] === null || $second['start_time'] === null || $second['end_time'] === null
    ) {
        return false;
    }

    return (bool) array_intersect(explode(',', $first['meeting_days']), explode(',', $second['meeting_days']))
        && $first['start_time'] < $second['end_time']
        && $second['start_time'] < $first['end_time'];
}

function getCourseStudentAssignments(
    PDO $pdo,
    string $courseCode,
    int $capacity,
    int $enrolledCount,
    ?array $cohortFilter = null,
    ?int $courseId = null
): array {
    global $academicPeriod;
    $capacity = min($capacity, 10);
    $availableSeats = $capacity - $enrolledCount;
    if ($availableSeats < 0) {
        throw new InvalidArgumentException('The course already exceeds its capacity.');
    }

    $submittedIds = $_POST['student_ids'] ?? [];
    if (!is_array($submittedIds)) {
        throw new InvalidArgumentException('Choose valid students for manual assignment.');
    }
    $studentIds = [];
    foreach ($submittedIds as $submittedId) {
        $studentId = filter_var($submittedId, FILTER_VALIDATE_INT);
        if ($studentId === false || $studentId <= 0) {
            throw new InvalidArgumentException('Choose valid students for manual assignment.');
        }
        $studentIds[] = $studentId;
    }
    $studentIds = array_values(array_unique($studentIds));

    if ($courseId !== null && $studentIds) {
        $existingPlaceholders = [];
        $existingParams = ['course_id' => $courseId];
        foreach ($studentIds as $index => $studentId) {
            $parameter = 'existing_student_' . $index;
            $existingPlaceholders[] = ':' . $parameter;
            $existingParams[$parameter] = $studentId;
        }
        $existingStmt = $pdo->prepare("
            SELECT student_id
            FROM enrollments
            WHERE course_id = :course_id
              AND student_id IN (" . implode(',', $existingPlaceholders) . ")
        ");
        $existingStmt->execute($existingParams);
        $existingIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
        $studentIds = array_values(array_diff($studentIds, $existingIds));
    }

    if (count($studentIds) > $availableSeats) {
        throw new InvalidArgumentException("Only $availableSeats student seat(s) remain in this course.");
    }
    if (!$studentIds) {
        return [];
    }

    $studentParams = [];
    $studentPlaceholders = [];
    foreach ($studentIds as $index => $studentId) {
        $parameter = 'student_' . $index;
        $studentPlaceholders[] = ':' . $parameter;
        $studentParams[$parameter] = $studentId;
    }
    $cohortSql = '';
    $cohortParams = [];
    if ($cohortFilter !== null) {
        if (!$cohortFilter['year_levels']) {
            throw new InvalidArgumentException('Choose a year level before assigning students.');
        }
        if ($cohortFilter['program_ids']) {
            $programPlaceholders = [];
            foreach ($cohortFilter['program_ids'] as $index => $programId) {
                $parameter = 'cohort_program_' . $index;
                $programPlaceholders[] = ':' . $parameter;
                $cohortParams[$parameter] = $programId;
            }
            $cohortSql .= ' AND u.program_id IN (' . implode(',', $programPlaceholders) . ')';
        }
        $yearPlaceholders = [];
        foreach ($cohortFilter['year_levels'] as $index => $yearLevel) {
            $parameter = 'cohort_year_' . $index;
            $yearPlaceholders[] = ':' . $parameter;
            $cohortParams[$parameter] = $yearLevel;
        }
        $cohortSql .= ' AND u.year_level IN (' . implode(',', $yearPlaceholders) . ')';
    }
    $eligibilityStmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        JOIN programs p ON p.id = u.program_id
        JOIN departments d ON d.id = p.department_id
        WHERE u.role = 'student' AND u.is_active = 1 AND u.archived_at IS NULL
          AND p.is_active = 1 AND d.is_active = 1
          AND p.code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
          AND u.id IN (" . implode(',', $studentPlaceholders) . ")
          AND NOT EXISTS (
              SELECT 1
              FROM enrollments e
              WHERE e.course_code = :course_code AND e.student_id = u.id
                AND e.academic_year = :academic_year AND e.semester = :semester
          )
          $cohortSql
    ");
    $eligibilityStmt->execute(array_merge(
        [
            'course_code' => $courseCode,
            'academic_year' => $academicPeriod['academic_year'],
            'semester' => $academicPeriod['semester'],
        ],
        $studentParams,
        $cohortParams
    ));
    $eligibleIds = array_map('intval', $eligibilityStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($eligibleIds) !== count($studentIds)) {
        throw new InvalidArgumentException('One or more selected students are no longer eligible or are already assigned to this course.');
    }
    return $eligibleIds;
}

function insertApprovedCourseEnrollments(PDO $pdo, int $courseId, string $courseCode, array $studentIds): void
{
    $stmt = $pdo->prepare("
        INSERT INTO enrollments
            (student_id, course_id, course_code, academic_year, semester, status, decided_at)
        SELECT :student_id, c.id, :course_code, c.academic_year, c.semester, 'APPROVED', NOW()
        FROM courses c
        WHERE c.id = :course_id
    ");
    foreach ($studentIds as $studentId) {
        $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId, 'course_code' => $courseCode]);
    }
}

function getStudentCohortFilter(PDO $pdo): array
{
    $programStmt = $pdo->query("
        SELECT id, code FROM programs
        WHERE is_active = 1
          AND code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
    ");
    $programs = $programStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $programIdInputs = $_POST['assignment_program_ids'] ?? [];
    $yearInputs = $_POST['assignment_year_levels'] ?? [];
    if (!is_array($programIdInputs) || !is_array($yearInputs)) {
        throw new InvalidArgumentException('Choose valid student assignment filters.');
    }

    $programIds = [];
    foreach ($programIdInputs as $programIdInput) {
        if (!is_string($programIdInput)) {
            throw new InvalidArgumentException('Choose a valid active degree program.');
        }
        $validatedProgramId = filter_var($programIdInput, FILTER_VALIDATE_INT);
        if ($validatedProgramId === false || !isset($programs[$validatedProgramId])) {
            throw new InvalidArgumentException('Choose an active degree program.');
        }
        $programIds[] = (int) $validatedProgramId;
    }
    $programIds = array_values(array_unique($programIds));

    $yearLevels = [];
    foreach ($yearInputs as $yearInput) {
        if (!is_string($yearInput)) {
            throw new InvalidArgumentException('Choose valid student assignment filters.');
        }
        $validatedYear = filter_var($yearInput, FILTER_VALIDATE_INT);
        if ($validatedYear === false || $validatedYear < 1 || $validatedYear > 4) {
            throw new InvalidArgumentException('Choose a year level from 1 to 4.');
        }
        $yearLevels[] = (int) $validatedYear;
    }
    $yearLevels = array_values(array_unique($yearLevels));

    return [
        'program_ids' => $programIds,
        'year_levels' => $yearLevels,
    ];
}

try {
    switch ($action) {
        case 'add_course_group':
            $code = strtoupper(trim((string) ($_POST['course_code'] ?? '')));
            $name = trim((string) ($_POST['course_name'] ?? ''));
            if ($code === '' || strlen($code) > 20 || $name === '' || strlen($name) > 100) {
                throw new InvalidArgumentException('Enter a course code and course name.');
            }

            $pdo->beginTransaction();
            $existingCoursesStmt = $pdo->prepare("
                SELECT section_name, course_name FROM courses
                WHERE course_code = :course_code AND academic_year = :academic_year
                  AND semester = :semester AND archived_at IS NULL
                FOR UPDATE
            ");
            $existingCoursesStmt->execute([
                'course_code' => $code,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $existingCourses = $existingCoursesStmt->fetchAll();
            foreach ($existingCourses as $existingCourse) {
                if ($existingCourse['course_name'] !== $name) {
                    throw new InvalidArgumentException("Course $code already exists with a different name.");
                }
                if ($existingCourse['section_name'] === 'General') {
                    throw new InvalidArgumentException("Course $code already has a draft.");
                }
            }

            $insertCourseStmt = $pdo->prepare("
                INSERT INTO courses
                    (course_code, course_name, section_name, academic_year, semester)
                VALUES
                    (:course_code, :course_name, 'General', :academic_year, :semester)
            ");
            $insertCourseStmt->execute([
                'course_code' => $code,
                'course_name' => $name,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $pdo->commit();
            respond(true, "Course $code created as a draft. Assign its schedule, professor, and students when ready.");
            break;

        case 'add_course':
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $name = trim($_POST['course_name'] ?? '');
            $sectionName = trim($_POST['section_name'] ?? '');
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $profId = !empty($_POST['professor_id']) ? (int) $_POST['professor_id'] : 0;
            $capacityInput = $_POST['max_capacity'] ?? '10';
            $capacity = filter_var($capacityInput, FILTER_VALIDATE_INT);
            $capacity = $capacity === false ? 0 : $capacity;
            $programIds = getDepartmentProgramIds($pdo, $departmentId);
            $schedule = getCourseScheduleInput();

            if (
                empty($code) || empty($name) || empty($sectionName) || strlen($sectionName) > 50
                || !in_array($sectionName, ['1', '2', '3'], true) || !$programIds
                || $profId <= 0 || $capacity < 1 || $capacity > 10 || $schedule === null
            ) {
                respond(false, 'Enter a course code and name, choose section 1–3, a department, an active professor, and a capacity from 1 to 10. Enter a valid schedule or leave schedule fields blank.');
            }

            $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = :id AND is_active = 1');
            $departmentCheck->execute(['id' => $departmentId]);
            if (!$departmentCheck->fetchColumn()) {
                respond(false, 'Choose an active department.');
            }
            $professorCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'professor' AND is_active = 1 AND archived_at IS NULL");
            $professorCheck->execute(['id' => $profId]);
            if (!$professorCheck->fetchColumn()) {
                respond(false, 'Choose an active professor.');
            }
            $conflict = findProfessorScheduleConflict($pdo, $profId, $schedule);
            if ($conflict) {
                respond(false, sprintf(
                    'Schedule conflict: this professor already teaches %s (Section %s) at %s–%s on an overlapping day.',
                    $conflict['course_code'],
                    $conflict['section_name'],
                    date('g:i A', strtotime($conflict['start_time'])),
                    date('g:i A', strtotime($conflict['end_time']))
                ));
            }

            $check = $pdo->prepare('SELECT id FROM courses WHERE course_code = :code AND section_name = :section_name AND academic_year = :academic_year AND semester = :semester AND archived_at IS NULL');
            $check->execute([
                'code' => $code,
                'section_name' => $sectionName,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($check->fetch()) {
                respond(false, "Course '$code' already has a section $sectionName.");
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO courses
                    (course_code, course_name, section_name, professor_id, max_capacity, meeting_days, start_time, end_time, room, academic_year, semester)
                VALUES (:code, :name, :section_name, :prof, :cap, :meeting_days, :start_time, :end_time, :room, :academic_year, :semester)
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
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $courseId = (int) $pdo->lastInsertId();
            $mappingStmt = $pdo->prepare('INSERT INTO course_programs (course_id, program_id) VALUES (:course_id, :program_id)');
            foreach ($programIds as $programId) {
                $mappingStmt->execute(['course_id' => $courseId, 'program_id' => $programId]);
            }
            $studentIds = getCourseStudentAssignments($pdo, $code, $capacity, 0);
            insertApprovedCourseEnrollments($pdo, $courseId, $code, $studentIds);
            $pdo->commit();
            respond(true, "Course $code, Section $sectionName created with " . count($studentIds) . ' student(s) assigned.');
            break;

        case 'update_course_schedule':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $profId = (int) ($_POST['professor_id'] ?? 0);
            $schedule = getCourseScheduleInput();
            if ($courseId <= 0 || $profId <= 0 || $schedule === null) {
                respond(false, 'Choose an active professor, valid meeting days, and a valid start and end time.');
            }

            $professorCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'professor' AND is_active = 1 AND archived_at IS NULL");
            $professorCheck->execute(['id' => $profId]);
            if (!$professorCheck->fetchColumn()) {
                respond(false, 'Choose an active professor.');
            }

            $pdo->beginTransaction();
            $courseStmt = $pdo->prepare("
                SELECT course_code, max_capacity, academic_year, semester
                FROM courses
                WHERE id = :course_id AND academic_year = :academic_year
                  AND semester = :semester AND archived_at IS NULL
                FOR UPDATE
            ");
            $courseStmt->execute([
                'course_id' => $courseId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $course = $courseStmt->fetch();
            if (!$course) {
                $pdo->rollBack();
                respond(false, 'The selected course could not be found.');
            }
            $enrollmentStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = :course_id AND status = 'APPROVED'");
            $enrollmentStmt->execute(['course_id' => $courseId]);
            $enrolledCount = (int) $enrollmentStmt->fetchColumn();

            $cohortFilter = getStudentCohortFilter($pdo);

            $conflict = findProfessorScheduleConflict($pdo, $profId, $schedule, $courseId);
            if ($conflict) {
                $pdo->rollBack();
                respond(false, sprintf(
                    'Schedule conflict: this professor already teaches %s (Section %s) at %s–%s on an overlapping day.',
                    $conflict['course_code'],
                    $conflict['section_name'],
                    date('g:i A', strtotime($conflict['start_time'])),
                    date('g:i A', strtotime($conflict['end_time']))
                ));
            }

            $studentIds = getCourseStudentAssignments(
                $pdo,
                (string) $course['course_code'],
                (int) $course['max_capacity'],
                $enrolledCount,
                $cohortFilter,
                $courseId
            );
            $scheduleStmt = $pdo->prepare("
                UPDATE courses
                SET professor_id = :professor_id, max_capacity = LEAST(max_capacity, 10),
                    meeting_days = :meeting_days, start_time = :start_time,
                    end_time = :end_time, room = :room
                WHERE id = :course_id
            ");
            $scheduleStmt->execute([
                'professor_id' => $profId,
                'meeting_days' => $schedule['meeting_days'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'room' => $schedule['room'],
                'course_id' => $courseId,
            ]);
            insertApprovedCourseEnrollments($pdo, $courseId, (string) $course['course_code'], $studentIds);
            if ($cohortFilter['program_ids']) {
                $programMappingStmt = $pdo->prepare("
                    INSERT IGNORE INTO course_programs (course_id, program_id)
                    VALUES (:course_id, :program_id)
                ");
                foreach ($cohortFilter['program_ids'] as $programId) {
                    $programMappingStmt->execute([
                        'course_id' => $courseId,
                        'program_id' => $programId,
                    ]);
                }
            } else {
                $programMappingStmt = $pdo->prepare("
                    INSERT IGNORE INTO course_programs (course_id, program_id)
                    SELECT :course_id, id
                    FROM programs
                    WHERE is_active = 1
                      AND code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
                ");
                $programMappingStmt->execute(['course_id' => $courseId]);
            }
            $pdo->commit();
            respond(true, 'Course schedule updated; ' . count($studentIds) . ' student(s) assigned.');
            break;

        case 'archive_course':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $submittedToken = $_POST['csrf_token'] ?? '';
            if (
                $_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($submittedToken)
                || empty($_SESSION['registrar_course_archive_csrf'])
                || !is_string($_SESSION['registrar_course_archive_csrf'])
                || !hash_equals($_SESSION['registrar_course_archive_csrf'], $submittedToken)
            ) {
                respond(false, 'Your session expired. Reload the page and try again.');
            }
            if ($courseId <= 0) {
                respond(false, "Invalid course selected.");
            }
            $stmt = $pdo->prepare("
                UPDATE courses
                SET archived_at = CURRENT_TIMESTAMP
                WHERE id = :id AND academic_year = :academic_year
                  AND semester = :semester AND archived_at IS NULL
            ");
            $stmt->execute([
                'id' => $courseId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($stmt->rowCount() === 0) {
                respond(false, 'Course not found or already archived in the selected academic period.');
            }
            respond(true, 'Course archived. Enrollment and course records were retained.', '../views/registrar/registrar_dashboard.php?view=courses&course_status=archived');
            break;

        case 'restore_course':
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            $submittedToken = $_POST['csrf_token'] ?? '';
            $courseRedirect = '../views/registrar/registrar_dashboard.php?view=courses&course_status=all';
            if (
                $_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($submittedToken)
                || empty($_SESSION['registrar_course_archive_csrf'])
                || !is_string($_SESSION['registrar_course_archive_csrf'])
                || !hash_equals($_SESSION['registrar_course_archive_csrf'], $submittedToken)
            ) {
                respond(false, 'Your session expired. Reload the page and try again.', $courseRedirect);
            }
            if ($courseId === false || $courseId === null || $courseId < 1) {
                respond(false, 'Invalid course selected.', $courseRedirect);
            }

            $archivedCourseStmt = $pdo->prepare("
                SELECT course_code, section_name
                FROM courses
                WHERE id = :id AND academic_year = :academic_year
                  AND semester = :semester AND archived_at IS NOT NULL
            ");
            $archivedCourseStmt->execute([
                'id' => $courseId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            $archivedCourse = $archivedCourseStmt->fetch(PDO::FETCH_ASSOC);
            if (!$archivedCourse) {
                respond(false, 'Archived course not found in the selected academic period.', $courseRedirect);
            }

            $duplicateCourseStmt = $pdo->prepare("
                SELECT id
                FROM courses
                WHERE id <> :id AND course_code = :course_code
                  AND section_name = :section_name
                  AND academic_year = :academic_year AND semester = :semester
                  AND archived_at IS NULL
                LIMIT 1
            ");
            $duplicateCourseStmt->execute([
                'id' => $courseId,
                'course_code' => $archivedCourse['course_code'],
                'section_name' => $archivedCourse['section_name'],
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($duplicateCourseStmt->fetchColumn()) {
                respond(false, 'A course with the same code and section is already active. Archive it before restoring this course.', $courseRedirect);
            }

            $restoreCourseStmt = $pdo->prepare("
                UPDATE courses
                SET archived_at = NULL
                WHERE id = :id AND academic_year = :academic_year
                  AND semester = :semester AND archived_at IS NOT NULL
            ");
            $restoreCourseStmt->execute([
                'id' => $courseId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($restoreCourseStmt->rowCount() === 0) {
                respond(false, 'Course could not be restored. Reload and try again.', $courseRedirect);
            }
            respond(true, 'Course restored.', $courseRedirect);
            break;

        case 'save_student_placements':
            $placements = $_POST['placements'] ?? null;
            $submittedToken = $_POST['placement_csrf'] ?? '';
            if (
                $_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($submittedToken)
                || empty($_SESSION['registrar_placement_csrf'])
                || !is_string($_SESSION['registrar_placement_csrf'])
                || !hash_equals($_SESSION['registrar_placement_csrf'], $submittedToken)
            ) {
                respond(false, 'Your session expired. Reload the page and try again.', '../views/registrar/registrar_placements.php');
            }
            if (!is_array($placements) || !$placements) {
                respond(false, 'Choose at least one student placement to save.', '../views/registrar/registrar_placements.php');
            }

            $pdo->beginTransaction();
            $openPeriodStmt = $pdo->prepare("
                SELECT status
                FROM academic_periods
                WHERE academic_year = :academic_year AND semester = :semester
                FOR UPDATE
            ");
            $openPeriodStmt->execute([
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if (!academicPeriodIsWritable($academicPeriod, $openPeriodStmt->fetchColumn() ?: null)) {
                throw new InvalidArgumentException('Student placements can only be changed in the open academic period.');
            }

            $normalizedPlacements = [];
            foreach ($placements as $studentIdInput => $placement) {
                $studentId = filter_var($studentIdInput, FILTER_VALIDATE_INT);
                if ($studentId === false || $studentId <= 0 || !is_array($placement)) {
                    throw new InvalidArgumentException('Choose valid students and placement details.');
                }
                if (($placement['selected'] ?? '') !== '1') {
                    continue;
                }
                $yearLevel = filter_var($placement['year_level'] ?? null, FILTER_VALIDATE_INT);
                $progressionStatus = $placement['progression_status'] ?? '';
                if ($yearLevel === false || !is_string($progressionStatus)) {
                    throw new InvalidArgumentException('Choose a valid year and progression status for each student.');
                }
                $normalizedPlacements[$studentId] = [
                    'year_level' => (int) $yearLevel,
                    'progression_status' => $progressionStatus,
                ];
            }
            if (!$normalizedPlacements) {
                throw new InvalidArgumentException('Choose at least one student placement to save.');
            }

            $studentPlaceholders = [];
            $studentParams = [];
            foreach (array_keys($normalizedPlacements) as $index => $studentId) {
                $parameter = 'student_' . $index;
                $studentPlaceholders[] = ':' . $parameter;
                $studentParams[$parameter] = $studentId;
            }
            $studentsStmt = $pdo->prepare("
                SELECT u.id, u.program_id, u.year_level, p.code AS program_code
                FROM users u
                JOIN programs p ON p.id = u.program_id AND p.is_active = 1
                WHERE u.role = 'student' AND u.is_active = 1 AND u.archived_at IS NULL
                  AND u.id IN (" . implode(',', $studentPlaceholders) . ")
                FOR UPDATE
            ");
            $studentsStmt->execute($studentParams);
            $studentsById = [];
            foreach ($studentsStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
                $studentsById[(int) $student['id']] = $student;
            }
            if (count($studentsById) !== count($normalizedPlacements)) {
                throw new InvalidArgumentException('One or more selected students are no longer active. Reload the placement list.');
            }

            $previousPlacementsStmt = $pdo->prepare("
                SELECT student_id, academic_year, semester, year_level
                FROM student_academic_placements
                WHERE student_id IN (" . implode(',', $studentPlaceholders) . ")
                  AND (
                      academic_year < :target_academic_year_before
                      OR (academic_year = :target_academic_year_equal AND semester < :target_semester)
                  )
                ORDER BY student_id, academic_year DESC, FIELD(semester, 'first', 'second') DESC
            ");
            $previousPlacementsStmt->execute(array_merge($studentParams, [
                'target_academic_year_before' => $academicPeriod['academic_year'],
                'target_academic_year_equal' => $academicPeriod['academic_year'],
                'target_semester' => $academicPeriod['semester'],
            ]));
            $previousPlacementsByStudent = [];
            foreach ($previousPlacementsStmt->fetchAll(PDO::FETCH_ASSOC) as $previousPlacement) {
                $previousPlacementsByStudent[(int) $previousPlacement['student_id']] ??= $previousPlacement;
            }

            $gradePeriodConditions = [];
            $gradePeriodParams = [];
            foreach ($previousPlacementsByStudent as $index => $previousPlacement) {
                $studentParameter = 'grade_student_' . $index;
                $yearParameter = 'grade_year_' . $index;
                $semesterParameter = 'grade_semester_' . $index;
                $gradePeriodConditions[] = "(student_id = :{$studentParameter} AND academic_year = :{$yearParameter} AND semester = :{$semesterParameter})";
                $gradePeriodParams[$studentParameter] = (int) $index;
                $gradePeriodParams[$yearParameter] = $previousPlacement['academic_year'];
                $gradePeriodParams[$semesterParameter] = $previousPlacement['semester'];
            }
            $gradesByStudent = [];
            if ($gradePeriodConditions !== []) {
                $placementGradesStmt = $pdo->prepare("
                    SELECT student_id, academic_year, semester, final_grade, grade_status, grade_released_at
                    FROM enrollments
                    WHERE status = 'APPROVED'
                      AND (" . implode(' OR ', $gradePeriodConditions) . ")
                    FOR UPDATE
                ");
                $placementGradesStmt->execute($gradePeriodParams);
                foreach ($placementGradesStmt->fetchAll(PDO::FETCH_ASSOC) as $gradeRow) {
                    $gradesByStudent[(int) $gradeRow['student_id']][] = $gradeRow;
                }
            }

            $validStatuses = ['initial', 'new', 'continued', 'promoted', 'retake'];
            $savePlacementStmt = $pdo->prepare("
                INSERT INTO student_academic_placements
                    (student_id, academic_year, semester, program_id, year_level, progression_status, assigned_by)
                VALUES
                    (:student_id, :academic_year, :semester, :program_id, :year_level, :progression_status, :assigned_by)
                ON DUPLICATE KEY UPDATE
                    program_id = VALUES(program_id), year_level = VALUES(year_level),
                    progression_status = VALUES(progression_status),
                    assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP
            ");
            $updateStudentCohortStmt = $pdo->prepare("
                UPDATE users SET year_level = :year_level
                WHERE id = :student_id AND role = 'student'
            ");
            foreach ($normalizedPlacements as $studentId => $placement) {
                $student = $studentsById[$studentId];
                $previousPlacement = $previousPlacementsByStudent[$studentId] ?? null;
                $studentCourseGrades = array_values(array_filter(
                    $gradesByStudent[$studentId] ?? [],
                    static fn(array $gradeRow): bool => $gradeRow['grade_status'] !== 'Drop'
                ));
                $studentGradesReady = $previousPlacement === null || $studentCourseGrades !== [];
                $studentGradesPassed = $studentGradesReady;
                foreach ($studentCourseGrades as $gradeRow) {
                    $gradeReleased = $gradeRow['grade_released_at'] !== null && $gradeRow['final_grade'] !== null;
                    if (!$gradeReleased) {
                        $studentGradesReady = false;
                        $studentGradesPassed = false;
                    } elseif (
                        $gradeRow['grade_status'] === 'Fail'
                        || !studentFinalGradePasses((float) $gradeRow['final_grade'])
                    ) {
                        $studentGradesPassed = false;
                    }
                }
                if (!$studentGradesReady) {
                    throw new InvalidArgumentException('Placement is locked until all approved course grades are released.');
                }
                if (
                    !isValidStudentYearLevel(
                        (string) $student['program_code'],
                        $placement['year_level']
                    ) || !in_array($placement['progression_status'], $validStatuses, true)
                ) {
                    throw new InvalidArgumentException('A year level or progression status is invalid for one of the selected students.');
                }
                if (
                    ($studentGradesPassed && $placement['progression_status'] === 'retake')
                    || (!$studentGradesPassed && $placement['progression_status'] !== 'retake')
                ) {
                    throw new InvalidArgumentException($studentGradesPassed
                        ? 'Retake is only available when at least one final grade is failing.'
                        : 'A failing final grade requires Retake at the current year.');
                }
                if (
                    !isValidStudentProgression(
                        $placement['progression_status'],
                        $placement['year_level'],
                        $previousPlacement === null ? null : (int) $previousPlacement['year_level'],
                        $previousPlacement['academic_year'] ?? null,
                        $previousPlacement['semester'] ?? null,
                        $student['year_level'] === null ? null : (int) $student['year_level'],
                        $academicPeriod['academic_year'],
                        $academicPeriod['semester']
                    )
                ) {
                    throw new InvalidArgumentException("The selected progression status does not match the student's previous recorded year level.");
                }
                $savePlacementStmt->execute([
                    'student_id' => $studentId,
                    'academic_year' => $academicPeriod['academic_year'],
                    'semester' => $academicPeriod['semester'],
                    'program_id' => (int) $student['program_id'],
                    'year_level' => $placement['year_level'],
                    'progression_status' => $placement['progression_status'],
                    'assigned_by' => (int) $_SESSION['user_id'],
                ]);
                $updateStudentCohortStmt->execute([
                    'year_level' => $placement['year_level'],
                    'student_id' => $studentId,
                ]);
            }
            $pdo->commit();
            respond(true, count($normalizedPlacements) . ' student placement(s) saved for the selected academic period.', '../views/registrar/registrar_placements.php');
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

            if (
                $_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($submittedToken)
                || empty($_SESSION['registrar_user_csrf'])
                || !is_string($_SESSION['registrar_user_csrf'])
                || !hash_equals($_SESSION['registrar_user_csrf'], $submittedToken)
            ) {
                respond(false, 'Your session expired. Please reload the page and try again.', $userPage);
            }

            if ($fn === '' || $ln === '' || $idNumber === '' || $email === '' || $password === '') {
                respond(false, 'Complete all required fields to create the account.', $userPage);
            }
            if (!in_array($role, ['dean', 'professor', 'student'], true)) {
                respond(false, 'Choose Dean, Professor, or Student as the user role.', $userPage);
            }
            if (
                ($role === 'student' && !$programId)
                || (in_array($role, ['dean', 'professor'], true) && !$departmentId)
            ) {
                respond(false, 'Choose a department for Dean or Professor accounts, or a degree program for Student accounts.', $userPage);
            }
            if (
                strlen($fn) > 50 || strlen($ln) > 50 || strlen($middleName) > 50
                || strlen($suffix) > 20 || strlen($idNumber) > 30
                || strlen($email) > 100
            ) {
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
                    (first_name, middle_name, last_name, suffix, id_number, program_id, department_id, year_level, cohort_section, email, password, role, created_by)
                VALUES
                    (:first_name, :middle_name, :last_name, :suffix, :id_number, :program_id, :department_id, :year_level, :cohort_section, :email, :password, :role, :created_by)
            ");
            $pdo->beginTransaction();
            try {
                $stmt->execute([
                    'first_name' => $fn,
                    'middle_name' => $middleName !== '' ? $middleName : null,
                    'last_name' => $ln,
                    'suffix' => $suffix !== '' ? $suffix : null,
                    'id_number' => $idNumber,
                    'program_id' => $role === 'student' ? $programId : null,
                    'department_id' => $accountDepartmentId,
                    'year_level' => $role === 'student' ? 1 : null,
                    'cohort_section' => null,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'role' => $role,
                    'created_by' => (int) $_SESSION['user_id'],
                ]);
                $createdUserId = (int) $pdo->lastInsertId();
                if ($role === 'student') {
                    $initialPlacementStmt = $pdo->prepare("
                        INSERT INTO student_academic_placements
                            (student_id, academic_year, semester, program_id, year_level, progression_status, assigned_by)
                        VALUES
                            (:student_id, :academic_year, :semester, :program_id, 1, 'new', :assigned_by)
                    ");
                    $initialPlacementStmt->execute([
                        'student_id' => $createdUserId,
                        'academic_year' => $academicPeriod['academic_year'],
                        'semester' => $academicPeriod['semester'],
                        'program_id' => $programId,
                        'assigned_by' => (int) $_SESSION['user_id'],
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                    respond(false, 'That email address or ID number is already assigned to an account.', $userPage);
                }
                error_log('Registrar user account creation failed: ' . $exception->getMessage());
                respond(false, 'The account could not be saved. Please try again.', $userPage);
            }

            respond(true, ucfirst($role) . ' account created successfully.', $userPage);
            break;

        case 'update_enrollment':
            $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
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
                      AND e.academic_year = :academic_year AND e.semester = :semester
                    LIMIT 1
                ");
                $eligibilityStmt->execute([
                    'enrollment_id' => $enrollmentId,
                    'academic_year' => $academicPeriod['academic_year'],
                    'semester' => $academicPeriod['semester'],
                ]);
                if (!$eligibilityStmt->fetchColumn()) {
                    respond(false, 'This student is not eligible for the selected course degree track.');
                }
            }

            $stmt = $pdo->prepare("UPDATE enrollments SET status = :status, decided_at = NOW() WHERE id = :id AND academic_year = :academic_year AND semester = :semester");
            $stmt->execute([
                'status' => $status,
                'id' => $enrollmentId,
                'academic_year' => $academicPeriod['academic_year'],
                'semester' => $academicPeriod['semester'],
            ]);
            if ($stmt->rowCount() === 0) {
                respond(false, 'Enrollment not found in the selected academic period.');
            }
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
    respond(false, $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : "Operation failed: " . $e->getMessage(), $errorRedirect);
}
