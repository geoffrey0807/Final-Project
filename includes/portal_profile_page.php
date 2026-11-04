<?php
require_once __DIR__ . '/session_check.php';
checkAccess([$portalProfileRole], '../../auth/login.php');
require_once __DIR__ . '/../config/db.php';

$profileStmt = $pdo->prepare(
    'SELECT first_name, last_name, email, role, created_at, profile_photo FROM users WHERE id = :id'
);
$profileStmt->execute(['id' => (int) $_SESSION['user_id']]);
$profile = $profileStmt->fetch();
if (!$profile) {
    header('Location: ' . $portalProfileDashboard);
    exit;
}

if (empty($_SESSION['portal_profile_csrf'])) {
    $_SESSION['portal_profile_csrf'] = bin2hex(random_bytes(32));
}
$profileCsrfToken = $_SESSION['portal_profile_csrf'];
$profilePhotoPath = (string) ($profile['profile_photo'] ?? '');
$profilePhotoUrl = '';
if (preg_match('/\Auploads\/profiles\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $profilePhotoPath)) {
    $fullPath = dirname(__DIR__) . '/' . $profilePhotoPath;
    if (is_file($fullPath)) {
        $profilePhotoUrl = '../../' . $profilePhotoPath . '?v=' . filemtime($fullPath);
    }
}

$pageTitle = 'My Profile | APEX Learning Hub';
$extraCss = [
    '../../css/admin.css?v=202610301240',
    '../../css/student.css?v=202611020900',
    '../../css/portal-profile.css?v=' . time()
];
$extraJs = $portalProfileRole === 'student'
    ? [
        '../../js/student.js?v=202610301835',
        '../../js/portal-profile.js?v=' . time()
    ]
    : [
        '../../js/admin.js?v=202611012150',
        '../../js/student.js?v=202610301835',
        '../../js/portal-profile.js?v=' . time()
    ];
$bodyClass = 'admin-dashboard student-dashboard ' . $portalProfileRole . '-portal';
$useVisualTheme = true;
$hideSharedSearch = true;

require_once __DIR__ . '/header.php';
?>

<div class="student-dashboard-shell" id="studentDashboardShell">
    <?php require $portalProfileSidebarPath; ?>
    <div class="student-dashboard-content container">
        <div class="portal-profile-shell">
            <!-- Two-Column Grid: Personal Info Form (with photo) & Change Password Form -->
            <div class="portal-profile-grid">
                
                <!-- ======================================================== -->
                <!-- FORM 1: PERSONAL INFORMATION & PHOTO (SEPARATED)        -->
                <!-- ======================================================== -->
                <section class="portal-profile-card" aria-labelledby="personalInfoHeading">
                    <div class="portal-card-header">
                        <h2 class="portal-card-title" id="personalInfoHeading">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Personal Information
                        </h2>
                        <p class="portal-card-desc">Update your profile picture and personal details.</p>
                    </div>

                    <form id="portalProfileInfoForm" class="portal-profile-form" action="../../api/portal_profile_process.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($profileCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="form_action" value="personal_info">

                        <!-- Photo upload section with live image preview -->
                        <div class="portal-profile-photo-box">
                            <div class="portal-profile-avatar-wrap" data-avatar-preview-wrap tabindex="0" role="button" aria-label="Click to select photo from folders" title="Click to choose a photo">
                                <img data-profile-photo-preview src="<?= htmlspecialchars($profilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Profile photo preview"<?= $profilePhotoUrl === '' ? ' hidden' : '' ?>>
                                <svg data-profile-photo-fallback class="portal-default-avatar-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"<?= $profilePhotoUrl !== '' ? ' hidden' : '' ?>><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <span class="portal-avatar-overlay" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                </span>
                            </div>
                            <div class="portal-profile-photo-controls">
                                <div class="portal-photo-btn-group">
                                    <label class="btn btn-outline portal-profile-photo-btn" for="profilePhoto" data-add-photo-btn>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                        <span>Add photo</span>
                                    </label>
                                    <?php if ($profilePhotoPath !== ''): ?>
                                        <button class="btn btn-outline portal-profile-remove-photo-btn" type="submit"
                                            name="remove_profile_photo" value="1" formnovalidate
                                            data-profile-photo-remove>Remove photo</button>
                                    <?php endif; ?>
                                    <input class="portal-profile-file-input" type="file" id="profilePhoto" name="profile_photo"
                                        accept="image/jpeg,image/png,image/webp" data-profile-photo-input>
                                    <button type="button" class="btn btn-outline portal-photo-revert-btn" data-profile-photo-revert hidden>
                                        Cancel selection
                                    </button>
                                </div>
                                <span class="portal-photo-selected-badge" data-photo-status hidden></span>
                                <p class="portal-profile-hint">JPG, PNG, or WebP. Maximum 5 MB.</p>
                            </div>
                        </div>

                        <!-- Fields List (Proportionate Full-Width to match right card) -->
                        <div class="portal-form-fields">
                            <div class="form-group">
                                <label for="profileFirstName">First name <span class="required">*</span></label>
                                <input class="form-control" id="profileFirstName" name="first_name" maxlength="50"
                                    value="<?= htmlspecialchars($profile['first_name'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="given-name" required>
                            </div>
                            <div class="form-group">
                                <label for="profileLastName">Last name <span class="required">*</span></label>
                                <input class="form-control" id="profileLastName" name="last_name" maxlength="50"
                                    value="<?= htmlspecialchars($profile['last_name'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="family-name" required>
                            </div>
                            <div class="form-group">
                                <label for="profileEmail">APEX email <span class="required">*</span></label>
                                <input class="form-control" type="email" id="profileEmail" name="email" maxlength="100"
                                    pattern="[A-Za-z0-9._%+\-]+@apex\.edu\.ph"
                                    title="Use an APEX email ending in @apex.edu.ph."
                                    value="<?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
                            </div>
                        </div>

                        <div class="portal-card-actions">
                            <button class="btn btn-primary" type="submit">Save information</button>
                            <a class="btn btn-outline" href="<?= htmlspecialchars($portalProfileDashboard, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
                        </div>
                    </form>
                </section>

                <!-- ======================================================== -->
                <!-- FORM 2: CHANGE PASSWORD (SEPARATED FORM)                 -->
                <!-- ======================================================== -->
                <section class="portal-profile-card" aria-labelledby="changePasswordHeading">
                    <div class="portal-card-header">
                        <h2 class="portal-card-title" id="changePasswordHeading">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Change Password
                        </h2>
                        <p class="portal-card-desc">Keep your account secure with an alphanumeric and title case password.</p>
                    </div>

                    <form id="portalProfilePasswordForm" class="portal-profile-form" action="../../api/portal_profile_process.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($profileCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="form_action" value="change_password">

                        <div class="portal-form-fields">
                            <!-- Current password -->
                            <div class="form-group">
                                <label for="currentPassword">Current password <span class="required">*</span></label>
                                <div class="portal-password-wrap">
                                    <input class="form-control" type="password" id="currentPassword" name="current_password"
                                        autocomplete="current-password" required placeholder="Enter current password">
                                    <button type="button" class="portal-password-toggle" data-password-toggle data-target="currentPassword" aria-label="Show password" title="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- New password -->
                            <div class="form-group">
                                <label for="newPassword">New password <span class="required">*</span></label>
                                <div class="portal-password-wrap">
                                    <input class="form-control" type="password" id="newPassword" name="new_password"
                                        minlength="8" maxlength="72"
                                        pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                                        title="Use at least 8 characters with alphanumeric and title case (uppercase), lowercase, a number, and a special character."
                                        autocomplete="new-password" required placeholder="Enter new password">
                                    <button type="button" class="portal-password-toggle" data-password-toggle data-target="newPassword" aria-label="Show password" title="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Live Password Validation Checklist -->
                            <div class="portal-password-requirements">
                                <div class="portal-req-title">Password requirements:</div>
                                <ul class="portal-req-list">
                                    <li class="portal-req-item" id="reqLength">
                                        <span class="portal-req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                        <span>Minimum of 8 characters</span>
                                    </li>
                                    <li class="portal-req-item" id="reqUppercase">
                                        <span class="portal-req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                        <span>Title case / uppercase letter (A-Z)</span>
                                    </li>
                                    <li class="portal-req-item" id="reqLowercase">
                                        <span class="portal-req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                        <span>Lowercase letter (a-z)</span>
                                    </li>
                                    <li class="portal-req-item" id="reqNumber">
                                        <span class="portal-req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                        <span>Alphanumeric number (0-9)</span>
                                    </li>
                                    <li class="portal-req-item" id="reqSpecial">
                                        <span class="portal-req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                                        <span>Special character (!@#$%^&amp;*...)</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Confirm new password -->
                            <div class="form-group">
                                <label for="confirmNewPassword">Confirm new password <span class="required">*</span></label>
                                <div class="portal-password-wrap">
                                    <input class="form-control" type="password" id="confirmNewPassword" name="confirm_new_password"
                                        minlength="8" maxlength="72" autocomplete="new-password" required placeholder="Re-enter new password">
                                    <button type="button" class="portal-password-toggle" data-password-toggle data-target="confirmNewPassword" aria-label="Show password" title="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                                <div id="passwordMatchIndicator" class="portal-match-indicator">
                                    <span id="passwordMatchText"></span>
                                </div>
                            </div>
                        </div>

                        <div class="portal-card-actions">
                            <button class="btn btn-primary" type="submit" id="btnUpdatePassword">Update password</button>
                            <button class="btn btn-outline" type="reset">Clear</button>
                        </div>
                    </form>
                </section>

            </div>
        </div>
    </div>
</div>
<button type="button" class="student-sidebar-backdrop" aria-label="Close portal navigation"></button>

<?php require_once __DIR__ . '/footer.php'; ?>
