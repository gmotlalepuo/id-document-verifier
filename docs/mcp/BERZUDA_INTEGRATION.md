# BERZUDA MCP Client Integration Contract

Status: Accepted  
Contract version: 1.0

## Server-side gateway

The BERZUDA browser must communicate with a same-origin server route such as
`POST /api/agent`. The route is responsible for:

1. Validating the Supabase browser session.
2. Obtaining the current short-lived access token.
3. Validating that supplied context IDs belong to the current UI state.
4. Calling Laravel `POST /api/mcp` with the bearer token.
5. Streaming or returning only redacted agent output.

The browser should not call Ollama directly.

## Context envelope

BERZUDA should provide explicit current context:

```json
{
  "message": "Issue a licence for national ID 436415528 in UAT.",
  "mode": "confirm_writes",
  "context": {
    "workspace_id": "uuid",
    "collection_id": "uuid-or-null",
    "environment_id": "uuid-or-null",
    "request_id": "uuid-or-null"
  },
  "conversation_id": "uuid-or-null"
}
```

The active workspace and environment are automatically supplied, but the panel
must display them. Laravel still validates every context ID through RLS.

## AI Agent panel contract

Desktop:

- Right-side, resizable panel.
- Default width between 360 and 420 pixels.
- Collapsible to an AI rail/button.
- Open state and width persisted per user.
- Current workspace and environment shown as context chips.

Mobile:

- Full-height drawer or dedicated agent surface.
- Must not create a narrow fifth workspace column.
- Must coexist with the existing mobile navigation.

Required interaction states:

- Empty/new chat.
- Planning.
- Running a read tool.
- Missing information.
- Confirmation required.
- Write in progress.
- Partial sequence failure.
- Success with evidence.
- MCP, model, authorization, and upstream failures.
- Expired confirmation.

Write approval controls must appear on the message containing the exact plan.
Approving a later, modified plan requires a new confirmation.

## Future safe execution route

The existing BERZUDA `/api/proxy` is browser-session and same-origin protected.
Laravel must not bypass those controls.

The recommended future design is a dedicated BERZUDA server-to-server execution
route that:

- Reuses the existing URL, redirect, header, timeout, and response-size checks.
- Accepts the current user's short-lived Supabase token.
- Validates a short-lived internal request signature from Laravel.
- Loads the stored RequestLab request and environment rather than accepting an
  arbitrary model-supplied URL.
- Returns structured, size-limited execution evidence.

The internal signing secret must never be sent to the browser or model.

### Deployment variables

Laravel and BERZUDA must use the same random value (at least 32 characters) for
`MCP_INTERNAL_SIGNING_SECRET`. Laravel also needs:

```text
BERZUDA_EXECUTION_URL=https://requestlab.example.com/api/mcp/execute
MCP_EXECUTION_TIMEOUT=45
```

BERZUDA loads the signing secret only at runtime. It is not a `NEXT_PUBLIC_*`
variable and must not be supplied as a Docker build argument. Rotate the secret
on both services together.
