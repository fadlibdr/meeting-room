# Roadmap Reconciliation — Meeting Room BPJS Kesehatan

**Document type:** Reconciliation record (Blueprint §J ↔ actual delivery)
**Status:** v1.3 — verified against `develop@d9f8a5d` (30 May 2026)
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)
**Related documents:** Blueprint Implementasi v3 (§J Roadmap, §N Closing), Database Schema v2, Struktur Proyek Laravel v2, `docs/deployment-runbook.md`, `docs/uat-script.md`, `docs/hardening-checklist.md`, `docs/decision-log.md`

---

## Version History

- **v1.0 (23 May 2026)** — initial reconstruction.
- **v1.1 (23 May 2026)** — verified against `b709765`; Sprint 2 ❌.
- **v1.2 (30 May 2026)** — verified against `763393f`: Sprint 2 ✅, `bookings.index` real, audit viewer + Settings ✅; Sprint 5 substantially complete with three PRs pending merge.
- **v1.3 (30 May 2026)** — verified against `d9f8a5d`. **Sprint 4 fully repaired:** Gap F closed (in-app `NotificationDropdown` built, tested, merged — suite 518), and Gaps E, D, G resolved via the new Architecture Decision Log (ADR-001 blesses the Actions-based workflow; ADR-007 closes the RBAC checkpoint; ADR-006 documents Settings). `RoomBlockCreated` confirmed dispatched (`BlockRoomAction:153`). Sprint 6 documentation set produced (runbook, UAT, hardening, decision log). Sprint 5 features (exports/attachments/reminders) remain built + **pending merge**.

---

## 1. Canonical Mapping (verified 30 May, develop@d9f8a5d)

| Blueprint §J Sprint | Verified status |
|---|---|
| **Sprint 0** — Foundation | ✅ Done |
| **Sprint 1** — Auth, RBAC, Users | ✅ Done |
| **Sprint 2** — Room Management | ✅ Done (Gap A closed) |
| **Sprint 3** — Booking Core & Conflict | ✅ Done |
| **Sprint 4** — Approval, Dashboards, Inbox | ✅ **Done** — deviations resolved (Gaps E/F/D/G; see §2, §4) |
| **— (not in §J)** — Lifecycle edges (M3) | ✅ Done |
| **Sprint 5** — Audit, Notifications, Exports, Attachments | 🟡 Audit ✅, notifications ✅ (now readable in-app), CSV export + attachments + reminders **built/tested/pushed, pending merge**; XLSX + async export deferred |
| **Sprint 6** — Hardening, UAT, Deployment | 🟡 Documentation complete (runbook/UAT/hardening/decision-log); execution + sign-off outstanding |

---

## 2. Per-Sprint Detail — changes since v1.2

### Sprint 4 — now fully resolved
- **Gap F (in-app notifications unreadable) — CLOSED.** `NotificationDropdown` (nav bell + unread badge + mark-read, scoped to the user) shipped and merged into `develop@d9f8a5d`; five feature tests; suite 518. The booking Actions' notification records are now visible in-app, including `RoomBlockCreated` cancellation notices.
- **Gap E (`BookingWorkflowService`) — RESOLVED by decision (ADR-001).** The Actions (`Submit/Approve/Reject/Cancel/Reschedule/Update`) + `ApprovalRoutingService` + `BookingConflictService` structure is accepted in place of a facade. No service built.
- **Gap D (RBAC checkpoint) — RESOLVED (ADR-007).** RBAC is enforced via gate-registered policies and treated as verified by their suites; UAT-04 is the human-facing check.
- **Gap G (Settings undocumented) — RESOLVED (ADR-006).**
- **`RoomBlockCreated` notification — CONFIRMED LIVE.** Dispatched at `BlockRoomAction:153` to each force-cancelled booking's requester (after commit).

### Sprint 5 — unchanged from v1.2
CSV export, attachments, and reminders are built, tested, and pushed on their branches but **not yet merged** to `develop`. `BookingReminder` and `RoomBlockCreated` enum cases are present on `develop`. XLSX + `GenerateLargeExportJob` and email-queued notifications remain deferred (ADR-004, ADR-005).

*(Sprints 0–3 and M3 unchanged from v1.2.)*

---

## 3. Branch / PR Index (updated)

`develop` HEAD: `d9f8a5d`. Merged since v1.2: `fix/notification-dropdown` (Gap F). Still **pending merge**: `feat/booking-reminders`, `feat/booking-exports`, `feat/booking-attachments`. Documentation branches awaiting batch merge: `docs/roadmap-reconciliation-v1.3` (supersedes the v1.2 branch), `docs/deployment-runbook`, `docs/uat-script`, `docs/hardening-checklist`, `docs/decision-log`.

---

## 4. Open Gaps (refreshed 30 May)

- **Gap A** (Room Management): ✅ CLOSED.
- **Gap B** (Sprint 5): remaining = **merge the three feature PRs**; XLSX/async export + email-queued deferred (ADR-004/005).
- **Gap C** (`bookings.index`): ✅ CLOSED.
- **Gap D**: ✅ RESOLVED (ADR-007).
- **Gap E**: ✅ RESOLVED (ADR-001).
- **Gap F**: ✅ CLOSED (built + merged).
- **Gap G**: ✅ RESOLVED (ADR-006).
- **`RoomBlockCreated`**: ✅ CONFIRMED dispatched.
- **Attachment cleanup on booking delete**: ⏳ pending — verify whether deletion already removes attachment files once the attachments PR lands; add cleanup only if missing.

---

## 5. Re-Sequenced Remaining Work (30 May)

1. **Merge the three Sprint-5 feature PRs** → `develop` (full feature set); redeploy staging via the runbook (delta: feature code + `reminder_sent_at` migration + `npm run build` + `optimize`).
2. **Post-merge:** verify/add attachment cleanup on booking delete (with a test); flip this and the three features to merged in this document.
3. **Sprint 6 execution:** run the UAT script, perform the backup/restore drill and the hardening checklist, add the explicit parallel-submit race test (§M.4), ×2 staging rehearsal.
4. Merge the documentation branches as one batch.

---

## 6. Go / No-Go (against Blueprint §N.4, refreshed 30 May)

| §N.4 criterion | Status |
|---|---|
| Conflict tests pass | ✅ |
| Policy tests pass | ✅ (matrix complete; Gaps A/D resolved) |
| Race-condition / parallel-submit test | 🟡 `lockForUpdate` in place; explicit test outstanding |
| Integrity test | ✅ |
| In-app notifications usable | ✅ (Gap F closed) |
| UAT 30 scenarios signed off | ❌ — script ready (`docs/uat-script.md`), execution pending |
| Staging rehearsal ×2 | 🟡 staging healthy at `d9f8a5d`; formal ×2 pending |
| Backup/restore verified | ❌ — drill ready (`docs/hardening-checklist.md` §6), execution pending |
| SSL / firewall / APP_DEBUG=false / least-priv DB | 🟡 SSL ✅, APP_DEBUG=false ✅; firewall + least-priv DB pending (procedures in hardening §7–8) |
| Operational runbook | ✅ (`docs/deployment-runbook.md`) |

**Summary:** All feature modules exist; Sprint 4 deviations are resolved and recorded. Launch is gated by merging the three completed Sprint-5 PRs and by **executing** the Sprint 6 procedures (UAT, backup/restore drill, hardening, race test) — the documents for which now exist. No feature work remains beyond the attachment-cleanup verify.

---

## 7. Verification Record

- **23 May 2026** — recon at `b709765` (383 tests). (v1.1)
- **30 May 2026 (a)** — recon at `763393f` (513): Sprint 2 / audit viewer / `bookings.index` / Settings verified; three Sprint-5 features built + pushed, unmerged. (v1.2)
- **30 May 2026 (b)** — recon at `d9f8a5d` (518): Gap F merged (`NotificationDropdown`, 5 tests); Architecture Decision Log created (ADR-001..008); `RoomBlockCreated` dispatch verified at `BlockRoomAction:153`; three Sprint-5 features confirmed still unmerged. **Pending verification (after the attachments PR merges):** attachment cleanup on booking delete.

---

## 8. Corrections to v1.0

(Unchanged.) v1.0's claims that `BookingWorkflowService` and `NotificationDropdown` existed were inaccurate; both were corrected in v1.1. As of v1.3, the `NotificationDropdown` now exists (built deliberately, Gap F), and the `BookingWorkflowService` is formally superseded by ADR-001.

---

## 9. Maintenance

Update at every milestone close: extend §3, move §4/§5 items as delivered, refresh §6, append §7. Per Blueprint §B, scope/decision changes go through the Decision Log (`docs/decision-log.md`) first.

---

*Internal Use Only • BPJS Kesehatan • Roadmap Reconciliation v1.3*
