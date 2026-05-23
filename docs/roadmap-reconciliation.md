# Roadmap Reconciliation — Meeting Room BPJS Kesehatan

**Document type:** Reconciliation record (Blueprint §J ↔ actual delivery)
**Status:** v1.1 — verified against repository recon of 23 May 2026
**Date:** 23 May 2026
**Owner:** Project lead (Fadli)
**Related documents:** Blueprint Implementasi v3 (§J Roadmap, §N Closing), Database Schema v2, Struktur Proyek Laravel v2, `docs/sprint-2-plan.md`, `docs/sprint-2b-conflict-scenarios.md`, `docs/m1-submit-ui-spec.md`, `docs/sprint-2d-approval-workflow-spec.md`, `docs/m3-lifecycle-edges-spec.md`

---

## Version History

- **v1.0 (23 May 2026)** — initial reconstruction from working-session record.
- **v1.1 (23 May 2026)** — verified against a live repository recon (`develop`
  at `b709765`). Two v1.0 claims were corrected (see §8). Sprint 2 status
  hardened from ⚠️ unverified to ❌ not built. New drift items recorded.

---

## 0. Why This Document Exists

Blueprint v3 §C names *"drift between documents and implementation"* as a tracked
project risk (§N.2). That drift occurred — as a naming/sequencing divergence
after Sprint 1, and (v1.1 finding) as genuine **scope gaps**.

After Sprint 1 the team stopped following Blueprint §J's numbering. What §J calls
Sprints 2, 3 and 4 were re-organised into a "Sprint 2" umbrella with sub-phases,
then "Phase 2 Pieces 1–3", then a three-milestone **"Booking Lifecycle Arc"**
(M1 / M2 / M3).

This document restores the mapping between Blueprint §J and what shipped. It
**records** delivery; it does not change scope. Per Blueprint §B, any scope or
decision change must go through the Blueprint Decision Log first.

---

## 1. The Canonical Mapping (verified)

| Blueprint §J Sprint | Blueprint scope | Actual project label(s) | Verified status |
|---|---|---|---|
| **Sprint 0** | Foundation, setup, seeders | Sprint 0 | ✅ Done |
| **Sprint 1** | Auth, RBAC, User Management | Sprint 1 (1A–1F) | ✅ Done |
| **Sprint 2** | Room Management (admin CRUD UI) | `docs/sprint-2-plan.md` only | ❌ **Not built — see §4 Gap A** |
| **Sprint 3** | Booking Core & Conflict Validation | "Sprint 2B" + "Phase 2 Pieces 1–3" + **M1** | ✅ Done |
| **Sprint 4** | Approval Workflow, Dashboards, Inbox | "Sprint 2D" / **M2** | 🟡 Done with structural deviations — see §4 Gaps E, F |
| **— (not in §J)** | Booking lifecycle edges | **M3** (edit / cancel / reschedule / delete) | ✅ Done |
| **Sprint 5** | Audit, Notifications, Exports, Attachments | Partially pulled forward | 🟡 Partial (~25%) |
| **Sprint 6** | Hardening, Optimisation, UAT, Deployment | — | ❌ Not started |

### Milestone-arc equivalence

| Arc milestone | Blueprint equivalent | Spec document |
|---|---|---|
| M1 — Submit UI | Sprint 3 (UI portion) | `docs/m1-submit-ui-spec.md` |
| M2 — Approval UI | Sprint 4 | `docs/sprint-2d-approval-workflow-spec.md` |
| M3 — Lifecycle Edges | (no direct §J equivalent) | `docs/m3-lifecycle-edges-spec.md` |

---

## 2. Per-Sprint Reconciliation Detail (verified)

### Sprint 0 — Foundation ✅
Laravel 12.57 + PHP 8.3.30 + Livewire 3 + Breeze; MariaDB; VPS staging; GitHub
with main/develop/feature; CI (PHPUnit + Pint + PHPStan L5); migrations and the
full seeder set. `migrate:fresh --seed` runs clean. **Verified note:** 19
migration files on disk; Database Schema v2 specified 18 (17 tables + the
self-reference migration). The surplus is consistent with the unplanned Settings
feature (§4 Gap G) plus Laravel defaults — confirm exactly with
`php artisan migrate:status`.

### Sprint 1 — Auth, RBAC, User Management ✅
Session auth, registration disabled at routing, login throttling/lockout,
in-house RBAC, `PermissionCacheService`, `EnsurePermission`, role-aware nav,
user-management UI (`UserList` / `UserForm` Livewire), `ActivityLogger`
(pulled forward from Sprint 5). Merged via PRs #7/#11/#12/#13. **Open process
item — §4 Gap D:** the Dec-02 RBAC revisit checkpoint is not recorded in `docs/`.

### Sprint 2 — Room Management ❌ NOT BUILT
**Verified absent.** Repository recon shows: no `RoomController` /
`FacilityController` / `RoomBlockController`; no `RoomPolicy` / `FacilityPolicy`;
no `BlockRoomAction`; no `admin/facilities` or `admin/room-blocks` routes; admin
views directory contains only `users`. The only room routes — `admin/rooms` and
`rooms` — are `GET`-only closures returning read-only views (no controller, no
Livewire binding). The Room/Facility/Block *models, factories and seeders* exist
(from Sprint 0); the *admin module* does not. `docs/sprint-2-plan.md` exists —
Sprint 2 was planned but never implemented. A GA Admin currently cannot create,
edit, or block a room without a seeder or `tinker`. This is a "Must"-priority
module in Blueprint §D. **Highest-priority remaining work.**

### Sprint 3 equivalent — Booking Core & Conflict ✅
`BookingConflictService` (test-first, 22 §M.2 scenarios green), `BookingPolicy`
(47 tests), `StoreBookingRequest` (16 tests), `SubmitBookingAction` (transaction
+ `Room::lockForUpdate()` + conflict re-check + `booking_code`), and Livewire
`BookingForm` / `BookingCalendar` / `RoomAvailabilityPicker` with debounced live
conflict checking. Merged via PR #22, deployed.

### Sprint 4 equivalent — Approval Workflow 🟡 DEVIATIONS
Functionally delivered: `ApproveBookingAction` + `RejectBookingAction` (TDD),
`ApprovalInbox` Livewire component, dashboard widgets, database notifications,
and a **green hybrid-pointer integrity test** (Dec-03 / §M.4 satisfied).
**Structural deviations from the Blueprint (verified):**
- `BookingWorkflowService` (mandated by §F.2 / Struktur §B.1) **does not exist**.
  The atomic pointer logic lives inside the Approve/Reject Actions, with
  `ApprovalRoutingService` handling routing. It works, but the structure differs.
- `NotificationDropdown` Livewire component (§E.5, 1 of 5 planned) **does not
  exist** — notifications are written but there is no navbar dropdown to read
  them. 4 of 5 planned Livewire components were built.

### M3 — Lifecycle Edges ✅
`UpdateBookingAction`, `SubmitDraftAction` + `ApprovalRoutingService` (unplanned,
correctly extracted), `RescheduleBookingAction`, `DeleteBookingAction`.
383 tests green, merged via PR #24 (`develop` at `b709765`), deployed.

### Sprint 5 — Audit, Notifications, Exports, Attachments 🟡 ~25%
See §4 Gap B.

### Sprint 6 — Hardening, UAT, Deployment ❌
Not started.

---

## 3. Branch / PR → Blueprint Sprint Index (verified where possible)

| Branch / artefact | PR | Blueprint sprint satisfied |
|---|---|---|
| `feat/folder-structure` | #2 | Sprint 0 |
| `feat/seeder` | #7 | Sprint 0 |
| `feat/rbac` | #11 | Sprint 1 |
| `feat/layout-and-nav` | #12 | Sprint 1 |
| `feat/user-management` | #13 | Sprint 1 |
| `docs/sprint-2-plan.md` | — | Sprint 2 — **planning only, not implemented** |
| (conflict-service work) + `feat/m1-submit-ui` | #22 | Sprint 3 |
| `feat/sprint-2d-approval-workflow` | — | Sprint 4 |
| `feat/m3-lifecycle-edges` | #24 | M3 (lifecycle edges) |

`develop` HEAD at verification: `b709765` (Merge PR #24).

---

## 4. Open Gaps Against the Blueprint (verified)

### Gap A — Room Management module (Blueprint Sprint 2) NOT BUILT
**Severity: Critical.** A "Must"-priority module with zero admin UI. Confirmed by
recon. **Top of the queue (§5 item 1).**

### Gap B — Sprint 5 is ~25% done

| Sprint 5 item | Verified status |
|---|---|
| `ActivityLogger` (audit writer) | ✅ Done (pulled into Sprint 1) |
| `BookingStatusHistory` writes | ✅ Done (within booking actions) |
| Database notifications | ✅ Done — but only 4 of 6 `NotificationType` values (missing `booking_reminder`, `room_block_created`) |
| `RecentActivityFeed` dashboard widget | ✅ Done |
| Audit-log **viewer page** (`ActivityLogController` + §D.6 views) | ❌ Not built |
| Email-queued notifications | ❌ Not built (no `Jobs/` dir) |
| CSV/XLSX exports + `GenerateLargeExportJob` | ❌ Not built |
| Attachments (upload / download, policy-gated) | ❌ Not built |

### Gap C — `bookings.index` is a placeholder
Verified: route resolves to a closure with no controller/Livewire binding.

### Gap D — Dec-02 RBAC revisit checkpoint not recorded
Verified: no such doc in `docs/`. Record retroactively (§5 item 6).

### Gap E — `BookingWorkflowService` absent (structural)
Mandated by Blueprint §F.2 / Struktur §B.1. Pointer logic lives in the Actions
instead. Functionally correct (integrity test green); structurally divergent.
Decision: either build the service, or amend the Blueprint to bless the current
structure. Route through the Decision Log per §B.

### Gap F — `NotificationDropdown` Livewire component absent
Planned in §E.5. Notifications are written but unreadable in-app. Build it, or
record an explicit decision to defer.

### Gap G — Unplanned scope: Settings feature
`SettingsService` + `SettingsManager` Livewire component exist with no Blueprint
mandate. Not harmful, but it is undocumented drift — record it in the Blueprint
Decision Log as a new decision (e.g. Dec-14) so the Blueprint stays authoritative.

### Carried deferrals (from M3 spec §7)
- Attachment-file cleanup on delete (blocked on Gap B — attachments).
- The unenforced `starts_at > now()` cancel guard (Blueprint §H.5).

---

## 5. Re-Sequenced Remaining Work (verified priorities)

1. **Room Management module (Gap A)** — `RoomController`, `FacilityController`,
   `RoomBlockController`, `RoomPolicy`, `FacilityPolicy`, `BlockRoomAction`,
   `admin/rooms|facilities|room-blocks` views. The app is not operable without it.
2. **Real `bookings.index` listing (Gap C)** — exports and front-office views
   depend on it.
3. **Attachments (Gap B)** — closes the M3 file-cleanup deferral.
4. **Audit-log viewer page (Gap B).**
5. **Exports — CSV/XLSX + `GenerateLargeExportJob` (Gap B).**
6. **Record Gaps D, E, G** in the Blueprint Decision Log; build or formally
   defer `NotificationDropdown` (Gap F) and `BookingWorkflowService` (Gap E).
7. **Email-queued notifications + the 2 missing notification types (Gap B).**
8. **Sprint 6** — hardening, performance tuning, 30-scenario UAT, runbook,
   backup/restore verification, production deployment.

---

## 6. Go / No-Go Status (against Blueprint §N.4)

Production launch is **not** permitted. Verified standing:

| §N.4 criterion | Status |
|---|---|
| All 22+ `BookingConflictService` tests pass | ✅ |
| All 100+ Policy tests pass | 🟡 `BookingPolicy` (47) done; `RoomPolicy`/`FacilityPolicy` cannot exist (Gap A); full matrix incomplete |
| Race-condition test passes with reasonable latency | 🟡 `lockForUpdate` in place; explicit parallel-submit test (§M.4) unconfirmed |
| Integrity test (approver pointer) passes | ✅ |
| UAT 30 scenarios signed off | ❌ |
| Staging deployment rehearsal ×2 | 🟡 Multiple staging deploys done; not a formal rehearsal |
| Backup & restore verified | ❌ |
| SSL / firewall / `APP_DEBUG=false` / least-privilege DB user | 🟡 Partial (staging only) |
| Operational runbook available | ❌ |
| Blueprint v3 signed off by architecture team | — |

**Summary:** the hardest *domain-logic* bars are cleared (conflict, integrity).
Launch is blocked by a missing "Must" module (Sprint 2), most of Sprint 5, and
all of Sprint 6.

---

## 7. Verification Record

- **23 May 2026** — repository recon executed on `develop` at `b709765`
  (383 tests passing, 822 assertions). Recon covered: route list, controllers,
  policies, admin views, Actions, Services, Livewire components, `docs/`.
- Result: Sprint 2 confirmed not built; Gaps B/C/D confirmed; new Gaps E/F/G
  identified; two v1.0 claims corrected (see §8).

---

## 8. Corrections to v1.0

Recorded transparently for audit integrity. v1.0 was reconstructed from the
working-session record before a repository recon; two claims were inaccurate:

1. **`BookingWorkflowService`** — v1.0 stated M2 delivered it. It does not
   exist; pointer logic is in the Actions (now Gap E).
2. **`NotificationDropdown`** — v1.0 listed it as delivered in M2. It does not
   exist (now Gap F).

Both are corrected throughout v1.1. The lesson recorded for future
reconciliations: status claims must be verified against the repository, not
reconstructed from session memory.

---

## 9. Maintenance of This Document

Update at every milestone close: extend §3, move items out of §4/§5 as
delivered, refresh §6, and append to §7. Per Blueprint §B, any change implying
new scope or a new decision goes through the Blueprint Decision Log first — this
document only records delivery against existing decisions.

---

*Internal Use Only • BPJS Kesehatan • Roadmap Reconciliation v1.1*
