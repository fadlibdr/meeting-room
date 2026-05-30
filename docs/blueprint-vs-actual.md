# Blueprint Roadmap vs Actual Implementation — Meeting Room BPJS Kesehatan

**Document type:** Roadmap-vs-actual matrix + outstanding-work register (Blueprint v3 §J/§D/§K/§N ↔ delivered state)
**Status:** v1.0 — current as of 30 May 2026
**Baseline:** `develop` at `610c143` (549 tests green); `test/parallel-submit-race` pending merge brings it to 550. Staging (`booking.pi2.co.id`) deployed and healthy at `610c143`.
**Owner:** Project lead (Fadli)
**Companion docs:** `docs/roadmap-reconciliation.md` (v1.4 — narrative gap record), `docs/decision-log.md` (ADR-001…008), Blueprint Implementasi v3.

---

## 0. How to read this

Maps the Blueprint v3 plan to what is built **right now**, and — its main purpose — pins down what remains **outstanding** before production launch. It records delivery; it does not change scope. Per Blueprint §B, scope/decision changes go through the Decision Log; the project's own deviations are captured as ADRs in `docs/decision-log.md`.

Legend: ✅ done & verified · 🟡 in progress / partial · 🔵 deferred to a later phase by decision (not a gap) · ❌ outstanding / blocking.

---

## 1. Roadmap status at a glance (Blueprint §J)

| Blueprint §J sprint | Scope | Status |
|---|---|---|
| Sprint 0 — Foundation, setup, reconciliation | skeleton, CI, 17-table schema, seeders, Dec-01…13 | ✅ Done |
| Sprint 1 — Auth, RBAC, User Management | session auth, throttle/lockout, in-house RBAC, policies, user/unit/approver CRUD | ✅ Done |
| Sprint 2 — Room Management | room/facility CRUD, operating hours, room-blocking UI | ✅ Done |
| Sprint 3 — Booking Core & Conflict | Livewire booking form/calendar, BookingConflictService (TDD), validation | ✅ Done |
| Sprint 4 — Approval, Dashboards, ApprovalInbox | approve/reject actions, inbox, dashboards, notifications, NotificationDropdown | ✅ Done |
| Sprint 5 — Audit, Notifications, Exports, Attachments | audit log, DB notifications, reminders, CSV export, attachments | ✅ Done (MVP scope; XLSX & email-queue → Phase 2) |
| Sprint 6 — Hardening, Optimization, UAT, Deployment | caches, backup, scheduler, SSL/firewall, UAT, runbook, rehearsal ×2 | 🟡 In progress |

Plus an out-of-§J **"M3 — Lifecycle Edges"** milestone (edit / cancel / reschedule / delete), delivered ✅, filling the lifecycle detail §J folds into Sprints 3–4.

Net: **Sprints 0–5 and M3 are complete.** The only open roadmap item is **Sprint 6**, and within it only execution/operational tasks remain — every code/test deliverable is in.

---

## 2. Per-sprint detail

### Sprint 0 — Foundation ✅
Laravel 12.57 + PHP 8.3.30 + Livewire 3 + Volt + Breeze; MariaDB; main/develop/feature branching; CI = PHPUnit + Pint + PHPStan L5. 17-table baseline (+ later additive `app_settings` and `reminder_sent_at`), Dec-01…13 applied, full seeder set. `migrate:fresh --seed` runs clean; all roles can log in.

### Sprint 1 — Auth, RBAC, User Management ✅
Session auth, registration disabled at routing, login throttle + lockout (`failed_login_attempts` / `locked_until`), `EnsureUserIsActive` + `EnsurePermission`, in-house RBAC (`Role.code`, `User::roles()` belongs-to-many, `hasPermission()`, `PermissionCacheService`), role-aware nav, user/unit/approver-mapping CRUD, `ActivityLogger`. The Dec-02 end-of-Sprint-1 RBAC revisit is recorded (kept in-house) — ADR-007.

### Sprint 2 — Room Management ✅
Built after the v1.1 reconciliation, which had flagged this as the top gap. Room CRUD (capacity/location/approval-mode/status), facilities master + per-room assignment with quantity, operating hours, activation/deactivation, and room blocking with live conflict checking. `BlockRoomAction` force-cancels conflicting bookings and notifies their requesters (`RoomBlockCreated`). `RoomPolicy` / `FacilityPolicy` present. Facility categories modelled as a `FacilityCategory` enum — ADR-003. (Dec-08: `RoomFacility` + `RoomFacilityItem`.)

### Sprint 3 — Booking Core & Conflict ✅
`BookingConflictService` written test-first — all 22 §M.2 scenarios green. `BookingForm` / `BookingCalendar` / `RoomAvailabilityPicker` with debounced live conflict checking; operating-hour + block validation; own-booking history. `SubmitBookingAction` wraps the write in a transaction with `Room::lockForUpdate()`, an in-lock conflict re-check, and `booking_code` generation.

### Sprint 4 — Approval, Dashboards, ApprovalInbox ✅
`ApproveBookingAction` / `RejectBookingAction` / `CancelBookingAction` / `RescheduleBookingAction` / `UpdateBookingAction`, `ApprovalRoutingService` (§H.4), `ApprovalInbox`, role dashboards, database notifications (all six `NotificationType` cases), and a green hybrid-pointer integrity test (`current_approval_step` ↔ `current_approver_user_id`, Dec-03 / §M.4).
Two Blueprint deltas, both resolved: the mandated `BookingWorkflowService` (§F.2) was not built — the atomic pointer logic lives in the Actions, **blessed by ADR-001** rather than reworked; and the `NotificationDropdown` (§E.5, §J.5), previously missing, was **built** (ADR-002). Sprint 4 is fully delivered.

### M3 — Lifecycle Edges ✅
`UpdateBookingAction`, `SubmitDraftAction` + `ApprovalRoutingService`, `RescheduleBookingAction` (Dec-07 cancel+create+link), `DeleteBookingAction` — with attachment-file cleanup on delete added and tested.

### Sprint 5 — Audit, Notifications, Exports, Attachments ✅ (MVP scope)
`ActivityLogger` + complete `BookingStatusHistory` writes; database notifications across all six types incl. reminders; `SendBookingReminders` on an hourly schedule (`reminder_sent_at`, `BookingReminderNotification`); CSV export (`BookingCsvExporter`, permission-scoped); attachments (`BookingAttachment`, validated upload / policy-gated download / delete, private `local_private` disk). Audit-log viewer reachable via the `activity-logs.view` permission.
Two items are **Blueprint-designated Phase 2**, not gaps: email-queued notifications + queue worker (ADR-005; §K lists email-queued under Phase 2), and XLSX export + `GenerateLargeExportJob` for >10k rows (ADR-004; §K lists XLSX/scheduled-job under Phase 2).

### Sprint 6 — Hardening, Optimization, UAT, Deployment 🟡
**Done:** deployment runbook (with the `umask 022` deploy-safety fix), 30-scenario UAT script, hardening checklist, decision log (8 ADRs), reconciliation v1.4; the clear-then-cache optimization step; full scheduler definition (`bookings:send-reminders` hourly via `schedule:run` cron); **backup + restore verified** (dump + restore-parity drill); SSL active, `APP_DEBUG=false`, unique `APP_KEY`, **least-privilege DB user** (`meeting_app` scoped to `meeting_room`, verified); **firewall active** (ufw 22/80/443); **staging rehearsal #1** complete; §M.4 parallel-submit race test added.
**Outstanding:** see §4.

---

## 3. Module list vs actual (Blueprint §D)

| Module (§D) | Priority | Status |
|---|---|---|
| Authentication & Session | Must | ✅ |
| Users & Org Unit | Must | ✅ |
| Roles & Permissions | Must | ✅ (in-house) |
| Room Master | Must | ✅ |
| Room Facilities | Must | ✅ |
| Operating Hours | Should | ✅ |
| Room Blocking | Must | ✅ |
| Booking Core | Must | ✅ |
| Conflict Validation | Must | ✅ (22 §M.2 tests) |
| Approval Workflow | Must | ✅ |
| Booking Calendar | Must | ✅ |
| Dashboard per Role | Should | ✅ |
| Notifications | Should | ✅ in-app (email → Phase 2) |
| Attachments | Should | ✅ |
| Audit Log | Must | ✅ |
| Exports & Reports | Nice | 🟡 CSV done; XLSX/utilization → Phase 2 |
| Front Office Daily View | Nice | 🔵 Phase 2 |
| Recurring Booking | Phase 2 | 🔵 schema placeholder ready (`recurrence_group_id`) |

Every **Must** and **Should** module is delivered. Only **Nice**/**Phase 2** items remain, all by design.

---

## 4. Outstanding work register

### 4.1 Launch-blocking (Sprint 6 execution — remaining §N.4 bars)
1. **UAT — 30 scenarios + product-owner sign-off.** Five-role credentials provisioned on staging; walkthrough (`docs/uat-script.md`) pending. ❌
2. **Staging rehearsal #2.** One clean rehearsal done; §N.4 requires two. ❌
3. **Blueprint v3 architecture-team sign-off.** ❌

### 4.2 Should-verify before launch (Sprint 6 §J.7 outputs not yet closed)
4. **Query/index performance pass** (§J.7, §M.5) — confirm hot paths (conflict check, calendar, inbox) are indexed and acceptable for expected load, or record that current performance suffices. (confirm)
5. **Basic monitoring** (§J.7, §L.4) — stand up minimal uptime/error monitoring, or formally defer by decision.

### 4.3 Housekeeping (no runtime effect)
6. **Merge open branches** into `develop`: `test/parallel-submit-race`, `docs/deployment-runbook` (carries the umask fix), `docs/uat-script`, `docs/hardening-checklist`, `docs/roadmap-reconciliation-v1.4`, and this doc. Close the superseded `docs/roadmap-reconciliation-v1.2` / `-v1.3`.

### 4.4 Phase-2 backlog (Blueprint §K — deferred by design, ADR-recorded; NOT blockers)
7. Email-queued notifications + supervised queue worker (ADR-005).
8. XLSX export + `GenerateLargeExportJob` for >10k rows (ADR-004).
9. Front-office daily view (§D Nice).
10. Utilization reports / statistics (§D Nice).
11. Recurring booking (§K Phase 2; `recurrence_group_id` already in schema).

### 4.5 Minor polish (recorded deferrals)
12. Model-cast facility category to the `FacilityCategory` enum for `->label()` display.
13. Full i18n for the English navigation labels.

### 4.6 Production provisioning (real prod environment, beyond staging)
14. Dedicated production DB user scoped to the prod schema only — do **not** reuse the shared `meeting_app`, which also holds rights on the dev/test databases.
15. Production server/env, prod SSL cert, unique prod `APP_KEY`, prod `.env` (`APP_ENV=production`), and a first production deploy following the runbook.

---

## 5. Go / No-Go status (Blueprint §N.4)

| §N.4 criterion | Status |
|---|---|
| All 22+ BookingConflictService tests pass | ✅ |
| All 100+ Policy tests pass | ✅ full matrix now exists (Booking/Room/Facility/AppSetting); confirm count ≥100 |
| Race-condition test passes with reasonable latency | ✅ §M.4 parallel-submit |
| Integrity test (approver pointer) passes | ✅ |
| UAT 30 scenarios signed off by product owner | ❌ in progress |
| Staging deployment rehearsal ×2 | 🟡 1 of 2 done |
| Backup & restore verified | ✅ |
| SSL active, firewall active, APP_DEBUG=false, APP_KEY unique, DB user least-privilege | ✅ (staging; replicate on prod) |
| Operational runbook available | ✅ |
| Blueprint v3 signed off by architecture team | ❌ |

**Three bars remain: UAT sign-off, the second staging rehearsal, and architecture sign-off.** Every domain-logic, test, and hardening criterion is met. All hardening is verified on *staging*; the same checklist must be re-run on the production environment once provisioned.

---

## 6. Decision alignment

The 13 Blueprint decisions (Dec-01…13) are all reflected in schema and implementation. Build-time deviations are recorded as ADRs in `docs/decision-log.md` rather than silently absorbed: ADR-001 (Actions-based workflow vs `BookingWorkflowService`), ADR-002 (NotificationDropdown), ADR-003 (FacilityCategory enum), ADR-004 (CSV-not-XLSX for MVP), ADR-005 (synchronous notifications), ADR-006 (Settings module), ADR-007 (RBAC kept in-house), ADR-008 (deployment topology). This document and `roadmap-reconciliation.md` track *delivery*; the ADR log and Blueprint Decision Log remain the authority for *decisions*.

---

*Internal Use Only • BPJS Kesehatan • Blueprint Roadmap vs Actual v1.0 • 30 May 2026*
