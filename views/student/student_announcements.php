<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
header('Location: ../announcements.php', true, 302);
exit;
