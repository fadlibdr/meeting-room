# Sprint 2 Plan — Booking Core

**Status:** Planning only (Sprint 1 complete as of 2026-05-02 23:00 WIB)
**Sprint 2 start:** Tomorrow morning, fresh
**Estimated duration:** 5-7 days

---

## Foundation Already in Place (Sprint 0 + Sprint 1)

Verified during Sprint 1F-5 close:

- All 17 migrations applied and tested
- Booking model with full relationships + casts (app/Models/Booking.php)
- BookingApproval, BookingAttachment, BookingStatusHistory models exist
- BookingFactory with realistic data (database/factories/BookingFactory.php)
- BookingApprovalFactory, BookingAttachmentFactory, BookingStatusHistoryFactory exist
- All 5 enums with Indonesian labels (BookingStatus, RoomStatus, RoomApprovalMode, RoomBlockType, NotificationType)
- config/meeting_room.php with all Dec-XX constants
- BookingsSeeder produces 50 sample bookings across all statuses

**Implication:** No "scaffolding sprint" needed. Sprint 2 jumps straight into business logic.

---

## Phase Breakdown

### Phase 2A — BookingConflictService (TDD, 1-2 days)

**The most important work in the entire project.** Bugs here mean production double-bookings.

Process:
1. Read Blueprint Bagian H (Business Rules) end to end
2. Read Database Schema v2 §G.2 (Conflict Formula with Buffer)
3. Write 22+ test scenarios per Blueprint M.2 BEFORE any implementation
4. Implement BookingConflictService to pass tests
5. Refactor as needed; tests must remain green

Key edge cases:
- Buffer math: `(existing.ends_at + buffer_minutes) > requested.starts_at`
- Status filter: only `submitted` and `approved` lock slots
- Block schedules: hard locks, no buffer
- Operating hours: validate per day_of_week
- Capacity: `attendee_count <= room.capacity` unless admin override
- Duration: `<= max_booking_duration_hours` (default 8)
- Past datetime: reject for non-admin requesters
- DST: Indonesia doesn't have DST but UTC math should be consistent

Output:
- app/Services/BookingConflictService.php
- tests/Unit/Services/BookingConflictServiceTest.php (22+ test methods)

### Phase 2B — SubmitBookingAction + BookingPolicy (1 day)

After conflict service is solid, wire it into the submit workflow.

Output:
- app/Actions/SubmitBookingAction.php — DB::transaction + lockForUpdate
- app/Policies/BookingPolicy.php — view, create, update, delete, approve, cancel
- tests/Unit/Actions/SubmitBookingActionTest.php
- tests/Unit/Policies/BookingPolicyTest.php (matrix per blueprint)

### Phase 2C — Routes + Controllers (0.5 day)

Thin controllers, mostly delegation.

Output:
- app/Http/Controllers/BookingController.php (thin — list, show only)
- app/Http/Requests/Booking/StoreBookingRequest.php
- routes/web.php updates

### Phase 2D — BookingForm Livewire Component (1 day)

The interactive form with live conflict check.

Output:
- app/Livewire/Booking/BookingForm.php
- resources/views/livewire/booking/booking-form.blade.php
- tests/Feature/Livewire/BookingFormTest.php
- Integrate with SubmitBookingAction

### Phase 2E — BookingCalendar Livewire Component (1 day)

The calendar UI for browsing room availability.

Output:
- app/Livewire/Booking/BookingCalendar.php
- app/Livewire/Booking/RoomAvailabilityPicker.php
- resources/views/livewire/booking/booking-calendar.blade.php
- tests/Feature/Livewire/BookingCalendarTest.php

### Phase 2F — Cancel + Reschedule + Integration (0.5 day)

Output:
- app/Actions/CancelBookingAction.php
- app/Actions/RescheduleBookingAction.php (Dec-07: cancel + create new + link)
- End-to-end test: requester creates booking → submits → sees in calendar

---

## Hard Rules for Sprint 2

These are lessons banked from Sprint 1F. No exceptions.

1. **`git branch --show-current` before any `git add`.** Three wrong-branch incidents in Sprint 1F. No fourth.
2. **TDD for BookingConflictService.** Tests first. 22+ scenarios. No implementation until tests are written and red.
3. **Verify schema/code state before writing.** Sprint 0 may have done more than expected — check before duplicating.
4. **End-to-end UI smoke test before declaring done.** Both 1F-4 bugs passed isolated tests but failed real flow.
5. **No work within 6 hours of demo.** Pre-demo polish lives in a separate window.
6. **Stop at clean phase boundaries when fatigued.** Don't push through into high-stakes code with low energy.

---

## First Steps Tomorrow Morning

When you sit down fresh:

1. `cd ~/meeting-room-dev && git branch --show-current` (read aloud)
2. `git checkout develop && git pull origin develop`
3. `git checkout -b feat/sprint-2a-booking-conflict-service`
4. Open Blueprint v3 to Bagian H (Business Rules) — read it
5. Open Database Schema v2 §G.2 (Conflict Formula) — read it
6. Open Blueprint M.2 (Test Scenarios) — read all 22 scenarios
7. Open editor to `tests/Unit/Services/BookingConflictServiceTest.php` (doesn't exist yet)
8. Write 22 test method stubs first — just the names and what they test, no bodies
9. Then fill bodies one at a time, watch them go red
10. Then implement BookingConflictService to make them green

**Time estimate for tomorrow morning's first session:** 3-4 hours just to get all 22 tests written and red. Don't try to also implement the service in the same sitting. Implementation can be afternoon or next-day work.

---

## Open Items Carried from Sprint 1

These do NOT block Sprint 2:

- Email-based password delivery (post-MVP)
- Admin-initiated password reset (post-MVP)
- Move ops scripts to repo at scripts/staging/
- Add optimize:clear to deploy.sh before cache builds
- Wire migrate:fresh --seed into CI
- PHPDoc generics on relationships for full PHPStan type safety
- UU PDP retention policy doc

---

*Document version 1.0. Generated end of Sprint 1F session, 2026-05-02 23:00 WIB.*
