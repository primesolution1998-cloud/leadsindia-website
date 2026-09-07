<?php
$file = __DIR__ . '/loans.html';
if (!is_file($file)) { http_response_code(404); exit('Loans page unavailable.'); }
$html = file_get_contents($file);
$replacements = [
    'https://leadsindia.in/loans.html' => 'https://leadsindia.in/loans',
    'href="index.html"' => 'href="/"',
    'href="loans.html"' => 'href="/loans"',
    'href="properties.html"' => 'href="/properties"',
    'href="software.html"' => 'href="/software"',
    "href='index.html'" => "href='/'",
    "href='loans.html'" => "href='/loans'",
    "href='properties.html'" => "href='/properties'",
    "href='software.html'" => "href='/software'"
];
$html = strtr($html, $replacements);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
