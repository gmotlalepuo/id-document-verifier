# MCP Security, Autonomy, and Audit Policy

Status: Accepted  
Policy version: 1.0

## Autonomy modes

### `plan_only`

- May inspect authorized metadata.
- May resolve a request to a redacted preview.
- Must not execute any HTTP request.

### `read_only_auto`

- May automatically execute `GET`, `HEAD`, and `OPTIONS`.
- Applies only to stored, authorized RequestLab request IDs.
- Must still validate the selected environment, resolved host, and inputs.

### `confirm_writes`

- Treats `POST`, `PUT`, `PATCH`, and `DELETE` as state-changing by default.
- Also treats any endpoint explicitly classified as side-effecting as a write,
  regardless of its HTTP method.
- Requires a user confirmation bound to the exact plan.

### `scheduled_job`

- Deferred until a later release.
- Requires a named, versioned workflow and allowlisted request/environment IDs.
- Requires explicit administrative approval outside the model conversation.

## Confirmation contract

A write confirmation preview must include:

- Workspace and environment names.
- Workflow name and version, when applicable.
- Stored request ID and display name.
- HTTP method and redacted host/path preview.
- Non-secret input values.
- Intended state change.
- Values extracted from prior read steps.

A confirmation is valid only for the cryptographic digest of:

- Authenticated user ID.
- Workspace ID.
- Environment ID.
- Workflow name and version.
- Ordered request IDs.
- Methods and resolved non-secret arguments.
- Intended state transition.

Confirmations expire. Any change to the plan or arguments invalidates the
confirmation. The model cannot create or approve a confirmation.

The agreed licence status value is exactly:

```text
Endorsement-Complete
```

## Missing and ambiguous information

The system must ask for clarification rather than guess when:

- A required workflow input is absent.
- A required response extraction path is missing.
- More than one workspace, environment, endpoint, or record is a plausible match.
- A placeholder remains unresolved.
- The current registration status cannot be validated.
- Documentation conflicts with the stored request definition.

Example:

```json
{
  "code": "MISSING_REQUIRED_INPUT",
  "message": "The workflow needs reg_status before it can continue.",
  "missing_inputs": ["reg_status"]
}
```

## Endpoint authority

Documentation is ranking and explanation context only. Before execution Laravel
must validate the stored:

- Request ID and collection.
- Method and URL template.
- Headers and authentication type.
- Body and parameters.
- Selected environment.
- Resource relationships.
- Side-effect classification.

The model cannot create an executable endpoint from free-form documentation.

## Secret classification and redaction

Secret-looking keys include tokens, passwords, API keys, authorization headers,
cookies, secrets, and credentials. Redaction applies to:

- MCP tool results.
- Model prompts and responses.
- Chat persistence.
- Confirmation previews.
- Application logs.
- Audit records.
- Error details and stack-context metadata.

Secret values are represented as `[REDACTED]`. The redaction implementation must
also replace a known secret value if it appears inside another resolved string.

## Audit contract

Every future execution record must contain:

- Authenticated user ID.
- Workspace ID.
- Collection ID.
- Request ID.
- Environment ID.
- Workflow name and version, if used.
- UTC timestamp.
- Resolved hostname, never credentials or a complete sensitive URL.
- HTTP method.
- Autonomy mode.
- Confirmation ID and confirming user for writes.
- Idempotency key for writes.
- Status, duration, and redacted outcome summary.

Audit records must not contain:

- Access tokens.
- Bearer/basic credentials.
- API keys or secret environment values.
- Full sensitive response bodies.
- Raw model prompts containing user data.

Audit retention must be configured before production rollout.

## Stable error catalogue

| Code | Meaning |
|---|---|
| `AUTH_REQUIRED` | Missing, invalid, or expired user authentication |
| `AUTH_SERVICE_UNAVAILABLE` | Supabase Auth could not be reached |
| `WORKSPACE_ACCESS_DENIED` | RLS or authorization denied access |
| `RESOURCE_NOT_FOUND` | Resource absent or not visible to the user |
| `RESOURCE_RELATIONSHIP_INVALID` | Resource IDs do not share the required parent |
| `INVALID_TOOL_ARGUMENTS` | Tool input failed schema validation |
| `TOOL_NOT_FOUND` | Tool is not in the registry |
| `UNRESOLVED_VARIABLE` | One or more environment placeholders are unresolved |
| `MISSING_REQUIRED_INPUT` | A workflow input or extracted value is unavailable |
| `AMBIGUOUS_MATCH` | More than one safe candidate exists |
| `CONFIRMATION_REQUIRED` | A write plan is ready but not approved |
| `CONFIRMATION_EXPIRED` | Approval is no longer valid |
| `UNSAFE_URL` | Outbound URL failed safety policy |
| `REQUEST_TIMEOUT` | Upstream request exceeded the allowed time |
| `RESPONSE_TOO_LARGE` | Upstream response exceeded the allowed size |
| `UPSTREAM_ERROR` | Authorized upstream endpoint failed |
| `DATA_SERVICE_UNAVAILABLE` | RequestLab data could not be reached |
| `MODEL_UNAVAILABLE` | Qwen/Ollama could not be reached |
| `MODEL_OUTPUT_INVALID` | Model plan did not match its schema |

Errors shown to users must be concise and actionable. Internal diagnostics may be
logged only after redaction.

