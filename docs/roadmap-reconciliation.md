# Roadmap Reconciliation — Meeting Room BPJS Kesehatan

**Document type:** Reconciliation record (Blueprint §J ↔ actual delivery)
**Status:** v1.4 — verified against `develop@b9c17d3` (30 May 2026)
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)
**Related documents:** Blueprint Implementasi v3 (§J Roadmap, §N Closing), Database Schema v2, Struktur Proyek Laravel v2, `docs/deployment-runbook.md`, `docs/uat-script.md`, `docs/hardening-checklist.md`, `docs/decision-log.md`

---

## Version History

- **v1.0–v1.1 (23 May 2026)** — initial reconstruction; verified against `b709765` (Sprint 2 ❌).
- **v1.2 (30 May 2026)** — verified against `763393f`: Sprint 2 ✅, `bookings.index` real, audit viewer + Settings ✅; Sprint 5 substantially complete with three PRs pending merge.
- **v1.3 (30 May 2026)** — verified against `d9f8a5d`: Sprint 4 repaired (Gap F merged; Gaps E/D/G recorded in the new decision log; `RoomBlockCreated` confirmed dispatched).
- **v1.4 (30 May 2026)** — verified against `develop@b9c17d3` (547). **Repair phase code-complete:** all three Sprint-5 feature PRs (reminders/exports/attachments) merged into `develop`; the attachment-cleanup-on-delete gap fixed (`fix/attachment-cleanup-on-delete`, suite 549) and pushed, pending merge with the documentation branches. **Every reconciliation gap is now resolved or closed.** What remains is operational: merge the open branches, redeploy staging, and execute the Sprint 6 procedures.

---

## 1. Canonical Mapping (verified 30 May, develop@b9c17d3)

| Blueprint §J Sprint | Verified status |
|---|---|
| **Sprint 0** — Foundation | ✅ Done |
| **Sprint 1** — Auth, RBAC, Users | ✅ Done |
| **Sprint 2** — Room Management | ✅ Done (Gap A closed) |
| **Sprint 3** — Booking Core & Conflict | ✅ Done |
| **Sprint 4** — Approval, Dashboards, Inbox | ✅ Done (deviations resolved — Gaps E/F/D/G) |
| **— (not in §J)** — Lifecycle edges (M3) | ✅ Done |
| **Sprint 5** — Audit, Notifications, Exports, Attachments | ✅ **Done** — audit, notifications (readable in-app), CSV export, attachments, reminders all merged; XLSX + async export deferred (ADR-004) |
| **Sprint 6** — Hardening, UAT, Deployment | 🟡 Documentation complete; **execution** + sign-off outstanding |

---

## 2. Per-Sprint Detail — changes since v1.3

### Sprint 5 — features merged; cleanup fixed
All three feature PRs are merged into `develop@b9c17d3`:
- **CSV export** — `BookingCsvExporter` + the `BookingList` export action (scope-aware, audited).
- **Attachments** — `BookingAttachmentController` (upload/download/delete, policy-gated) + the `local_private` disk + the Lampiran UI.
- **Reminders** — `SendBookingReminders` command + `BookingReminderNotification` + the `reminder_sent_at` column, scheduled via `routes/console.php`.

The attachment-cleanup-on-booking-delete gap is **fixed**: `DeleteBookingAction` now captures each attachment's `disk`+`path` and purges the files from storage after the transaction commits (the `cascadeOnDelete` FK removes only the rows). Two tests cover it; suite 549. On `fix/attachment-cleanup-on-delete`, pending merge.

XLSX export + `GenerateLargeExportJob` and email-queued notifications remain deferred (ADR-004, ADR-005).

*(Sprints 0–4 and M3 unchanged from v1.3 — Sprint 4 fully resolved there.)*

---

## 3. Branch / PR Index (updated)

`develop` HEAD: `b9c17d3` (547). Merged since v1.3: the three feature PRs (reminders/exports/attachments) and the v1.3 reconciliation. **Open branches pending merge:** `fix/attachment-cleanup-on-delete` (549), plus the documentation branches `docs/roadmap-reconciliation-v1.4`, `docs/deployment-runbook`, `docs/uat-script`, `docs/hardening-checklist`, `docs/decision-log`.

---

## 4. Open Gaps (refreshed 30 May)

- **Gap A** (Room Management): ✅ CLOSED.
- **Gap B** (Sprint 5): ✅ CLOSED — export/attachments/reminders merged; XLSX/async export + email-queued deferred (ADR-004/005).
- **Gap C** (`bookings.index`): ✅ CLOSED.
- **Gap D** (RBAC checkpoint): ✅ RESOLVED (ADR-007).
- **Gap E** (`BookingWorkflowService`): ✅ RESOLVED (ADR-001).
- **Gap F** (in-app notifications): ✅ CLOSED (built + merged).
- **Gap G** (Settings): ✅ RESOLVED (ADR-006).
- **`RoomBlockCreated`**: ✅ CONFIRMED dispatched.
- **Attachment cleanup on booking delete**: ✅ RESOLVED — files purged post-commit (`fix/attachment-cleanup-on-delete`, pending merge).

**No open gaps remain. Every item is resolved, closed, or formally deferred via the decision log.**

---

## 5. Remaining Work (30 May) — operational, not feature

1. **Merge the open branches** (`fix/attachment-cleanup-on-delete` + the five documentation branches) → `develop`.
2. **Redeploy staging** via the runbook to bring reminders/exports/attachments live (delta: merged feature code + the `reminder_sent_at` migration + `npm run build` + `optimize`).
3. **Execute the Sprint 6 procedures:** run the UAT script (`docs/uat-script.md`), perform the backup/restore drill and the hardening checklist (`docs/hardening-checklist.md` §6–8), add the explicit parallel-submit race test (§M.4), and complete the ×2 staging rehearsal.

---

## 6. Go / No-Go (against Blueprint §N.4, refreshed 30 May)

| §N.4 criterion | Status |
|---|---|
| Conflict tests pass | ✅ |
| Policy tests pass | ✅ (matrix complete) |
| Race-condition / parallel-submit test | 🟡 `lockForUpdate` in place; explicit test outstanding |
| Integrity test | ✅ |
| In-app notifications usable | ✅ (Gap F closed) |
| UAT 30 scenarios signed off | ❌ — script ready, execution pending |
| Staging rehearsal ×2 | 🟡 staging healthy; redeploy + formal ×2 pending |
| Backup/restore verified | ❌ — drill ready, execution pending |
| SSL / firewall / APP_DEBUG=false / least-priv DB | 🟡 SSL ✅, APP_DEBUG=false ✅; firewall + least-priv DB pending (procedures in hardening §7–8) |
| Operational runbook | ✅ |

**Summary:** All feature and gap work is complete — no code remains beyond merging the open branches. Launch is now gated solely by **executing** the Sprint 6 procedures (UAT, backup/restore drill, hardening, race test) and merging/redeploying. The hard correctness bars (conflict, integrity, policy matrix) are cleared.

---

## 7. Verification Record

- **23 May 2026** — recon at `b709765` (383). (v1.1)
- **30 May 2026 (a)** — recon at `763393f` (513): Sprint 2 / audit viewer / `bookings.index` / Settings verified; three Sprint-5 features pushed, unmerged. (v1.2)
- **30 May 2026 (b)** — recon at `d9f8a5d` (518): Gap F merged; decision log created (ADR-001..008); `RoomBlockCreated` dispatch verified at `BlockRoomAction:153`. (v1.3)
- **30 May 2026 (c)** — recon at `develop@b9c17d3` (547): three Sprint-5 feature PRs merged. Attachment-cleanup-on-delete fixed and verified — `DeleteBookingAction` purges files post-commit, 2 tests, suite 549 — on `fix/attachment-cleanup-on-delete`. All reconciliation gaps resolved or closed.

---

## 8. Corrections to v1.0

(Unchanged.) v1.0's claims that `BookingWorkflowService` and `NotificationDropdown` existed were inaccurate; corrected in v1.1. As of v1.3+, `NotificationDropdown` exists (built deliberately, Gap F) and `BookingWorkflowService` is formally superseded by ADR-001.

---

## 9. Maintenance

Update at every milestone close: extend §3, move §4/§5 items as delivered, refresh §6, append §7. Per Blueprint §B, scope/decision changes go through the Decision Log (`docs/decision-log.md`) first.

---

*Internal Use Only • BPJS Kesehatan • Roadmap Reconciliation v1.4*
