<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';
require_once '../includes/academic_access.php';

$respond = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $respond(['results' => []], 405);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$sessionRole = $_SESSION['role'] ?? '';
if ($userId < 1 || !in_array($sessionRole, ['admin', 'registrar', 'dean', 'professor'], true)) {
    $respond(['results' => []], 403);
}

$accountStmt = $pdo->prepare('SELECT role, is_active, archived_at FROM users WHERE id = :id');
$accountStmt->execute(['id' => $userId]);
$account = $accountStmt->fetch();
if (
    !$account
    || $account['role'] !== $sessionRole
    || (int) $account['is_active'] !== 1
    || $account['archived_at'] !== null
) {
    $respond(['results' => []], 401);
}

$deanDepartmentId = null;
if ($sessionRole === 'dean') {
    if (isset($_GET['user_id'])) {
        $targetUserId = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
        if (!$targetUserId || !deanCanAccessUser($pdo, $userId, $targetUserId)) {
            $respond(['results' => [], 'message' => 'Forbidden: this account is outside your academic department.'], 403);
        }
    }

    $departmentStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = :id AND role = 'dean'");
    $departmentStmt->execute(['id' => $userId]);
    $deanDepartmentId = $departmentStmt->fetchColumn();
    if (!$deanDepartmentId) {
        $respond(['results' => []], 403);
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
if ($queryLength < 2 || $queryLength > 120) {
    $respond(['results' => []]);
}

$scopeSql = match ($sessionRole) {
    'dean' => "u.role = 'professor' AND u.department_id = :dean_department_id",
    'professor' => "u.role = 'student' AND EXISTS (
        SELECT 1
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        WHERE e.student_id = u.id AND c.professor_id = :professor_id
    )",
    default => '1 = 1',
};

$pattern = '%' . $query . '%';
$targetCourseSql = $sessionRole === 'professor'
    ? ",
       (
           SELECT e.course_id
           FROM enrollments e
           JOIN courses c ON c.id = e.course_id
           WHERE e.student_id = u.id AND c.professor_id = :target_professor_id
           ORDER BY e.course_id
           LIMIT 1
       ) AS target_course_id"
    : '';
$searchSql = "(
    u.first_name LIKE :first_name
    OR u.last_name LIKE :last_name
    OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE :full_name
    OR u.email LIKE :email
    OR u.id_number LIKE :id_number
    OR u.role LIKE :role
    OR DATE_FORMAT(u.created_at, '%Y-%m-%d') LIKE :created_at
    OR LOWER(CASE
        WHEN u.archived_at IS NOT NULL THEN 'archived'
        WHEN u.is_active = 0 THEN 'disabled'
        ELSE 'active'
    END) LIKE :status
)";

$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.id_number, u.role, u.created_at,
               CASE
                   WHEN u.archived_at IS NOT NULL THEN 'Archived'
                   WHEN u.is_active = 0 THEN 'Disabled'
                   ELSE 'Active'
               END AS account_status
               $targetCourseSql
        FROM users u
        WHERE $scopeSql AND $searchSql
        ORDER BY u.last_name ASC, u.first_name ASC
        LIMIT 40";
$stmt = $pdo->prepare($sql);
$params = [
    'first_name' => $pattern,
    'last_name' => $pattern,
    'full_name' => $pattern,
    'email' => $pattern,
    'id_number' => $pattern,
    'role' => $pattern,
    'created_at' => $pattern,
    'status' => strtolower($pattern),
];
if ($sessionRole === 'professor') {
    $params['professor_id'] = $userId;
    $params['target_professor_id'] = $userId;
}
if ($sessionRole === 'dean') {
    $params['dean_department_id'] = (int) $deanDepartmentId;
}
$stmt->execute($params);

$results = $stmt->fetchAll();
foreach ($results as &$result) {
    $resultUserId = (int) $result['id'];
    $result['target_url'] = match ($sessionRole) {
        'admin' => match ($result['account_status']) {
                'Archived' => '../views/admin/admin_archived_accounts.php',
                'Disabled' => '../views/admin/admin_accounts.php',
                default => '../views/admin/admin_users.php',
            } . '?role='
            . (in_array($result['role'], ['dean', 'professor', 'student'], true)
                ? rawurlencode((string) $result['role'])
                : 'all')
            . '#user-' . $resultUserId,
        'registrar' => '../views/registrar/registrar_users.php#user-' . $resultUserId,
        'dean' => '../views/dean/dean_professors.php#user-' . $resultUserId,
        'professor' => '../views/professor/professor_course.php?id='
            . (int) $result['target_course_id'] . '#student-' . $resultUserId,
    };
}
unset($result);

$respond(['results' => $results]);