---
title: API Reference
description: HTTP API routes exposed by the package.
---

# API Reference

The package registers API controllers for compliance dashboards and workflows.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/overview` | Compliance overview |
| GET | `/settings` | Package settings |
| GET | `/dsar` | List DSAR requests |
| POST | `/dsar` | Create DSAR request |
| GET | `/dsar/{id}` | Show DSAR request |
| POST | `/dsar/{id}/execute` | Execute DSAR request |
| GET | `/consent` | List consent records |
| POST | `/consent/grant` | Grant consent |
| POST | `/consent/revoke` | Revoke consent |
| GET | `/risks` | List risk entries |
| POST | `/risks` | Create risk entry |
| GET | `/risks/{id}` | Show risk entry |
| PATCH | `/risks/{id}` | Update risk entry |
| GET | `/incidents` | List incidents |
| POST | `/incidents` | Open incident |
| GET | `/incidents/{id}` | Show incident |
| POST | `/incidents/{id}/transition` | Transition incident |
| GET | `/bias` | List bias snapshots |
| POST | `/bias/capture` | Capture bias snapshot |
| GET | `/human-reviews` | List human reviews |
| POST | `/human-reviews` | Create human review |
| POST | `/human-reviews/{id}/transition` | Transition human review |
| GET | `/attestations` | List attestations |
| POST | `/attestations` | Create attestation |
| GET | `/attestations/{id}` | Show attestation |
| GET | `/regulatory-amendments` | List amendments |
| GET | `/regulatory-amendments/{id}` | Show amendment |
| PATCH | `/regulatory-amendments/{id}` | Update amendment |
| POST | `/regulatory-amendments/poll` | Poll amendments |
| GET | `/tenants` | List tenants |
| POST | `/tenants` | Create tenant |
| GET | `/tenants/{slug}` | Show tenant |
| PATCH | `/tenants/{slug}` | Update tenant |
