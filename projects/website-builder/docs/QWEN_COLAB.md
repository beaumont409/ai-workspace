# Optional Qwen provider (staging integration)

The builder has an opt-in `--provider qwen-colab` adapter. Inference is expected
to run only in the user's Google Colab notebook on a Tesla T4. Nothing in this
integration launches Qwen, downloads model weights, or opens a listener on the
4 GB website-builder host. With no endpoint configured, generation is recorded
as a local queued job and no network request is made. The existing template
provider remains the default.

## Current status

No authenticated Colab endpoint was found during host inspection. The paths
below describe a proposed API contract for a future Colab notebook; they are
not a working or verified endpoint. Do not configure a guessed URL or reuse an
unrelated tunnel credential. The n8n workflows and Telegram bot are not changed
by this branch. No preview is published by the adapter, and generated code is
saved under `qwen-generated/` for review only; it is not executed or served.

## Required Colab configuration

1. In Colab, load `Qwen/Qwen2.5-Coder-7B-Instruct` using a supported 4-bit
   quantization configuration on the Tesla T4. Keep inference and model memory
   on Colab; do not proxy it through the droplet for execution.
2. Implement an HTTPS tunnel with authentication. Require a high-entropy bearer
   token on both routes. Store the token in Colab Secrets and in a root-only
   token file on the builder host; never put it in the endpoint URL, notebook,
   Git, n8n execution data, or logs.
3. Implement this minimal authenticated API contract:

   - `GET /health` returns JSON with `status: "ok"`, the exact model ID,
     `quantization: "4-bit"` (or `"NF4"`), and a `device` string identifying
     Tesla T4. The adapter rejects health responses that do not establish all
     these runtime facts.
   - `POST /v1/generate` accepts JSON fields `model`, `prompt`, `theme`,
     `max_file_bytes`, and `allowed_files`; it returns
     `{"files":{"index.html":"...","css/style.css":"...","js/main.js":"..."}}`.
     Return only those relative paths, with UTF-8 text values; do not execute
     generated output or include archives, shell commands, or credentials.
4. Provide a stable authenticated HTTPS endpoint and test its auth rejection,
   health response, timeout behavior, and generated response before enabling
   the integration.
5. Only after endpoint validation, configure the builder service environment:

   ```text
   WEBSITE_BUILDER_QWEN_COLAB_URL=https://<authenticated-colab-host>
   WEBSITE_BUILDER_QWEN_COLAB_TOKEN_FILE=/root/.config/website-builder/colab-token
   WEBSITE_BUILDER_QWEN_JOB_DB=/root/.local/state/website-builder/qwen-jobs.sqlite3
   ```

   Create the token file with owner-only permissions (`0600`), and the
   containing directories with `0700`. The token is never displayed by the
   adapter. The endpoint is disabled until both URL and token file are set.

## Job behavior and review

Jobs have `queued`, `running`, `completed`, or `failed` status. Requests use a
5-second health timeout and 180-second generation timeout. An unreachable or
unhealthy Colab leaves the job queued with a sanitized error code and retry
delay; malformed output fails closed. The local SQLite queue uses owner-only
permissions and stores prompts, so treat its directory as private project data.
There is no background worker in this staging change. Re-run the opted-in
generation command after Colab is available to create a new request; queued-job
resume/poll orchestration is not yet wired to n8n.

Output is written only below the newly generated project's `qwen-generated/`
directory. It is not copied over the baseline generated site, previewed,
published, or run automatically. Review the files before any separately
approved preview process.

## OpenClaw / Telegram / n8n boundary

The current active n8n workflows and Telegram integration were inspected and
left unchanged. No active Qwen route exists. Before connecting Telegram prompts,
create a separate inactive workflow with explicit authentication, input-size
limits, allowlisted theme values, job IDs/status-only outputs, and no prompt or
token logging. Use a private route to a future adapter service; do not expose
the Colab endpoint publicly without authentication. Activate only after an
isolated end-to-end test and separate authorization. Telegram credentials and
customer-facing notifications are outside this adapter.
