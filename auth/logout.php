<?php
session_start();
$_SESSION = [];
session_destroy();
session_start();
session_regenerate_id(true);
require_once __DIR__ . '/../includes/portal_toast.php';
setPortalToast('You have been logged out.');
session_write_close();
$destination = ($_GET['destination'] ?? '') === 'homepage-chat'
    ? '../visual/homepage.php?chat=1'
    : 'login.php';
header("Location: {$destination}");
exit;
