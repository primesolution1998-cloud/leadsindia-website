# Rainbow AI Overnight Autonomous Worker

## Definition of done

The worker is considered production-ready only after all of the following are verified:

1. Admin can enqueue an internal/safe command through `rainbow-ai/overnight-api.php`.
2. Hostinger cron invokes `cron/rainbow-overnight-worker.php` on schedule.
3. One safe specialist step runs per cron tick, with a filesystem lock preventing overlap.
4. Jobs and reports persist under the private Rainbow storage directory.
5. Spend, external publishing/uploads, bulk messaging, CRM mutations, credentials/security changes and other approval-gated actions do not execute autonomously.
6. Failed or blocked jobs stop safely and preserve evidence.
7. A production smoke test confirms queue → execution → persisted report end-to-end.

## Rollback

This implementation is isolated to new files. Before production deployment, keep the previous deployment revision available. Rollback is simply removing/rolling back these new files and disabling the cron entry. No database migration is introduced.

## Files

- `rainbow-ai/overnight-api.php` — authenticated queue/status/cancel API.
- `cron/rainbow-overnight-worker.php` — CLI-only worker.

## Required environment flag

The worker will do nothing unless:

```text
RAINBOW_OVERNIGHT_ENABLED=1
```

Keep the flag unset or `0` until production verification is ready.

## Hostinger cron

Recommended cadence after validation: every 10–15 minutes overnight. The worker intentionally performs a maximum of one AI specialist execution per tick to stay within shared-hosting timeouts and to limit blast radius.

Example PHP command (verify the account-specific absolute path in Hostinger before saving):

```text
php /home/<hostinger-user>/domains/leadsindia.in/public_html/cron/rainbow-overnight-worker.php
```

Do not guess `<hostinger-user>`; use the actual path shown by Hostinger.

## Queue API

All requests require existing Rainbow admin authentication and CSRF protection.

### Enqueue

```json
{
  "action": "enqueue",
  "command": "YTC Library project: create and internally QA the next original beginner English practice content. Do not publish or modify external systems.",
  "max_steps": 12,
  "csrf": "<current-token>"
}
```

### Status

```json
{
  "action": "status",
  "csrf": "<current-token>"
}
```

### Cancel

```json
{
  "action": "cancel",
  "job_id": "rj_<24-hex>",
  "csrf": "<current-token>"
}
```

## Safety model

- CLI-only worker: direct browser calls return 404.
- Disabled by default using `RAINBOW_OVERNIGHT_ENABLED`.
- Non-blocking `flock` prevents overlapping worker processes.
- Queue and reports live outside the public web root where possible through `rainbow_private_root()`.
- Existing Rainbow approval classifier blocks sensitive/external commands.
- Each plan step is re-checked before execution.
- Only steps marked `execution_allowed` and passing project-context checks are run.
- One execution per cron tick limits failure blast radius.
- No database migration, payment mutation, DNS action or secret write is introduced.

## Production rollout checklist

1. PHP lint the two new files.
2. Run worker with the feature flag disabled and verify it exits without execution.
3. Enable the flag in a controlled environment and enqueue a harmless internal generation task.
4. Run one manual cron invocation.
5. Verify queue state, execution record and overnight report files.
6. Verify a deliberately sensitive command is rejected/blocked.
7. Verify concurrent invocation exits safely due to the lock.
8. Only then configure the recurring Hostinger cron.

## Current status

CODED on feature branch. Not merged, not deployed, and not production-verified until the rollout checklist passes.
