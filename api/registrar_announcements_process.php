<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session_check.php';
require_once '../includes/portal_toast.php';

checkAccess(['registrar'], '../auth/login.php');

$redirect = '../views/registrar/registrar_dashboard.php?view=announcements';
$respond = static function (bool $success, string $message) use ($redirect): never {
    setPortalToast($message, $success ? 'success' : 'error');
    header('Location: ' . $redirect);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedCsrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($submittedCsrfToken)
    || !isset($_SESSION['registrar_announcement_csrf'])
    || !hash_equals($_SESSION['registrar_announcement_csrf'], $submittedCsrfToken)) {
    $respond(false, 'Your session expired. Refresh the page and try again.');
}
if ($academicReadOnly) {
    $respond(false, 'This past academic period is view-only.');
}

$action = $_POST['action'] ?? '';
$announcementDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';

if ($action === 'publish') {
    $headlineInput = $_POST['headline'] ?? null;
    $messageInput = $_POST['message'] ?? null;
    if (!is_string($headlineInput) || !is_string($messageInput)) {
        $respond(false, 'Enter a valid headline and message.');
    }
    $headline = trim($headlineInput);
    $message = trim($messageInput);
    $isHomepageNotice = ($_POST['is_homepage_notice'] ?? '') === '1';
    $audienceRoles = $_POST['audience_roles'] ?? [];
    $allAudienceRoles = ['admin', 'registrar', 'dean', 'professor', 'student'];

    if ($headline === '' || mb_strlen($headline) > 150) {
        $respond(false, 'Enter a headline of 1 to 150 characters.');
    }
    if ($message === '' || strlen($message) > 65535) {
        $respond(false, 'Enter a message that does not exceed 65,535 bytes.');
    }
    if ($isHomepageNotice) {
        $audienceRoles = $allAudienceRoles;
    } else {
        if (!is_array($audienceRoles)
            || array_filter($audienceRoles, static fn($role): bool => !is_string($role))) {
            $respond(false, 'Select ALL or one or more valid audience roles.');
        }
        $isAllAudience = ($_POST['audience_all'] ?? '') === '1';
        if ($isAllAudience) {
            $audienceRoles = $allAudienceRoles;
        } else {
            $audienceRoles = array_values(array_unique($audienceRoles));
            if (!$audienceRoles || array_diff($audienceRoles, ['admin', 'dean', 'professor', 'student'])) {
                $respond(false, 'Select ALL or one or more valid audience roles.');
            }
        }
    }

    $homepageCategory = $_POST['homepage_category'] ?? '';
    $validHomepageCategories = ['academic', 'system', 'event', 'policy'];
    if ($isHomepageNotice) {
        if (!is_string($homepageCategory) || !in_array($homepageCategory, $validHomepageCategories, true)) {
            $respond(false, 'Choose one category for the homepage notice.');
        }
    } else {
        $homepageCategory = null;
    }

    $imagePath = null;
    $uploadedFile = $_FILES['image'] ?? null;
    if (isset($_FILES['image']) && !is_array($uploadedFile)) {
        $respond(false, 'Choose a valid image file.');
    }
    if (is_array($uploadedFile) && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadError = $uploadedFile['error'] ?? null;
        if (!is_int($uploadError) || $uploadError !== UPLOAD_ERR_OK) {
            $respond(false, 'The image upload failed. Please choose the image again.');
        }
        $temporaryPath = $uploadedFile['tmp_name'] ?? null;
        if (!is_string($temporaryPath)) {
            $respond(false, 'Choose a valid image file.');
        }
        $imageSize = (int) ($uploadedFile['size'] ?? 0);
        if ($imageSize < 1 || $imageSize > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            $respond(false, 'Choose an image smaller than 5 MB.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $imageExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($imageExtensions[$mimeType]) || getimagesize($temporaryPath) === false) {
            $respond(false, 'Use a valid JPG, PNG, or WebP image.');
        }
        if (!is_dir($announcementDirectory)
            && !mkdir($announcementDirectory, 0750, true)
            && !is_dir($announcementDirectory)) {
            error_log('Announcement image directory could not be created.');
            $respond(false, 'Image storage is unavailable. Please try again later.');
        }

        $imageName = bin2hex(random_bytes(16)) . '.' . $imageExtensions[$mimeType];
        $destination = $announcementDirectory . DIRECTORY_SEPARATOR . $imageName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            error_log('Announcement image could not be moved to permanent storage.');
            $respond(false, 'The image could not be saved. Please try again.');
        }
        $imagePath = 'uploads/announcements/' . $imageName;
    }

    try {
        $pdo->beginTransaction();
        $insertAnnouncement = $pdo->prepare(
            'INSERT INTO announcements (headline, message, image_path, is_homepage_notice, homepage_category, author_id)
             VALUES (:headline, :message, :image_path, :is_homepage_notice, :homepage_category, :author_id)'
        );
        $insertAnnouncement->execute([
            'headline' => $headline,
            'message' => $message,
            'image_path' => $imagePath,
            'is_homepage_notice' => $isHomepageNotice ? 1 : 0,
            'homepage_category' => $homepageCategory,
            'author_id' => (int) $_SESSION['user_id'],
        ]);
        $announcementId = (int) $pdo->lastInsertId();

        $insertAudience = $pdo->prepare(
            'INSERT INTO announcement_audience_roles (announcement_id, role) VALUES (:announcement_id, :role)'
        );
        foreach ($audienceRoles as $role) {
            $insertAudience->execute(['announcement_id' => $announcementId, 'role' => $role]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($imagePath !== null && is_file($announcementDirectory . DIRECTORY_SEPARATOR . basename($imagePath))) {
            unlink($announcementDirectory . DIRECTORY_SEPARATOR . basename($imagePath));
        }
        error_log('Registrar announcement publishing failed: ' . $exception->getMessage());
        $respond(false, 'The announcement could not be published. Please try again.');
    }

    $respond(true, 'Announcement published successfully.');
}

if ($action === 'delete') {
    $submittedIds = $_POST['announcement_ids'] ?? [];
    if (!is_array($submittedIds) || !$submittedIds || count($submittedIds) > 200) {
        $respond(false, 'Select at least one announcement to delete.');
    }
    $announcementIds = [];
    foreach ($submittedIds as $submittedId) {
        if (!is_string($submittedId) && !is_int($submittedId)) {
            $respond(false, 'The selected announcements are invalid.');
        }
        $announcementId = filter_var($submittedId, FILTER_VALIDATE_INT);
        if ($announcementId === false || $announcementId < 1) {
            $respond(false, 'The selected announcements are invalid.');
        }
        $announcementIds[] = $announcementId;
    }
    $announcementIds = array_values(array_unique($announcementIds));
    $placeholders = implode(', ', array_fill(0, count($announcementIds), '?'));

    try {
        $pdo->beginTransaction();
        $selectImages = $pdo->prepare(
            "SELECT id, image_path FROM announcements WHERE id IN ($placeholders) AND author_id = ? FOR UPDATE"
        );
        $selectImages->execute(array_merge($announcementIds, [(int) $_SESSION['user_id']]));
        $selectedAnnouncements = $selectImages->fetchAll();
        if (count($selectedAnnouncements) !== count($announcementIds)) {
            $pdo->rollBack();
            $respond(false, 'One or more selected announcements are no longer available.');
        }

        $deleteAnnouncements = $pdo->prepare(
            "DELETE FROM announcements WHERE id IN ($placeholders) AND author_id = ?"
        );
        $deleteAnnouncements->execute(array_merge($announcementIds, [(int) $_SESSION['user_id']]));
        $deletedCount = $deleteAnnouncements->rowCount();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Registrar announcement deletion failed: ' . $exception->getMessage());
        $respond(false, 'The selected announcements could not be deleted. Please try again.');
    }

    foreach (array_column($selectedAnnouncements, 'image_path') as $path) {
        if (is_string($path)
            && preg_match('/\Auploads\/announcements\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $path)) {
            $absolutePath = $announcementDirectory . DIRECTORY_SEPARATOR . basename($path);
            if (is_file($absolutePath) && !unlink($absolutePath)) {
                error_log('Announcement image cleanup failed for announcement path: ' . $path);
            }
        }
    }

    if ($deletedCount < 1) {
        $respond(false, 'No matching announcements were found.');
    }
    $respond(true, 'Selected announcements deleted.');
}

$respond(false, 'Invalid announcement action.');
