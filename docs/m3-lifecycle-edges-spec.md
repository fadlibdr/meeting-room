# M3 — Lifecycle Edges — Implementation Spec

**Milestone:** M3 (Lifecycle Edges)
**Depends on:** M1 (Submit), 2D (Approval Workflow) — both shipped + deployed
**Status:** Locked — 2026-05-22
**Branch:** feat/m3-lifecycle-edges

## 1. Scope

M3 builds the requester-controlled lifecycle transitions that pull a booking
out of, or sideways within, its flow: edit, cancel, reschedule, delete.

Booking lifecycle transitions:

| From      | Edge        | To                                  | Notes |
|-----------|-------------|-------------------------------------|-------|
| Draft     | edit        | Draft                               | in-place save |
| Draft     | submit      | Submitted                           | M1 |
| Draft     | delete      | (hard-deleted)                      | M3-F, super_admin only |
| Submitted | edit        | Draft                               | M3-Dec-1 — reverts; re-submit required |
| Submitted | approve     | Approved                            | 2D |
| Submitted | reject      | Rejected                            | 2D |
| Submitted | cancel      | Cancelled                           | clears pointer + pending approval |
| Approved  | cancel      | Cancelled                           | cancellation reason required |
| Approved  | reschedule  | Cancelled (+ new Submitted booking) | M3-Dec-2 / M3-Dec-3 |
| Approved  | scheduler   | Completed                           | post-ends_at, prior milestone |

There is no `Rescheduled` status. Reschedule terminates the old booking as
`Cancelled`; the new booking carries `rescheduled_from_booking_id` pointing
back to it.

### Out of scope
- `bookings.index` real listing — remains a placeholder. M3 edges attach to
  `bookings/show`, which is real (shipped 2D).
- Authorization — `BookingPolicy::update/cancel/delete` already exist and are
  complete. M3 adds exactly one policy method: `reschedule()` (M3-Dec-2).

## 2. Decisions

### M3-Dec-1 — Editing a Submitted booking reverts it to Draft
`BookingPolicy::update()` permits editing Draft and Submitted bookings.
Editing a **Draft** booking is a plain in-place save — no status change.
Editing a **Submitted** booking **reverts it to Draft**:
- `current_approval_step` and `current_approver_user_id` are nulled.
- The pending `BookingApproval` row is set to `status = cancelled`,
  `action_at = now()`.
- A `BookingStatusHistory` row records `Submitted -> Draft`.
- The requester must re-submit. Conflict check and approval routing run
  fresh at re-submission.

Rationale: scheduling data must never be mutated under a live approver (the
Dec-07 principle). Reverting to Draft makes the re-conflict-check automatic
and keeps the approval trail honest.

### M3-Dec-2 — New policy method `BookingPolicy::reschedule()`
Reschedule applies to **Approved bookings only** (Submitted bookings are
handled by edit, M3-Dec-1). New method:

    reschedule(User $user, Booking $booking): bool
      = booking.status === Approved  AND  cancel($user, $booking) === true

Routes use `->can('reschedule', 'booking')`; the show page uses
`@can('reschedule', $booking)`. This is the only policy change in M3.

### M3-Dec-3 — Reschedule suppresses the old booking's cancel-notification
`RescheduleBookingAction` cancels booking A and submits booking B in one
transaction. To avoid two notifications to the same approver, A's
`BookingCancelledNotification` is **suppressed**. `CancelBookingAction` takes
a `notify: bool = true` parameter; the reschedule path passes `notify: false`.
B's `BookingSubmittedNotification` (dispatched normally by
`SubmitBookingAction`) is the single approver-facing signal; B's detail page
shows "rescheduled from {A.booking_code}".

### M3-Dec-4 — Hard-delete is in scope, built last
`destroy` builds as the final sub-phase (M3-F): Draft-only, `bookings.delete`
permission (super_admin per the role matrix). `BookingController::destroy()`
+ DELETE route + a show-page button visible only to a super_admin on a Draft
booking.

## 3. Locked Mechanics

Derived rules, not forks — recorded so every sub-phase implements them
identically.

**Cancel:**
- Slot-freeing is implicit. `BookingStatus::Cancelled` is not in `locksSlot()`;
  the conflict service stops counting a booking the instant it flips to
  Cancelled. No explicit release step.
- Cancelling a **Submitted** booking clears the hybrid pointer and sets the
  pending `BookingApproval` row to `cancelled` — same as M3-Dec-1's revert.
  The 2D `IntegrityTest` invariant (non-submitted => pointer null) is the guard.
- Cancelling an **Approved** booking touches no pointer (already null) and no
  approval rows (already terminal).
- `CancelBookingAction` acquires **no room lock** — it runs no conflict check.
- Cancellation reason: **required** when cancelling an Approved booking,
  optional for Draft / Submitted (Blueprint H.5).
- Cancelling a Draft booking dispatches no notification (never visible to an
  approver). Cancelling Submitted/Approved notifies the approval-relevant user
  via `BookingCancelledNotification` (recipient by FK id, per 2D-Dec-11).

**Reschedule:**
- `RescheduleBookingAction` runs in a single `DB::transaction`:
  1. Cancel booking A via `CancelBookingAction` (notify: false).
  2. Create booking B via `SubmitBookingAction` (full conflict check +
     approval routing).
  3. Set `B.rescheduled_from_booking_id = A.id`.
- Order matters: A is cancelled first so B's conflict check — same
  transaction, same connection — does not see A's old slot.
- B enters approval fresh — it is a new booking with a new time.

## 4. Sub-Phase Plan

| Phase | Deliverable |
|-------|-------------|
| M3-0  | This spec doc. (docs commit) |
| M3-A  | `CancelBookingAction` + unit tests. |
| M3-B  | Cancel UI: endpoint + route + show-page button & reason modal; `BookingCancelledNotification`; feature test. |
| M3-C  | Edit: `BookingForm` edit mode + `BookingController::update` + route + button; M3-Dec-1 revert behavior; tests. |
| M3-D  | `RescheduleBookingAction` (composes Cancel + Submit) + unit tests. |
| M3-E  | Reschedule UI: `reschedule()` policy method + `BookingForm` reschedule mode + routes + button; feature test. |
| M3-F  | `destroy` (hard-delete Draft) + DELETE route + button + test. |
| M3-G  | Spec reconciliation, journey + integrity test sweep, full-suite green, PR. |

## 5. Build Surface

**New files:**
- `app/Actions/CancelBookingAction.php`
- `app/Actions/RescheduleBookingAction.php`
- `app/Notifications/BookingCancelledNotification.php`
- `app/Http/Requests/Booking/CancelBookingRequest.php`
- test files per phase

**Modified files:**
- `app/Policies/BookingPolicy.php` — add `reschedule()`
- `app/Http/Controllers/BookingController.php` — add `update()`, `cancel()`, `destroy()`
- `app/Livewire/Booking/BookingForm.php` — edit + reschedule modes
- `routes/web.php` — edit / update / cancel / reschedule / destroy routes
- `resources/views/bookings/show.blade.php` — action buttons

## 6. Test Plan

- **Unit (Actions):** `CancelBookingActionTest`, `RescheduleBookingActionTest`
  — every status path, pointer-clearing, history rows, notification
  dispatch/suppression, transaction atomicity.
- **Unit (Policy):** `reschedule()` cases added to `BookingPolicyTest`.
- **Feature:** cancel journey (Draft / Submitted / Approved, reason rule),
  edit journey (Draft in-place, Submitted revert-to-Draft), reschedule journey
  (A cancelled + B created + linked), destroy (Draft-only, super_admin).
- **Integrity:** the 2D hybrid-pointer `IntegrityTest` must stay green after
  every cancel/edit path.
- Gate: full suite green + PHPStan level 5 + Pint before every commit.
