<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

header('Location: ' . admin_url('medicators.php'), true, 302);
exit;

