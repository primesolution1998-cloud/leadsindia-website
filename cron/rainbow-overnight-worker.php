<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-overnight.php';
rainbow_load_private_env();

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ((string)(getenv('RAINBOW_OVERNIGHT_ENABLED') ?: '') !== '1') {
    fwrite(STDOUT, "Rainbow overnight worker disabled.\n");
    exit(0);
}

$root = rainbow_private_root();
$reportDir = $root . '/rainbow-overnight-reports';
$workerLockFile = $root . '/rainbow-overnight.lock';

if (!is_dir($reportDir) && !mkdir($reportDir, 0750, true) && !is_dir($reportDir)) {
    fwrite(STDERR, "report_storage_unavailable\n");
    exit(2);
}

$workerLock = fopen($workerLockFile, 'c+');
if ($workerLock === false || !flock($workerLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Another overnight worker is already running.\n");
    exit(0);
}

$writeReport = static function(array $job) use ($reportDir): void {
    $jobId = (string)($job['job_id'] ?? '');
    if (preg_match('/^rj_[a-f0-9]{24}$/', $jobId)) {
        rainbow_atomic_json_write($reportDir . '/' . $jobId . '.json', $job);
    }
};

try {
    // Claim one job under the short queue lock. Agent execution happens outside it.
    $job = rainbow_overnight_mutate_queue(static function(array &$queue): ?array {
        foreach ($queue as $i => $candidate) {
            if (!is_array($candidate)) continue;
            if (!in_array((string)($candidate['status'] ?? ''), ['pending', 'running'], true)) continue;
            $candidate['status'] = 'running';
            $candidate['started_at'] = $candidate['started_at'] ?? gmdate('c');
            $candidate['updated_at'] = gmdate('c');
            $candidate['executions'] = is_array($candidate['executions'] ?? null) ? $candidate['executions'] : [];
            $candidate['cursor'] = max(0, (int)($candidate['cursor'] ?? 0));
            $candidate['max_steps'] = max(1, min(50, (int)($candidate['max_steps'] ?? 12)));
            $queue[$i] = $candidate;
            return $candidate;
        }
        return null;
    });

    if (!is_array($job)) {
        fwrite(STDOUT, "No pending overnight jobs.\n");
        exit(0);
    }

    $jobId = (string)($job['job_id'] ?? '');
    $command = trim((string)($job['command'] ?? ''));
    if (!preg_match('/^rj_[a-f0-9]{24}$/', $jobId) || $command === '') {
        $failed = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId): ?array {
            $index = rainbow_overnight_find_job_index($queue, $jobId);
            if ($index === null) return null;
            $queue[$index]['status'] = 'failed';
            $queue[$index]['error'] = 'invalid_job';
            $queue[$index]['updated_at'] = gmdate('c');
            return $queue[$index];
        });
        if (is_array($failed)) $writeReport($failed);
        fwrite(STDERR, "invalid_job\n");
        exit(1);
    }

    if (!isset($job['context']) || !is_array($job['context'])) {
        $job['context'] = rainbow_build_context($command);
    }
    if (!isset($job['plan']) || !is_array($job['plan'])) {
        $job['plan'] = rainbow_local_plan($job['context'], $command);
        $job['plan_created_at'] = gmdate('c');
    }

    // Persist generated context/plan without overwriting a concurrent cancellation.
    $current = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId, $job): ?array {
        $index = rainbow_overnight_find_job_index($queue, $jobId);
        if ($index === null) return null;
        $status = (string)($queue[$index]['status'] ?? '');
        if ($status === 'cancelled') return $queue[$index];
        $queue[$index]['context'] = $job['context'];
        $queue[$index]['plan'] = $job['plan'];
        $queue[$index]['plan_created_at'] = $job['plan_created_at'] ?? null;
        $queue[$index]['updated_at'] = gmdate('c');
        return $queue[$index];
    });

    if (!is_array($current)) throw new RuntimeException('overnight_job_disappeared');
    if ((string)($current['status'] ?? '') === 'cancelled') {
        $writeReport($current);
        fwrite(STDOUT, "Job cancelled before execution.\n");
        exit(0);
    }
    $job = $current;

    $globalApproval = rainbow_external_approval_reason($command);
    if ($globalApproval !== null) {
        $blocked = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId, $globalApproval): ?array {
            $index = rainbow_overnight_find_job_index($queue, $jobId);
            if ($index === null) return null;
            if ((string)($queue[$index]['status'] ?? '') !== 'cancelled') {
                $queue[$index]['status'] = 'blocked_for_approval';
                $queue[$index]['error'] = $globalApproval;
                $queue[$index]['updated_at'] = gmdate('c');
            }
            return $queue[$index];
        });
        if (is_array($blocked)) $writeReport($blocked);
        fwrite(STDOUT, "Job blocked for approval.\n");
        exit(0);
    }

    $steps = is_array($job['plan']['steps'] ?? null) ? $job['plan']['steps'] : [];
    $safeSteps = [];
    foreach ($steps as $step) {
        if (!is_array($step)) continue;
        $task = trim((string)($step['action'] ?? ''));
        if ($task === '') continue;
        if (!rainbow_task_respects_context($job['context'], $task)) continue;
        if (rainbow_external_approval_reason($task) !== null) continue;
        if (empty($step['execution_allowed'])) continue;
        $safeSteps[] = [
            'agent' => rainbow_allowed_agent((string)($step['agent'] ?? 'Project Manager')),
            'task' => $task,
        ];
    }

    if (count($safeSteps) === 0) {
        $blocked = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId): ?array {
            $index = rainbow_overnight_find_job_index($queue, $jobId);
            if ($index === null) return null;
            if ((string)($queue[$index]['status'] ?? '') !== 'cancelled') {
                $queue[$index]['status'] = 'blocked_for_approval';
                $queue[$index]['error'] = 'no_safe_executable_steps';
                $queue[$index]['updated_at'] = gmdate('c');
            }
            return $queue[$index];
        });
        if (is_array($blocked)) $writeReport($blocked);
        fwrite(STDOUT, "No safe executable steps.\n");
        exit(0);
    }

    $cursor = (int)($job['cursor'] ?? 0);
    $maxSteps = (int)($job['max_steps'] ?? 12);
    if ($cursor >= count($safeSteps) || $cursor >= $maxSteps) {
        $completed = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId): ?array {
            $index = rainbow_overnight_find_job_index($queue, $jobId);
            if ($index === null) return null;
            if ((string)($queue[$index]['status'] ?? '') !== 'cancelled') {
                $queue[$index]['status'] = 'completed';
                $queue[$index]['completed_at'] = gmdate('c');
                $queue[$index]['updated_at'] = gmdate('c');
            }
            return $queue[$index];
        });
        if (is_array($completed)) $writeReport($completed);
        fwrite(STDOUT, "Job completed.\n");
        exit(0);
    }

    // Re-check cancellation immediately before the expensive specialist call.
    $latest = rainbow_overnight_read_queue();
    $latestIndex = rainbow_overnight_find_job_index($latest, $jobId);
    if ($latestIndex === null) throw new RuntimeException('overnight_job_disappeared');
    if ((string)($latest[$latestIndex]['status'] ?? '') === 'cancelled') {
        $writeReport($latest[$latestIndex]);
        fwrite(STDOUT, "Job cancelled before execution.\n");
        exit(0);
    }

    $step = $safeSteps[$cursor];
    $prior = rainbow_recent_project_outputs((string)$job['context']['project_id'], 4);
    $execution = rainbow_run_agent($job['context'], $step['agent'], $step['task'], $prior);
    $executionAudit = [
        'execution_id' => $execution['execution_id'] ?? null,
        'agent' => $execution['agent'] ?? $step['agent'],
        'task' => $execution['task'] ?? $step['task'],
        'status' => $execution['status'] ?? 'failed',
        'finished_at' => gmdate('c'),
    ];

    // Re-read/reconcile under lock so enqueue/cancel writes can never be lost.
    $final = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId, $cursor, $maxSteps, $safeSteps, $execution, $executionAudit): ?array {
        $index = rainbow_overnight_find_job_index($queue, $jobId);
        if ($index === null) return null;
        $latest =& $queue[$index];
        $latest['executions'] = is_array($latest['executions'] ?? null) ? $latest['executions'] : [];
        $latest['executions'][] = $executionAudit;
        $latest['updated_at'] = gmdate('c');

        // Cancellation wins over a result that completes after cancel was requested.
        if ((string)($latest['status'] ?? '') === 'cancelled') return $latest;

        if (($execution['status'] ?? '') === 'completed') {
            $latest['cursor'] = max((int)($latest['cursor'] ?? 0), $cursor + 1);
            $latest['status'] = 'running';
            $latest['error'] = null;
            if ($latest['cursor'] >= count($safeSteps) || $latest['cursor'] >= $maxSteps) {
                $latest['status'] = 'completed';
                $latest['completed_at'] = gmdate('c');
            }
        } elseif (($execution['status'] ?? '') === 'blocked_for_approval') {
            $latest['status'] = 'blocked_for_approval';
            $latest['error'] = $execution['error'] ?? 'blocked_for_approval';
        } else {
            $latest['status'] = 'failed';
            $latest['error'] = $execution['error'] ?? 'execution_failed';
        }
        return $latest;
    });

    if (!is_array($final)) throw new RuntimeException('overnight_job_disappeared');
    $writeReport($final);

    fwrite(STDOUT, json_encode([
        'job_id' => $jobId,
        'status' => $final['status'] ?? 'unknown',
        'cursor' => (int)($final['cursor'] ?? 0),
        'safe_steps' => count($safeSteps),
    ], JSON_UNESCAPED_SLASHES) . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Rainbow overnight worker failed safely: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_resource($workerLock)) {
        flock($workerLock, LOCK_UN);
        fclose($workerLock);
    }
}
