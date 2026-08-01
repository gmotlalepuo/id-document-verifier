# MCP Operations and Rollout

## Safe rollout order

1. Deploy with `MCP_EXECUTION_ENABLED=false`, run migrations, and verify BERZUDA read-only planning.
2. Confirm the six `MCP_ISSUE_LICENSE_*` allowlist values match the reviewed UAT resources.
3. Run `php artisan mcp:readiness --allow-disabled`, then `php artisan mcp:model-check --plan`.
4. Run `php artisan mcp:evaluate`; do not enable writes unless every case passes.
5. Set `MCP_EXECUTION_ENABLED=true`, recreate the app and scheduler containers, and test one approved UAT record.
6. Inspect the redacted execution audit before expanding usage. This release does not permit production environments or additional workflows.

## VPS commands

```sh
docker compose build
docker compose up -d db ollama ollama-init
docker compose run --rm app php artisan migrate --force
docker compose up -d app scheduler nginx
docker compose exec app php artisan mcp:readiness
docker compose exec app php artisan mcp:evaluate
```

The `scheduler` service runs Laravel's scheduler continuously. It invokes `mcp:prune` daily, using the configured conversation and audit retention periods.

## Rollback

Set `MCP_EXECUTION_ENABLED=false` and recreate `app` and `scheduler`. Planning and read-only discovery remain available, but preparation and approvals fail closed. Do not delete execution or confirmation records during rollback; retain them for the configured audit period.

## Alerts and investigation

Alert on repeated `MODEL_UNAVAILABLE`, `ROLLOUT_NOT_ALLOWED`, `ENDPOINT_DEFINITION_CHANGED`, `IDEMPOTENCY_CONFLICT`, and failed execution statuses. Audit views contain safe metadata only. Use server logs for correlation IDs and error codes, never raw request bodies, access tokens, national IDs, or QR links.
