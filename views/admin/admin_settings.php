<?php
require_once '../../includes/session_check.php';
checkAccess(['admin'], '../../auth/login.php');
$portalSettingsRole = 'admin';
$adminPage = 'settings';
require_once '../../includes/portal_settings_page.php';
