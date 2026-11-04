<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/portal_toast.php';

checkAccess(['registrar'], '../auth/login.php');

$redirect = '../views/registrar/registrar_users.php';
$success = false;
$message = 'Invalid program action.';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken)
    || empty($_SESSION['registrar_user_csrf'])
    || !is_string($_SESSION['registrar_user_csrf'])
    || !hash_equals($_SESSION['registrar_user_csrf'], $submittedToken)) {
    setPortalToast('Your session expired. Reload the page and try again.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add_program') {
        $message = 'Programs are centrally managed. Contact an administrator to request catalog changes.';
    } elseif ($action === 'set_program_status') {
        $programId = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);
        $activeInput = $_POST['is_active'] ?? '';

        if (!$programId || !in_array($activeInput, ['0', '1', 0, 1], true)) {
            $message = 'Choose a valid program and status.';
        } else {
            $statement = $pdo->prepare("
                UPDATE programs
                SET is_active = :is_active
                WHERE id = :id AND code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')
            ");
            $statement->execute([
                'is_active' => (int) $activeInput,
                'id' => $programId,
            ]);
            if ($statement->rowCount() > 0) {
                $success = true;
                $message = (int) $activeInput === 1 ? 'Program activated.' : 'Program deactivated.';
            } else {
                $exists = $pdo->prepare("SELECT is_active FROM programs WHERE id = :id AND code IN ('BSSE', 'BSMAD', 'BSCS', 'GPMC', 'BSIT', 'ADFSWD')");
                $exists->execute(['id' => $programId]);
                $currentStatus = $exists->fetchColumn();
                if ($currentStatus !== false && (int) $currentStatus === (int) $activeInput) {
                    $success = true;
                    $message = 'Program status is unchanged.';
                } else {
                    $message = 'The selected program could not be found.';
                }
            }
        }
    }
} catch (PDOException $exception) {
    error_log('Registrar program catalog operation failed: ' . $exception->getMessage());
    $message = 'The program catalog could not be updated. Please try again.';
}

setPortalToast($message, $success ? 'success' : 'error');
header('Location: ' . $redirect . '#tabUsers');
exit;
