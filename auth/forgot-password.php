<?php
session_start();
require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid institutional email.";
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            header("Location: reset-password.php");
            exit;
        } else {
            $error = "No user found with that institutional email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &bull; APEX Learning Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width: 600px; display: block; min-height: auto;">
        <section class="auth-form-panel" style="padding: 2.5rem;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div class="logo logo-compact" style="margin: 0 auto 1rem auto; justify-content: center;">
                    <svg class="logo-mark" viewBox="0 0 504 428" role="img" aria-labelledby="fp-mark-title">
                        <title id="fp-mark-title">APEX three-part geometric mark</title>
                        <path fill="var(--navy)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                        <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                        <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                    </svg>
                    <div class="logo-type">
                        <span class="wordmark">APEX</span>
                        <span class="tagline">Learning Hub</span>
                    </div>
                </div>
                <h2>Reset Your Password</h2>
                <p class="text-muted">Enter your registered institutional email to reset your portal password.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="forgot-password.php" method="POST">
                <div class="form-group">
                    <label for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="name@apex.edu.ph" required>
                </div>

                <button type="submit" class="btn btn-teal" style="width: 100%; padding: 0.75rem;">
                    Find Account &amp; Proceed
                </button>
            </form>

            <div class="auth-switch" style="margin-top: 1.5rem;">
                <a href="login.php">&larr; Return to Sign In</a>
            </div>
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

