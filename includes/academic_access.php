<?php
const STUDENT_COURSE_ACCESS_DENIED_MESSAGE = "Access Denied: This course or academic resource is not suitable or available for your account's registered degree track.";

function requireDeanDepartmentId(PDO $pdo, int $deanId): int
{
    $statement = $pdo->prepare("
        SELECT dean.department_id
        FROM users dean
        JOIN departments department ON department.id = dean.department_id AND department.is_active = 1
        WHERE dean.id = :id AND dean.role = 'dean'
    ");
    $statement->execute(['id' => $deanId]);
    $departmentId = $statement->fetchColumn();

    if (!$departmentId) {
        http_response_code(403);
        exit('Forbidden: no academic department is assigned to this Dean account.');
    }

    return (int) $departmentId;
}

function deanCanAccessUser(PDO $pdo, int $deanId, int $targetUserId): bool
{
    $statement = $pdo->prepare("
        SELECT 1
        FROM users dean
        JOIN users target ON target.id = :target_id
        WHERE dean.id = :dean_id
          AND dean.role = 'dean'
          AND EXISTS (
              SELECT 1 FROM departments department
              WHERE department.id = dean.department_id AND department.is_active = 1
          )
          AND target.role IN ('professor', 'student')
          AND target.department_id = dean.department_id
    ");
    $statement->execute(['dean_id' => $deanId, 'target_id' => $targetUserId]);

    return (bool) $statement->fetchColumn();
}

function assertDeanCanAccessUser(PDO $pdo, int $deanId, int $targetUserId): void
{
    if (!deanCanAccessUser($pdo, $deanId, $targetUserId)) {
        http_response_code(403);
        exit('Forbidden: this account is outside your academic department.');
    }
}

function studentCanAccessCourse(PDO $pdo, int $studentId, int $courseId): bool
{
    $statement = $pdo->prepare("
        SELECT 1
        FROM users student
        JOIN course_programs allowed ON allowed.program_id = student.program_id
        JOIN programs program ON program.id = allowed.program_id AND program.is_active = 1
        WHERE student.id = :student_id
          AND student.role = 'student'
          AND allowed.course_id = :course_id
        LIMIT 1
    ");
    $statement->execute(['student_id' => $studentId, 'course_id' => $courseId]);

    return (bool) $statement->fetchColumn();
}

function studentEnrollmentCourseScopeSql(string $courseAlias, string $enrollmentAlias): string
{
    foreach ([$courseAlias, $enrollmentAlias] as $alias) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $alias)) {
            throw new InvalidArgumentException('Invalid SQL alias for student course access.');
        }
    }

    return "EXISTS (
        SELECT 1
        FROM course_programs allowed_program
        JOIN programs allowed_track ON allowed_track.id = allowed_program.program_id AND allowed_track.is_active = 1
        JOIN users scoped_student ON scoped_student.program_id = allowed_track.id AND scoped_student.role = 'student'
        WHERE allowed_program.course_id = {$courseAlias}.id
          AND scoped_student.id = {$enrollmentAlias}.student_id
    )";
}

function denyStudentCourseAccess(bool $isAjax = false): void
{
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => STUDENT_COURSE_ACCESS_DENIED_MESSAGE]);
    } else {
        echo STUDENT_COURSE_ACCESS_DENIED_MESSAGE;
    }
    exit;
}
