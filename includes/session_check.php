<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/academic_year.php';
$academicPeriod = academicPeriodFromValue($_SESSION['academic_period'] ?? null);
$academicReadOnly = $academicPeriod['read_only'] ?? false;


function checkAccess(array $allowedRoles, string $loginRedirect = '../auth/login.php', bool $jsonResponse = false) {
    $denyAccess = static function () use ($jsonResponse, $loginRedirect): void {
        if ($jsonResponse) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Your session has expired or your account no longer has access. Sign in again, then retry.',
                'redirect' => $loginRedirect,
            ]);
            exit;
        }
        header('Location: ' . $loginRedirect . '?error=unauthorized');
        exit;
    };

    if (!isset($_SESSION['user_id'])) {
        $denyAccess();
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
        $denyAccess();
    }

    global $academicPeriod, $academicReadOnly;
    $activePeriodStmt = $pdo->query("
        SELECT academic_year, semester
        FROM academic_periods
        WHERE status = 'open'
        ORDER BY opened_at DESC
        LIMIT 1
    ");
    $activePeriod = $activePeriodStmt->fetch();
    if (!$activePeriod) {
        $currentPeriodValue = currentAcademicPeriodValue();
        if ($currentPeriodValue !== null) {
            [$currentAcademicYear, $currentSemester] = explode(':', $currentPeriodValue, 2);
            $currentPeriodStmt = $pdo->prepare("
                SELECT academic_year, semester
                FROM academic_periods
                WHERE academic_year = :academic_year AND semester = :semester
            ");
            $currentPeriodStmt->execute([
                'academic_year' => $currentAcademicYear,
                'semester' => $currentSemester,
            ]);
            $activePeriod = $currentPeriodStmt->fetch();
        }
    }
    if (!$activePeriod) {
        throw new RuntimeException('No active academic period is configured. Ask the Registrar to select one.');
    }

    $activePeriodValue = $activePeriod['academic_year'] . ':' . $activePeriod['semester'];
    $periodMode = $_SESSION['academic_period_mode'] ?? 'global';
    $sessionPeriodValue = $_SESSION['academic_period'] ?? '';
    $academicPeriod = $periodMode === 'historical' && is_string($sessionPeriodValue)
        ? academicPeriodFromValue($sessionPeriodValue)
        : null;
    if ($academicPeriod === null) {
        $academicPeriod = academicPeriodFromValue($activePeriodValue);
        $_SESSION['academic_period'] = $activePeriodValue;
        $_SESSION['academic_period_mode'] = 'global';
    }

    $periodStatusStmt = $pdo->prepare("
        SELECT status
        FROM academic_periods
        WHERE academic_year = :academic_year AND semester = :semester
    ");
    $periodStatusStmt->execute([
        'academic_year' => $academicPeriod['academic_year'],
        'semester' => $academicPeriod['semester'],
    ]);
    $periodStatus = $periodStatusStmt->fetchColumn();
    $academicReadOnly = !academicPeriodIsWritable($academicPeriod, $periodStatus ?: null);
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
