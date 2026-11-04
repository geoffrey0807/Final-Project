<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db.php';
require_once '../includes/email_policy.php';

function contactRequestRespond(bool $success, string $message, ?string $ticketCode = null, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'ticket_code' => $ticketCode,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contactRequestRespond(false, 'Use the contact form to send a request.', null, 405);
}

$submittedToken = $_POST['csrf_token'] ?? null;
if (!is_string($submittedToken)
    || empty($_SESSION['contact_request_csrf'])
    || !hash_equals($_SESSION['contact_request_csrf'], $submittedToken)) {
    contactRequestRespond(false, 'Your session expired. Reload the page and try again.', null, 403);
}

$honeypot = $_POST['fax_number'] ?? $_POST['website'] ?? '';
if (!is_string($honeypot)) {
    contactRequestRespond(false, 'We could not verify your submission. Please reload the form and try again.', null, 422);
}
if (trim($honeypot) !== '') {
    contactRequestRespond(false, 'We could not verify your submission. Please reload the form and try again.', null, 422);
}

$subjects = ['enrollment', 'policy', 'technical', 'announcement', 'other', 'account_access', 'appeal'];
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
if (!is_string($subject) || !in_array($subject, $subjects, true)
    || !is_string($message) || trim($message) === ''
    || mb_strlen(trim($message), 'UTF-8') > 5000) {
    contactRequestRespond(false, 'Choose a subject and enter a message of up to 5,000 characters.', null, 422);
}
$message = trim($message);

try {
$userId = null;
$requesterRole = null;
$ticketCode = null;
$requesterName = '';
$requesterEmail = '';
$sessionUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$sessionRole = $_SESSION['role'] ?? null;
$ticketRoles = ['student', 'professor', 'dean', 'registrar'];

if ($sessionUserId && is_string($sessionRole) && in_array($sessionRole, $ticketRoles, true)) {
    $account = $pdo->prepare("
        SELECT id, first_name, last_name, email, role
        FROM users
        WHERE id = :id AND role = :role AND is_active = 1 AND archived_at IS NULL
    ");
    $account->execute(['id' => $sessionUserId, 'role' => $sessionRole]);
    $user = $account->fetch();
    if (!$user) {
        contactRequestRespond(false, 'Please sign in to your active APEX account before submitting a support ticket.', null, 401);
    }

    $userId = (int) $user['id'];
    $requesterRole = $user['role'];
    $requesterName = trim($user['first_name'] . ' ' . $user['last_name']);
    $requesterEmail = $user['email'];
    $ticketCode = 'APX-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
} else {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    if (!is_string($firstName) || !is_string($lastName) || !is_string($email)) {
        contactRequestRespond(false, 'Enter your name and a valid email address.', null, 422);
    }
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    $requesterEmail = trim($email);
    if ($firstName === '' || $lastName === ''
        || mb_strlen($firstName, 'UTF-8') > 60 || mb_strlen($lastName, 'UTF-8') > 60
        || strlen($requesterEmail) > 100 || !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
        contactRequestRespond(false, 'Enter your name and a valid email address.', null, 422);
    }
    $requesterName = $firstName . ' ' . $lastName;

    if (in_array($subject, ['account_access', 'appeal'], true)) {
        if (!isValidApexEmail($requesterEmail)) {
            contactRequestRespond(false, 'Account-access, password, and appeal requests require the APEX email registered to your account.', null, 422);
        }

        $accountLookup = $pdo->prepare("
            SELECT id, first_name, last_name, email, role
            FROM users
            WHERE LOWER(email) = LOWER(:email)
              AND role IN ('student', 'professor', 'dean', 'registrar')
            LIMIT 1
        ");
        $accountLookup->execute(['email' => $requesterEmail]);
        $matchedAccount = $accountLookup->fetch();
        $normalizeIdentity = static fn(string $value): string => mb_strtolower(trim($value), 'UTF-8');
        if (!$matchedAccount
            || $normalizeIdentity($matchedAccount['first_name']) !== $normalizeIdentity($firstName)
            || $normalizeIdentity($matchedAccount['last_name']) !== $normalizeIdentity($lastName)) {
            contactRequestRespond(false, 'The name and APEX email must match an account in the database for account-access, password, or appeal requests.', null, 422);
        }

        $userId = (int) $matchedAccount['id'];
        $requesterRole = $matchedAccount['role'];
        $requesterName = trim($matchedAccount['first_name'] . ' ' . $matchedAccount['last_name']);
        $requesterEmail = $matchedAccount['email'];
        $ticketCode = 'APX-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if ($userId === null) {
    $limit = $pdo->prepare("
        SELECT COUNT(*)
        FROM support_requests
        WHERE user_id IS NULL AND requester_email = :email
          AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $limit->execute(['email' => $requesterEmail]);
    if ((int) $limit->fetchColumn() >= 5) {
        contactRequestRespond(false, 'Too many requests were sent from this email recently. Please try again later.', null, 429);
    }
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
    'message' => $message,
]);
} catch (PDOException $exception) {
    error_log('Contact request submission failed: ' . $exception->getMessage());
    contactRequestRespond(false, 'Your request could not be saved right now. Please try again later.', null, 500);
}

contactRequestRespond(
    true,
    $ticketCode === null
        ? 'Your message was sent to the Admin team. No support ticket is needed.'
        : 'Your support ticket was sent to the Admin team.',
    $ticketCode
);
