# Sprint 2D — Approval Workflow Spec

**Arc:** Sprint 2 Closing — Booking Lifecycle
**Milestone:** 2D of 3 (Submit UI ✓ → **Approval UI** → Lifecycle Edges)
**Status:** Spec locked, implementation pending
**Blueprint reference:** Sprint 4 / M2 (J.5)

---

## 1. Scope

### 2D ships

A logged-in approver (unit_approver, ga_admin, or super_admin) can:

- Visit a full-page **`/approvals`** Livewire inbox showing pending bookings assigned to them
- See live counter of pending bookings (auto-refresh via `wire:poll.30s`)
- Open a booking's read-only detail page at **`/bookings/{booking}`** with approval timeline
- **Approve** a booking inline from the inbox — atomic action with conflict re-check, status transition, notification dispatch
- **Reject** a booking inline with required notes — atomic action, terminal state, notification dispatch
- See a clear error banner if approval fails because the slot was taken since submit (race mitigation)

A logged-in requester can:

- Click their own booking from any list to view its read-only detail at `/bookings/{booking}`
- See the approval timeline (current status, who approved/rejected, when, with what notes)
- Receive a database notification when their booking is approved or rejected

A logged-in **super_admin** can:

- Override-approve or override-reject ANY submitted booking, regardless of `current_approver_user_id`
- Override actions are logged with full context for audit

### 2D does NOT ship

- Booking edit / cancel / reschedule UI (M3)
- Email channel for notifications (Sprint 5 — database channel only in 2D)
- Multi-step approval (Phase 3 — schema supports `sequence_no` but UI assumes step=1)
- Delegated approver / approval routing UI (Phase 3)
- Notification preferences (Phase 3)
- Mobile-specific inbox layout — desktop-first; mobile gets a stacked list version of same component
- Recurring booking approval (Phase 2)
- Activity log viewer UI (Sprint 5)

---

## 2. Locked Decisions

| ID | Decision | Choice | Rationale |
|---|---|---|---|
| 2D-Dec-1 | Super Admin override | `Gate::before` in AuthServiceProvider checks `super_admin` role; bypasses ALL Policy methods | Laravel-idiomatic, central, no Policy method changes |
| 2D-Dec-2 | Conflict re-check failure UX | Throw `BookingConflictException` from action; catch in Livewire component; render banner showing the now-blocking conflict | Clear cause, actionable for approver |
| 2D-Dec-3 | Reject UX | Inline reveal of textarea on click; submit button disabled until textarea has content | Simpler than modal; fewer Livewire round-trips |
| 2D-Dec-4 | Inbox auto-refresh interval | `wire:poll.30s` | Matches NotificationDropdown precedent from M1; respects Blueprint C.1's "≥30s" rule |
| 2D-Dec-5 | Notification read-state on inbox view | Manual — bell icon dropdown handles read-state; inbox view does NOT auto-mark | Inbox is action-oriented; notification = audit/awareness; separate concerns |
| 2D-Dec-6 | Booking show page timeline | Show all `booking_approvals` rows + `booking_status_histories` in unified chronological timeline | Comprehensive audit; read-only |
| 2D-Dec-7 | Booking detail route binding | `Route::get('/bookings/{booking}', BookingController@show)` with implicit model binding | Standard Laravel; matches existing `bookings.index` pattern |
| 2D-Dec-8 | Approval action atomicity | DB::transaction + `Room::lockForUpdate()` + re-check inside transaction | Per Blueprint H.4 race mitigation; matches SubmitBookingAction pattern |
| 2D-Dec-9 | BookingSubmittedNotification retroactive wiring | Goes in 2D-F (the notifications phase), NOT 2D-A | Keeps phase-A focused on action logic; F is the holistic notification cascade |
| 2D-Dec-10 | Notification author for override | super_admin override uses `acted_by_user_id` distinct from `current_approver_user_id` snapshot at submission | Audit clarity — original assignee preserved, override actor recorded separately |

---

## 3. Aesthetic Direction

Inherits M1's design system:

- bpjs-blue primary, bpjs-green success accents
- Public Sans body, IBM Plex Mono for codes/IDs
- Consistent button hierarchy: solid primary for "Approve", outline danger for "Reject", subtle secondary for "View Details"
- Status pills use BookingStatus enum colors (already defined)
- Empty states: Indonesian copy, friendly tone, illustrative not punitive ("Tidak ada booking yang menunggu persetujuan saat ini")
- Approval timeline: vertical step indicator, similar visual language to delivery tracking apps

---

## 4. Sub-Phase Breakdown

### 2D-0 — Spec doc (THIS DOCUMENT)

Lock decisions, scope, sub-phase plan. No code.

**Output:** `docs/sprint-2d-approval-workflow-spec.md`

### 2D-A — `ApproveBookingAction` (TDD)

The heart of 2D. Atomic approval with race mitigation.

**Pseudocode:**
public function execute(Booking $booking, User $actor, ?string $notes = null): Booking
{
    return DB::transaction(function () use ($booking, $actor, $notes) {
        // 1. Lock the room row
        room=Room::lockForUpdate()−>findOrFail(room = Room::lockForUpdate()->findOrFail(
room=Room::lockForUpdate()−>findOrFail(booking->room_id);
// 2. Reload booking inside lock to get fresh state
    $booking = $booking->fresh()->lockForUpdate();

    // 3. Re-check conflict (Blueprint H.4 race mitigation)
    $conflicts = $conflictService->findConflicts($room, $booking->starts_at, $booking->ends_at, ignoreBookingId: $booking->id);
    if ($conflicts->isNotEmpty()) {
        throw BookingConflictException::raceLost($conflicts);
    }

    // 4. Validate booking is still in submitted status
    if ($booking->status !== BookingStatus::Submitted) {
        throw new DomainException("Booking sudah tidak dalam status submitted.");
    }

    // 5. Update BookingApproval row
    $approvalRow = $booking->approvals()
        ->where('sequence_no', $booking->current_approval_step)
        ->firstOrFail();
    $approvalRow->update([
        'status' => 'approved',
        'action_at' => now(),
        'action_notes' => $notes,
        'acted_by_user_id' => $actor->id,
    ]);

    // 6. Update Booking — atomic hybrid pointer clear (Dec-03)
    $booking->update([
        'status' => BookingStatus::Approved,
        'approved_at' => now(),
        'current_approval_step' => null,           // Dec-03: clear when terminal
        'current_approver_user_id' => null,        // Dec-03: clear cache
        'updated_by_user_id' => $actor->id,
    ]);

    // 7. Insert BookingStatusHistory row
    BookingStatusHistory::create([...]);

    // 8. Dispatch BookingApprovedNotification (interface — class lives in 2D-F)
    $booking->requester->notify(new BookingApprovedNotification($booking));

    return $booking->fresh(['room', 'requester', 'approvals']);
});
}
**Tests (5+ minimum):**
1. Happy path — submitted booking → approved, status history written, notification dispatched
2. Race lost — conflict appears between submit and approve → throws BookingConflictException
3. Wrong actor — non-assigned user can't approve (caught at Policy layer; verify via integration)
4. Already-approved — booking transitioned by another path → throws DomainException
5. Super admin override — super_admin approves a booking they aren't assigned to (Policy-level; verify in 2D-C)
6. Approval row updated correctly — sequence_no, action_notes, acted_by_user_id all populated

**Output:**
- `app/Actions/ApproveBookingAction.php`
- `app/Exceptions/BookingConflictException.php` (extend with `raceLost()` static factory if not yet there)
- `tests/Unit/Actions/ApproveBookingActionTest.php`

### 2D-B — `RejectBookingAction` (TDD)

Same shape as 2D-A. Required `notes` parameter (the rejection reason).

**Tests (4+ minimum):**
1. Happy path — submitted → rejected with reason, history written, notification dispatched
2. Empty reason — throws ValidationException (defense-in-depth; primary validation in Form Request)
3. Already-terminal — booking not submitted → throws DomainException
4. Pointer cleared — current_approval_step + current_approver_user_id both null after reject

**Output:**
- `app/Actions/RejectBookingAction.php`
- `tests/Unit/Actions/RejectBookingActionTest.php`

### 2D-C — Super Admin override (Policy / Gate::before)

**Implementation:**
// app/Providers/AuthServiceProvider.php
Gate::before(function (User $user, string $ability) {
if ($user->hasRole('super_admin')) {
return true;
}
});
This is one line of code but big-effect. Existing Policies (BookingPolicy, RoomPolicy, etc.) all gain Super Admin override automatically.

**Tests (2-3 minimum):**
1. super_admin can approve a booking they aren't assigned to
2. super_admin can reject a booking they aren't assigned to
3. super_admin override path works through the actions (integration with 2D-A and 2D-B)

**Risk:** Existing tests may have implicit assumption that super_admin is denied without role. Run full suite after Gate::before to surface failures.

**Output:**
- `app/Providers/AuthServiceProvider.php` modified
- New tests appended to existing Policy tests

### 2D-D — Booking show page

**Route:** `Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show')`

**Controller:** Thin — `$this->authorize('view', $booking); return view('bookings.show', compact('booking'));`

**View:** `resources/views/bookings/show.blade.php`
- Booking header: code, status pill, room, requester
- Booking body: subject, agenda, attendee count, time, room
- Approval timeline section: chronological list of approval rows + status histories
- Action area (if applicable): Approve/Reject buttons IF user can approve THIS specific booking
- Calendar back-link

**Tests (4+ minimum):**
1. Requester sees own booking
2. Approver sees booking they're assigned to
3. Random employee 403s on someone else's booking
4. Page renders timeline (approvals + status histories) in correct order
5. Approve/Reject buttons visible only when user can approve this booking

**Output:**
- `app/Http/Controllers/BookingController.php` modified (add show)
- `resources/views/bookings/show.blade.php` (new)
- `routes/web.php` modified (add show route)
- `tests/Feature/Bookings/ShowBookingTest.php` (new)

### 2D-E — `ApprovalInbox` Livewire component + `/approvals` route

**Component:** `app/Livewire/Approval/ApprovalInbox.php` (full-page Livewire)

**Properties:**
- `$pendingBookings` — eager-loaded query for current user's queue
- `$rejectingBookingId` — tracks which booking is in reject-mode (inline reveal)
- `$rejectionReason` — textarea content for the active rejection

**Methods:**
- `approve($bookingId)` — calls ApproveBookingAction, refreshes list
- `startReject($bookingId)` — toggles inline reveal
- `confirmReject($bookingId)` — calls RejectBookingAction, refreshes list
- `cancelReject()` — closes the inline reveal

**View:**
- List of pending bookings (one row per booking)
- Each row: subject, room, requester, time, "Approve" button + "Reject" button
- When Reject is clicked: row expands to show textarea + "Confirm Reject" + "Cancel"
- Empty state: "Tidak ada booking yang menunggu persetujuan saat ini."
- `wire:poll.30s` on the root component to auto-refresh

**Tests (6+ minimum):**
1. Authorized approver can render inbox
2. Inbox shows only bookings assigned to current user
3. Approver clicks Approve → ApproveBookingAction called, booking removed from list
4. Approver clicks Reject → textarea appears
5. Approver submits empty rejection reason → validation error
6. Approver submits valid rejection → RejectBookingAction called, booking removed
7. Race lost — booking conflict appears mid-approve → banner shows error, list refreshes
8. Polling refreshes counter (verify via Livewire::test() set/get cycle)

**Output:**
- `app/Livewire/Approval/ApprovalInbox.php` (new)
- `resources/views/livewire/approval/approval-inbox.blade.php` (new)
- `routes/web.php` modified (add `/approvals` route)
- `tests/Feature/Livewire/Approval/ApprovalInboxTest.php` (new)

### 2D-F — Notifications cascade

Three notification classes, all using database channel only.

**Classes:**
- `BookingSubmittedNotification` — to approver, when SubmitBookingAction creates a submitted booking. RETROACTIVELY wired into existing SubmitBookingAction (modify it).
- `BookingApprovedNotification` — to requester, when ApproveBookingAction succeeds.
- `BookingRejectedNotification` — to requester, when RejectBookingAction succeeds.

**Payload shape (database channel):**
```json
{
  "booking_id": 123,
  "booking_code": "BKG-20260504-0001",
  "subject": "Kick-off Meeting Q3",
  "room_name": "Ruang Borneo",
  "starts_at": "2026-05-11T03:00:00Z",
  "actor_user_id": 45,
  "actor_name": "Budi Santoso",
  "rejection_reason": null   // only for rejected
}
```

**Tests (3+ minimum):**
1. Submitting a booking creates a database notification for the assigned approver
2. Approving a booking creates a notification for the requester
3. Rejecting a booking creates a notification for the requester (with rejection_reason in payload)

**Output:**
- `app/Notifications/BookingSubmittedNotification.php` (new)
- `app/Notifications/BookingApprovedNotification.php` (new)
- `app/Notifications/BookingRejectedNotification.php` (new)
- `app/Actions/SubmitBookingAction.php` modified (dispatch BookingSubmittedNotification)
- `app/Actions/ApproveBookingAction.php` modified (dispatch BookingApprovedNotification)
- `app/Actions/RejectBookingAction.php` modified (dispatch BookingRejectedNotification)
- `tests/Feature/Notifications/BookingNotificationsTest.php` (new)

### 2D-G — Integration tests + light UI polish

**End-to-end flow tests (4-6 minimum):**
1. Submit → approver sees in inbox → approves → requester sees notification
2. Submit → approver sees in inbox → rejects with reason → requester sees notification with reason
3. Submit booking A → submit booking B (overlap) — A in approver inbox; approver tries to approve A; race fails because B was approved → approver sees banner, A returns to list
4. Super admin override path — submit; super admin (not assigned approver) approves directly via show page
5. Super admin override notification — when super admin approves, requester still gets notified

**Optional UI polish:**
- Notification dropdown counter shows unread booking-related notifications (already shipped in M1; verify it picks up new types)

**Output:**
- `tests/Feature/Approval/EndToEndApprovalTest.php` (new)

### 2D-H — Polish + PR

- Pint clean across all 2D files
- PHPStan clean at level 5
- All tests green
- Spec doc updated with any decisions emerged during implementation
- Open PR `feat/sprint-2d-approval-workflow → develop`

---

## 5. Test Coverage Targets

| Layer | Target | Files |
|---|---|---|
| Actions (ApproveBookingAction + RejectBookingAction) | 95%+ | `tests/Unit/Actions/` |
| Policy methods (approve, reject, override) | 100% | existing `BookingPolicyTest.php` |
| Livewire ApprovalInbox | 70%+ | `tests/Feature/Livewire/Approval/ApprovalInboxTest.php` |
| BookingController@show | feature test only | `tests/Feature/Bookings/ShowBookingTest.php` |
| Notifications | 100% | `tests/Feature/Notifications/` |
| Integration end-to-end | full flow per scenario | `tests/Feature/Approval/EndToEndApprovalTest.php` |

**Suite expectation post-2D:** ~240+ tests (current 207 + ~30-35 new across 2D-A through 2D-G).

---

## 6. Risk Watch

| Risk | Mitigation | Severity |
|---|---|---|
| Race condition in approve flow | DB::transaction + Room::lockForUpdate + re-check (2D-Dec-8) | HIGH |
| Hybrid pointer (Dec-03) drift | Atomic update inside transaction; integrity test | HIGH |
| Super admin Gate::before unintended bypasses | Run full suite after wiring; expect surprises in existing Policy tests | MEDIUM |
| Notification dispatch failures cascade rollback | Use `Notification::send()` outside transaction OR catch+log | MEDIUM |
| ApprovalInbox poll spamming server | 30s interval matches Blueprint; verify in load test (2D-G optional) | LOW |
| ApprovalRoutingException not handled in approve flow | Already handled in submit; approve doesn't route, so N/A | N/A |

---

## 7. Order of Implementation Within a Sub-Phase

Default pattern (matches M1 cadence):

1. Recon — read existing code, identify anchor points
2. Decisions log — note any sub-phase-internal decisions
3. Code — TDD where domain-heavy (actions); test-after where UI-heavy (Livewire)
4. Pint + PHPStan green
5. Full suite green (N≥3 for "reliable" per Q9)
6. Commit (single-purpose; small enough to review)
7. Move to next sub-phase

---

## 8. Out of Scope (Tracked for Future)

- Multi-step approval enable (schema supports it; UI assumes step=1)
- Email channel for notifications
- Approval delegation when approver is on leave
- Approval analytics / report
- Mobile-native approval (future Phase 3)
- Approval override audit log viewer

---

## 9. Sign-off

This spec is locked as of 2026-05-04. Changes require a new entry in this section with rationale.

| Date | Change | Reason |
|---|---|---|
| 2026-05-04 | Initial lock | Sprint 2D kickoff |

