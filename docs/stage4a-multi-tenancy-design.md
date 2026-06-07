# Stage 4a — Multi-Tenancy Design (project plan, pre-implementation)

**Status:** Design / decision document. **No tenancy code is written yet.**
**Grounded on:** `v1.33.1` (29 tables, 26 models, 809 tests, single-tenant).
**Why a design doc first:** 4a is the single highest-blast-radius change in the
project's history — `tenant_id` on every table, a global scope on every query,
re-auditing every policy/export/webhook/conflict path. The brief mandates
treating it as its own project with a design + spike, never a one-pass retrofit.
This document is the gate: review + approve the decisions below before any code.

---

## 1. Decision: isolation model

| Option | Isolation | Ops cost | Data residency | Verdict |
|---|---|---|---|---|
| **Row-level** (`tenant_id` + global scope) | Logical (app-enforced) | Low (one DB) | Weak (shared DB) | **Recommended default** |
| **DB-per-tenant** | Physical (separate DB/schema) | High (N DBs, N migrations, connection routing) | Strong | Per-customer option |
| Schema-per-tenant | Medium | Medium-high | Medium | Not recommended (MySQL pain) |

**Recommendation:** **row-level first**, with **DB-per-tenant available as a
per-customer upgrade** for clients whose data-residency/contract demands physical
isolation (likely for some gov customers). Build the tenant *resolution* layer so
the isolation strategy is swappable without rewriting domain code.

**Risk of row-level:** a single missing global scope = cross-tenant data leak.
Mitigations are non-negotiable (§4).

## 2. Tenant resolution & context
- `tenants` table: id, name, slug, primary_domain, status, plan_id, settings.
- Resolution middleware: subdomain (`acme.app.example`) or custom domain →
  `Tenant`; bind into a `TenantContext` (container singleton) for the request.
- Console/queue: tenant must be set explicitly (jobs serialize `tenant_id`;
  scheduled commands loop tenants). **The conflict engine, webhooks, reports,
  exports, and the calendar-sync job all run outside a web request — each must
  set tenant context explicitly.** This is the easiest place to leak.

## 3. Schema & data work
- Add `tenant_id` (FK, indexed) to every tenant-owned table — ~25 of 29 (exclude
  framework tables: migrations, jobs, cache, sessions, personal_access_tokens is
  per-user→per-tenant via user).
- **Composite uniqueness:** every unique constraint becomes `(tenant_id, …)` —
  e.g. `resources.code`, `bookings.booking_code`, `users.email` (email unique
  *per tenant* — a person can exist in two tenants), `app_settings.key`,
  `roles.code`. This is a schema migration on top of the existing 30 releases.
- `BelongsToTenant` trait: a global scope (`where tenant_id = current`) + auto-set
  `tenant_id` on create. Applied to all tenant-owned models.
- **Backfill:** create a "BPJS Kesehatan" tenant; set `tenant_id` on every
  existing row to it in a single migration. Irreversible in practice — snapshot
  first.

## 4. Mandatory leakage controls (the whole ballgame)
1. **Default-deny global scope** on every tenant model; a base test asserts no
   tenant model lacks the trait (reflection test).
2. **Cross-tenant test harness:** seed 2 tenants; for every list/show/export/API
   endpoint, assert tenant B cannot see tenant A's rows (IDOR + scope bypass).
3. Re-audit: every `Model::withoutGlobalScopes()`, every raw `DB::` query, every
   `whereHas`, the conflict engine (`resource_id` lookups), webhook dispatch
   (subscriptions are per-tenant), reports/BI export, calendar sync job.
4. Re-run the **entire 809-test suite under tenancy** (a `tenant_id` is set in the
   base TestCase) + the new cross-tenant suite.
5. Unique-token surfaces (calendar feed token, signed check-in URL, API tokens)
   must resolve tenant from the token, not the host.

## 5. Phased plan (each its own PR, suite green)
- **P0 — Spike (timeboxed):** `tenants` + `BelongsToTenant` + resolution on **one
  vertical** (resources + bookings) behind a flag; prove the cross-tenant harness
  catches a deliberately-unscoped query. Validate the approach before committing.
- **P1 — Schema:** `tenant_id` + composite uniques across all tenant tables;
  backfill migration into the BPJS tenant.
- **P2 — Models/scope:** trait on all models; tenant context middleware + console/
  queue context; fix every leak the harness finds.
- **P3 — Subsystems:** conflict engine, approvals, webhooks, reports/BI, exports,
  calendar sync, settings (per-tenant) re-audited + tested.
- **P4 — Full regression:** entire suite under tenancy + cross-tenant suite green;
  load test re-run.

## 6. Downstream (depend on 4a)
- **4b White-label** (M): per-tenant logo/colours/domain/email-sender on the
  design-token system. Depends on 4a + tenant settings.
- **4c Self-service onboarding** (L): signup → automated tenant provisioning →
  setup wizard → trial.
- **4e Provider console** (M/L): manage tenants/plans/usage, support
  impersonation (with audit), per-tenant feature flags.
- **4d Billing** (L): use a provider — Stripe (Cashier) globally, Midtrans/Xendit
  for Indonesia. Do **not** build billing. Plans/seats/usage/dunning/trial→paid.
- **4g Support/lifecycle**, **4h GTM**: largely non-engineering.

## 7. Recommendation to proceed
Approve §1 (row-level + DB-per-tenant option) and §4 (leakage controls as a hard
gate), then authorize **P0 the spike** as the next unit of work. Do not start P1
schema until the spike proves the harness + scope approach. Estimate: 4a is a
multi-week milestone on its own; 4b–4h follow only after 4a is regression-clean.
