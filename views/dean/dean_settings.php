<?php
require_once '../../includes/session_check.php';
checkAccess(['dean'], '../../auth/login.php');
$portalSettingsRole = 'dean';
$deanPage = 'settings';
require_once '../../includes/portal_settings_page.php';
