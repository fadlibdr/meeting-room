# M1 — Submit UI Spec

**Arc:** Sprint 2 Closing — Booking Lifecycle
**Milestone:** M1 of 3 (Submit UI → Approval UI → Lifecycle Edges)
**Status:** Spec locked, implementation in progress

---

## 1. Scope

### M1 ships
A logged-in requester can:
- Browse a daily room availability calendar at `/calendar`
- Open a Livewire form at `/bookings/new` (or by clicking an empty calendar cell)
- Pick a room visually via `RoomAvailabilityPicker`
- Receive live conflict feedback as they fill in time fields
- Submit a booking, which lands as `approved` (mode=none rooms) or `submitted` (mode=unit_approver / ga_admin rooms)
- See success or field-mapped error messages in Indonesian

The wiring from Livewire form → existing `SubmitBookingAction` is proven end-to-end via `Livewire::test()` feature tests.

### M1 does NOT ship
- Approve / reject UI (M2)
- Cancel / reschedule UI (M3)
- Booking detail read-only view (M2 — needed for approver context)
- Notifications dropdown changes (already shipped in Sprint 2A; left untouched)
- Real-time updates via `wire:poll` (M2 introduces it for ApprovalInbox)
- Recurring booking UI (Phase 2)
- Email notifications (Sprint 5)

---

## 2. Locked Decisions

| ID | Decision | Choice |
|---|---|---|
| D1 | Calendar layout | Time-grid by room (desktop ≥768px) → list grouped by room (mobile <768px); same Livewire component, two render branches |
| D2 | Form pattern | Single-page; all fields visible; submit at bottom |
| D3 | Live conflict trigger | `wire:model.live.debounce.500ms` on time fields, gated: only fires when `room_id` + `starts_at` + `ends_at` all set |
| D4 | Calendar location | `/calendar` standalone full-page Livewire route |
| M1-Dec-1 | Form state | Public properties on `BookingForm` Livewire class (Livewire-native) |
| M1-Dec-2 | Calendar query | Direct Eloquent in `BookingCalendar::loadBookings()` private method; promote to service if 2nd caller appears in M1-F |
| M1-Dec-3 | Calendar default state | Today's date, all rooms, status filter `[submitted, approved]` |
| M1-Dec-4 | Time grid resolution | 30-minute slots (08:00, 08:30, ...) |
| M1-Dec-5 | Calendar interactivity | Empty cells → redirect to `/bookings/new?room_id=X&starts_at=Y`; booking blocks → no-op until M2 |

---

## 3. Aesthetic Direction

Government-grade refined for BPJS Kesehatan internal use.

| Aspect | Choice |
|---|---|
| Display font | Public Sans (USWDS gov-designed, Google Fonts) |
| Body font | IBM Plex Sans |
| Primary color | `#0066B3` (BPJS blue) |
| Accent color | `#00B140` (BPJS green) |
| Neutrals | Warm slate (`slate-50` … `slate-900`) |
| Status pills | Match `BookingStatus::color()` enum: gray/amber/green/red |
| Motion | Restrained; 150ms transitions on hover/focus; skeleton screens > spinners |
| Spacing | 8px base grid; generous on desktop, denser mobile |
| Differentiation | Alternating row bands (`slate-50` / white) on time grid to reduce scan fatigue |

This locks for M1 + M2 + M3 — consistency across the arc.

---

## 4. Components

### BookingForm

**Class:** `App\Livewire\Booking\BookingForm`
**Route:** `Route::get('bookings/new', BookingForm::class)->name('bookings.new')` — full-page Livewire component.
**View:** `resources/views/livewire/booking/booking-form.blade.php`

**Public properties:**
- `?int $roomId` — populated from query string `?room_id=X` if present
- `?string $startsAt` — ISO 8601 in user timezone, populated from `?starts_at=Y` if present
- `?string $endsAt`
- `string $subject = ''`
- `?string $agenda = null`
- `int $attendeeCount = 1`
- `?array $conflictDetails = null` — populated by live conflict check
- `string $conflictStatus = 'unknown'` — one of: `unknown`, `checking`, `clear`, `conflict`

**Methods:**
- `mount(?int $room_id = null, ?string $starts_at = null): void` — accepts query string pre-fill, authorizes via `$this->authorize('create', Booking::class)`
- `updatedRoomId(): void` / `updatedStartsAt(): void` / `updatedEndsAt(): void` — trigger `$this->checkAvailability()`
- `checkAvailability(): void` — gates on all 3 fields set; calls `BookingConflictService::findConflicts()`; updates `$conflictStatus` + `$conflictDetails`
- `submit(): RedirectResponse` — calls `$this->validate()`, then `SubmitBookingAction::execute()`; catches `BookingConflictException` and `ApprovalRoutingException`; redirects to `dashboard` with flash on success

**Validation rules** (mirror `StoreBookingRequest`):
- `roomId`: required, integer, exists in rooms
- `subject`: required, string, max 150
- `agenda`: nullable, string, max 5000
- `attendeeCount`: required, integer, min 1
- `startsAt`: required, date, after now
- `endsAt`: required, date, after starts_at

Server-side validation defers to `SubmitBookingAction` for the duration/capacity/active-room rules (defense in depth — same path the HTTP form uses).

### BookingCalendar

**Class:** `App\Livewire\Booking\BookingCalendar`
**Route:** `Route::get('calendar', BookingCalendar::class)->name('calendar.index')` — full-page Livewire component.
**View:** `resources/views/livewire/booking/booking-calendar.blade.php`

**Public properties:**
- `string $selectedDate` — ISO `YYYY-MM-DD`, defaults to today in user TZ
- `array $roomFilterIds = []` — empty means "all rooms"
- `array $statusFilter = ['submitted', 'approved']` — what counts as "booked"

**Methods:**
- `mount(): void` — authorizes via `$this->authorize('viewAny', Booking::class)`
- `nextDay(): void` / `previousDay(): void` / `setToday(): void` — date navigation
- `toggleRoom(int $roomId): void` — multi-select filter
- `getBookingsProperty(): Collection` — computed property; runs the date+filter query, eager-loads room + requester
- `getRoomsProperty(): Collection` — computed property; lists active rooms (filtered if `$roomFilterIds`)
- Slot click → emits Livewire navigation event with `room_id` + `starts_at`

**Render branches:**
- Desktop (≥768px): Tailwind grid; rows = 30-min slots from earliest `open_time` to latest `close_time` across visible rooms; columns = rooms; alternating row bands; existing bookings rendered as colored blocks spanning their slots
- Mobile (<768px): same data, list-by-room view; each room is a section header followed by its bookings as cards

The two branches share the same data, just different `<div class="hidden md:block">` / `<div class="md:hidden">` toggles.

### RoomAvailabilityPicker

**Class:** `App\Livewire\Booking\RoomAvailabilityPicker`
**Lives:** Nested inside `BookingForm` view via `<livewire:booking.room-availability-picker :starts-at="..." :ends-at="..." />`
**View:** `resources/views/livewire/booking/room-availability-picker.blade.php`

**Public properties:**
- `?string $startsAt` — passed from parent
- `?string $endsAt` — passed from parent
- `?int $selectedRoomId = null`

**Methods:**
- `getRoomsProperty(): Collection` — list of active rooms with availability flag, computed from `BookingConflictService::findConflicts()` per room
- `selectRoom(int $id): void` — emits `room-selected` event with `$id`; parent `BookingForm` listens and sets `$roomId`

If `startsAt` or `endsAt` is null, the picker shows all rooms with neutral state (no badge).

---

## 5. Routes Added
GET  /calendar      → BookingCalendar (Livewire)   [calendar.index]
GET  /bookings/new  → BookingForm (Livewire)       [bookings.new]

The existing placeholder `Route::view('bookings', 'placeholder')->name('bookings.index')` remains as M2/M3 surface.

---

## 6. Test Plan (~13 tests, executed in M1-G)

### BookingForm (5 tests)
1. Mounts for authenticated requester; unauthorized user gets 403
2. Validates required fields; missing subject blocks submit
3. Live conflict check fires only after `roomId` + `startsAt` + `endsAt` all set; before that, `conflictStatus = 'unknown'`
4. Successful submit → redirects to `/dashboard` with success flash; booking exists in DB
5. `BookingConflictException` from action → field error on `startsAt`, no booking created

### BookingCalendar (5 tests)
1. Renders for authenticated user; shows today's date as default
2. `nextDay()` advances date; `previousDay()` reverses; `setToday()` resets
3. Existing approved/submitted bookings appear in their slots
4. `toggleRoom()` filters rooms; multi-select preserved
5. Date with no bookings shows empty-state message

### RoomAvailabilityPicker (3 tests)
1. Renders rooms with neutral state when starts/ends are null
2. Updates badges when parent passes time window
3. `selectRoom()` emits event payload with the room ID

---

## 7. Out-of-Scope Cross-References

| Feature | Where it lands |
|---|---|
| Booking detail read-only view | M2 (approver needs context to approve) |
| Approve / reject inline | M2 |
| Cancel button | M3 |
| Reschedule flow | M3 |
| Email notifications | Sprint 5 |
| Real-time refresh on submit | M2 (`wire:poll` for ApprovalInbox) |
| Recurring booking UI | Phase 2 |

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Livewire 3 + Tailwind setup might not be wired (haven't seen Sprint 2A's structure yet) | M1-A recon step; if missing, M1-A also wires it |
| `Public Sans` + `IBM Plex Sans` not in Tailwind config | M1-A patches `tailwind.config.js` to add them |
| Calendar query is N+1 prone with many rooms × bookings | Eager-load `room` + `requester.unit`; index `(room_id, starts_at, ends_at)` already exists per Schema v2 §H |
| Mobile fallback diverges from desktop in subtle ways | Single component, two `@if` branches over the same data — drift is structurally prevented |
| Live conflict check spams DB on every keystroke | D3's debounce 500ms + room-gated trigger keeps it cheap; existing `BookingConflictService` has no caching but query is indexed |

---

**End of M1 spec.** Implementation begins at M1-A.
