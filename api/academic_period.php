<?php
session_start();
require_once '../includes/session_check.php';
require_once '../includes/portal_toast.php';

header('Content-Type: application/json; charset=utf-8');
checkAccess(['admin', 'registrar', 'dean', 'professor', 'student'], '../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST to update the academic period.']);
    exit;
}

if (($_POST['clear'] ?? '') === '1') {
    $currentPeriodStmt = $pdo->query("
        SELECT academic_year, semester
        FROM academic_periods
        WHERE status = 'open'
        ORDER BY opened_at DESC
        LIMIT 1
    ");
    $currentPeriod = $currentPeriodStmt->fetch();
    if (!$currentPeriod) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No academic period is currently open.']);
        exit;
    }
    $_SESSION['academic_period'] = $currentPeriod['academic_year'] . ':' . $currentPeriod['semester'];
    $_SESSION['academic_period_mode'] = 'global';
    if (($_POST['notify'] ?? '') === '1') {
        setPortalToast('Returned to the current academic period.');
    }
    echo json_encode(['success' => true]);
    exit;
}

$academicYear = $_POST['academic_year'] ?? '';
$semester = $_POST['semester'] ?? '';
$selection = is_string($academicYear) && is_string($semester)
    ? academicPeriodFromValue($academicYear . ':' . $semester)
    : null;

if ($selection === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid academic year and semester.']);
    exit;
}

$periodValue = $selection['academic_year'] . ':' . $selection['semester'];
if (($_SESSION['role'] ?? '') === 'registrar') {
    try {
        $pdo->beginTransaction();
        $periodStatusStmt = $pdo->prepare("
            SELECT status
            FROM academic_periods
            WHERE academic_year = :academic_year AND semester = :semester
            FOR UPDATE
        ");
        $periodStatusStmt->execute([
            'academic_year' => $selection['academic_year'],
            'semester' => $selection['semester'],
        ]);
        if ($periodStatusStmt->fetchColumn() === false) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'That academic period has not been opened or recorded yet.']);
            exit;
        }

        $closeOtherPeriods = $pdo->prepare("
            UPDATE academic_periods
            SET status = 'closed', closed_at = COALESCE(closed_at, NOW())
            WHERE status = 'open'
              AND (academic_year <> :academic_year OR semester <> :semester)
        ");
        $closeOtherPeriods->execute([
            'academic_year' => $selection['academic_year'],
            'semester' => $selection['semester'],
        ]);
        $activatePeriod = $pdo->prepare("
            UPDATE academic_periods
            SET status = 'open', opened_at = COALESCE(opened_at, NOW()), closed_at = NULL
            WHERE academic_year = :academic_year AND semester = :semester
        ");
        $activatePeriod->execute([
            'academic_year' => $selection['academic_year'],
            'semester' => $selection['semester'],
        ]);
        $pdo->commit();
        $_SESSION['academic_period_mode'] = 'global';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Global academic period update failed: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The active academic period could not be updated.']);
        exit;
    }
} else {
    $periodStatusStmt = $pdo->prepare("
        SELECT status
        FROM academic_periods
        WHERE academic_year = :academic_year AND semester = :semester
    ");
    $periodStatusStmt->execute([
        'academic_year' => $selection['academic_year'],
        'semester' => $selection['semester'],
    ]);
    if ($periodStatusStmt->fetchColumn() === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'That academic period has not been opened or recorded yet.']);
        exit;
    }
    $_SESSION['academic_period_mode'] = 'historical';
}

$_SESSION['academic_period'] = $periodValue;
if (($_POST['notify'] ?? '') === '1') {
    setPortalToast(($_SESSION['role'] ?? '') === 'registrar'
        ? 'Active academic period updated for the portal.'
        : 'Academic period updated.');
}
echo json_encode(['success' => true]);
