<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/rainbow-ai.php';

function loan_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$cases = [
    'AssanLoan personal loan guidance' => 'Personal Loan Expert',
    'Home loan for construction' => 'Home and Property Loan Expert',
    'MSME working capital loan' => 'Business and MSME Loan Expert',
    'Education loan for overseas study' => 'Education Loan Expert',
    'Used car loan' => 'Vehicle and Equipment Loan Expert',
    'Agriculture crop loan' => 'Agriculture Loan Expert',
    'Loan against gold' => 'Gold and Secured Loan Expert',
    'Compare bank policies' => 'Bank Policy Expert',
    'GOVERNMENT LOAN SCHEMES' => 'Government Scheme Expert',
    'Personal loan CIBIL documents issue' => 'Credit and Documents Expert',
    'Home loan EMI eligibility' => 'Eligibility and EMI Expert',
    'Home loan balance transfer' => 'Balance Transfer Expert',
    'Unusual loan product' => 'Loan Master',
    'Write education curriculum' => 'Curriculum Designer',
    'Act as Content Writer. Write a chapter about loans.' => 'Content Writer',
];
foreach ($cases as $command => $expected) {
    loan_expect(rainbow_route_agent($command)===$expected, 'Routing failed: '.$command);
}
foreach (rainbow_loan_specialists() as $name => $_) {
    loan_expect(rainbow_allowed_agent($name)===$name, 'Allowlist failed: '.$name);
    loan_expect(rainbow_route_agent('Act as '.$name.'. Help me.')===$name, 'Explicit role failed: '.$name);
    loan_expect(str_contains(rainbow_loan_instructions($name), 'no live policy retrieval'), 'Evidence limitation missing');
}
loan_expect(rainbow_extract_explicit_project('Rainbow AI for AssanLoan personal loan')==='AssanLoan', 'AssanLoan identity lost');
loan_expect(rainbow_extract_explicit_project('YTC Library project')==='YTC Library', 'YTC regression');
loan_expect(rainbow_loan_instructions('Content Writer')==='', 'Loan policy leaked into non-loan role');
loan_expect(rainbow_external_approval_reason('Submit a loan application')!==null, 'External approval guard missing');
echo "PASS loan specialist routing, explicit roles, project identity and safety instructions\n";
