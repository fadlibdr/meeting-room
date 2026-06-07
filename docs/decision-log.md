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
**Status:** ~~Accepted (30 May 2026)~~ **Superseded by ADR-009 (6 Jun 2026)** — notifications now also send email and implement `ShouldQueue`.

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

### ADR-009 · Notifications send via `['database', 'mail']` and are queued (Stage 1 real email)
**Decision:** All six notifications (`BookingSubmitted`, `BookingApproved`, `BookingRejected`, `BookingReminder`, `BookingCancelled`, `RoomBlockCreated`) now `implement ShouldQueue` and send on both the `database` (in-app inbox) and `mail` channels, each with a Bahasa-Indonesia `toMail()`. The in-app `toArray()` payloads are unchanged.
**Context:** Deviation **D-2** ("live but silent") — the app was in production with `MAIL_MAILER=smtp` but every notification was database-channel only, so nothing ever emailed. This closes D-2: approval/rejection/cancellation/reminder/block emails now reach people.
**Consequence — queue dependence:** because the notifications are now queued, the **`database` channel also routes through the queue**. The default `jobs`/`failed_jobs` tables (removed in Sprint 0) are re-added by this change, and a **queue worker becomes mandatory in every non-`sync` environment** — without a jobs table + running worker, *both* email and in-app notifications stop. `sync` remains the test/`phpunit.xml` setting (notifications fire inline, mail captured by the `array` mailer), and is an acceptable low-volume fallback for staging.
**Stage 2 (future):** per-user notification preferences will gate the `mail` channel inside `via()` (e.g. return `['database']` when a user opts out of email).
**Status:** Accepted (6 Jun 2026, `feat/stage1-real-email`). Supersedes ADR-005.

### ADR-010 · Email transport + the notification email toggle are DB-backed (managed in Settings)
**Decision:** SMTP transport and the email-notification master switch are managed from the in-app Settings page (`app_settings`), not the `.env`:
- An **`email` settings group** (8 keys: `mailer`, `host`, `port`, `username`, `password`, `encryption`→SMTP `scheme`, `from_address`, `from_name`) seeded with `.env` defaults at seed time.
- The SMTP **password is stored encrypted** at rest — a new **`encrypted` `data_type`** (Laravel `Crypt`): ciphertext in the column, decrypted only in memory. `AppSetting::getCastedValue()` decrypts (null on failure); `SettingsService::serializeValue()` encrypts on write; encrypted values are never cached. *(Caveat: DB + `APP_KEY` together can decrypt it — keep `APP_KEY` out of the backups' blast radius.)*
- A new **`MailSettingsServiceProvider`** layers any non-empty `email.*` setting over `config('mail.*')` at boot (DB → `.env` → skip; empty never blanks a working credential; guarded if `app_settings` is absent/DB down; composes with `config:cache`).
- Notification `via()` now gates the `mail` channel on **`notifications.send_email_default`** (default `0` → **email is opt-in**); `database` is always present.
- Settings UI: the encrypted password renders as a **write-only** field (never emits the secret; blank = unchanged), and a **"Kirim Email Uji"** test-send verifies transport before flipping the toggle.
**Context:** Consolidates and supersedes ADR-009's standalone channel change — admins, not deployers, own email config; D-2 stays closed.
**Consequence — seeder is now value-preserving:** the deploy re-runs `AppSettingsSeeder --force`, so the seeder was changed to **create missing keys but never overwrite an existing value** (sync metadata only) — otherwise every deploy would wipe admin-edited SMTP config. Builds on ADR-009 (jobs table + worker still mandatory).
**Status:** Accepted (6 Jun 2026, `feat/email-in-settings`). Supersedes ADR-009 for the `via()`/transport mechanism.

### ADR-011 · Stage-1 monitoring is a self-contained scheduled health check (no external SaaS)
**Decision:** A `system:health-check` Artisan command (scheduled every 5 minutes, `withoutOverlapping`) checks DB connectivity, **queue-worker liveness** (oldest pending `jobs.created_at` > 5 min ⇒ worker likely dead), **failed-job backlog**, **free disk %** (< 15%), and **mail misconfig** (email toggle on while `mail.default` is still `log`). It logs, **exits non-zero** so an external cron/uptime monitor catches a hard failure, and alerts admins (`app-settings.update` holders) via a `SystemHealthNotification` — sent with **`Notification::sendNow`** (NOT queued: a dead worker is a primary failure mode, so the alert must not depend on the queue) and **throttled to once per 60 min**.
**Context:** Stage-1 "Monitoring & alerting". Chosen over an external APM/SaaS because the health check + logs meet the Stage-1 bar with no third-party account; the public `/up` endpoint stays a shallow boot check (no DB/queue/disk disclosure). Optional `sentry/sentry-laravel` remains available for stack-trace aggregation later.
**Status:** Accepted (6 Jun 2026, `feat/stage1-monitoring`). Builds on ADR-009 (the worker it watches).

### Stage-1 load / performance check (Step 5) — *to be recorded after the run*
Run `docs/loadtest.js` (k6) against **staging** and record p95 latency + error rate here:
- Date / commit:
- Scenario (VUs / duration / endpoints):
- p95 latency: ___ ms · error rate: ___ %  → within budget? (p95 < 800ms, errors < 1%)

### Stage-1 sign-offs (Step 6, D-4) — *artifacts to attach*
- [ ] UAT 30-scenario result (link / file in `docs/`):
- [ ] Architecture sign-off (link / file in `docs/`):

### ADR-012 · Large data exports are queued to disk, not streamed in-request
**Decision:** Bookings export now supports **CSV and XLSX** (openspout, streamed to a file so memory stays flat). A single `BookingExportRowMapper` owns the column layout so the two formats never drift, and a single `BookingExportQuery` builds the scoped+filtered query so the **on-screen list, the synchronous stream, and the queued job all apply identical scope/filters**. Exports **at or below `config('exports.sync_row_limit')` (default 1000)** stream directly in the request; larger ones create an `exports` row (`pending`), dispatch **`GenerateBookingExportJob`** (`ShouldQueue`), and the user gets an **`ExportReadyNotification`** (database always; mail gated by the global toggle + per-user opt-in, per ADR-010) with a download link. Files live on the **`local_private`** disk for **24h**, served by an **owner-only** `exports.download` route (404 — not 403 — for non-owners / pending / expired, so existence isn't leaked) and reaped hourly by **`exports:prune`**.
**Context:** Stage-2.2. Streaming a large XLSX inside the Livewire request risks timeouts/memory; XLSX is a zip and can't stream to `php://output` incrementally, so it is written to a temp/disk file either way. The row-count threshold keeps the common small export instant while making the large one reliable. Reuses the queue worker + jobs table from ADR-009.
**Consequence:** Adds the `exports` table (new migration) and the `openspout/openspout` dependency; the worker and hourly scheduler are now load-bearing for exports too.
**Status:** Accepted (6 Jun 2026, `feat/stage2-export-xlsx`). Builds on ADR-009 (worker) and ADR-010 (notification gating).

### ADR-013 · Internationalization is Indonesian-first with an opt-in English UI
**Decision:** The UI supports **`id` (default) and `en`**. A `SetLocale` middleware (appended to the `web` group) resolves the active locale by precedence **authenticated user's `users.locale` → guest session `locale` → `config('app.locale')`**, honouring only codes in `config('app.available_locales')`. Users switch via a **profile language select** (persisted) or a **header/login pill toggle** (`POST locale/{locale}`, session + persisted when authed). Translation mechanism is **two-track**: structured chrome uses PHP short-keys (`lang/{id,en}/nav.php`, `common.php`); body content keeps the existing **`__('Indonesian string')`** pattern with a single **`lang/en.json`** providing the English — so under the default `id` locale `__()` returns the Indonesian key verbatim (no `id.json` needed) and only `en` does a lookup.
**Context:** Stage-3.1. The app was built Indonesian-first with hardcoded strings; the JSON-string track lets the remaining ~75 views be internationalized incrementally (wrap a raw string in `__()`, add one `en.json` line) without a risky big-bang refactor. This PR delivers the full locale infrastructure + switchers and translates the global chrome, auth, profile, and dashboard; remaining admin/booking views follow in subsequent Q-locked PRs.
**Consequence — REQUIRED prod env change:** `APP_LOCALE` and `APP_FALLBACK_LOCALE` must be **`id`** (set in `.env`, `.env.example`, `phpunit.xml`). **If prod leaves `APP_LOCALE=en`, shipping `en.json` would flip the entire default UI to English.** The deploy of this release MUST set `APP_LOCALE=id` (and re-run `config:cache`) before/with the code rollout.
**Status:** Accepted (6 Jun 2026, `feat/stage3-i18n-foundation`). **Completed (6 Jun 2026, `v1.12.0`):** full coverage delivered across all booking + admin views via PRs #66, #69–#75 — **467** `en.json` keys, EN-render tests on chrome/calendar/show + 7 admin surfaces, a heuristic sweep confirming no unwrapped strings remain. The "remaining ~75 views" are done; the two-track mechanism (PHP short-keys for chrome + `en.json` for body) held throughout.

### ADR-014 · PWA is an installable shell with a conservative, network-first service worker
**Decision:** The app ships a **`manifest.webmanifest`** (standalone display, BPJS-blue `#005490` theme, 192/512 + maskable icons generated from the white logo via GD) linked from both layouts with the matching `theme-color` / apple-touch meta, plus a **service worker** registered from `app.js`. The SW is deliberately cautious for an authenticated CSRF/Livewire app: **navigations are network-first** with a cached **`/offline`** fallback (never serves a stale authenticated HTML page), **`/build/*` and `/images/*` are stale-while-revalidate** (content-hashed/immutable), and everything else (POST, Livewire updates, cross-origin) passes through. Responsive layout was already mature (off-canvas sidebar < 1024px, table `overflow-x` < 860px, topbar breakpoints), so 3.2 adds installability + offline rather than a CSS overhaul.
**Context:** Stage-3.2. A cache-first SW would have risked serving stale CSRF tokens / Livewire snapshots; network-first keeps correctness while still giving installability and a graceful offline page. SW registration failures are swallowed — the app is identical without it.
**Consequence:** `public/manifest.webmanifest`, `public/sw.js`, and `public/images/pwa/*` are committed (they live outside the gitignored `public/build`). The SW is served from the web root so its scope is `/`.
**Status:** Accepted (6 Jun 2026, `feat/stage3-responsive-pwa`).

### ADR-015 · Front-office check-in is a new permission + role, not a status change
**Decision:** Stage-4.1 adds a **`bookings.checked_in_at`** timestamp (nullable, UTC) and a **`bookings.check-in`** permission, granted to a new **`front_office`** role (view + view-all + check-in + rooms.view) plus `ga_admin` and `super_admin`. The reception desk uses a daily view (`/front-desk`, gated `bookings.check-in`) listing the selected day's **Approved** bookings chronologically, with an idempotent manual **check-in** (stamps `checked_in_at`, logs `bookings.check-in`) and an **undo** (clears it, logs `bookings.check-in-undo`). `BookingPolicy::checkIn` allows it only for Approved bookings held by a `bookings.check-in` grantee — org-wide scope by design (the desk checks anyone in).
**Context:** Check-in is an attendance fact, not a lifecycle transition — modelling it as a separate timestamp keeps the BookingStatus state machine (draft→submitted→approved→completed/…) untouched and avoids a new terminal status. A dedicated role matches the real persona without overloading GA Admin. The seeder’s `firstOrCreate`/`sync` keeps the new role + permission idempotent across `db:seed --force` deploys.
**Consequence:** New migration (`bookings.checked_in_at`) and a 6th seeded role. `super_admin` picks up the new permission automatically (syncs all).
**Status:** Accepted (6 Jun 2026, `feat/stage4-front-office-checkin`).

### ADR-016 · No-show auto-release is a bounded, system-actor cancel with a distinct `released_at` signal
**Decision:** Stage-3 A.1 adds `bookings.released_at` and a `bookings:auto-release` command (scheduled every 5 min) that cancels **ongoing** no-shows: status `Approved`, started more than `booking.auto_release_grace_minutes` (default 15, admin-tunable) ago, **not yet ended** (`now < ends_at`), never checked in, not already released. It runs through **`ReleaseNoShowBookingAction`** — a system action mirroring the cancel pipeline but with a **null actor** (the audit/history actor columns are already nullable), setting status→Cancelled + `cancelled_at` + **`released_at`** (the no-show signal that distinguishes an auto-release from a manual cancel for analytics), clearing the Dec-03 pointer, recording a `bookings.auto-release` audit row, and notifying the **requester** (`BookingAutoReleasedNotification`). A check-in (`checked_in_at`) makes a booking ineligible, so QR/manual check-in (A.3) naturally prevents release. `Booking::scopeAutoReleased()` backs the A.2 no-show metric.
**Context:** Bounding to in-progress meetings reclaims a wasted room immediately and — critically — avoids a **retroactive mass-cancel/email blast** over historical no-shows on the first run. Cancelled is non-locking, so releasing a slot can never create a conflict; the action still `lockForUpdate`s the row for race safety against a concurrent check-in. Idempotency is the `released_at` guard.
**Status:** Accepted (6 Jun 2026, `feat/stage3-auto-release`). Note: the brief's `stage3-auto-release.patch` was not provided, so A.1 was implemented from the spec; branched off `main` (v1.13.1) since `develop` is 67 commits stale.

### ADR-017 · QR self-check-in is a temporary-signed public route through the shared check-in action
**Decision:** Stage-3 A.3 adds a per-booking **QR** encoding a **temporary signed URL** (`URL::temporarySignedRoute('bookings.checkin', $booking->ends_at, …)`) — the signature is the credential, so the route is **public** (no login); the `signed` middleware 403s tampered or expired links (TTL = the meeting end). The controller then enforces the **window** (no earlier than 30 min before start, not after end) and booking state (Approved, not released, idempotent if already checked in), and stamps the check-in via a new shared **`CheckInBookingAction`** — the single check-in path now used by both the front-office daily view (Stage 4.1) and QR. A check-in makes the booking ineligible for no-show auto-release (A.1). Result is a standalone bilingual confirmation page (the booking page needs auth, which a guest scanner lacks). The QR (inline SVG via `simplesoftwareio/simple-qrcode`, no GD needed) renders on the **booking detail page** (for the requester) and the **front-office screen** (per pending meeting).
**Context:** Possession of a short-lived signed link is a reasonable self-service credential for an on-prem reception flow; identity can't be proven by a QR scan alone, so the signature + window + state checks are the security model. Extracting `CheckInBookingAction` keeps the desk and QR paths byte-identical (idempotent stamp + `bookings.check-in` audit row with a `via: desk|qr` marker).
**Consequence:** Adds the `simplesoftwareio/simple-qrcode` dependency.
**Status:** Accepted (6 Jun 2026, `feat/stage3-qr-checkin`). Closes Phase A (A.1 + A.2 + A.3).

### ADR-018 · Multi-step approval = configurable policy chains resolved to concrete approvers
**Decision:** Stage-3 B replaces single-step routing with a **chain**. A named, reusable **`approval_policies`** (+ ordered `approval_policy_steps`) is assignable to a **room and/or a unit**; resolution order at submit is **room policy > requester-unit policy > legacy per-room `approval_mode`** (so existing single-step behaviour is a 0/1-length chain — fully backward compatible). Each step resolves to ONE approver by type — **`unit_approver`** (the requester's own approver), **`role`** (lowest-id active holder), or **`user`** — then passes through **`approval_delegations`** (an active delegation re-routes an away approver to a stand-in, one hop) and is **de-duplicated** (no self-approval, no double-asking). `SubmitBookingAction`/`SubmitDraftAction` create N `booking_approvals` rows (seq 1..N) and point the **Dec-03 pointer** at step 1; `ApproveBookingAction` now **advances-or-finalizes** — approving a non-final step advances the pointer + `current_approver_user_id` atomically (status stays Submitted, the next approver is notified), the final step finalizes to Approved (requester notified). Reject at any step terminates.
**Context:** Chosen the configurable-policy-table option (vs per-room/per-unit only) for flexibility. The pointer ↔ approval-row invariant (IntegrityTest) is preserved at every transition; routing stays pure (`ApprovalRoutingService` → `ApprovalChainResolver` → `DelegationResolver`), the calling action owns the transaction and the room/booking `lockForUpdate`. Highest-blast-radius internal item: shipped engine-only, all 57 existing approval-engine tests stay green plus new chain/resolver/e2e suites.
**Consequence:** New tables (`approval_policies`, `approval_policy_steps`, `approval_delegations`) + `rooms.approval_policy_id` / `units.approval_policy_id`. Admin CRUD UI for policies & delegations is a tracked follow-up PR.
**Status:** Accepted (6 Jun 2026, `feat/stage3-approval-chains`).

### ADR-019 · Scheduled reports are emailed; the BI feed is push (scheduled CSV dump)
**Decision:** Stage-3 D adds **`reports:send --period=weekly|monthly`** — builds the previous complete week/month's booking XLSX (reusing `BookingXlsxExporter`), computes the utilization summary (`RoomUtilizationReport`), and queues a mail-only **`ScheduledReportNotification`** (XLSX attached from the report disk) to every active holder of `reports.view`. The **BI feed is push**, not pull: **`reports:bi-export`** writes a full bookings snapshot CSV (`BookingCsvExporter`, Jakarta-labelled times) to a configured disk/path (`bookings-latest.csv`, overwritten) for a BI tool to ingest. Scheduled weekly (Mon 07:00) / monthly (1st 07:00) / daily (06:00). The report notification is deliberately **not** gated by the per-user booking-notification opt-in — its audience is permission-resolved and it is an explicit admin-scheduled feature.
**Context:** Push chosen over a pull endpoint to avoid a new authenticated API surface for D (a tokened pull can be added later, or via Phase C's API). Everything reuses the Stage-2.2 exporters + the mail/queue/scheduler already in place, so one column layout serves the on-demand export, the scheduled report, and the BI feed.
**Consequence:** `config/reports.php` (recipient permission, BI/report disk + path). Files accumulate on the report disk (small, infrequent); add to a prune later if needed.
**Status:** Accepted (6 Jun 2026, `feat/stage3-scheduled-reports`).

### ADR-020 · Public API is Sanctum token-scoped and routes writes through the domain actions
**Decision:** Stage-3 C1 adds a versioned **`/api/v1`** authenticated by **Sanctum personal access tokens** with **abilities** (`read`, `booking:write`) — registered as `ability:` middleware. Read endpoints (`rooms`, `rooms/{room}/availability`, `bookings` = the token owner's) require `read`; **`POST bookings`** requires `booking:write` **and** authorises the caller via `BookingPolicy::create`, then routes through **`SubmitBookingAction`** — so conflict detection and the approval chain apply identically to the web flow (the API is never a bypass, and a token never exceeds its owner). Rate-limited 60/min per token (`throttle:api`). JSON via API Resources; timestamps UTC ISO-8601 (clients localise). Users self-manage tokens at `/api-tokens` (plaintext shown once). OpenAPI spec at `docs/openapi-v1.yaml`.
**Context:** Reusing the domain actions keeps one source of truth for booking rules across web + API. Abilities give least-privilege tokens (a read-only integration vs a booking-writer). Webhooks (C2) follow in a separate PR.
**Consequence:** Adds `laravel/sanctum` + the `personal_access_tokens` table + API routing in `bootstrap/app.php`.
**Status:** Accepted (6 Jun 2026, `feat/stage3-api-sanctum`). Phase C part 1 of 2 (webhooks next).

### ADR-021 · Webhooks are HMAC-signed, queued, retried, and fired post-commit from the actions
**Decision:** Stage-3 C2 adds outbound webhooks. **`webhook_subscriptions`** (url + per-subscription secret + subscribed events + active flag) and **`webhook_deliveries`** (one row per (subscription, event) dispatch, updated across retries). On a booking lifecycle event, **`WebhookDispatcher`** — called from each action's **post-commit** section (alongside the notifications, so a rolled-back transaction never emits) — fans out to active subscriptions `listeningFor` that event and queues a **`SendWebhookJob`** (`afterCommit`). The job signs the exact JSON body with **HMAC-SHA256** (`X-Webhook-Signature: sha256=…`), POSTs with a 10s timeout, records the response, and on non-2xx **throws to retry** (`tries=4`, backoff 10/60/300s); `failed()` marks the delivery `failed` after the final attempt. Events: `booking.{submitted,approved,rejected,cancelled,auto_released,checked_in}`. Managed at `/admin/webhooks` (app-settings.update); the secret is shown once.
**Context:** Explicit post-commit dispatch from the actions (vs a model observer) keeps emission aligned with the same point notifications fire and avoids double-firing on coupled column changes (e.g. release sets both status=cancelled and released_at). Signing the byte-exact body lets receivers verify integrity. Reuses the existing queue worker.
**Consequence:** Adds two tables + wiring lines in Submit/Approve/Reject/Cancel/Release/CheckIn actions. Completes Phase C.
**Status:** Accepted (6 Jun 2026, `feat/stage3-webhooks`). Phase C part 2 of 2.

### ADR-022 · Rooms generalize into bookable `resources`, rolled out in stages behind a type-scoped `Room`
**Decision:** Stage-3 E adopts the **full resources abstraction** (vs extending `rooms` with a type column): the `rooms` table is renamed to **`resources`** with a **`type`** discriminator (`room` default + `equipment`/`vehicle`/`desk`, see `ResourceType`) and a per-type **`metadata`** JSON bag, and a general **`Resource`** model owns the schedulable behaviour. To keep the rename safe across a ~215-reference room-centric codebase, it ships in stages, suite green at each: **E1 (this PR)** — table rename + `Resource` model + `ResourceType`; the legacy **`Room` becomes a subclass of `Resource`** with a global scope (`type = room`) and a `creating` stamp, so every existing query, relation, factory, controller and test behaves identically. `bookings.room_id` is **retained** (MySQL auto-repoints the FK at the renamed table) and gains a `Booking::resource()` accessor for unscoped (any-type) access. **E2** — generalize `ConflictService`/availability/calendar to `Resource` (widen `Room` hints to `Resource`; `Room` is-a `Resource`), a generic resource admin UI, and booking of non-room types. **E3 (optional)** — the `room_id` → `resource_id` column rename + reference sweep.
**Context:** The `metadata` column avoids Eloquent's reserved internal `$attributes`. Staging behind the `Room` subclass means the highest-blast-radius structural change lands with zero behavioural churn — the abstraction exists end-to-end (a non-room `Resource` can be created, typed, and carry metadata) before any subsystem is asked to consume it.
**Consequence:** Renames `rooms`→`resources` (+ `type`, `metadata`); adds `App\Models\Resource`, `App\Enums\ResourceType`, `ResourceFactory`, `Booking::resource()`. Child tables keep their `room_id` columns (now referencing `resources`) until E2/E3.
**Status:** Accepted (6 Jun 2026, `feat/stage3-resource-abstraction`). E1 shipped v1.21.0 · E2a (engine) v1.22.0 · E2b (admin CRUD) v1.23.0 · E2c (booking flow) v1.24.0.

### ADR-022b · E3 — `bookings.room_id` renamed to `resource_id` (breaking API field, with write alias)
**Decision:** Completes the milestone. The `bookings.room_id` column is renamed to **`resource_id`** (MySQL carries the FK across), and all internal reads/queries/relations follow (`Booking::room()`/`resource()` FK, conflict service, block action, reports, calendar, dashboard). Per an explicit product decision, the **public v1 API field is also renamed** (request `resource_id`, response nests `resource` not `room`, availability + webhook payload use `resource_id`) — a breaking change for API clients, softened by accepting the legacy **`room_id` as a deprecated write alias** (normalized at the action / FormRequest / API-controller boundary; documented `deprecated: true` in the OpenAPI spec). The room-specific child tables (`room_facility_items`, `room_operating_hours`, `room_block_schedules`) deliberately keep their `room_id` columns — they remain room concepts — as does the `?room_id=` web prefill param.
**Context:** Chosen over the internal-only or skip options. The write alias means existing integrations posting `room_id` keep working; only response readers must migrate `room`→`resource`. ~215 references swept across app + tests; the test sweep distinguished booking `room_id` (renamed) from block/facility/operating-hours columns and URL params (kept).
**Consequence:** Migration `rename_bookings_room_id_to_resource_id`; OpenAPI fields updated; `room_id` retained as a deprecated input alias only.
**Status:** Accepted (7 Jun 2026, `feat/stage3-resource-id-rename`). Phase E complete.

### ADR-023 · SSO is Entra ID (Azure AD) OIDC via Socialite, feature-flagged with JIT provisioning
**Decision:** Stage-3 F.1 adds **Microsoft Entra ID (Azure AD) OIDC** login using `laravel/socialite` + `socialiteproviders/microsoft-azure`. It is **off by default** (`config/sso.php` `enabled`, env `SSO_ENABLED`) — the redirect/callback routes 404 and the login page hides the SSO button until the Entra app credentials (`AZURE_TENANT_ID/CLIENT_ID/CLIENT_SECRET/REDIRECT_URI`) are set and the flag is on; email/password remains the fallback. On callback, **`SsoUserProvisioner`** matches by email and **JIT-provisions** unknown users (when `auto_provision`) with an unusable random local password (SSO-only accounts). Roles derive from the token's `groups` claim via `config('sso.group_role_map')` (Entra group object-id → role code): **when groups are present they are authoritative** (synced every login); otherwise a brand-new user gets `default_role` and existing users keep their roles (no clobber). Inactive users are refused.
**Context:** Chosen over SAML/LDAP (smaller, OIDC fits M365). Socialite keeps the flow standard + mockable — tests stub `Socialite::driver('azure')->user()` so the whole path (provision, group-map, login, inactive-refusal, flag-off 404) is covered without live credentials. Group-as-authoritative-only-when-present avoids wiping locally-granted admin roles for users whose token omits groups.
**Consequence:** Adds `laravel/socialite` + the Azure provider, `config/sso.php`, `config/services.php` azure block, `App\Services\Sso\SsoUserProvisioner`, `App\Http\Controllers\Auth\AzureSsoController`, two guest routes, a login button, and `.env.example` keys. No schema change. Activation is config-only (set AZURE_* + SSO_ENABLED in prod). Phase F part 1.
**Status:** Accepted (7 Jun 2026, `feat/stage3-sso-oidc`).

---

*Internal Use Only • BPJS Kesehatan • Architecture Decision Log v1.0*
