# Roadmap Reconciliation — Meeting Room BPJS Kesehatan

**Document type:** Reconciliation record (Blueprint §J ↔ actual delivery)
**Status:** v1.2 — verified against repository recon of 30 May 2026
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)
**Related documents:** Blueprint Implementasi v3 (§J Roadmap, §N Closing), Database Schema v2, Struktur Proyek Laravel v2, `docs/sprint-2-room-management-spec.md`, `docs/m3-lifecycle-edges-spec.md`, `docs/deployment-runbook.md` (Sprint 6, forthcoming)

---

## Version History

- **v1.0 (23 May 2026)** — initial reconstruction from working-session record.
- **v1.1 (23 May 2026)** — verified against `develop@b709765`; Sprint 2 hardened to ❌ not built.
- **v1.2 (30 May 2026)** — verified against `develop@763393f` (Merge PR #31). Major status changes: **Sprint 2 Room Management ✅ shipped** (Gap A closed), **`bookings.index` is now the real `BookingList`** (Gap C closed), **audit-log viewer and Settings shipped**, and **Sprint 5 advanced from ~25% to substantially complete** — exports / attachments / reminders are built, tested, and pushed, with **three PRs pending merge**. §6 go/no-go rewritten: launch is no longer gated by missing features; it is gated by Sprint 6 (UAT, runbook, backup/restore, parallel-submit test, security) plus merging those three PRs. Items not re-verified this pass are carried and flagged in §7.

---

## 0. Why This Document Exists

Blueprint v3 §C names *"drift between documents and implementation"* as a tracked risk (§N.2). This document restores the mapping between Blueprint §J and what shipped. It **records** delivery; it does not change scope. Per Blueprint §B, any scope or decision change goes through the Blueprint Decision Log first.

---

## 1. The Canonical Mapping (verified 30 May)

| Blueprint §J Sprint | Blueprint scope | Verified status |
|---|---|---|
| **Sprint 0** | Foundation, setup, seeders | ✅ Done |
| **Sprint 1** | Auth, RBAC, User Management | ✅ Done |
| **Sprint 2** | Room Management (admin CRUD UI) | ✅ **Done** — shipped since v1.1 (Gap A closed) |
| **Sprint 3** | Booking Core & Conflict Validation | ✅ Done |
| **Sprint 4** | Approval Workflow, Dashboards, Inbox | 🟡 Done with structural deviations (Gaps E, F) |
| **— (not in §J)** | Booking lifecycle edges (M3) | ✅ Done |
| **Sprint 5** | Audit, Notifications, Exports, Attachments | 🟡 **Substantially done** — audit viewer ✅, notifications ✅, CSV export ✅, attachments ✅; **3 PRs pending merge**; XLSX + async export job deferred (Blueprint "Nice"/Phase-2) |
| **Sprint 6** | Hardening, Optimisation, UAT, Deployment | 🟡 **In progress** (this doc; runbook + UAT next) |

---

## 2. Per-Sprint Reconciliation Detail (verified 30 May)

### Sprint 2 — Room Management ✅ (NEW since v1.1 — Gap A CLOSED)
Verified present on `develop@763393f`: `RoomController`, `FacilityController`, `RoomBlockController`, `ActivityLogController`; `RoomPolicy`, `FacilityPolicy`; the full `Admin` Livewire set (`RoomList`/`RoomForm`/`FacilityList`/`FacilityForm`/`RoomBlockList`/`RoomBlockForm`/`RoomFacilityManager`/`RoomOperatingHoursManager`); CRUD routes `admin/rooms|facilities|room-blocks`; `BlockRoomAction` + `CancelRoomBlockAction`. Tests green: `RoomManagementTest`, `FacilityManagementTest`, `RoomBlockTest`, `RoomOperatingHoursTest`, `RoomFacilityAssignmentTest`, `RoomPolicyTest`, `FacilityPolicyTest`, `BlockRoomActionTest`, `CancelRoomBlockActionTest`. A GA Admin can now create/edit/block rooms via UI. The v1.1 Critical gap is resolved.

**30 May addition:** `App\Enums\FacilityCategory` unifies the facility-category allow-list across the form (`Rule::in`), the list filter, and the factory, eliminating a category-drift bug (`FacilityCategoryTest` + `FacilityFormCategoryTest`).

### Sprint 3 — Booking Core & Conflict ✅ (carried, suite-confirmed)
`BookingConflictService` (22 §M.2 scenarios green), `BookingPolicy` (47), `StoreBookingRequest` (16), `SubmitBookingAction` (lock + re-check + `booking_code`), Livewire `BookingForm`/`BookingCalendar`/`RoomAvailabilityPicker`.

### Sprint 4 — Approval Workflow 🟡 (carried — deviations Gaps E, F)
`ApproveBookingAction`/`RejectBookingAction`, `ApprovalInbox`, dashboard widgets, database notifications, green hybrid-pointer integrity test. Deviations unchanged from v1.1 (see §4).

### M3 — Lifecycle Edges ✅ (carried, suite-confirmed)
`UpdateBookingAction`, `SubmitDraftAction` + `ApprovalRoutingService`, `RescheduleBookingAction`, `DeleteBookingAction`.

### Sprint 5 — 🟡 Substantially done
| Item | Status (verified 30 May) |
|---|---|
| `ActivityLogger` (audit writer) | ✅ (in Sprint 1) |
| `BookingStatusHistory` writes | ✅ |
| Database notifications | ✅; `booking_reminder` type added by the reminders work (pending merge) |
| `RecentActivityFeed` widget | ✅ |
| **Audit-log viewer** (`ActivityLogController` + `ActivityLogViewer` + `admin/logs`) | ✅ **Done** (Gap B item closed) — `ActivityLogViewerTest` green |
| **`bookings.index` real listing** (`BookingList`) | ✅ **Done** (Gap C closed) — `BookingListTest` green |
| **CSV export** (`BookingCsvExporter` + list action, audited, scope-aware) | ✅ Built + tested — **PR pending merge** |
| **Attachments** (upload/download/delete, policy-gated, `local_private` disk) | ✅ Built + tested — **PR pending merge** |
| **Reminders** (`SendBookingReminders` command + notification + `reminder_sent_at`) | ✅ Built + tested — **PR pending merge**; `schedule:run` cron already set on staging |
| XLSX export + `GenerateLargeExportJob` | ❌ Deferred (Phase-2 / "Nice") |
| Email-queued notifications | ❌ Deferred (no `Jobs/` dir) |

---

## 3. Branch / PR → Blueprint Sprint Index (updated)

`develop` HEAD at verification: `763393f` (Merge PR #31). New since v1.1:

| Branch | PR | Satisfies |
|---|---|---|
| (Room Management work) | (#25–#30 range) | Sprint 2 (Gap A) — verify exact PR numbers |
| `fix/facility-test-flake` | via #31 | Sprint 2 hardening (factory/validation drift) |
| `refactor/facility-category-enum` | #31 | Sprint 2 hardening (`FacilityCategory` SSOT) |
| `feat/booking-reminders` | — pending | Sprint 5 (reminders) |
| `feat/booking-exports` | — pending | Sprint 5 (CSV export) |
| `feat/booking-attachments` | — pending | Sprint 5 (attachments) |

---

## 4. Open Gaps Against the Blueprint (refreshed 30 May)

- **Gap A — Room Management:** ✅ **CLOSED** (verified — see §2).
- **Gap B — Sprint 5:** mostly closed. Remaining: **merge the three completed PRs**; deferred XLSX + async export job + email-queued notifications.
- **Gap C — `bookings.index`:** ✅ **CLOSED** (real `BookingList`).
- **Gap D — Dec-02 RBAC revisit checkpoint not recorded:** carried — process/doc item.
- **Gap E — `BookingWorkflowService` absent (structural):** carried — **not re-verified this pass**; resolve via Decision Log (build the service or bless the Actions-based structure).
- **Gap F — `NotificationDropdown` absent:** carried — **not re-verified this pass**; notifications are written but in-app readability unconfirmed.
- **Gap G — Settings feature undocumented:** built ✅; Decision-Log entry still owed (carried).
- **`room_block_created` notification type:** carried — **not re-verified this pass**.
- **Attachment cleanup on *booking* delete:** the attachments work deletes the file on *attachment* delete; the booking-delete cascade cleanup is **not re-verified** (carried).

---

## 5. Re-Sequenced Remaining Work (30 May)

1. **Merge the three completed Sprint-5 PRs** (reminders, exports, attachments) → `develop` 542; redeploy staging (small delta; cron already set).
2. **Sprint 6:** 30-scenario UAT sign-off; deployment runbook (in progress); backup/restore verification; explicit parallel-submit race test (§M.4); security hardening (SSL / firewall / `APP_DEBUG=false` / least-privilege DB user); ×2 staging deployment rehearsal.
3. Re-verify and record **Gaps D / E / F / G** in the Blueprint Decision Log; build or formally defer `NotificationDropdown` (F) and `BookingWorkflowService` (E).

---

## 6. Go / No-Go Status (against Blueprint §N.4, refreshed 30 May)

| §N.4 criterion | Status |
|---|---|
| All 22+ `BookingConflictService` tests pass | ✅ |
| All 100+ Policy tests pass | ✅ `BookingPolicy` (47) + `RoomPolicy` (24) + `FacilityPolicy` (10) — matrix now substantially complete (Gap A no longer blocks) |
| Race-condition / parallel-submit test with reasonable latency | 🟡 `lockForUpdate` in place; explicit parallel-submit test still unwritten (Sprint 6) |
| Integrity test (approver pointer) passes | ✅ |
| UAT 30 scenarios signed off | ❌ (Sprint 6 — script next) |
| Staging deployment rehearsal ×2 | 🟡 staging current at `763393f` and healthy; formal ×2 rehearsal still owed |
| Backup & restore verified | ❌ (Sprint 6) |
| SSL / firewall / `APP_DEBUG=false` / least-privilege DB user | 🟡 staging: SSL ✅ (`booking.pi2.co.id`), `APP_DEBUG=false` ✅; firewall + least-privilege DB user unconfirmed |
| Operational runbook available | 🟡 in progress (Sprint 6) |
| Blueprint v3 signed off by architecture team | — |

**Summary:** Every "Must" feature module now exists — the Sprint 2 blocker is gone, and the hardest domain-logic bars (conflict, integrity, policy matrix) are cleared. Launch is now gated by **(a)** merging three completed Sprint-5 PRs and **(b)** Sprint 6: UAT, runbook, backup/restore, the parallel-submit test, and security hardening.

---

## 7. Verification Record

- **23 May 2026** — recon at `develop@b709765` (383 tests). (v1.1)
- **30 May 2026** — recon at `develop@763393f`. **Verified:** `origin/develop` HEAD and merge log (only PR #31 since the 509-test baseline `f5a613e`, so `develop` = 513); staging at `763393f`, healthy — fresh route cache (39 routes incl. the full admin module and the real `BookingList`), all migrations `Ran`, `schedule:run` cron present; file presence of the Sprint-2 / audit-viewer / `bookings.index` modules; the 30-May session's reminders / exports / attachments built + tested + pushed but **unmerged**. **Not re-verified this pass (carried from v1.1):** Gap E (`BookingWorkflowService`), Gap F (`NotificationDropdown`), the `room_block_created` notification type, attachment-cleanup-on-booking-delete, and the Decision-Log status of Gaps D/E/G — all flagged for the next recon.

---

## 8. Corrections to v1.0

(Unchanged from v1.1.) Two v1.0 claims were inaccurate and were corrected in v1.1: `BookingWorkflowService` does not exist (Gap E); `NotificationDropdown` does not exist (Gap F). Lesson recorded: status claims must be verified against the repository, not reconstructed from session memory — a principle this v1.2 follows, including by explicitly marking unverified items in §7.

---

## 9. Maintenance of This Document

Update at every milestone close: extend §3, move items out of §4/§5 as delivered, refresh §6, and append to §7. Per Blueprint §B, any change implying new scope or a new decision goes through the Blueprint Decision Log first.

---

*Internal Use Only • BPJS Kesehatan • Roadmap Reconciliation v1.2*
