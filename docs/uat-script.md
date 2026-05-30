# User Acceptance Test Script — Meeting Room BPJS Kesehatan

**Document type:** UAT script (Blueprint §N.4 — "UAT 30 scenarios signed off")
**Status:** v1.0
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)
**Test environment:** Staging — `https://booking.pi2.co.id`
**Test data:** Seeded roles + at least one user per role; at least 2 rooms with operating hours; at least 1 facility per category.

---

## How to use this script

Execute each scenario in order on staging. For each, mark **Result** (`☐ Pass` / `☐ Fail`), record the **Tester** and **Date**, and note any deviation. UAT-01 → UAT-30 are the **core sign-off set** and require the deployed build at the time of UAT. The **Pending-Merge** block (UAT-P*) is executed only after the reminders / exports / attachments PRs are merged and redeployed. The **Known-Gap** block (UAT-G*) documents an expected failure that is scheduled for repair.

**Role legend:** Pegawai = `requester` · Approver = `unit_approver` · GA Admin = `ga_admin` · Super Admin = `super_admin`.

---

## A. Authentication & Access Control

### UAT-01 — Login with valid credentials
**Role:** any · **Pre:** a seeded user exists.
**Steps:** open `/login`; enter a valid email + password; submit.
**Expected:** authenticated and redirected to the dashboard; session established.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-02 — Login rejected on bad credentials
**Role:** any · **Steps:** open `/login`; enter a valid email with a wrong password; submit.
**Expected:** validation error shown; no session created; remains on login.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-03 — Logout and protected-route guard
**Role:** any (logged in) · **Steps:** log out; then attempt to open `/bookings` directly.
**Expected:** session ended; protected routes redirect to `/login`.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-04 — RBAC matrix spot-check
**Steps & Expected (each a check):**
- Pegawai opens `/admin/rooms` → **blocked (403/redirect).**
- GA Admin opens `/admin/rooms`, `/admin/facilities`, `/admin/room-blocks`, `/admin/logs` → **all load.**
- GA Admin opens `/admin/users` → **blocked.**
- Super Admin opens `/admin/users` → **loads.**
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## B. User Management

### UAT-05 — Super Admin creates a user
**Role:** Super Admin · **Steps:** `/admin/users/create`; enter name/email, assign a role and a unit; save.
**Expected:** user created; appears in the list with the assigned role/unit; can log in.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-06 — Super Admin edits a user
**Role:** Super Admin · **Steps:** open an existing user's edit page; change role and/or unit and/or active flag; save.
**Expected:** changes persist on reload; an inactive user cannot log in.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## C. Room & Facility Administration

### UAT-07 — GA Admin creates a room
**Role:** GA Admin · **Steps:** `/admin/rooms/create`; enter name (Indonesian bird theme), capacity, location; save.
**Expected:** room created and listed; available for booking selection.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-08 — GA Admin edits / deactivates a room
**Role:** GA Admin · **Steps:** edit a room; change capacity; set status inactive; save.
**Expected:** edits persist; an inactive room is not selectable when creating a new booking.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-09 — GA Admin creates facilities across categories
**Role:** GA Admin · **Steps:** `/admin/facilities/create`; create one facility for each category (AV, Furniture, Connectivity, Comfort) and one with no category.
**Expected:** all save; the category dropdown offers exactly those four options plus a blank; no invalid category is accepted.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-10 — Assign facilities to a room
**Role:** GA Admin · **Steps:** open a room's facility manager; attach two facilities; save; reopen.
**Expected:** the two facilities are shown as attached; visible on the room's detail when booking.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-11 — Set room operating hours
**Role:** GA Admin · **Steps:** open a room's operating-hours manager; set e.g. 08:00–17:00 for weekdays; save.
**Expected:** hours persist; they are enforced in UAT-16.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## D. Room Blocks

### UAT-12 — GA Admin blocks a room
**Role:** GA Admin · **Steps:** `/admin/room-blocks/create`; choose a room + a date/time range + reason; save.
**Expected:** block created; the room is unavailable for that range when booking.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-13 — Block forces cancellation of a conflicting booking
**Role:** GA Admin · **Pre:** an approved booking exists for room R at time T.
**Steps:** create a block on room R covering time T.
**Expected:** the conflicting booking is force-cancelled; a notification record is created for its requester; the block is recorded in the audit log.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## E. Booking Creation & Validation

### UAT-14 — Create a valid booking
**Role:** Pegawai · **Steps:** `/bookings/new`; pick an active room, a time within operating hours, attendees ≤ capacity, no overlap; submit.
**Expected:** booking created with a generated booking code; status reflects the configured flow (Draft or Submitted).
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-15 — Capacity exceeded is rejected
**Role:** Pegawai · **Steps:** create a booking with attendees greater than the room capacity.
**Expected:** validation error; booking not created.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-16 — Outside operating hours is rejected
**Role:** Pegawai · **Steps:** create a booking for a time outside the room's operating hours (set in UAT-11).
**Expected:** validation error; booking not created.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-17 — Overlapping booking on the same room is rejected
**Role:** Pegawai · **Pre:** a slot-locking booking exists for room R at time T.
**Steps:** attempt a second booking for room R overlapping time T.
**Expected:** conflict error; second booking not created.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-18 — Calendar / availability picker reflects bookings
**Role:** Pegawai · **Steps:** open the booking calendar / availability picker for room R.
**Expected:** the existing booked/blocked slots are shown as unavailable; free slots are selectable.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## F. Approval Workflow

### UAT-19 — Submit a draft for approval (routing)
**Role:** Pegawai · **Pre:** a Draft booking exists.
**Steps:** submit the draft for approval.
**Expected:** status → Submitted; the booking is routed to the correct approver and appears in their approval inbox.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-20 — Approve a booking
**Role:** Approver · **Steps:** open `/approvals`; approve the submitted booking.
**Expected:** status → Approved; the slot is locked against new conflicting bookings; a notification record is created for the requester.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-21 — Reject a booking
**Role:** Approver · **Steps:** open `/approvals`; reject a submitted booking with a reason.
**Expected:** status → Rejected; reason recorded; a notification record is created for the requester.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-22 — Conflict re-check at approval time
**Role:** Approver · **Pre:** two submitted bookings overlap the same room/slot (created before either was approved).
**Steps:** approve the first; then attempt to approve the second.
**Expected:** the second approval is blocked by the conflict re-check; it cannot be approved into a locked slot.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-23 — Approval inbox scope
**Role:** Approver · **Steps:** review the approval inbox.
**Expected:** only bookings routed to this approver / their unit are shown; not bookings belonging to other approvers.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## G. Booking Lifecycle

### UAT-24 — Cancel own booking releases the slot
**Role:** Pegawai · **Steps:** cancel an own Approved/Submitted booking.
**Expected:** status → Cancelled; the slot becomes bookable again (verify by creating a booking for the freed slot).
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-25 — Reschedule re-validates conflict
**Role:** Pegawai · **Steps:** reschedule an own booking to a new time; then attempt to reschedule into a known conflicting slot.
**Expected:** valid reschedule succeeds and updates the time; the conflicting reschedule is rejected.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-26 — Delete a booking is audited
**Role:** Pegawai (own) or GA Admin · **Steps:** delete a booking.
**Expected:** the booking is removed from the active list; the deletion is recorded in the audit log.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-27 — Cannot act on another user's booking
**Role:** Pegawai · **Steps:** while logged in as user A, attempt to cancel/edit a booking owned by user B (e.g. via its URL).
**Expected:** blocked (403); no change.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## H. Audit & Settings

### UAT-28 — Audit log viewer
**Role:** GA Admin · **Steps:** open `/admin/logs`; filter by module, by event, and by actor.
**Expected:** the actions performed in earlier scenarios (create/approve/reject/cancel/block) appear with actor, timestamp, and context; filters narrow the list correctly.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-29 — Settings change persists and is audited
**Role:** Super Admin / GA Admin (per policy) · **Steps:** open `/admin/settings`; change a setting; save; reload.
**Expected:** the new value persists across reload and takes effect; the change is recorded in the audit log.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## I. Concurrency & Integrity

### UAT-30 — Parallel submit, one winner
**Role:** two Pegawai sessions · **Steps:** two users submit a booking for the exact same room/slot as close to simultaneously as possible.
**Expected:** exactly one booking secures the slot; the other is rejected with a conflict (no double-booking). *(This mirrors the §M.4 race-condition requirement; a scripted version is added in the Sprint 6 test work.)*
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## Pending-Merge Features — execute only after the reminders / exports / attachments PRs are merged and staging is redeployed

### UAT-P1 — CSV export from the booking list
**Steps:** from `/bookings`, trigger CSV export.
**Expected:** a CSV downloads; rows match the caller's scope (Pegawai = own; admin = all/filtered); the export is recorded in the audit log (event `export`).
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-P2 — Attachment upload
**Steps:** on a booking show page (in an allowed status), upload a PDF ≤ 10 MB.
**Expected:** the file appears under Lampiran; the upload is audited (event `attach`).
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-P3 — Attachment download & delete
**Steps:** download the attachment from UAT-P2; then delete it.
**Expected:** the file is served on download; delete removes the record and the stored file and is audited (event `detach`).
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-P4 — Attachment guardrails
**Steps & Expected:** uploading a file > 10 MB → **rejected**; a non-owner without view-all attempting to manage attachments → **403**; uploading to a booking in a disallowed status → **blocked**.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

### UAT-P5 — Booking reminder
**Steps:** with the `schedule:run` cron active, have an upcoming booking inside the reminder window.
**Expected:** a reminder notification is sent once; `reminder_sent_at` is set; the reminder is not re-sent on the next run.
**Result:** ☐ Pass ☐ Fail · Tester: ________ · Date: ________

---

## Known Gap — expected failure until repair

### UAT-G1 — In-app notification visibility
**Steps:** as the requester from UAT-20/UAT-21, open the app and look for the approval/rejection notification in an in-app notification dropdown/center.
**Expected (target):** the notification is visible in-app.
**Current status:** **EXPECTED TO FAIL.** Notification *records* are created (verified in UAT-20/21), but no in-app surface reads them yet — this is Gap F (`NotificationDropdown`), scheduled for the Sprint 4 repair. Re-run after that ships.
**Result:** ☐ Pass ☐ Fail (☐ N/A — gap open) · Tester: ________ · Date: ________

---

## Sign-Off

| Set | Scenarios | Pass | Fail | Blocked/N-A |
|---|---|---|---|---|
| Core (UAT-01–30) | 30 | | | |
| Pending-merge (UAT-P1–P5) | 5 | | | |
| Known-gap (UAT-G1) | 1 | | | |

**UAT executed by:** ____________________  **Date:** __________
**Accepted for release by:** ____________________  **Date:** __________
**Notes / defects raised:** ___________________________________________

---

*Internal Use Only • BPJS Kesehatan • UAT Script v1.0*
