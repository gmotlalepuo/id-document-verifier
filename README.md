# ID Document Verifier API

Laravel 13 / PHP 8.5+ API that downloads an official identity document, enhances its pages, extracts Botswana ID fields with Tesseract OCR, and compares them with supplied identity data.

## Result semantics

- `verified`: every required field was extracted and matched.
- `mismatch`: every field was extracted, but one or more values did not match.
- `inconclusive`: at least one required field could not be extracted. This is deliberately not treated as a mismatch.
- `failed`: the document could not be securely downloaded, decoded, enhanced, or OCR-processed.

Every field includes `comparable`, `match`, `score`, and a reason. ID numbers are exact after removing formatting. Names use normalized fuzzy matching; forenames may be in a different order but all supplied names must be present.

## Requirements

- PHP 8.5+ with `fileinfo`, `json`, `mbstring`, `openssl`, and PDO MySQL
- Composer 2
- MySQL/MariaDB (SQLite can be used for tests)
- [Tesseract OCR](https://github.com/tesseract-ocr/tesseract)
- ImageMagick (`magick`) with PNG/JPEG/TIFF support
- Poppler (`pdftoppm`) for PDF documents

On Windows, put all three executable directories on `PATH`, or set their full executable paths in `.env`. If you use Docker, these native OCR tools are installed inside the app container.

## Docker setup

The deployment stack runs five services:

- `app`: PHP-FPM with Laravel and the native OCR dependencies
- `nginx`: web server exposed on `HTTP_PORT` (default `6060`)
- `db`: MariaDB, bound to host loopback on port `3307` by default
- `ollama`: pinned Ollama `0.32.5` server on the private Compose network
- `ollama-init`: one-shot initializer that downloads `qwen2.5:1.5b`

Laravel and Nginx run the source baked into their images; the VPS source tree
does not overwrite production dependencies. Laravel storage, MariaDB data, and
Ollama models use named volumes.

Create the deployment environment file:

```powershell
Copy-Item .env.docker.example .env.docker
php artisan key:generate --show
```

Put the generated key and deployment secrets in `.env.docker`, then start the
CPU-compatible stack:

```powershell
docker compose --env-file .env.docker up --build -d
```

On first startup, `ollama-init` downloads the configured model into the
`ollama-data` volume. The Laravel container waits for MariaDB, a healthy Ollama
server, and a successful model pull. Later starts reuse the model volume.

For an NVIDIA VPS with the NVIDIA Container Toolkit configured:

```powershell
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.gpu.yml up --build -d
```

Verify the deployment:

```powershell
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs ollama-init
docker compose --env-file .env.docker exec app php artisan mcp:model-check --plan
curl.exe http://localhost:6060/api/v1/health
```

Ollama is intentionally not published on a host port. Laravel reaches it at
`http://ollama:11434` inside the private Compose network.

Stop the containers while preserving all data and downloaded models:

```powershell
docker compose --env-file .env.docker down
```

The following destructive command removes the database, Laravel runtime storage,
and downloaded model:

```powershell
docker compose --env-file .env.docker down -v
```

## Manual setup

```powershell
cd C:\xampp\htdocs\id-document-verifier
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create the `id_document_verifier` database, set its database credentials and a strong random `DOCUMENT_VERIFICATION_API_KEY` in `.env`, then run:

```powershell
php artisan migrate
php artisan serve
```

Make sure these writable directories exist in deployments: `storage/app/verification`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache`.

## API

Health check (no authentication):

```http
GET /api/v1/health
```

It returns HTTP `200` only when all OCR executables are available, otherwise `503` with the missing dependency names.

Verify a document:

```http
POST /api/v1/verifications
X-API-Key: YOUR_DOCUMENT_VERIFICATION_API_KEY
Content-Type: application/json

{
  "document_url": "https://twosixdigitalbw.app/api/v2/document/.../download",
  "surname": "Motlalepuo",
  "forenames": "Garenosi",
  "id_number": "436415528"
}
```

Retrieve the audit result:

```http
GET /api/v1/verifications/{request_id}
X-API-Key: YOUR_DOCUMENT_VERIFICATION_API_KEY
```

## MCP discovery API

The accepted architecture, policies, client boundary, schemas, and phase gates are
documented in [docs/mcp/ARCHITECTURE.md](docs/mcp/ARCHITECTURE.md).

The read-only Phase 1 MCP endpoint is available at:

```http
POST /api/mcp
Authorization: Bearer SUPABASE_USER_ACCESS_TOKEN
Content-Type: application/json
```

Configure the RequestLab Supabase project in `.env`:

```text
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_PUBLISHABLE_KEY=your-publishable-key
```

The caller's Supabase access token is forwarded to Supabase REST, so the existing
RequestLab row-level security policies remain the authorization boundary. The MCP
server does not use a service-role key.

Phase 1 supports `initialize`, `ping`, `tools/list`, and `tools/call` with these
read-only tools:

```text
list_workspaces
list_collections
list_folders
list_requests
list_environments
get_workspace_members
get_collection
get_folder
get_request
get_environment
resolve_request
```

`resolve_request` creates a redacted preview only. It does not perform an outbound
request. Secret-looking environment variables, authentication values, and headers
are not returned to the MCP client.

### Local Qwen planning

Phase 3 adds two authenticated MCP tools:

```text
model_status
plan_request
```

`plan_request` sends a bounded prompt and the versioned model-plan JSON Schema to
Ollama, validates Qwen's response again in Laravel, and returns a plan with
`execution_enabled` reflects `MCP_EXECUTION_ENABLED`. Planning itself never
performs a write.

Install [Ollama](https://docs.ollama.com/windows), then pull the configured model:

```powershell
ollama pull qwen2.5:1.5b
ollama list
php artisan mcp:model-check --plan
```

Default model configuration:

```text
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5:1.5b
OLLAMA_TIMEOUT=120
OLLAMA_CONTEXT_LENGTH=8192
OLLAMA_KEEP_ALIVE=10m
```

The local runtime was verified with `qwen2.5:1.5b` using real
schema-constrained completions. For `issue_license`, Laravel converts the
model-classified intent into the reviewed deterministic preparation step.

Phase 2 provides a versioned RequestLab database dictionary, endpoint knowledge,
deterministic endpoint discovery, and the `issue_license` workflow. Both stored
RequestLab UUIDs and the UAT extraction paths are reviewed and bound.

Phase 4 adds `prepare_issue_license` when `MCP_EXECUTION_ENABLED=true`.
Preparation runs only the reviewed GET lookup and creates an expiring,
digest-bound confirmation preview. The model cannot approve the write. BERZUDA
submits an explicit user approval to:

```text
POST /api/mcp/confirmations/{confirmation_id}/approve
```

The write is idempotent and audited without storing the national ID, QR link,
credentials, or response body.

Phase 7 rollout is restricted to the reviewed UAT resource UUIDs configured by
the `MCP_ISSUE_LICENSE_*` variables. Before enabling writes, follow
[`docs/mcp/OPERATIONS.md`](docs/mcp/OPERATIONS.md) and run:

```text
php artisan mcp:readiness
php artisan mcp:evaluate
```

Example response:

```json
{
  "success": true,
  "request_id": "0f80a65d-0963-4b58-85b2-c78ec65ae50a",
  "status": "verified",
  "verified": true,
  "ocr": {"confidence": 0.91, "quality": "high"},
  "extracted": {"surname": "MOTLALEPUO", "forenames": "GARENOSI", "id_number": "436415528", "document_type": "botswana_identity_document"},
  "comparison": {
    "status": "verified",
    "all_fields_match": true,
    "fields": {
      "surname": {"provided": "Motlalepuo", "extracted": "MOTLALEPUO", "comparable": true, "match": true, "score": 1},
      "forenames": {"provided": "Garenosi", "extracted": "GARENOSI", "comparable": true, "match": true, "score": 1},
      "id_number": {"provided": "436415528", "extracted": "436415528", "comparable": true, "match": true, "score": 1}
    },
    "summary": {"matched": 3, "mismatched": 0, "unable_to_compare": 0}
  }
}
```

## Security and operations

The downloader accepts HTTPS only, blocks credentials in URLs, validates DNS, rejects private/reserved IPs, revalidates redirects, limits redirects, checks file signatures/MIME types, and enforces a size limit. Temporary original/enhanced files are deleted after processing by default. The database stores only a SHA-256 hash of the source URL.

Run this service behind HTTPS. Restrict the API key, database, and logs; define an audit-record retention period appropriate for Botswana privacy requirements. For higher volume, move verification into a Laravel queue and return `202 Accepted`; the current endpoint processes synchronously.

Tune `OCR_MIN_FIELD_SCORE` using a labeled set of real documents before production. OCR confidence describes text recognition quality; it must not be interpreted as proof that a document is genuine. This service checks data consistency, not document authenticity or tampering.
