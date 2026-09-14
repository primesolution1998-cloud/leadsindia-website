# Book pipeline status

Implemented on the feature branch; not deployed or scheduled.

- A validated book specification creates an isolated writer/editor workflow.
- Identical specifications return the same job; no duplicate job is created.
- Editor handoff uses the immediately preceding saved execution in exact order.
- Completed edited chapters export as a JSON manuscript.
- The PDF composer creates an A5 review copy with cover, contents, exercises,
  answer keys and page numbers. It checks minimum length, exact duplicate chapter
  bodies/titles, missing answers and invalid prices. These checks do not establish
  factual accuracy, originality across the catalogue, or readiness for sale.
- No input markup, file references or remote resources are interpreted.
- Non-ASCII content is explicitly rejected until multilingual fonts are supported.
- Atomic PDF creation refuses to overwrite existing files, including concurrent writes.

## Controlled execution

Set these in the worker runtime's private environment after deployment:

    RAINBOW_WORKER_ENABLED=1
    RAINBOW_WORKER_WORKFLOW_IDS=rw_<selected ID>,rw_<another selected ID>
    RAINBOW_WORKER_DAILY_CALL_LIMIT=<integer from 1 to 1000>

Missing/invalid selection or limit means no jobs execute. The cap is per UTC day
for this CLI worker, not a currency budget or account-wide API limit.
Attempts are reserved before calls, including failures. A crash may consume a
reservation without producing output. Manual/browser execution is separate.
Existing legacy jobs are not automatically enrolled.

Create a job (no AI request), then enroll its returned workflow ID:

    php cron/rainbow-book.php create /private/book-spec.json

Invoke at most once a minute via a server scheduler:

    php cron/rainbow-workflow.php

Export a completed internal manuscript:

    php cron/rainbow-book.php export rw_<workflow ID>

Save that output privately, then compose using an isolated Python environment:

    python -m pip install -r scripts/requirements-book-pdf.txt
    python scripts/book_pdf.py /private/manuscript.json /private/review.pdf

PDF layout uses [ReportLab](https://pypi.org/project/reportlab/5.0.1/).
All generated output is a review copy. No publication count is incremented.

## Verified and outstanding

Tests use mocked AI outputs. A synthetic 11-page PDF was independently parsed
and rendered; content/answer keys and page bounds were checked and cover/chapter
images inspected. The synthetic fixture is not a book for sale.

Local live-generation probe stopped at openai_not_configured before any AI call.
The local worker has no loaded OpenAI key; this does not establish the configuration
of hosted Rainbow AI or Shruti AI. Use the existing private hosting configuration,
never copy secrets into this repository.

Deployment, server Python/renderer support, private runtime API access, scheduling,
catalogue-wide similarity checks, editorial acceptance, paid-file protection and an
idempotent WordPress publishing adapter remain required. Razorpay was not changed.
