<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
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
$queueFile = $root . '/rainbow-overnight-queue.json';
$reportDir = $root . '/rainbow-overnight-reports';
$lockFile = $root . '/rainbow-overnight.lock';

if (!is_dir($reportDir) && !mkdir($reportDir, 0750, true) && !is_dir($reportDir)) {
    fwrite(STDERR, "report_storage_unavailable\n");
    exit(2);
}

$lock = fopen($lockFile, 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Another overnight worker is already running.\n");
    exit(0);
}

try {
    $queue = [];
    if (is_file($queueFile)) {
        $decoded = json_decode((string)file_get_contents($queueFile), true);
        if (is_array($decoded)) $queue = $decoded;
    }

    $jobIndex = null;
    foreach ($queue as $i => $job) {
        if (!is_array($job)) continue;
        if (in_array((string)($job['status'] ?? ''), ['pending', 'running'], true)) {
            $jobIndex = $i;
            break;
        }
    }

    if ($jobIndex === null) {
        fwrite(STDOUT, "No pending overnight jobs.\n");
        exit(0);
    }

    $job = $queue[$jobIndex];
    $command = trim((string)($job['command'] ?? ''));
    if ($command === '') {
        $job['status'] = 'failed';
        $job['error'] = 'empty_command';
        $job['updated_at'] = gmdate('c');
        $queue[$jobIndex] = $job;
        rainbow_atomic_json_write($queueFile, $queue);
        exit(1);
    }

    $job['status'] = 'running';
    $job['started_at'] = $job['started_at'] ?? gmdate('c');
    $job['updated_at'] = gmdate('c');
    $job['executions'] = is_array($job['executions'] ?? null) ? $job['executions'] : [];
    $job['cursor'] = (int)($job['cursor'] ?? 0);
    $job['max_steps'] = max(1, min(50, (int)($job['max_steps'] ?? 12)));

    if (!isset($job['context']) || !is_array($job['context'])) {
        $job['context'] = rainbow_build_context($command);
    }

    if (!isset($job['plan']) || !is_array($job['plan'])) {
        $job['plan'] = rainbow_local_plan($job['context'], $command);
        $job['plan_created_at'] = gmdate('c');
    }

    $globalApproval = rainbow_external_approval_reason($command);
    if ($globalApproval !== null) {
        $job['status'] = 'blocked_for_approval';
        $job['error'] = $globalApproval;
        $job['updated_at'] = gmdate('c');
        $queue[$jobIndex] = $job;
        rainbow_atomic_json_write($queueFile, $queue);
        rainbow_atomic_json_write($reportDir . '/' . $job['job_id'] . '.json', $job);
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
        $job['status'] = 'blocked_for_approval';
        $job['error'] = 'no_safe_executable_steps';
        $job['updated_at'] = gmdate('c');
        $queue[$jobIndex] = $job;
        rainbow_atomic_json_write($queueFile, $queue);
        rainbow_atomic_json_write($reportDir . '/' . $job['job_id'] . '.json', $job);
        fwrite(STDOUT, "No safe executable steps.\n");
        exit(0);
    }

    if ($job['cursor'] >= count($safeSteps) || $job['cursor'] >= $job['max_steps']) {
        $job['status'] = 'completed';
        $job['completed_at'] = gmdate('c');
        $job['updated_at'] = gmdate('c');
        $queue[$jobIndex] = $job;
        rainbow_atomic_json_write($queueFile, $queue);
        rainbow_atomic_json_write($reportDir . '/' . $job['job_id'] . '.json', $job);
        fwrite(STDOUT, "Job completed.\n");
        exit(0);
    }

    $step = $safeSteps[$job['cursor']];
    $prior = rainbow_recent_project_outputs((string)$job['context']['project_id'], 4);
    $execution = rainbow_run_agent($job['context'], $step['agent'], $step['task'], $prior);

    $job['executions'][] = [
        'execution_id' => $execution['execution_id'] ?? null,
        'agent' => $execution['agent'] ?? $step['agent'],
        'task' => $execution['task'] ?? $step['task'],
        'status' => $execution['status'] ?? 'failed',
        'finished_at' => gmdate('c'),
    ];

    if (($execution['status'] ?? '') === 'completed') {
        $job['cursor']++;
        if ($job['cursor'] >= count($safeSteps) || $job['cursor'] >= $job['max_steps']) {
            $job['status'] = 'completed';
            $job['completed_at'] = gmdate('c');
        }
    } elseif (($execution['status'] ?? '') === 'blocked_for_approval') {
        $job['status'] = 'blocked_for_approval';
        $job['error'] = $execution['error'] ?? 'blocked_for_approval';
    } else {
        $job['status'] = 'failed';
        $job['error'] = $execution['error'] ?? 'execution_failed';
    }

    $job['updated_at'] = gmdate('c');
    $queue[$jobIndex] = $job;
    rainbow_atomic_json_write($queueFile, $queue);
    rainbow_atomic_json_write($reportDir . '/' . $job['job_id'] . '.json', $job);

    fwrite(STDOUT, json_encode([
        'job_id' => $job['job_id'],
        'status' => $job['status'],
        'cursor' => $job['cursor'],
        'safe_steps' => count($safeSteps),
    ], JSON_UNESCAPED_SLASHES) . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Rainbow overnight worker failed safely: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
