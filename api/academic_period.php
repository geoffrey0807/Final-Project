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
    unset($_SESSION['academic_period']);
    if (($_POST['notify'] ?? '') === '1') {
        setPortalToast('Academic period cleared.');
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

$_SESSION['academic_period'] = $selection['academic_year'] . ':' . $selection['semester'];
if (($_POST['notify'] ?? '') === '1') {
    setPortalToast('Academic period updated.');
}
echo json_encode(['success' => true]);
