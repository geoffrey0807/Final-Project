<?php
require_once '../../includes/session_check.php';
checkAccess(['professor'], '../../auth/login.php');
$portalSettingsRole = 'professor';
$professorView = 'settings';
require_once '../../includes/portal_settings_page.php';
