<?php
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/portal_toast.php';

$supportRequestRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];
checkAccess($supportRequestRoles, '../auth/login.php');

$userId = (int) $_SESSION['user_id'];
$role = (string) $_SESSION['role'];
$redirectPath = $role === 'admin'
    ? '../views/admin/admin_message_requests.php'
    : '../views/support/my_requests.php';

$redirectWithMessage = static function (string $key, string $message) use ($redirectPath): never {
    setPortalToast($message, $key === 'error' ? 'error' : 'success');
    header('Location: ' . $redirectPath);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectWithMessage('error', 'Use the request inbox to delete messages.');
}

$submittedToken = $_POST['csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !isset($_SESSION['support_request_csrf'])
    || !hash_equals($_SESSION['support_request_csrf'], $submittedToken)
) {
    $redirectWithMessage('error', 'Your session expired. Reload the page and try again.');
}

$submittedIds = $_POST['request_ids'] ?? null;
if (!is_array($submittedIds) || count($submittedIds) < 1 || count($submittedIds) > 100) {
    $redirectWithMessage('error', 'Select between 1 and 100 requests to delete.');
}

$requestIds = [];
foreach ($submittedIds as $submittedId) {
    $requestId = filter_var($submittedId, FILTER_VALIDATE_INT);
    if ($requestId === false || $requestId < 1) {
        $redirectWithMessage('error', 'One or more selected requests are invalid.');
    }
    $requestIds[] = $requestId;
}
$requestIds = array_values(array_unique($requestIds));
if (count($requestIds) !== count($submittedIds)) {
    $redirectWithMessage('error', 'Select each request only once.
    ');
}

$placeholders = [];
$params = [];
foreach ($requestIds as $index => $requestId) {
    $placeholder = ':request_' . $index;
    $placeholders[] = $placeholder;
    $params[substr($placeholder, 1)] = $requestId;
}
$scopeSql = '';
if ($role !== 'admin') {
    $scopeSql = ' AND user_id = :user_id';
    $params['user_id'] = $userId;
}
$idList = implode(', ', $placeholders);

try {
    $pdo->beginTransaction();
    $ownedRequests = $pdo->prepare(
        "SELECT id FROM support_requests WHERE id IN ($idList)$scopeSql FOR UPDATE"
    );
    $ownedRequests->execute($params);
    $authorizedIds = array_map('intval', $ownedRequests->fetchAll(PDO::FETCH_COLUMN));
    if (count($authorizedIds) !== count($requestIds)) {
        $pdo->rollBack();
        $redirectWithMessage('error', 'One or more selected requests could not be found or cannot be deleted.');
    }

    $delete = $pdo->prepare(
        "DELETE FROM support_requests WHERE id IN ($idList)$scopeSql"
    );
    $delete->execute($params);
    if ($delete->rowCount() !== count($requestIds)) {
        throw new RuntimeException('The number of deleted support requests did not match the selection.');
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Support request deletion failed: ' . $exception->getMessage());
    $redirectWithMessage('error', 'The selected requests could not be deleted. Please try again.');
}

$redirectWithMessage('msg', count($requestIds) === 1
    ? 'Request permanently deleted.'
    : count($requestIds) . ' requests permanently deleted.');
