<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/rainbow-ai.php';
require dirname(__DIR__) . '/lib/rainbow-overnight.php';
rainbow_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rainbow_json(['ok'=>false,'code'=>'method_not_allowed','message'=>'POST required.'],405);
}

rainbow_require_admin_json();

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/json')) {
    rainbow_json(['ok'=>false,'code'=>'invalid_content_type','message'=>'application/json required.'],415);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 20000) {
    rainbow_json(['ok'=>false,'code'=>'invalid_request','message'=>'Request too large or unreadable.'],400);
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    rainbow_json(['ok'=>false,'code'=>'invalid_json','message'=>'Invalid JSON request.'],400);
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf'] ?? ''));
if (!rainbow_verify_csrf($csrf)) {
    rainbow_json(['ok'=>false,'code'=>'csrf_failed','message'=>'Security token expired. Refresh and try again.'],403);
}

if (!rainbow_rate_limit(12, 60)) {
    rainbow_json(['ok'=>false,'code'=>'rate_limited','message'=>'Too many requests.'],429);
}

$action = trim((string)($body['action'] ?? 'status'));

try {
    if ($action === 'status') {
        $queue = rainbow_overnight_read_queue();
        $safe = array_map(static function(array $job): array {
            return [
                'job_id'=>(string)($job['job_id'] ?? ''),
                'status'=>(string)($job['status'] ?? 'unknown'),
                'project_id'=>(string)($job['context']['project_id'] ?? ''),
                'cursor'=>(int)($job['cursor'] ?? 0),
                'max_steps'=>(int)($job['max_steps'] ?? 0),
                'created_at'=>$job['created_at'] ?? null,
                'updated_at'=>$job['updated_at'] ?? null,
                'error'=>$job['error'] ?? null,
            ];
        }, $queue);
        rainbow_json(['ok'=>true,'enabled'=>(string)(getenv('RAINBOW_OVERNIGHT_ENABLED') ?: '') === '1','jobs'=>$safe]);
    }

    if ($action === 'enqueue') {
        $command = trim((string)($body['command'] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($command, 'UTF-8') : strlen($command);
        if ($length < 3 || $length > 4000) {
            rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Command must be between 3 and 4000 characters.'],422);
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', $command)) {
            rainbow_json(['ok'=>false,'code'=>'invalid_command','message'=>'Unsupported control characters.'],422);
        }

        $approval = rainbow_external_approval_reason($command);
        if ($approval !== null) {
            rainbow_json([
                'ok'=>false,
                'code'=>'approval_required',
                'approval_type'=>$approval,
                'message'=>'This command contains an external or sensitive action. Split it into internal preparation work, then approve the external action separately.'
            ],409);
        }

        $maxSteps = max(1, min(50, (int)($body['max_steps'] ?? 12)));
        $jobId = 'rj_' . bin2hex(random_bytes(12));
        $job = [
            'job_id'=>$jobId,
            'command'=>$command,
            'status'=>'pending',
            'cursor'=>0,
            'max_steps'=>$maxSteps,
            'executions'=>[],
            'created_at'=>gmdate('c'),
            'updated_at'=>gmdate('c'),
            'started_at'=>null,
            'completed_at'=>null,
            'error'=>null,
        ];
        rainbow_overnight_mutate_queue(static function(array &$queue) use ($job): void {
            $queue[] = $job;
        });
        rainbow_json(['ok'=>true,'job'=>['job_id'=>$jobId,'status'=>'pending','max_steps'=>$maxSteps]]);
    }

    if ($action === 'cancel') {
        $jobId = trim((string)($body['job_id'] ?? ''));
        if (!preg_match('/^rj_[a-f0-9]{24}$/', $jobId)) {
            rainbow_json(['ok'=>false,'code'=>'invalid_job_id'],422);
        }

        $cancelled = rainbow_overnight_mutate_queue(static function(array &$queue) use ($jobId): bool {
            $index = rainbow_overnight_find_job_index($queue, $jobId);
            if ($index === null) return false;
            $status = (string)($queue[$index]['status'] ?? '');
            if (in_array($status, ['completed','cancelled'], true)) return false;
            $queue[$index]['status'] = 'cancelled';
            $queue[$index]['updated_at'] = gmdate('c');
            $queue[$index]['error'] = null;
            return true;
        });
        if (!$cancelled) rainbow_json(['ok'=>false,'code'=>'job_not_found_or_final'],404);
        rainbow_json(['ok'=>true,'job_id'=>$jobId,'status'=>'cancelled']);
    }

    rainbow_json(['ok'=>false,'code'=>'unsupported_action'],422);
} catch (RuntimeException $e) {
    $code = $e->getMessage();
    $safe = in_array($code, ['overnight_queue_invalid','overnight_queue_lock_unavailable','overnight_queue_lock_failed','execution_persist_failed'], true)
        ? $code : 'overnight_queue_error';
    rainbow_json(['ok'=>false,'code'=>$safe,'message'=>'Overnight queue is temporarily unavailable.'],503);
}
