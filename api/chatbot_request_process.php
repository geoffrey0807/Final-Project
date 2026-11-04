<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db.php';
require_once '../includes/email_policy.php';

function chatbotRespond(bool $success, string $message, ?string $ticketCode = null, int $statusCode = 200, ?string $errorCode = null): never
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'ticket_code' => $ticketCode,
        'code' => $errorCode,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatbotRespond(false, 'Send your concern through the support assistant.', null, 405, 'method_not_allowed');
}

$submittedToken = $_POST['csrf_token'] ?? null;
if (!is_string($submittedToken)
    || empty($_SESSION['contact_request_csrf'])
    || !hash_equals($_SESSION['contact_request_csrf'], $submittedToken)) {
    chatbotRespond(false, 'Your session expired. Reload the page and try again.', null, 403, 'csrf_failed');
}

$honeypot = $_POST['fax_number'] ?? '';
if (!is_string($honeypot) || trim($honeypot) !== '') {
    chatbotRespond(false, 'We could not verify your submission. Please try again.', null, 422, 'spam_check_failed');
}

$subjects = ['enrollment', 'policy', 'technical', 'announcement', 'other', 'account_access', 'appeal'];
$subject = $_POST['subject'] ?? '';
$concern = $_POST['message'] ?? '';
$hasApexAccount = $_POST['apex_account'] ?? '';
$email = $_POST['email'] ?? '';
$firstName = $_POST['first_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';

if (!is_string($subject) || !in_array($subject, $subjects, true)
    || !is_string($concern) || trim($concern) === ''
    || mb_strlen(trim($concern), 'UTF-8') > 4800
    || !is_string($hasApexAccount) || !in_array($hasApexAccount, ['yes', 'no'], true)
    || !is_string($email) || strlen(trim($email)) > 100
    || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
    chatbotRespond(false, 'Check your email and concern, then try sending again.', null, 422, 'invalid_input');
}
if (in_array($subject, ['account_access', 'appeal'], true) && $hasApexAccount !== 'yes') {
    chatbotRespond(false, 'Account-access and appeal requests must be linked to a matching APEX account.', null, 422, 'account_required');
}

$email = trim($email);
$concern = trim($concern);
$userId = null;
$requesterRole = null;
$ticketCode = null;
$requesterName = '';
$requesterEmail = $email;

try {
    if ($hasApexAccount === 'yes') {
        if (!isValidApexEmail($email)) {
            chatbotRespond(false, 'Please enter the APEX email registered to your account, ending in @apex.edu.ph.', null, 422, 'apex_email_required');
        }
        if (!is_string($firstName) || trim($firstName) === '' || mb_strlen(trim($firstName), 'UTF-8') > 50
            || !is_string($lastName) || trim($lastName) === '' || mb_strlen(trim($lastName), 'UTF-8') > 50) {
            chatbotRespond(false, 'Enter the first and last name registered to your APEX account.', null, 422, 'invalid_name');
        }

        $accountLookup = $pdo->prepare("
            SELECT id, first_name, last_name, email, role
            FROM users
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
        ");
        $accountLookup->execute(['email' => $email]);
        $account = $accountLookup->fetch();
        $normalizeIdentity = static fn(string $value): string => mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value),
            'UTF-8'
        );
        if (!$account
            || $normalizeIdentity($account['first_name']) !== $normalizeIdentity($firstName)
            || $normalizeIdentity($account['last_name']) !== $normalizeIdentity($lastName)) {
            chatbotRespond(false, 'The first name, last name, and APEX email must match your account. Please check them and try again.', null, 422, 'account_identity_mismatch');
        }

        $userId = (int) $account['id'];
        $requesterRole = $account['role'];
        $requesterName = trim($account['first_name'] . ' ' . $account['last_name']);
        $requesterEmail = $account['email'];
        $ticketCode = 'APX-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    } else {
        $name = $_POST['name'] ?? '';
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 120) {
            chatbotRespond(false, 'Enter your name so the Admin team knows who sent the request.', null, 422, 'invalid_name');
        }
        $requesterName = trim($name);
    }

    $rateLimit = $pdo->prepare("
        SELECT COUNT(*)
        FROM support_requests
        WHERE requester_email = :email
          AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $rateLimit->execute(['email' => $requesterEmail]);
    if ((int) $rateLimit->fetchColumn() >= 5) {
        chatbotRespond(false, 'Too many requests were sent from this email recently. Please try again later.', null, 429, 'rate_limited');
    }

    $insert = $pdo->prepare("
        INSERT INTO support_requests
            (ticket_code, user_id, requester_name, requester_email, requester_role, subject, message)
        VALUES
            (:ticket_code, :user_id, :requester_name, :requester_email, :requester_role, :subject, :message)
    ");
    $insert->execute([
        'ticket_code' => $ticketCode,
        'user_id' => $userId,
        'requester_name' => $requesterName,
        'requester_email' => $requesterEmail,
        'requester_role' => $requesterRole,
        'subject' => $subject,
        'message' => "Submitted through the local support assistant.\n\n" . $concern,
    ]);
} catch (PDOException $exception) {
    error_log('Local support assistant submission failed: ' . $exception->getMessage());
    chatbotRespond(false, 'Your request could not be saved right now. Please try again later.', null, 500, 'save_failed');
}

chatbotRespond(true, 'Your concern was sent to the Admin team.', $ticketCode);
