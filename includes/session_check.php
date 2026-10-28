<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function checkAccess(array $allowedRoles, string $loginRedirect = '../auth/login.php') {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
        header("Location: " . $loginRedirect . "?error=unauthorized");
        exit;
    }
}


function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}


function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}


function currentUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}


function getActiveUser(PDO $pdo): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, profile_photo, created_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
