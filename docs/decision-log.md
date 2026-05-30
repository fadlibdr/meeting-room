# Architecture Decision Log — Meeting Room BPJS Kesehatan

**Document type:** Decision record (Blueprint §B — "decisions go through the Decision Log")
**Status:** v1.0
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)

Records architectural and scope decisions that deviate from, extend, or clarify Blueprint v3. Per §B, decisions implying new scope are recorded here. Entries are append-only; supersessions are noted, not deleted.

---

### ADR-001 · Workflow orchestration via Actions, not a `BookingWorkflowService`
**Decision:** Booking workflow transitions are implemented as single-purpose Action classes (`SubmitBookingAction`, `ApproveBookingAction`, `RejectBookingAction`, `CancelBookingAction`, `RescheduleBookingAction`, `UpdateBookingAction`, plus draft/block actions), backed by two focused domain services — `ApprovalRoutingService` (who approves) and `BookingConflictService` (slot-conflict rules). The Blueprint's implied single `BookingWorkflowService` facade is **not** built.
**Context:** Reconciliation Gap E. A facade wrapping the Actions would add indirection with no clear benefit; the Action-per-use-case structure is cohesive, individually unit-tested, and reused by both the controllers and the Livewire components.
**Status:** Accepted (30 May 2026). Supersedes the Blueprint's `BookingWorkflowService`. Revisit only if cross-cutting orchestration (multi-step sagas, compensation) becomes necessary.

### ADR-002 · In-app notifications surfaced via `NotificationDropdown`
**Decision:** Database notifications are surfaced by a `NotificationDropdown` Livewire component (nav bell + unread badge + mark-read), reading the standard Laravel `notifications` table scoped to the current user.
**Context:** Reconciliation Gap F — notification *records* were written by the booking Actions but had no in-app reader, so they were invisible.
**Status:** Accepted (30 May 2026). Closes Gap F; UAT-G1 becomes a normal pass.

### ADR-003 · Facility categories as a `FacilityCategory` enum (single source of truth)
**Decision:** The facility-category allow-list (`av`, `furniture`, `connectivity`, `comfort`) is defined once in `App\Enums\FacilityCategory` and consumed by the form validation and the factory.
**Context:** A duplicated category list in the form vs. the factory drifted (`writing` vs the allow-list), producing an intermittently failing test. The enum removes the duplication structurally.
**Status:** Accepted (PR #31, 30 May 2026). The `facilities.category` column stays a plain string for now; casting it to the enum for label display is a noted follow-up.

### ADR-004 · Exports ship as synchronous CSV; XLSX and async export deferred
**Decision:** Booking export is a synchronous, scope-aware, audited CSV download (`BookingCsvExporter`). XLSX output and a `GenerateLargeExportJob` async path are deferred.
**Context:** Blueprint §J.6 designates light/synchronous export as the "Must" and XLSX/async as Phase-2. Current export volumes are small.
**Status:** Accepted deferral (30 May 2026). Revisit when export sizes warrant async generation.

### ADR-005 · Notifications send synchronously (no queue worker)
**Decision:** Notifications use the database channel and send synchronously; none implements `ShouldQueue`, and no queue worker runs. `QUEUE_CONNECTION=database` is configured but inert.
**Context:** Notifications are in-app only and low-volume; email-queued notifications are deferred.
**Status:** Accepted (30 May 2026). A supervised worker is to be added if/when async work (email, large exports) ships — see deployment runbook §8.

### ADR-006 · Application Settings module
**Decision:** A runtime Settings module (`SettingsManager` UI + `SettingsService` + `app_settings` table) manages configurable application behavior, gated by an `app-settings.view` permission.
**Context:** Reconciliation Gap G — the Settings feature exists but was outside Blueprint §J scope.
**Status:** Accepted (30 May 2026). The exact set of configurable keys is owned by the project lead; this entry records the module's existence and governance.

### ADR-007 · RBAC matrix verified via policy test suites
**Decision:** The role/permission matrix is enforced through gate-registered policies (`BookingPolicy`, `RoomPolicy`, `FacilityPolicy`) and treated as verified by their test suites rather than by a separate manual checkpoint artifact.
**Context:** Reconciliation Gap D — a planned RBAC revisit checkpoint was not recorded as a standalone document; the policy test coverage satisfies the underlying intent.
**Status:** Accepted (30 May 2026). Closes Gap D as a documentation gap; UAT-04 provides the human-facing confirmation.

### ADR-008 · Two-checkout deployment topology on the VPS
**Decision:** The VPS runs a served staging checkout (`/var/www/meeting-room`, `APP_ENV=staging`, DB `meeting_room`) separate from a dev/test checkout (`~/meeting-room-dev`, `APP_ENV=local`, DB `meeting_room_dev`). The suite runs only in the dev checkout; the served app is never used for test runs.
**Context:** Isolating the test database and `RefreshDatabase` runs from the live staging instance.
**Status:** Accepted (30 May 2026). Documented operationally in deployment runbook §2.

---

*Internal Use Only • BPJS Kesehatan • Architecture Decision Log v1.0*
