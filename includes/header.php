<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'APEX Learning Hub';
$activeUserRole = $_SESSION['role'] ?? 'guest';
$activeUserName = $_SESSION['user_name'] ?? 'Guest User';
$userInitials = '';
if (!empty($activeUserName)) {
    $parts = explode(' ', trim($activeUserName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <?php if (isset($extraCss)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($extraCss) ?>">
    <?php endif; ?>
</head>
<body>
    <header class="app-navbar">
        <a href="../index.php" class="navbar-brand" title="APEX Learning Hub">
            <div class="logo logo-nav">
                <svg class="logo-mark" viewBox="0 0 504 428" role="img" aria-labelledby="nav-mark-title">
                    <title id="nav-mark-title">APEX three-part geometric mark</title>
                    <path fill="var(--navy)" d="M252 0 154 103a19 19 0 0 0-5 13v97L0 361v67h82l120-119v-62l50-49z" />
                    <path fill="var(--teal)" d="m262 13 87 92a15 15 0 0 1 5 12v97l150 147v67h-75L301 301v-53l-39-40z" />
                    <path fill="var(--coral)" d="M213 312h83l118 116h-99l-55-56h-57l-54 56H97z" />
                </svg>
                <div class="logo-type">
                    <span class="wordmark">APEX</span>
                    <span class="tagline">Learning Hub</span>
                </div>
            </div>
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-right">
                <div class="user-badge">
                    <div class="avatar-circle"><?= htmlspecialchars($userInitials) ?></div>
                    <div class="user-meta">
                        <span class="user-name"><?= htmlspecialchars($activeUserName) ?></span>
                        <span class="role-pill role-<?= htmlspecialchars($activeUserRole) ?>">
                            <?= htmlspecialchars(ucfirst($activeUserRole)) ?>
                        </span>
                    </div>
                </div>
                <a href="../auth/logout.php" class="btn btn-outline btn-sm" title="Sign out of your session">
                    Sign Out
                </a>
            </div>
        <?php else: ?>
            <div class="navbar-right">
                <a href="../auth/login.php" class="btn btn-primary btn-sm">Sign In</a>
                <a href="../auth/register.php" class="btn btn-teal btn-sm">Register</a>
            </div>
        <?php endif; ?>
    </header>
    <main class="app-main">

