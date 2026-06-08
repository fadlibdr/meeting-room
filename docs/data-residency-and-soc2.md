# Data Residency & SOC 2 Posture (Stage 4f.5)

_Internal Use Only • BPJS Kesehatan • 8 Jun 2026_

This is a **design/business** note, not code. It records how residency is offered
and how the SOC 2-style control story is told (truthfully).

## 1. Residency model

The platform ships with **row-level multi-tenancy** (`tenant_id` + `BelongsToTenant`
global scope; see `docs/stage4a-multi-tenancy-design.md`). All tenants share one
database; isolation is enforced at the query layer.

For customers with **physical-separation / data-residency** requirements, the
residency lever is **database-per-tenant** — a per-customer option, **not the
default**. This was an explicit decision in the 4a design (ADR-029).

| Option | Default? | When to use | Cost |
|---|---|---|---|
| Row-level (shared DB) | ✅ yes | Standard tenants | Lowest |
| Database-per-tenant | no | Residency/regulatory isolation, noisy-neighbour isolation | Per-tenant provisioning + ops overhead |

Implementing DB-per-tenant is a follow-up project (connection-resolver per tenant +
provisioning automation). It is **documented as available** in the DPA; it is not
built until a customer requires it.

## 2. SOC 2 / ISO 27001 — process, not a feature

SOC 2-style assurance is an **audit/process track**, not something a code change
produces. We do **not** claim certification we do not hold. The public
[`/legal/security`](../resources/legal/security.md) page is where the posture is
told, and it lists — truthfully — what is **not** done:

- No independent **penetration test** (esp. tenant-isolation verification).
- No **recorded load test** under the multi-tenant configuration.
- No **SOC 2 / ISO 27001** certification.
- No formal **operational-readiness sign-offs** (D-12 security / D-8 load / D-4 ops).

## 3. Controls that DO exist (mapped to common SOC 2 criteria)

These are real and can be evidenced today; they are a starting point for a future
audit, not a claim of certification.

| Trust criterion | Control in place |
|---|---|
| Security — access control | RBAC with granular permissions; per-tenant roles |
| Security — isolation | Row-level tenant scope (default-deny global scope) |
| Security — encryption | HTTPS in transit; SMTP/OAuth secrets encrypted at rest (APP_KEY out of DB-backup blast radius) |
| Security — headers | Baseline security headers on every response |
| Confidentiality | UU PDP export + anonymisation; data-retention enforcement |
| Availability | Health checks + alerts; offsite DB backups + documented restore test |
| Processing integrity | Audit log + request correlation ids |
| Privacy | Consent gate (analytics off by default); privacy policy |

## 4. What must happen before a SOC 2 effort

1. Close the assurance tail: pen-test, recorded load test, readiness sign-offs.
2. Formalise change-management, access-review, and incident-response **evidence**
   (see `docs/sla-and-incident-comms.md`).
3. Engage an auditor; scope a Type I (point-in-time) before a Type II (period).

Until then, the honest position stands: strong controls, no certification yet.
