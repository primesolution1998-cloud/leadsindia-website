<?php
$file = __DIR__ . '/software.html';
if (!is_file($file)) { http_response_code(404); exit('Software page unavailable.'); }
$html = file_get_contents($file);
$html = strtr($html, [
    'https://leadsindia.in/software.html' => 'https://leadsindia.in/software',
    'href="index.html"' => 'href="/"',
    'href="loans.html"' => 'href="/loans"',
    'href="properties.html"' => 'href="/properties"',
    'href="software.html"' => 'href="/software"',
    "href='index.html'" => "href='/'",
    "href='loans.html'" => "href='/loans'",
    "href='properties.html'" => "href='/properties'",
    "href='software.html'" => "href='/software'"
]);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
