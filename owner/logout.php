<?php
require_once __DIR__ . '/_auth.php';
owner_logout();
header('Location: /owner/login.php');
exit;
