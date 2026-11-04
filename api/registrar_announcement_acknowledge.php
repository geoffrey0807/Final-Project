<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/registrar_announcement_gate.php';

checkAccess(['admin', 'registrar', 'dean', 'professor', 'student'], '../auth/login.php');

function respondToAnnouncementAcknowledgement(int $status, array $response): never
{
    http_response_code($status);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respondToAnnouncementAcknowledgement(405, [
        'success' => false,
        'message' => 'Submit the announcement acknowledgment to continue.',
    ]);
}

$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (
    !is_string($submittedToken)
    || !isset($_SESSION['registrar_announcement_ack_csrf'])
    || !hash_equals($_SESSION['registrar_announcement_ack_csrf'], $submittedToken)
) {
    respondToAnnouncementAcknowledgement(403, [
        'success' => false,
        'message' => 'Your session expired. Reload the page and try again.',
    ]);
}

$announcementId = filter_var(
    $_SERVER['HTTP_X_ANNOUNCEMENT_ID'] ?? null,
    FILTER_VALIDATE_INT
);
if ($announcementId === false || $announcementId < 1) {
    respondToAnnouncementAcknowledgement(400, [
        'success' => false,
        'message' => 'The announcement could not be verified. Reload the page and try again.',
    ]);
}

$userId = (int) $_SESSION['user_id'];
try {
    $pendingAnnouncements = pendingRegistrarAnnouncements($pdo, (string) $_SESSION['role'], $userId);
    $pendingIds = array_map(
        static fn(array $announcement): int => (int) $announcement['id'],
        $pendingAnnouncements
    );
    if (!in_array($announcementId, $pendingIds, true)) {
        respondToAnnouncementAcknowledgement(409, [
            'success' => false,
            'message' => 'The announcement queue has changed. Reload the page to review the remaining notices.',
            'reload' => true,
        ]);
    }

    $acknowledge = $pdo->prepare("
        INSERT IGNORE INTO registrar_announcement_acknowledgements (announcement_id, user_id)
        VALUES (:announcement_id, :user_id)
    ");
    $acknowledge->execute([
        'announcement_id' => $announcementId,
        'user_id' => $userId,
    ]);

    $remainingAnnouncements = pendingRegistrarAnnouncements($pdo, (string) $_SESSION['role'], $userId);
} catch (PDOException $exception) {
    error_log('Registrar announcement acknowledgment failed: ' . $exception->getMessage());
    respondToAnnouncementAcknowledgement(500, [
        'success' => false,
        'message' => 'The notice could not be recorded. Please try again.',
    ]);
}

$nextAnnouncement = $remainingAnnouncements[0] ?? null;
if ($nextAnnouncement !== null) {
    $imagePath = $nextAnnouncement['image_path'];
    $nextAnnouncement['image_path'] = is_string($imagePath)
        && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $imagePath)
        ? $imagePath
        : null;
    $nextAnnouncement['id'] = (int) $nextAnnouncement['id'];
}

respondToAnnouncementAcknowledgement(200, [
    'success' => true,
    'nextAnnouncement' => $nextAnnouncement,
    'remainingCount' => count($remainingAnnouncements),
]);
