# Sprint 2D — Approval Workflow Spec

**Arc:** Sprint 2 Closing — Booking Lifecycle
**Milestone:** 2D of 3 (Submit UI ✓ → **Approval UI** → Lifecycle Edges)
**Status:** Implementation complete (Sprint 2D) — see §9 for the 2D-H reconciliation
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
| 2D-Dec-1 (amended 2026-05-22) | Super Admin override | A `bookings.override` permission, seeded to super_admin only, checked inside `BookingPolicy::approve`/`reject` AFTER the status gate. Bypasses the assignment check only — never the status gate. | Original `Gate::before` plan invalidated during 2D-C recon: Laravel 12 ships no `AuthServiceProvider`; a blanket bypass would let super_admin approve a Draft booking (breaks the status invariant 4 existing tests protect); and `BookingPolicy` is permission-based per Q3=A, so a direct role check would violate its own documented principle. |
| 2D-Dec-2 | Conflict re-check failure UX | Throw `BookingConflictException` from action; catch in Livewire component; render banner showing the now-blocking conflict | Clear cause, actionable for approver |
| 2D-Dec-3 | Reject UX | Inline reveal of textarea on click; submit button disabled until textarea has content | Simpler than modal; fewer Livewire round-trips |
| 2D-Dec-4 | Inbox auto-refresh interval | `wire:poll.30s` | Matches NotificationDropdown precedent from M1; respects Blueprint C.1's "≥30s" rule |
| 2D-Dec-5 | Notification read-state on inbox view | Manual — bell icon dropdown handles read-state; inbox view does NOT auto-mark | Inbox is action-oriented; notification = audit/awareness; separate concerns |
| 2D-Dec-6 | Booking show page timeline | Show all `booking_approvals` rows + `booking_status_histories` in unified chronological timeline | Comprehensive audit; read-only |
| 2D-Dec-7 | Booking detail route binding | `Route::get('/bookings/{booking}', BookingController@show)` with implicit model binding | Standard Laravel; matches existing `bookings.index` pattern |
| 2D-Dec-8 | Approval action atomicity | DB::transaction + `Room::lockForUpdate()` + re-check inside transaction | Per Blueprint H.4 race mitigation; matches SubmitBookingAction pattern |
| 2D-Dec-9 | BookingSubmittedNotification retroactive wiring | Goes in 2D-F (the notifications phase), NOT 2D-A | Keeps phase-A focused on action logic; F is the holistic notification cascade |
| 2D-Dec-10 | Notification author for override | super_admin override uses `acted_by_user_id` distinct from `current_approver_user_id` snapshot at submission | Audit clarity — original assignee preserved, override actor recorded separately |
| 2D-Dec-11 (added 2026-05-22) | Notification dispatch point | All three booking notifications are dispatched in the action's `execute()` AFTER `DB::transaction()` returns — never inside it — so a notification never fires for a rolled-back action. Recipients are resolved by FK id (`User::findOrFail($booking->requester_user_id)`), not the relation property. Auto-approved bookings (`approval_mode = none`) dispatch nothing — the requester is already present. | An in-transaction notify couples a `notifications` insert to rollback semantics and trips larastan's relation-type resolution; after-commit dispatch with an FK-id lookup is both correct and statically clean. |
| 2D-Dec-12 (added 2026-05-22) | 2D-G test scope | 2D-G ships end-to-end journey tests (`ApprovalJourneyTest`) plus the Dec-03 hybrid-pointer integrity test (`IntegrityTest`, written to be reusable as a nightly production check). Race-condition (parallel-submit) testing is deferred to Sprint-6 hardening. | The race mitigation itself (`lockForUpdate` + re-check-on-approve) is already covered by the action and journey tests; a `pcntl`-based concurrency test is fragile and belongs in the Sprint-6 hardening pass / production Go-No-Go gate. |

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

### 2D-C — Super Admin override (`bookings.override` permission)

**Implementation:** See the amended 2D-Dec-1. A `bookings.override` permission,
seeded to super_admin only, checked inside `BookingPolicy::approve()` and
`reject()` AFTER the status gate and the permission gate, BEFORE the assignment
check. It bypasses only the "is this approver assigned to this booking" check —
never the status gate.

The original `Gate::before` plan was invalidated during 2D-C recon: Laravel 12
ships no `AuthServiceProvider`; a blanket `Gate::before` bypass would let
super_admin approve a Draft booking (breaking the status invariant existing
tests protect); and `BookingPolicy` is permission-based, so a direct role check
would contradict its own documented principle.

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
| 2026-05-22 | 2D-Dec-1 amended | `Gate::before` plan invalidated during 2D-C recon; replaced with a `bookings.override` permission checked per-Policy-method. See the amended 2D-Dec-1 row above. |
| 2026-05-22 | 2D-C..2D-G reconciled at 2D-H | §4 2D-C section rewritten to match the shipped `bookings.override` design (it still described the rejected `Gate::before` plan). 2D-Dec-11 (notification dispatch point) and 2D-Dec-12 (2D-G test scope, race deferred) added to §2. §4 Sub-Phase Breakdown and §5 Coverage are the 2D-0 pre-implementation plan — the authoritative record of shipped artifacts (file locations, final test names, scenario coverage) is the git history, commits 2D-A through 2D-G. Final suite: 255 passing. |

