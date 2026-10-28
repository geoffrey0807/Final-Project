<?php
session_start();
require_once '../config/db.php';
require_once '../includes/password_policy.php';

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot-password.php");
    exit;
}

$userId = $_SESSION['reset_user_id'];
$email = $_SESSION['reset_email'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $newPassword = is_string($newPassword) ? $newPassword : '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please provide and confirm your new password.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!isValidPassword($newPassword)) {
        $error = passwordPolicyMessage();
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
        $stmt->execute(['pass' => $hash, 'id' => $userId]);

        unset($_SESSION['reset_user_id'], $_SESSION['reset_email']);
        header("Location: login.php?reset=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password &bull; APEX Learning Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width: 540px; display: block; min-height: auto;">
        <section class="auth-form-panel" style="padding: 2.5rem;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div class="logo logo-compact" style="margin: 0 auto 1rem auto; justify-content: center;">
                    <svg class="logo-mark" viewBox="0 0 504 428" role="img" aria-labelledby="rp-mark-title">
                        <title id="rp-mark-title">APEX three-part geometric mark</title>
                        <path fill="var(--navy)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                        <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                        <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                    </svg>
                    <div class="logo-type">
                        <span class="wordmark">APEX</span>
                        <span class="tagline">Learning Hub</span>
                    </div>
                </div>
                <h2>Create New Password</h2>
                <p class="text-muted">Resetting credentials for: <strong><?= htmlspecialchars($email) ?></strong></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="reset-password.php" method="POST">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="8+ characters, upper/lowercase, number, symbol"
                        minlength="8" maxlength="72"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,72}"
                        title="Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character."
                        autocomplete="new-password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                        placeholder="Re-enter new password" minlength="8" maxlength="72"
                        autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
                    Update Password &amp; Login
                </button>
            </form>
        </section>
    </div>

    <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-left">
                <strong>APEX Learning Hub</strong> &copy; <?= date('Y') ?> &bull; All Rights Reserved.
            </div>
            <div class="footer-right">
                <span class="text-muted">Connected to XAMPP &bull; PHP <?= phpversion() ?> &bull; MySQL</span>
            </div>
        </div>
    </footer>
</body>
</html>
