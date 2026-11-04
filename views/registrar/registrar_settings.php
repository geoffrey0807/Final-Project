<?php
require_once '../../includes/session_check.php';
checkAccess(['registrar'], '../../auth/login.php');
$portalSettingsRole = 'registrar';
$registrarView = 'settings';
require_once '../../includes/portal_settings_page.php';
