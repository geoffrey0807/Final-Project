<?php
require_once '../../includes/session_check.php';
checkAccess(['student'], '../../auth/login.php');
$portalSettingsRole = 'student';
$studentPage = 'settings';
require_once '../../includes/portal_settings_page.php';
