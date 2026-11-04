<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/academic_year.php';
$academicPeriod = academicPeriodFromValue($_SESSION['academic_period'] ?? null);
$academicReadOnly = $academicPeriod['read_only'] ?? false;


function checkAccess(array $allowedRoles, string $loginRedirect = '../auth/login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $loginRedirect . "?error=unauthorized");
        exit;
    }

    global $pdo;
    require_once __DIR__ . '/../config/db.php';
    $accountStmt = $pdo->prepare('SELECT role, is_active, archived_at FROM users WHERE id = :id');
    $accountStmt->execute(['id' => (int) $_SESSION['user_id']]);
    $account = $accountStmt->fetch();

    if (!$account
        || (int) $account['is_active'] !== 1
        || $account['archived_at'] !== null
        || !in_array($account['role'], $allowedRoles, true)
        || $account['role'] !== ($_SESSION['role'] ?? null)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header("Location: " . $loginRedirect . "?error=unauthorized");
        exit;
    }
}


function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}


function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}


function currentUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}


function getActiveUser(PDO $pdo): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, profile_photo, created_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
