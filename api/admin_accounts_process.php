<?php
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/password_policy.php';
require_once '../includes/email_policy.php';
require_once '../includes/admin_account_helpers.php';
require_once '../includes/portal_toast.php';

checkAccess(['admin'], '../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setPortalToast('Submit an account action from the admin portal.', 'error');
    header('Location: ../views/admin/admin_dashboard.php');
    exit;
}

if (!isValidAdminCsrfToken($_POST['csrf_token'] ?? null)) {
    setPortalToast('Your session expired. Reload the page and try again.', 'error');
    header('Location: ../views/admin/admin_dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$returnTo = $_POST['return_to'] ?? '';
$returnPath = match ($returnTo) {
    'active' => '../views/admin/admin_users.php',
    'archived' => '../views/admin/admin_archived_accounts.php',
    default => '../views/admin/admin_accounts.php',
};
$redirectTo = $action === 'disable_user'
    ? '../views/admin/admin_users.php'
    : $returnPath;

function adminAccountRedirect(bool $success, string $message, string $destination): void
{
    setPortalToast($message, $success ? 'success' : 'error');
    header('Location: ' . $destination);
    exit;
}

function withActiveRegistrarCapacityLock(PDO $pdo, callable $operation): mixed
{
    $lockName = 'apex_active_registrar_capacity';
    $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $lock->execute(['lock_name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Unable to verify active registrar capacity. Please try again.');
    }

    try {
        return $operation();
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $release->execute(['lock_name' => $lockName]);
    }
}

try {
    if ($action === 'create_registrar') {
        $registrarDestination = '../views/admin/admin_dashboard.php';
        $readText = static function (string $field): string {
            $value = $_POST[$field] ?? '';
            return is_string($value) ? trim($value) : '';
        };
        $firstName = $readText('first_name');
        $middleName = $readText('middle_name');
        $lastName = $readText('last_name');
        $suffix = $readText('suffix');
        $idNumber = strtoupper($readText('id_number'));
        $email = $readText('email');
        $passwordInput = $_POST['password'] ?? '';
        $password = is_string($passwordInput) ? $passwordInput : '';

        if ($firstName === '' || $lastName === '' || $idNumber === '' || $email === '' || $password === '') {
            adminAccountRedirect(false, 'Complete all required fields to create the registrar account.', $registrarDestination);
        }
        if (mb_strlen($firstName, 'UTF-8') > 50 || mb_strlen($lastName, 'UTF-8') > 50
            || mb_strlen($middleName, 'UTF-8') > 50 || mb_strlen($suffix, 'UTF-8') > 20
            || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !isValidApexEmail($email)) {
            adminAccountRedirect(false, 'Enter valid name and APEX email details within the allowed lengths.', $registrarDestination);
        }
        $currentYear = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
        if (!preg_match('/^REG-' . preg_quote($currentYear, '/') . '-[0-9]{4}$/D', $idNumber)) {
            adminAccountRedirect(false, 'Enter a registrar ID in the format REG-' . $currentYear . '-####.', $registrarDestination);
        }
        if (!isValidPassword($password)) {
            adminAccountRedirect(false, passwordPolicyMessage(), $registrarDestination);
        }

        $duplicateEmail = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $duplicateEmail->execute(['email' => $email]);
        if ($duplicateEmail->fetchColumn()) {
            adminAccountRedirect(false, 'An account already uses that email address.', $registrarDestination);
        }
        $duplicateId = $pdo->prepare('SELECT id FROM users WHERE UPPER(id_number) = :id_number LIMIT 1');
        $duplicateId->execute(['id_number' => $idNumber]);
        if ($duplicateId->fetchColumn()) {
            adminAccountRedirect(false, 'That ID number is already assigned to an account.', $registrarDestination);
        }

        $creationResult = withActiveRegistrarCapacityLock($pdo, static function () use (
            $pdo,
            $duplicateEmail,
            $duplicateId,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $email,
            $password,
            $idNumber
        ): array {
            $activeCount = $pdo->query("
                SELECT COUNT(*)
                FROM users
                WHERE role = 'registrar' AND is_active = 1 AND archived_at IS NULL
            ")->fetchColumn();
            if ((int) $activeCount >= 2) {
                return [
                    'success' => false,
                    'message' => 'Only two active registrar accounts are allowed. Disable an active registrar before creating another.',
                ];
            }

            $duplicateEmail->execute(['email' => $email]);
            if ($duplicateEmail->fetchColumn()) {
                return ['success' => false, 'message' => 'An account already uses that email address.'];
            }
            $duplicateId->execute(['id_number' => $idNumber]);
            if ($duplicateId->fetchColumn()) {
                return ['success' => false, 'message' => 'That ID number is already assigned to an account.'];
            }

            $insert = $pdo->prepare("
                INSERT INTO users
                    (first_name, middle_name, last_name, suffix, email, password, role, id_number, created_by)
                VALUES
                    (:first_name, :middle_name, :last_name, :suffix, :email, :password, 'registrar', :id_number, :created_by)
            ");
            try {
                $insert->execute([
                    'first_name' => $firstName,
                    'middle_name' => $middleName !== '' ? $middleName : null,
                    'last_name' => $lastName,
                    'suffix' => $suffix !== '' ? $suffix : null,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'id_number' => $idNumber,
                    'created_by' => (int) $_SESSION['user_id'],
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $duplicateId->execute(['id_number' => $idNumber]);
                    if ($duplicateId->fetchColumn()) {
                        return ['success' => false, 'message' => 'That ID number is already assigned to an account.'];
                    }
                    $duplicateEmail->execute(['email' => $email]);
                    if ($duplicateEmail->fetchColumn()) {
                        return ['success' => false, 'message' => 'An account already uses that email address.'];
                    }
                }
                throw $exception;
            }

            return ['success' => true, 'message' => 'Registrar account created successfully.'];
        });

        adminAccountRedirect(
            $creationResult['success'],
            $creationResult['message'],
            $creationResult['success'] ? '../views/admin/admin_dashboard.php' : $registrarDestination
        );
    }

    if ($action === 'update_admin_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($firstName === '' || $lastName === '' || !isValidApexEmail($email)
            || strlen($firstName) > 50 || strlen($lastName) > 50 || strlen($email) > 100) {
            adminAccountRedirect(false, 'Enter valid profile details within the allowed length.', '../views/admin/admin_profile.php');
        }

        $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id');
        $duplicate->execute(['email' => $email, 'id' => (int) $_SESSION['user_id']]);
        if ($duplicate->fetch()) {
            adminAccountRedirect(false, 'An account already uses that email address.', '../views/admin/admin_profile.php');
        }

        $update = $pdo->prepare("
            UPDATE users
            SET first_name = :first_name, last_name = :last_name, email = :email
            WHERE id = :id AND role = 'admin'
        ");
        $update->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'id' => (int) $_SESSION['user_id'],
        ]);
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['email'] = $email;
        adminAccountRedirect(true, 'Profile updated successfully.', '../views/admin/admin_profile.php');
    }

    if ($action === 'change_user_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $requestChannel = $_POST['request_channel'] ?? '';
        $requestReferenceInput = $_POST['request_reference'] ?? '';
        $requestReference = is_string($requestReferenceInput) ? trim($requestReferenceInput) : '';

        if (!$userId || $userId <= 0
            || !is_string($newPassword)
            || !is_string($confirmPassword)
            || !is_string($requestChannel)
            || !in_array($requestChannel, ['contact', 'ticket'], true)
            || !isValidPassword($newPassword)
            || !hash_equals($newPassword, $confirmPassword)
            || !is_string($requestReferenceInput)
            || strlen($requestReference) > 100
            || ($requestChannel === 'ticket' && $requestReference === '')) {
            adminAccountRedirect(
                false,
                $requestChannel === 'ticket' && $requestReference === ''
                    ? 'Enter the support ticket reference.'
                    : (!is_string($newPassword) || !isValidPassword($newPassword)
                        ? passwordPolicyMessage()
                        : 'Check the password confirmation and request details.'),
                $redirectTo
            );
        }

        $pdo->beginTransaction();
        $target = $pdo->prepare("
            SELECT id FROM users
            WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
            FOR UPDATE
        ");
        $target->execute(['id' => $userId]);
        if (!$target->fetchColumn()) {
            $pdo->rollBack();
            adminAccountRedirect(false, 'This account cannot be managed here.', $redirectTo);
        }

        $passwordUpdate = $pdo->prepare("
            UPDATE users
            SET password = :password
            WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
        ");
        $passwordUpdate->execute([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'id' => $userId,
        ]);

        $audit = $pdo->prepare("
            INSERT INTO admin_password_changes (user_id, admin_id, request_channel, request_reference)
            VALUES (:user_id, :admin_id, :request_channel, :request_reference)
        ");
        $audit->execute([
            'user_id' => $userId,
            'admin_id' => (int) $_SESSION['user_id'],
            'request_channel' => $requestChannel,
            'request_reference' => $requestReference === '' ? null : $requestReference,
        ]);
        $pdo->commit();

        adminAccountRedirect(true, 'Password changed. Share the new password with the user through a verified, secure channel.', $redirectTo);
    }

    if (!in_array($action, ['disable_user', 'archive_user', 'restore_user', 'unarchive_user'], true) || !$userId || $userId <= 0) {
        adminAccountRedirect(false, 'Choose a valid account action.', $redirectTo);
    }

    if ($action === 'disable_user') {
        $statement = $pdo->prepare("
            UPDATE users
            SET is_active = 0
            WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
              AND is_active = 1 AND archived_at IS NULL
        ");
        $statement->execute(['id' => $userId]);
        $successMessage = 'Account disabled. Its access will be blocked immediately.';
    } elseif ($action === 'archive_user') {
        $statement = $pdo->prepare("
            UPDATE users
            SET archived_at = CURRENT_TIMESTAMP
            WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
              AND is_active = 0 AND archived_at IS NULL
        ");
        $statement->execute(['id' => $userId]);
        $successMessage = 'Account archived and access disabled.';
    } elseif ($action === 'restore_user') {
        $restoreResult = withActiveRegistrarCapacityLock($pdo, static function () use ($pdo, $userId): array {
            $target = $pdo->prepare("
                SELECT role
                FROM users
                WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
                  AND is_active = 0 AND archived_at IS NULL
                FOR UPDATE
            ");
            $target->execute(['id' => $userId]);
            $targetRole = $target->fetchColumn();
            if (!is_string($targetRole)) {
                return [
                    'success' => false,
                    'message' => 'The account could not be updated. It may have already changed or is not manageable here.',
                ];
            }

            if ($targetRole === 'registrar') {
                $activeCount = $pdo->query("
                    SELECT COUNT(*)
                    FROM users
                    WHERE role = 'registrar' AND is_active = 1 AND archived_at IS NULL
                ")->fetchColumn();
                if ((int) $activeCount >= 2) {
                    return [
                        'success' => false,
                        'message' => 'Two registrar accounts are already active. Disable an active registrar before restoring this account.',
                    ];
                }
            }

            $statement = $pdo->prepare("
                UPDATE users
                SET is_active = 1, archived_at = NULL
                WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
                  AND is_active = 0 AND archived_at IS NULL
            ");
            $statement->execute(['id' => $userId]);
            return $statement->rowCount() === 1
                ? ['success' => true, 'message' => 'Account access restored.']
                : [
                    'success' => false,
                    'message' => 'The account could not be updated. It may have already changed or is not manageable here.',
                ];
        });
        adminAccountRedirect($restoreResult['success'], $restoreResult['message'], $redirectTo);
    } else {
        $statement = $pdo->prepare("
            UPDATE users
            SET is_active = 0, archived_at = NULL
            WHERE id = :id AND role IN ('registrar', 'dean', 'professor', 'student')
              AND is_active = 0 AND archived_at IS NOT NULL
        ");
        $statement->execute(['id' => $userId]);
        $successMessage = 'Account unarchived and returned to Disabled Accounts.';
    }

    if ($statement->rowCount() !== 1) {
        adminAccountRedirect(false, 'The account could not be updated. It may have already changed or is not manageable here.', $redirectTo);
    }

    adminAccountRedirect(true, $successMessage, $redirectTo);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin account operation failed: ' . $exception->getMessage());
    adminAccountRedirect(false, 'The account operation failed. Please try again.', $redirectTo);
} catch (InvalidArgumentException $exception) {
    error_log('Admin account operation failed: ' . $exception->getMessage());
    adminAccountRedirect(false, 'The account operation failed. Please try again.', $redirectTo);
} catch (RuntimeException $exception) {
    adminAccountRedirect(false, $exception->getMessage(), $redirectTo);
}
