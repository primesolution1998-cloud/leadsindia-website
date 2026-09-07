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
    "href='software.html'" => "href='/software'",
    'Assan Loan' => 'Leads India Loans',
    'RBI Authorized Bank DSA Partner' => 'Loan assistance subject to lender eligibility and approval',
    'Comparing 15+ Banks Across 50+ Cities' => 'Explore loan options across India',
    'Compare 15+ partner banks (SBI, HDFC, ICICI, Axis)' => 'Explore loan options from banks and NBFCs based on eligibility',
    'Home Loans starting @ <strong>8.35% p.a.</strong>' => 'Rates vary by lender, profile and eligibility',
    'starting @ 8.35% p.a.' => 'with lender-specific rates',
    'starting from 8.35% to 8.50% p.a.' => 'varying by lender, product and borrower profile',
    'authorized direct selling distribution partners for leading banks and NBFCs' => 'a loan assistance platform; final terms and approval are decided by the lender',
    'Paisabazaar & BankBazaar Alternative' => 'Loan Comparison & Assistance'
];
$html = strtr($html, $replacements);
$html = preg_replace('/<meta name="keywords"[^>]*>/i', '', $html);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo $html;
