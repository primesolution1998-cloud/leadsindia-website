# Rainbow background workflow worker

This change adds bounded CLI execution for existing stored internal workflows.
It does not generate PDF files or publish WordPress books.

## Operation

After deployment, a host scheduler can invoke the following once per minute:

    php /absolute/site/path/cron/rainbow-workflow.php

The private environment must explicitly set RAINBOW_WORKER_ENABLED=1.
Keep it unset or set it to 0 to pause the worker. This does not interrupt a stage
already in flight. No cron entry or production environment was changed here.

A tick processes at most one workflow stage. A global nonblocking lock prevents
overlapping CLI workers; workflow locks also protect browser-triggered execution.
The workflow is reloaded under lock and its project must match the supplied context.
Failed jobs stop for review. No automatic unbounded retry is enabled.

Progress remains in the existing private workflow and execution records.
The worker writes rainbow-worker-status.json in the existing private data root.
It reports internal stages, never counts them as published books.
An AI response without completed status is rejected rather than accepted as final.

## Verification and limits

Run php tests/rainbow-worker-test.php. The executor is mocked: these tests spend
no API credits and do not establish live OpenAI, PDF or WordPress functionality.
Run the existing rainbow-ai-test.php in an isolated copy because it writes records.
A process crash after an API response but before saving can still repeat API work.
PDF composition, book-level checks, idempotent WordPress publishing, cost limits,
and production scheduling remain required for an unattended book factory.
