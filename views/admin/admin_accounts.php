<?php
$_GET['status'] = ['disabled'];
if (!isset($_GET['roles']) && isset($_GET['role']) && is_string($_GET['role'])) {
    $_GET['roles'] = [$_GET['role']];
}
require 'admin_users.php';
