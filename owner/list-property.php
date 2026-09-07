<?php
require_once __DIR__ . '/_auth.php';
owner_require_login();
$file = dirname(__DIR__) . '/list-property.html';
if (!is_file($file)) { http_response_code(404); exit('Listing page unavailable.'); }
$html = file_get_contents($file);
$html = strtr($html, [
  'https://leadsindia.in/list-property.html' => 'https://leadsindia.in/list-property',
  'href="index.html"' => 'href="/"',
  'href="properties.html"' => 'href="/properties"',
  "href='index.html'" => "href='/'",
  "href='properties.html'" => "href='/properties'"
]);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
