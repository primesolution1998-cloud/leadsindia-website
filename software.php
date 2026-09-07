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
    "href='software.html'" => "href='/software'",
    'production-ready' => 'business-ready',
    'verified production-ready' => 'available business',
    'Official Meta WhatsApp Cloud API' => 'WhatsApp Cloud API-compatible',
    'official Meta WhatsApp Cloud API' => 'WhatsApp Cloud API-compatible',
    '80 msgs/sec throughput' => 'business broadcast workflow',
    'Commercial license included' => 'License terms shown per product',
    'commercial license included' => 'license terms shown per product',
    'Instant download' => 'Digital delivery',
    'instant download' => 'digital delivery',
    'Instant UPI QR download' => 'Digital software delivery',
    '100% duplicates' => 'duplicate entries'
]);
// Do not publish unsupported review/rating schema in advertising landing pages.
$html = preg_replace('/,\s*"aggregateRating"\s*:\s*\{[^{}]*\}/', '', $html);
$html = preg_replace('/<meta name="keywords"[^>]*>/i', '', $html);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
