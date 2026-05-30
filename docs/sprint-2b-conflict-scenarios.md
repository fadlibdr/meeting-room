# Sprint 2B — BookingConflictService Test Scenarios

**Purpose:** Authoritative test specification for `App\Services\BookingConflictService`. Implementation must satisfy every scenario in this document. Tests in `tests/Unit/Services/BookingConflictServiceTest.php` map 1:1 to scenarios listed here.

**Source:** Blueprint Implementasi v3 Bagian M.2 (22 scenarios), filtered to service scope per Sprint 2B Phase 0 architectural decisions.

## Architectural Decisions (Locked at Phase 0)

1. **Scope**: Slot/time conflicts only — room overlap, block overlap, operating hours.
2. **Out of scope** (handled elsewhere):
   - Input shape validation (`ends_at > starts_at`, max 8h duration) → Form Request
   - Capacity validation (`attendee_count vs room.capacity`) → Form Request
   - Retrospective booking authorization → Policy
3. **Public API**:
   - `assertNoConflict(Room $room, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $excludeBookingId = null): void` — throws `BookingConflictException` on conflict
   - `findConflicts(Room $room, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $excludeBookingId = null): Collection` — returns conflicts for UI display
4. **Buffer hierarchy** (Dec-10b):
   - If `room.booking_buffer_minutes > 0` → use room's value
   - Else → use `settings->get('booking.default_buffer_minutes')` (system default, seeded as 15)
5. **Locking**: Service is read-only. Caller (e.g. `SubmitBookingAction`) wraps in `DB::transaction` + `lockForUpdate` for race safety.

## Conflict Formula (Reference)
For BOOKING (with buffer):
existing.starts_at < requested.ends_at
AND (existing.ends_at + effective_buffer_minutes) > requested.starts_at
For BLOCK (no buffer applied):
block.starts_at < requested.ends_at
AND block.ends_at > requested.starts_at
Locking statuses: {submitted, approved}
Non-locking statuses: {draft, rejected, cancelled, completed}

All datetimes are UTC at DB level (per Dec-09).

## Scenarios — In Scope (14)

### Scenario 1: Empty room during operating hours
- **Setup**: Room A active, Mon-Fri 08:00-17:00, no existing bookings
- **Request**: Mon 10:00-11:00 (UTC)
- **Expected**: Valid (no conflict)
- **Test method**: `test_no_conflict_when_room_is_empty_during_operating_hours`

### Scenario 4: Outside operating hours (before open_time)
- **Setup**: Room A active, Mon 08:00-17:00, no existing bookings
- **Request**: Mon 07:00-09:00 (starts before 08:00 open)
- **Expected**: Invalid (operating hours conflict)
- **Test method**: `test_conflict_when_booking_starts_before_operating_hours_open`

### Scenario 4b: Outside operating hours (after close_time)
- **Setup**: Room A active, Mon 08:00-17:00, no existing bookings
- **Request**: Mon 16:00-18:00 (ends after 17:00 close)
- **Expected**: Invalid (operating hours conflict)
- **Test method**: `test_conflict_when_booking_ends_after_operating_hours_close`

### Scenario 5: Closed day
- **Setup**: Room A active, Sunday is_closed=true
- **Request**: Sunday 10:00-11:00
- **Expected**: Invalid (operating hours conflict — day is closed)
- **Test method**: `test_conflict_when_booking_falls_on_closed_day`

### Scenario 6: Exact overlap with approved booking
- **Setup**: Room A, existing approved booking Mon 09:00-10:00, buffer=0
- **Request**: Same room, Mon 09:00-10:00 exact
- **Expected**: Invalid (slot conflict)
- **Test method**: `test_conflict_on_exact_overlap_with_approved_booking`

### Scenario 7: Partial overlap — request starts during existing
- **Setup**: Room A, existing approved Mon 09:00-10:00, buffer=0
- **Request**: Mon 09:30-10:30 (request starts inside existing)
- **Expected**: Invalid
- **Test method**: `test_conflict_when_request_starts_during_existing_booking`

### Scenario 8: Partial overlap — request ends during existing
- **Setup**: Room A, existing approved Mon 09:00-10:00, buffer=0
- **Request**: Mon 08:30-09:30 (request ends inside existing)
- **Expected**: Invalid
- **Test method**: `test_conflict_when_request_ends_during_existing_booking`

### Scenario 9: Back-to-back (buffer=0, end=start)
- **Setup**: Room A, existing approved Mon 09:00-10:00, buffer=0
- **Request**: Mon 10:00-11:00 (touches end of existing)
- **Expected**: Valid (no overlap when buffer=0)
- **Note**: Formula is strict `<` and `>`, so touching boundaries are valid
- **Test method**: `test_no_conflict_when_back_to_back_with_zero_buffer`

### Scenario 10: Buffer=15, gap < 15 min
- **Setup**: Room A, existing approved 09:00-10:00, buffer=15
- **Request**: 10:10-11:00 (gap is 10 min, less than buffer)
- **Expected**: Invalid (10:00 + 15 = 10:15 > 10:10 = conflict)
- **Test method**: `test_conflict_when_gap_smaller_than_buffer`

### Scenario 11: Buffer=15, gap exactly = 15 min
- **Setup**: Room A, existing approved 09:00-10:00, buffer=15
- **Request**: 10:15-11:00 (gap is exactly 15 min)
- **Expected**: Valid (10:15 > 10:15 is false — strict comparison)
- **Note**: This is the "boundary equality" test. Formula uses `>` not `>=`.
- **Test method**: `test_no_conflict_when_gap_equals_buffer_exactly`

### Scenario 12: Buffer=15, gap > 15 min
- **Setup**: Room A, existing approved 09:00-10:00, buffer=15
- **Request**: 10:30-11:00 (gap is 30 min)
- **Expected**: Valid
- **Test method**: `test_no_conflict_when_gap_greater_than_buffer`

### Scenario 13: Overlap with draft (other user)
- **Setup**: Room A, existing DRAFT 09:00-10:00 by user X, buffer=0
- **Request**: 09:30-10:30 by user Y
- **Expected**: Valid (draft does NOT lock slot)
- **Test method**: `test_no_conflict_with_draft_status_booking`

### Scenario 14: Overlap with rejected/cancelled
- **Setup**: Room A, existing REJECTED 09:00-10:00, existing CANCELLED 11:00-12:00
- **Request**: 09:00-12:00 (overlaps both)
- **Expected**: Valid (rejected/cancelled don't lock)
- **Test method**: `test_no_conflict_with_rejected_or_cancelled_bookings`

### Scenario 15: Overlap with active room block
- **Setup**: Room A, active room_block_schedule Mon 14:00-16:00 (maintenance)
- **Request**: Mon 13:00-15:00 (overlaps block)
- **Expected**: Invalid (block conflict)
- **Test method**: `test_conflict_with_active_room_block`

### Scenario 16: Overlap with cancelled block
- **Setup**: Room A, room_block_schedule 14:00-16:00 with cancelled_at set
- **Request**: 13:00-15:00 (overlaps but block was cancelled)
- **Expected**: Valid (cancelled blocks don't apply)
- **Test method**: `test_no_conflict_with_cancelled_room_block`

### Scenario 17: Service is role-agnostic
- **Setup**: Room A, existing approved 09:00-10:00
- **Request**: Same slot (would conflict regardless of user)
- **Expected**: Invalid (service doesn't know/care about caller's role)
- **Note**: Tests that the service doesn't have backdoor for "admin override". Override is Action-layer concern, not Service.
- **Test method**: `test_service_does_not_bypass_conflict_for_any_caller`

### Scenario 22: All datetimes treated as UTC
- **Setup**: Room A operating hours stored in UTC, request in UTC
- **Request**: Equivalent of 09:00 WIB but supplied as 02:00 UTC
- **Expected**: Behavior consistent — service operates in UTC throughout
- **Note**: Tests that service doesn't accidentally apply timezone shifts. Per Dec-09, all data in/out is UTC.
- **Test method**: `test_service_treats_all_datetimes_as_utc`

## Additional Service Scenarios (Beyond Blueprint M.2)

### Scenario 23: Exclude self when re-checking own booking
- **Setup**: Room A, existing approved 09:00-10:00 with id=42, buffer=0
- **Request**: Same slot, but `excludeBookingId=42`
- **Expected**: Valid (excludes own booking from conflict set)
- **Note**: Used by edit flow — when user edits booking #42, the service shouldn't conflict #42 with itself.
- **Test method**: `test_excludeBookingId_excludes_self_from_conflict_check`

### Scenario 24: Buffer hierarchy — room buffer wins
- **Setup**: Room A with `booking_buffer_minutes=20`, system default=15
- **Request**: 10:10-11:00 against existing 09:00-10:00
- **Expected**: Invalid (uses room's 20 min, not system 15)
- **Test method**: `test_buffer_uses_room_value_when_room_has_explicit_buffer`

### Scenario 25: Buffer hierarchy — system default fallback
- **Setup**: Room A with `booking_buffer_minutes=0`, system default=15
- **Request**: 10:10-11:00 against existing 09:00-10:00
- **Expected**: Invalid (uses system 15 min)
- **Test method**: `test_buffer_uses_system_default_when_room_buffer_is_zero`

### Scenario 26: findConflicts returns multiple conflicts
- **Setup**: Room A, existing approved 09:00-10:00, existing submitted 11:00-12:00, room block 14:00-15:00
- **Request**: 09:00-15:00 (overlaps everything)
- **Expected**: Returns 3 conflict items (2 bookings + 1 block)
- **Test method**: `test_findConflicts_returns_all_overlapping_items`

### Scenario 27: assertNoConflict throws BookingConflictException
- **Setup**: Room A, existing approved 09:00-10:00, buffer=0
- **Request**: Same slot via `assertNoConflict`
- **Expected**: Throws `App\Exceptions\BookingConflictException` with the conflicts in the exception payload
- **Test method**: `test_assertNoConflict_throws_BookingConflictException_with_conflicts_payload`

## Out of Scope (handled by Form Request / Policy)

These scenarios from Blueprint M.2 are NOT covered by BookingConflictService tests. Cross-referenced for completeness:

- **Scenario 2** (`ends_at <= starts_at`): `StoreBookingRequest` validation rule
- **Scenario 3** (>8 hours): `StoreBookingRequest` validation rule against `config('meeting_room.max_booking_duration_hours')`
- **Scenario 18** (attendee > capacity): `StoreBookingRequest` validation rule
- **Scenario 19** (admin override capacity): Policy + flag in form request
- **Scenario 20** (retrospective booking, regular user): `StoreBookingRequest` rule (`starts_at > now()`)
- **Scenario 21** (retrospective booking, super admin): Policy authorization

## Test Count Summary

- **In-scope service tests**: 14 (from blueprint) + 5 (additional) = **19 tests**
- **Out-of-scope tests** (other layers): 6 — to be written when those layers exist (Sprint 2C+)

## Implementation Notes (Phase 1, NOT now)

- Service location: `app/Services/BookingConflictService.php`
- Constructor injection: `SettingsService` for buffer fallback
- Both `assertNoConflict` and `findConflicts` share private `queryConflictsFor` helper
- Use Eloquent query with `whereBetween`/`where` constraints — let DB do the heavy lifting
- For `findConflicts`, return a `Collection` of mixed types (bookings + blocks) — typed via wrapper class? TBD in Phase 1
