# Licence Workflow Binding Verification

The `issue_license` workflow bindings were verified on 2026-07-31.

- Collection: `UAT Endpoints` (`789a28c0-8094-4f7e-9d7c-ba6ba602caeb`)
- Environment: `User Acceptance Testing` (`c951fa51-33d4-471a-b2bd-9749c96b2d27`)
- Lookup request: `Registrations by ID` (`a9758cea-ef5b-4527-aa2b-3140f36f4524`)
- Lookup template: `{{teacher_reg_backend_base_url}}teacher_registrations/{{national_id}}`
- Update request: `update UAT status` (`105b8b1a-cee9-4e95-81c7-0b9f879f2f60`)
- Successful UAT lookup status: HTTP 200 JSON object
- `qr_link`: `$.teacher_registrations.qr_link`
- `reg_status`: `$.teacher_registrations.reg_status`
- Exact target value: `Endorsement-Complete`

No credentials, national IDs, QR links, or response bodies are stored in this
knowledge catalog. Runtime execution must still validate the saved request,
environment relationship, resolved host, required values, confirmation, and
idempotency key.
