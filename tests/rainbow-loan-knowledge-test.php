<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib/rainbow-loans.php';
function knowledge_expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$now = strtotime('2026-09-24T12:00:00Z');
$catalog = rainbow_loan_catalog();
knowledge_expect(count($catalog) === 3, 'Seed catalog missing');
foreach ($catalog as $record) knowledge_expect(rainbow_loan_snapshot_status($record, $now) === 'reviewed_snapshot', 'Invalid seed');
$sbi = rainbow_loan_evidence('Compare SBI and HDFC personal loans', $now);
knowledge_expect(count($sbi['records']) === 1 && $sbi['records'][0]['id'] === 'sbi-personal', 'Unsupported bank evidence invented');
knowledge_expect(rainbow_loan_evidence('HDFC loan', $now)['records'] === [], 'Unknown bank must not match');
knowledge_expect(rainbow_loan_evidence('sbi.evil.test', $now)['records'][0]['url'] === $catalog[0]['url'], 'Input URL used as evidence');
knowledge_expect(count(rainbow_loan_evidence('MUDRA PMMY Tarun Plus', $now)['records']) === 1, 'Duplicate aliases');
knowledge_expect(rainbow_loan_evidence('PMEGP', $now)['records'][0]['id'] === 'pmegp-new', 'Scheme matching');
$expired = rainbow_loan_evidence('SBI', strtotime('2026-10-01T00:00:00Z'));
knowledge_expect($expired['records'][0]['verification_status'] === 'recheck_needed' && $expired['records'][0]['facts'] === [], 'Expired facts leaked');
foreach (['2026-09-25','2026-02-30','not-a-date'] as $date) {
    $bad = $catalog[0]; $bad['checked_on'] = $date;
    knowledge_expect(rainbow_loan_evidence('SBI', $now, [$bad])['records'] === [], 'Invalid/future date accepted');
}
$bad = $catalog[0]; $bad['url'] = 'https://sbi.bank.in.evil.test/';
knowledge_expect(rainbow_loan_snapshot_status($bad, $now) === 'invalid', 'Untrusted source accepted');
$bad = $catalog[0]; $bad['review_after_days'] = 365;
knowledge_expect(rainbow_loan_snapshot_status($bad, $now) === 'invalid', 'Review interval unbounded');
knowledge_expect(rainbow_loan_evidence_instructions('Content Writer','SBI') === '', 'Non-loan regression');
knowledge_expect(str_contains(rainbow_loan_evidence_footer('Bank Policy Expert','HDFC'), 'No reviewed source'), 'Missing coverage not disclosed');
knowledge_expect(str_contains(rainbow_loan_evidence_instructions('Bank Policy Expert','SBI'), 'not a live check'), 'Snapshot limitation missing');
$code = file_get_contents(dirname(__DIR__).'/lib/rainbow-ai.php');
knowledge_expect(str_contains($code, 'rainbow_loan_evidence_instructions($agent,$task)'), 'Executor not grounded');
knowledge_expect(str_contains($code, 'rainbow_loan_evidence_footer($agent,$task)'), 'Provenance not appended');
echo "PASS policy sources, expiry, unknown coverage, isolation and executor wiring\n";
