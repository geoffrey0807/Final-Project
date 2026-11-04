<?php
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/admin_account_helpers.php';
require_once '../includes/portal_toast.php';

checkAccess(['admin'], '../auth/login.php');

function supportRequestRedirect(string $message, bool $success): never
{
    setPortalToast($message, $success ? 'success' : 'error');
    header('Location: ../views/admin/admin_message_requests.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    supportRequestRedirect('Use the message request page to update requests.', false);
}
if (!isValidAdminCsrfToken($_POST['csrf_token'] ?? null)) {
    supportRequestRedirect('Your session expired. Reload the page and try again.', false);
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';
$responseInput = $_POST['admin_response'] ?? '';
$adminResponse = is_string($responseInput) ? trim($responseInput) : '';
if (!$requestId || $requestId <= 0
    || !in_array($status, ['new', 'in_progress', 'resolved'], true)
    || !is_string($responseInput)
    || mb_strlen($adminResponse, 'UTF-8') > 5000
    || ($status === 'resolved' && $adminResponse === '')) {
    supportRequestRedirect(
        $status === 'resolved' && $adminResponse === ''
            ? 'Add a message for the requester before marking the concern resolved.'
            : 'Check the request status and keep the response under 5,000 characters.',
        false
    );
}

$update = $pdo->prepare("
    UPDATE support_requests
    SET status = :status,
        admin_response = :admin_response,
        resolved_at = CASE WHEN :resolved_status = 'resolved' THEN CURRENT_TIMESTAMP ELSE NULL END,
        resolved_by = CASE WHEN :resolved_by_status = 'resolved' THEN :admin_id ELSE NULL END
    WHERE id = :id
");
try {
    $update->execute([
        'status' => $status,
        'admin_response' => $adminResponse === '' ? null : $adminResponse,
        'resolved_status' => $status,
        'resolved_by_status' => $status,
        'admin_id' => (int) $_SESSION['user_id'],
        'id' => $requestId,
    ]);

    if ($update->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM support_requests WHERE id = :id');
        $exists->execute(['id' => $requestId]);
        if (!$exists->fetchColumn()) {
            supportRequestRedirect('The request could not be found.', false);
        }
    }
} catch (PDOException $exception) {
    error_log('Admin support request update failed: ' . $exception->getMessage());
    supportRequestRedirect('The request could not be updated. Please try again.', false);
}

supportRequestRedirect(
    $status === 'resolved'
        ? 'Resolution saved. Account-linked requesters can view your reply in My Support Requests.'
        : 'Request updated.',
    true
);
