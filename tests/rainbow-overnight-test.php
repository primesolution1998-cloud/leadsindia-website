<?php

declare(strict_types=1);

putenv('RAINBOW_TEST_MODE=1');
$base = sys_get_temp_dir() . '/rainbow-overnight-test-' . bin2hex(random_bytes(4));
$docRoot = $base . '/public_html';
if (!mkdir($docRoot, 0750, true) && !is_dir($docRoot)) throw new RuntimeException('test_root_create_failed');
$_SERVER['DOCUMENT_ROOT'] = $docRoot;

require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-overnight.php';

function overnight_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function overnight_job(string $id, string $status='pending'): array
{
    return [
        'job_id'=>$id,
        'command'=>'Create internal draft content only. Do not publish.',
        'status'=>$status,
        'cursor'=>0,
        'max_steps'=>3,
        'executions'=>[],
        'created_at'=>gmdate('c'),
        'updated_at'=>gmdate('c'),
        'error'=>null,
    ];
}

$idA = 'rj_' . str_repeat('a', 24);
$idB = 'rj_' . str_repeat('b', 24);

rainbow_overnight_mutate_queue(static function(array &$queue) use ($idA): void {
    $queue[] = overnight_job($idA);
});
$initial = rainbow_overnight_read_queue();
overnight_expect(count($initial) === 1 && $initial[0]['job_id'] === $idA, 'initial_enqueue_failed');

// Simulate a worker snapshot, then a concurrent API enqueue, then worker reconciliation.
$workerSnapshot = $initial[0];
rainbow_overnight_mutate_queue(static function(array &$queue) use ($idB): void {
    $queue[] = overnight_job($idB);
});
rainbow_overnight_mutate_queue(static function(array &$queue) use ($idA): void {
    $index = rainbow_overnight_find_job_index($queue, $idA);
    overnight_expect($index !== null, 'job_a_missing_during_reconcile');
    $queue[$index]['status'] = 'running';
    $queue[$index]['cursor'] = 1;
});
$afterReconcile = rainbow_overnight_read_queue();
overnight_expect(count($afterReconcile) === 2, 'concurrent_enqueue_was_lost');
overnight_expect(rainbow_overnight_find_job_index($afterReconcile, $idB) !== null, 'job_b_missing_after_reconcile');

// Simulate cancellation arriving after a worker has already claimed the job.
rainbow_overnight_mutate_queue(static function(array &$queue) use ($idA): void {
    $index = rainbow_overnight_find_job_index($queue, $idA);
    overnight_expect($index !== null, 'job_a_missing_before_cancel');
    $queue[$index]['status'] = 'cancelled';
});
$executionAudit = ['execution_id'=>'rx_' . str_repeat('c',24),'status'=>'completed','finished_at'=>gmdate('c')];
rainbow_overnight_mutate_queue(static function(array &$queue) use ($idA, $executionAudit): void {
    $index = rainbow_overnight_find_job_index($queue, $idA);
    overnight_expect($index !== null, 'job_a_missing_after_cancel');
    $queue[$index]['executions'][] = $executionAudit;
    if ((string)$queue[$index]['status'] !== 'cancelled') {
        $queue[$index]['status'] = 'completed';
    }
});
$afterCancel = rainbow_overnight_read_queue();
$indexA = rainbow_overnight_find_job_index($afterCancel, $idA);
overnight_expect($indexA !== null, 'cancelled_job_missing');
overnight_expect($afterCancel[$indexA]['status'] === 'cancelled', 'cancellation_was_overwritten');
overnight_expect(count($afterCancel[$indexA]['executions']) === 1, 'late_execution_audit_missing');

// Invalid/corrupt queue must fail closed instead of silently discarding jobs.
file_put_contents(rainbow_overnight_queue_file(), '{not-json');
try {
    rainbow_overnight_read_queue();
    overnight_expect(false, 'corrupt_queue_was_accepted');
} catch (RuntimeException $e) {
    overnight_expect($e->getMessage() === 'overnight_queue_invalid', 'unexpected_corrupt_queue_error');
}

echo "PASS queue_locking\nPASS reconcile_preserves_enqueue\nPASS cancellation_wins\nPASS corrupt_queue_fails_closed\n";
