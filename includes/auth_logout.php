<?php
require_once __DIR__ . '/auth_lib.php';
app_auth_logout();
header('Location: ' . app_url('index.php'));
exit;

