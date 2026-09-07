<?php
require_once __DIR__ . '/_auth.php';
owner_require_login();
readfile(dirname(__DIR__) . '/list-property.html');
