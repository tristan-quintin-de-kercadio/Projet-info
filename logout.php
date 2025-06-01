<?php
require_once 'auth.php';
$auth = new AuthManager();
$auth->logout();
header('Location: login.php?message=logout_success');
exit;
?>