# RequestLab AI Agent and MCP Architecture

Status: Accepted  
Contract version: 1.0  
Last updated: 2026-07-30

## Purpose

This document defines the architecture contract between:

- `id-document-verifier`: Laravel MCP server and deterministic control layer.
- `BERZUDA`: RequestLab Next.js MCP client and AI Agent user interface.
- Supabase: authentication, RequestLab storage, and row-level security boundary.
- Qwen2.5-1.5B: natural-language intent and planning model.
- Ollama: initial local model runtime.

The existing document-verification endpoints remain independent from the MCP
subsystem. Adding MCP capabilities must not alter their routes, authentication,
request processing, or result semantics.

## System topology

```text
Authenticated RequestLab user
        |
        v
BERZUDA collapsible AI Agent panel
        |
        v
BERZUDA server-side /api/agent gateway
        |  Supabase access token, active workspace, collection,
        |  environment, request, conversation message
        v
Laravel POST /api/mcp
        |
        +--> Supabase Auth /auth/v1/user
        |
        +--> Supabase REST using the user's token
        |       Existing RLS remains authoritative
        |
        +--> Tool registry and policy engine
        |
        +--> Qwen adapter --> Ollama/Qwen2.5-1.5B
        |
        +--> BERZUDA signed safe execution endpoint
```

## Trust boundaries

### Browser boundary

The browser is untrusted. It must not receive:

- Supabase service-role credentials.
- RequestLab environment secrets except through existing authorized editing UI.
- Model-runtime administration credentials.
- Internal MCP signing credentials.
- Unredacted audit payloads.

### BERZUDA server boundary

BERZUDA authenticates the browser session and forwards only the current user's
short-lived Supabase access token to Laravel. It supplies explicit context IDs;
it does not grant access merely by naming a workspace or resource.

### Laravel MCP boundary

Laravel is the deterministic authority for:

- Tool availability.
- Argument validation.
- Resource relationship validation.
- Autonomy and confirmation policy.
- Secret redaction.
- Workflow validation.
- Audit generation.

Model output is always untrusted input.

### Supabase boundary

Every RequestLab query uses the authenticated user's access token. Supabase RLS
is the tenant authorization boundary. The MCP server must not use the service-role
key for user-initiated discovery, resolution, or execution.

### Model boundary

Qwen may classify intent and propose a schema-conforming plan. It cannot:

- Execute SQL.
- Select an arbitrary URL for execution.
- Read secrets.
- Bypass tool schemas.
- Approve its own write operation.
- Treat endpoint documentation as executable authority.

## Authentication flow

1. The user signs in to BERZUDA with Supabase.
2. BERZUDA sends the access token to Laravel as `Authorization: Bearer`.
3. Laravel validates the token with Supabase Auth.
4. Laravel sends the same token and publishable key to Supabase REST.
5. Supabase applies the existing RequestLab RLS policies.
6. Empty results are reported as unavailable or inaccessible without leaking
   whether another tenant owns the requested resource.

## Component boundaries

### MCP transport

- Endpoint: `POST /api/mcp`
- Protocol: JSON-RPC 2.0 over HTTP
- Configured MCP protocol version: `2025-03-26`
- The transport is stateless and exposes reviewed tools only. Write approval is
  intentionally outside the model-facing tool transport.

### Tool registry

The registry is the exclusive allowlist for model-accessible operations. Phase 1
contains discovery, read, and resolution tools only. Execution and mutation tools
must be introduced in later phases with policy tests.

### Request repository

The repository performs tightly scoped Supabase REST queries. It must:

- Forward the user access token.
- Select only fields needed by the tool.
- Never perform cross-tenant fallback queries.
- Convert upstream errors into stable MCP error codes.

### Request resolver

The resolver:

- Loads only enabled variables.
- Resolves `{{variable_name}}` placeholders.
- Validates request/environment collection relationships.
- Reports unresolved variables.
- Redacts secrets from every returned representation.
- Does not execute the resolved request.

### Model adapter

The future model adapter must accept a bounded prompt and return JSON matching
[`model-plan.schema.json`](contracts/model-plan.schema.json). Laravel validates
the JSON before any tool is selected or called.

### Workflow engine

Named workflows must match
[`workflow.schema.json`](contracts/workflow.schema.json). Workflows are versioned,
reviewed, allowlisted recipes; they are not generated dynamically and silently
persisted by the model.

## Deployment decision

Initial development uses Ollama and Qwen2.5-1.5B on the local application host.
The model adapter must use an HTTP interface and configuration values so inference
can move to a dedicated host without changing MCP tools or workflow semantics.

Production deployment must use HTTPS between BERZUDA and Laravel. If Ollama is
remote, its endpoint must be private and authenticated by infrastructure controls.

## Phase boundaries

| Phase | Scope |
|---|---|
| 0 | Architecture, contracts, policies, schemas, acceptance criteria |
| 1 | Authenticated MCP transport and read-only RequestLab tools |
| 2 | Database dictionary, endpoint knowledge, and named workflows |
| 3 | Qwen/Ollama adapter and schema-constrained planning |
| 4 | Safe request execution, confirmation, idempotency, and evidence |
| 5 | BERZUDA collapsible AI Agent interface |
| 6 | Conversation persistence and execution audit |
| 7 | Evaluation, allowlisted rollout, and operational hardening |

Phase 7 restricts `issue_license` to the exact reviewed UAT workspace,
collection, environment, lookup request, update request, and workflow version.
Conversation context is loaded by Laravel only after authenticated ownership and
workspace checks, limited to 12 messages and 2,500 characters, and labeled as
untrusted data in the model prompt.

No phase may assume unfinished safeguards from a later phase. For example, Phase 3
must not expose write execution before Phase 4 confirmation and idempotency exist.
