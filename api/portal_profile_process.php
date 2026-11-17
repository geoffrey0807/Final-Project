<?php
session_start();
require_once '../config/db.php';
require_once '../includes/password_policy.php';
require_once '../includes/portal_toast.php';

$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$profileRoutes = [
    'admin' => '../views/admin/admin_profile.php',
    'registrar' => '../views/registrar/registrar_profile.php',
    'dean' => '../views/dean/dean_profile.php',
    'professor' => '../views/professor/professor_profile.php',
    'student' => '../views/student/student_profile.php',
];

if ($userId < 1 || !isset($profileRoutes[$role])) {
    header('Location: ../auth/login.php?error=unauthorized');
    exit;
}

$redirectWith = static function (string $key, string $message) use ($profileRoutes, $role): void {
    setPortalToast($message, $key === 'error' ? 'error' : 'success');
    header('Location: ' . $profileRoutes[$role]);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectWith('error', 'Submit the profile form to save changes.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($submittedToken)
    || !isset($_SESSION['portal_profile_csrf'])
    || !hash_equals($_SESSION['portal_profile_csrf'], $submittedToken)
) {
    $redirectWith('error', 'Your session expired. Reload the page and try again.');
}

$accountStmt = $pdo->prepare(
    'SELECT role, first_name, last_name, email, password, profile_photo, is_active, archived_at FROM users WHERE id = :id'
);
$accountStmt->execute(['id' => $userId]);
$account = $accountStmt->fetch();
if (
    !$account
    || $account['role'] !== $role
    || (int) $account['is_active'] !== 1
    || $account['archived_at'] !== null
) {
    $_SESSION = [];
    session_destroy();
    header('Location: ../auth/login.php?error=unauthorized');
    exit;
}

if (($_POST['remove_profile_photo'] ?? '') === '1') {
    try {
        $removePhotoStmt = $pdo->prepare(
            'UPDATE users SET profile_photo = NULL WHERE id = :id AND role = :role'
        );
        $removePhotoStmt->execute([
            'id' => $userId,
            'role' => $role,
        ]);
    } catch (PDOException $exception) {
        error_log('Profile photo removal failed for user ' . $userId . ': ' . $exception->getMessage());
        $redirectWith('error', 'Your profile photo could not be removed. Please try again.');
    }

    $_SESSION['profile_photo'] = '';
    $oldProfilePhoto = (string) ($account['profile_photo'] ?? '');
    if (preg_match('/\Auploads\/profiles\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $oldProfilePhoto)) {
        $oldPhotoPath = dirname(__DIR__) . '/' . $oldProfilePhoto;
        if (is_file($oldPhotoPath) && !unlink($oldPhotoPath)) {
            error_log('Removed profile photo reference but could not delete file for user ' . $userId . ': ' . $oldPhotoPath);
        }
    }

    $redirectWith('msg', 'Profile photo removed successfully.');
}

$formAction = trim((string) ($_POST['form_action'] ?? ''));

// =========================================================================
// ACTION: Change Password (Separated from personal info)
// =========================================================================
if ($formAction === 'change_password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmNewPassword = (string) ($_POST['confirm_new_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmNewPassword === '') {
        $redirectWith('error', 'Complete all three password fields to change your password.');
    }
    if (!password_verify($currentPassword, $account['password'])) {
        $redirectWith('error', 'Your current password is incorrect.');
    }
    if (!isValidPassword($newPassword)) {
        $redirectWith('error', passwordPolicyMessage());
    }
    if (!hash_equals($newPassword, $confirmNewPassword)) {
        $redirectWith('error', 'The new password and confirmation do not match.');
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    try {
        $updateStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $updateStmt->execute([
            'password' => $passwordHash,
            'id' => $userId,
        ]);
    } catch (PDOException $exception) {
        error_log('Password update failed for user ' . $userId . ': ' . $exception->getMessage());
        $redirectWith('error', 'Your password could not be updated. Please try again.');
    }

    $redirectWith('msg', 'Password updated successfully.');
}

if ($formAction !== 'personal_info') {
    $redirectWith('error', 'Choose a valid profile action and try again.');
}

// Update profile photo
$upload = $_FILES['profile_photo'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $redirectWith('error', 'Choose a profile picture to upload.');
}
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $redirectWith('error', 'The photo could not be uploaded. Choose it again and retry.');
}
if ((int) ($upload['size'] ?? 0) < 1 || (int) $upload['size'] > 5 * 1024 * 1024) {
    $redirectWith('error', 'Choose an image smaller than 5 MB.');
}

$temporaryPath = (string) ($upload['tmp_name'] ?? '');
$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $temporaryPath !== '' ? $fileInfo->file($temporaryPath) : false;
$imageInfo = $temporaryPath !== '' ? @getimagesize($temporaryPath) : false;
$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if (
    !is_string($mimeType)
    || !isset($allowedImageTypes[$mimeType])
    || !is_array($imageInfo)
    || ($imageInfo['mime'] ?? '') !== $mimeType
    || (int) ($imageInfo[0] ?? 0) > 6000
    || (int) ($imageInfo[1] ?? 0) > 6000
) {
    $redirectWith('error', 'Choose a valid JPG, PNG, or WebP image up to 6000 × 6000 pixels.');
}

$profileDirectory = dirname(__DIR__) . '/uploads/profiles';
if (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0755, true) && !is_dir($profileDirectory)) {
    $redirectWith('error', 'Photo storage is unavailable. Your profile picture was not updated.');
}
$photoFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageTypes[$mimeType];
$destination = $profileDirectory . DIRECTORY_SEPARATOR . $photoFileName;
if (!move_uploaded_file($temporaryPath, $destination)) {
    $redirectWith('error', 'The photo could not be stored. Choose it again and retry.');
}
$newProfilePhoto = 'uploads/profiles/' . $photoFileName;

try {
    $updateStmt = $pdo->prepare('UPDATE users SET profile_photo = :profile_photo WHERE id = :id AND role = :role');
    $updateStmt->execute([
        'profile_photo' => $newProfilePhoto,
        'id' => $userId,
        'role' => $role,
    ]);
} catch (PDOException $exception) {
    if (is_file($destination) && !unlink($destination)) {
        error_log('Could not remove failed profile upload for user ' . $userId . ': ' . $destination);
    }
    error_log('Profile photo update failed for user ' . $userId . ': ' . $exception->getMessage());
    $redirectWith('error', 'Your profile picture could not be updated. Please try again.');
}

$_SESSION['profile_photo'] = $newProfilePhoto;

$oldProfilePhoto = (string) ($account['profile_photo'] ?? '');
if (
    preg_match('/\Auploads\/profiles\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $oldProfilePhoto)
) {
    $oldPhotoPath = dirname(__DIR__) . '/' . $oldProfilePhoto;
    if (is_file($oldPhotoPath) && !unlink($oldPhotoPath)) {
        error_log('Profile photo updated but old image could not be deleted for user ' . $userId . ': ' . $oldPhotoPath);
    }
}

$redirectWith('msg', 'Profile picture updated successfully.');
