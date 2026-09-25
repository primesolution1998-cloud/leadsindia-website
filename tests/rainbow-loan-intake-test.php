<?php
declare(strict_types=1);
putenv('RAINBOW_TEST_MODE=1');
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);
require dirname(__DIR__).'/lib/rainbow-ai.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function intake_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$emi = rainbow_loan_emi(500000, 14, 36);
intake_expect($emi === ['emi'=>17088.81,'total_repayment'=>615197.34,'total_interest'=>115197.34], 'Reported EMI regression');
$zero = rainbow_loan_emi(12000, 0, 12);
intake_expect($zero['emi'] === 1000.0 && $zero['total_interest'] === 0.0, 'Zero interest');
$tiny = rainbow_loan_emi(12000, 0.00000001, 12);
intake_expect(abs($tiny['emi'] - 1000) < 0.01, 'Numerical stability');
foreach ([[0,14,36],[500000,-1,36],[500000,14,0],[500000,14,601],[INF,14,36],[500000,NAN,36]] as $args) {
    try { rainbow_loan_emi(...$args); throw new RuntimeException('Invalid input accepted'); }
    catch (InvalidArgumentException $expected) {}
}
$_SESSION['rainbow_current_project_id']='';
$context = rainbow_build_context('personal loan');
intake_expect($context['project_name'] === 'AssanLoan', 'Default project missing');
$old = rainbow_build_context('Discuss gardening'); // Preserve an assigned project.
intake_expect($old['project_id'] === $context['project_id'], 'Assigned project changed');
$ytc = rainbow_build_context('YTC Library project. Create a book.');
$followup = rainbow_build_context('personal loan');
intake_expect($followup['project_id'] === $ytc['project_id'], 'YTC context overwritten');
$_SESSION['rainbow_current_project_id']='';
$unassigned = rainbow_build_context('Discuss gardening');
intake_expect(str_starts_with($unassigned['project_id'],'unassigned-'), 'Non-loan misclassified');
$context = rainbow_build_context('personal loan');
intake_expect($context['project_name'] === 'AssanLoan', 'Existing unassigned not repaired');
$GLOBALS['rainbow_test_executor']=static function(): array { throw new RuntimeException('Unexpected model call'); };
$execution = rainbow_run_agent($context,'Personal Loan Expert','personal loan',[]);
intake_expect($execution['status'] === 'completed', 'Intake execution failed');
intake_expect(strlen($execution['output']) < 1100 && !str_contains($execution['output'],'unassigned-') && !str_contains($execution['output'],'snapshot'), 'Intake is noisy');
$calculator = rainbow_run_agent($context,'Eligibility and EMI Expert','EMI 500000 14 36',[]);
intake_expect($calculator['status'] === 'completed' && str_contains($calculator['output'],'17,088.81'), 'Calculator execution failed');
$blocked = rainbow_run_agent($context,'Personal Loan Expert','Submit a loan application',[]);
intake_expect($blocked['status'] === 'blocked_for_approval', 'External safety gate regression');
intake_expect(rainbow_loan_local_answer('Personal Loan Expert','SBI personal loan') === null, 'Bank-specific question intercepted');
intake_expect(rainbow_loan_local_answer('Content Writer','personal loan') === null, 'Non-loan specialist intercepted');
intake_expect(rainbow_loan_local_answer('Personal Loan Expert','personal loan 500000') === null, 'User facts discarded');
echo "PASS concise intake, context preservation, exact EMI calculation and execution safety\n";
