<?php

declare(strict_types=1);

/**
 * Small shared queue primitives for Rainbow overnight jobs.
 * Queue locks are intentionally held only around filesystem reads/writes;
 * agent execution must always happen outside this lock.
 */
function rainbow_overnight_queue_file(): string
{
    return rainbow_private_root() . '/rainbow-overnight-queue.json';
}

function rainbow_overnight_queue_lock_file(): string
{
    return rainbow_private_root() . '/rainbow-overnight-queue.lock';
}

function rainbow_overnight_decode_queue(string $queueFile): array
{
    if (!is_file($queueFile)) return [];
    $raw = file_get_contents($queueFile);
    if ($raw === false || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) throw new RuntimeException('overnight_queue_invalid');

    $seen = [];
    foreach ($decoded as $job) {
        if (!is_array($job)) throw new RuntimeException('overnight_queue_invalid');
        $jobId = (string)($job['job_id'] ?? '');
        if (!preg_match('/^rj_[a-f0-9]{24}$/', $jobId)) throw new RuntimeException('overnight_queue_invalid');
        if (isset($seen[$jobId])) throw new RuntimeException('overnight_queue_invalid');
        $seen[$jobId] = true;
    }

    return array_values($decoded);
}

function rainbow_overnight_read_queue(): array
{
    $lock = fopen(rainbow_overnight_queue_lock_file(), 'c+');
    if ($lock === false) throw new RuntimeException('overnight_queue_lock_unavailable');
    try {
        if (!flock($lock, LOCK_SH)) throw new RuntimeException('overnight_queue_lock_failed');
        return rainbow_overnight_decode_queue(rainbow_overnight_queue_file());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Execute a short queue mutation under an exclusive lock.
 * Callback receives queue by reference and may return any JSON-safe result.
 */
function rainbow_overnight_mutate_queue(callable $callback): mixed
{
    $lock = fopen(rainbow_overnight_queue_lock_file(), 'c+');
    if ($lock === false) throw new RuntimeException('overnight_queue_lock_unavailable');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('overnight_queue_lock_failed');
        $queueFile = rainbow_overnight_queue_file();
        $queue = rainbow_overnight_decode_queue($queueFile);
        $result = $callback($queue);
        rainbow_atomic_json_write($queueFile, array_values($queue));
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function rainbow_overnight_find_job_index(array $queue, string $jobId): ?int
{
    foreach ($queue as $i => $job) {
        if (is_array($job) && hash_equals((string)($job['job_id'] ?? ''), $jobId)) return $i;
    }
    return null;
}
