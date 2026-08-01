# Phase Acceptance Criteria

## Phase 0

- [x] Architecture and trust boundaries are version controlled.
- [x] Supabase user-token and RLS authorization flow is defined.
- [x] Model responsibilities and prohibitions are explicit.
- [x] Autonomy and exact-plan confirmation policies are defined.
- [x] Secret redaction and audit-field policies are defined.
- [x] BERZUDA gateway and UI integration boundaries are defined.
- [x] Model plan and workflow formats have machine-readable schemas.
- [x] `Endorsement-Complete` is recorded as the exact status value.

## Phase 1

- [x] Authenticated MCP HTTP endpoint exists.
- [x] Every MCP request requires Supabase authentication.
- [x] RequestLab queries use the caller's token and existing RLS.
- [x] Discovery and read tools are allowlisted.
- [x] Request/environment relationships are validated.
- [x] Unresolved variables produce structured errors.
- [x] Secret environment and authentication values are redacted.
- [x] No execution or mutation tool is exposed.
- [x] Existing document-verification behavior remains covered by passing tests.

## Gates for later phases

Phase 2 must provide reviewed table, endpoint, extraction, and workflow knowledge.

## Phase 2

- [x] RequestLab table responsibilities and relationships are version controlled.
- [x] Sensitive database fields are identified.
- [x] Endpoint documentation has a versioned profile and extraction convention.
- [x] Endpoint discovery is deterministically ranked inside an authorized workspace.
- [x] Discovery results state that documentation is not executable authority.
- [x] Named workflows are loaded from a reviewed manifest.
- [x] `issue_license` defines lookup, extraction, dependency, and write confirmation.
- [x] Missing `qr_link` or `reg_status` prevents the dependent write.
- [x] The exact write value is `Endorsement-Complete`.
- [x] Real RequestLab request IDs are mapped to both semantic workflow references.
- [x] Extraction paths are confirmed against a successful redacted UAT response.

Phase 3 must reject model output that fails `model-plan.schema.json` and must not
permit model-selected arbitrary URLs.

## Phase 3

- [x] Qwen is accessed through a replaceable model-client interface.
- [x] Ollama receives the model-plan JSON Schema in its `format` field.
- [x] Generation uses temperature `0` and non-streaming structured output.
- [x] Model JSON is decoded and validated again inside Laravel.
- [x] Model-selected tools are restricted to the read-only allowlist.
- [x] A model cannot change the authenticated UI context.
- [x] A write effect cannot suppress its confirmation requirement.
- [x] Invalid JSON, invented tools, and unavailable inference have stable errors.
- [x] Planning results explicitly report that execution is disabled.
- [x] Ollama is installed on the target runtime host.
- [x] `qwen2.5:1.5b` is pulled and verified on the target runtime host.
- [x] Phase 2 knowledge and workflow grounding is available.

Phase 4 must implement confirmation digests, expiry, idempotency, evidence, and
audit logging before enabling write tools.

## Phase 4

- [x] Read preparation executes only a reviewed stored request through BERZUDA.
- [x] BERZUDA validates an internal HMAC signature and the current Supabase user.
- [x] Supabase RLS remains authoritative during execution.
- [x] Stored request, method, collection, environment, and template relationships are revalidated.
- [x] Required `qr_link` and `reg_status` extraction failures stop the workflow.
- [x] Confirmation previews are bound to a canonical plan digest and expire.
- [x] Confirmation payloads are encrypted at rest.
- [x] The model-facing MCP tool cannot approve or execute the write.
- [x] Approval uses a separate authenticated HTTP route.
- [x] Idempotency prevents duplicate or changed operations.
- [x] Audit records omit national IDs, QR links, credentials, and response bodies.
- [x] Execution evidence is bounded and matched to the approved request.

Phase 5 must preserve BERZUDA accessibility, responsive behavior, existing themes,
and request-editor usability.

## Phase 5

- [x] BERZUDA has an authenticated server-side MCP gateway.
- [x] The right-side AI Agent panel receives trusted active context IDs.
- [x] Natural-language requests are planned by Qwen through Laravel.
- [x] Missing workflow inputs are shown conversationally.
- [x] Write previews expose explicit approve and cancel actions.
- [x] The AI Agent panel can be collapsed and reopened.
- [x] Responsive and reduced-motion styles are included.
- [x] TypeScript and the production Next.js build pass.
- [ ] Authenticated browser interaction and visual regression checks are completed.

## Phase 6

- [x] Conversations are scoped to the authenticated user and selected workspace.
- [x] Persisted message content is encrypted at rest.
- [x] Conversation history can be listed, reopened, and archived in BERZUDA.
- [x] Execution history exposes only redacted audit summaries and safe metadata.
- [x] Conversation and audit retention periods are configurable and pruned daily.
- [x] Historical chats cannot recreate an actionable write approval.
- [x] Laravel tests, BERZUDA TypeScript, lint, and production build pass.

## Phase 7

- [x] Write preparation and approval require an exact reviewed UAT allowlist match.
- [x] Workspace, collection, environment, request IDs, and workflow version are independently configured.
- [x] A repeatable live Qwen evaluation command fails on intent, missing-input, or tool-selection drift.
- [x] The reviewed Qwen evaluation set passes with `qwen2.5:1.5b`.
- [x] A production-readiness command checks storage, signing, HTTPS, model, and allowlist configuration.
- [x] Docker Compose includes a continuous Laravel scheduler service.
- [x] VPS rollout and fail-closed rollback steps are documented.
- [x] Persisted conversation context is bounded and re-authorized before model use.
- [x] Laravel, BERZUDA, Compose, and live-model checks pass.
