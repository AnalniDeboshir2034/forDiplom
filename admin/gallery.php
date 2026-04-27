<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_auth();

header('Location: /admin/medicators', true, 302);
exit;

