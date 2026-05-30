# Sprint 2 — Room Management — Implementation Spec

**Document type:** Milestone implementation spec (decisions-first)
**Status:** v1.2 — DELIVERED. All pieces shipped on `feat/sprint-2a-room-policies` (suite 484; +101 over the 383 baseline). PHPStan L5 + Pint clean.
**Date:** 23 May 2026
**Blueprint sprint:** §J.3 Sprint 2 (the last unbuilt "Must" module — Gap A in the roadmap reconciliation)
**Grounded in:** Blueprint v3 §D/§G/§H.7/§J.3; Database Schema v2 (rooms/facilities/operating-hours/blocks + §E.3/E.4/E.5 enums); Struktur Proyek v2 §D.3; and direct recon of the running codebase.

---

## Version History
- **v1.0** — initial draft, before any recon. Assumed resourceful-Blade CRUD with Form Requests, `rooms.manage`/`facilities.*` permissions, top-level controllers. All three were wrong.
- **v1.1** — corrected against recon and against the shipped S2-A/S2-B code. See §2 (Dec-18..21) for the corrections.
- **v1.2** — DELIVERED: all five pieces (policies, room CRUD, facilities + assignment, operating hours, blocking) shipped and tested; suite 484.

---

## 0. Status

| Piece | Scope | State |
|---|---|---|
| **S2-A** | `RoomPolicy` + `FacilityPolicy` + matrix tests (34) | ✅ Done, committed |
| **S2-B** | Room CRUD — `Admin\RoomController` + `RoomList`/`RoomForm` + views (15) | ✅ Done, committed |
| **S2-C.1** | Facilities master CRUD (`FacilityController` + `FacilityList`/`FacilityForm`) | ✅ Done (13) |
| **S2-C.2** | Room ↔ facility assignment (`RoomFacilityItem`: quantity, operational, notes) | ✅ Done (7) |
| **S2-D** | Operating-hours editor (7-day grid on room edit) | ✅ Done (8) |
| **S2-E** | Room blocking — action + notification + UI, §H.7 resolution | ✅ Done (E.1 12, E.3 12) |

Suite at Sprint 2 close: **484 passing** (101 new across S2-A…E).

---

## 1. Scope (Blueprint §J.3)

CRUD rooms; facility master + per-room assignment with quantity; per-room operating hours; room activation / deactivation / archiving; room blocking with conflict handling. Admins (super_admin, ga_admin) manage; all other roles get read-only room lists.

Out of scope (tracked elsewhere): public `rooms.index` read-only list, `bookings.index`, attachments, exports, audit-log viewer, Sprint 6.

---

## 2. Decisions

### 2.1 No delete — deactivate or archive (Dec-06)
Rooms: no `deleted_at`. **Deactivate** = `status=inactive`, `is_active=false`. **Archive** = `status=archived`, `is_active=false` (the "delete" replacement). `is_active` is derived from `RoomStatus::isBookable()`. Facilities use `is_active` only (no archive).

### 2.2 approval_mode change is sensitive
Changing a room's `approval_mode` must be audited with old/new snapshot, and must NOT affect in-flight bookings (they carry `approval_mode_snapshot` from submit, Dec-04). The room-edit form states this.

### 2.3 Deactivating/archiving a room with future bookings — non-destructive (Dec-15)
Allowed; existing `submitted`/`approved` bookings remain intact (only new bookings are prevented — the conflict service already requires `is_active && status=active`). The admin sees a warning with the future-booking count, but nothing cascades. **Shipped in S2-B `RoomList`.**

### 2.4 Room-block conflict checks `{submitted, approved}` (Dec-16)
§H.7 literally says "approved"; the slot-locking set is `{submitted, approved}`. We use the stricter locking set so a pending booking can't be approved into a blocked window. *(Applies in S2-E.)*

### 2.5 A Livewire component for block-conflict preview (Dec-17)
§D.3 says room blocking uses Livewire; Struktur §E.2 omitted it. We add `app/Livewire/Admin/RoomBlockForm` for the live conflict preview, mirroring `BookingForm`. *(S2-E.)*

### 2.6 Admin CRUD = thin controller + Livewire — NOT resourceful Blade (Dec-18)
**Recon correction.** The shipped `UserController` is three thin methods returning views; `UserList`/`UserForm` Livewire components do the work; `{id}` is resolved in the edit Blade view. This supersedes Blueprint §C.1's "admin CRUD = Blade murni." All Sprint 2 admin CRUD follows the shipped pattern: thin `Admin\*Controller` (index/create/edit) + `*List`/`*Form` Livewire + Blade wrappers. Validation lives inline in the Livewire component (`rules()`/`messages()`), not in Form Request classes (the user module has none; the Livewire golden rule permits inline `validate()`). Controllers live in `App\Http\Controllers\Admin`, not top-level.

### 2.7 Permissions — actual seeded set (Dec-19)
Seeded room permissions: `rooms.view`, `rooms.create`, `rooms.update`, `rooms.delete`, `rooms.manage-blocks`. There is **no `rooms.manage`** and **no `facilities.*`**. `RoomPolicy` keys on the granular four + `manage-blocks`; `FacilityPolicy` reuses `rooms.*` (the §G matrix is identical for facilities). The **admin management UI is gated by `rooms.update`**, not `rooms.view` — view-only roles hold `rooms.view` and will use the public list, so gating on `rooms.view` would let them into the admin UI.

### 2.8 Seeded roles diverge from §G (Dec-20)
Actual roles: `super_admin`, `system_admin`, `ga_admin`, `unit_approver`, `requester`. **No `front_office`** despite the §G Front Office row; **`system_admin` has zero room permissions**. Policy matrix tests cover the four cleanly-mapped roles only. Reconcile §G or accept the current set.

### 2.9 Authorization mechanism (Dec-21)
Admin CRUD authz is enforced two ways, matching the shipped `UserList`: route `permission:rooms.*` middleware + component `User::hasPermission()` checks. `RoomPolicy`/`FacilityPolicy` are the §M-mandated, tested encoding of the §G matrix and remain available for `->can()` usage (e.g. booking-style routes); they are deliberately *not* wired into the admin routes, to keep the admin pattern consistent with `UserList`. Auto-discovery does not resolve these policies in this app (every `->can()` denied in S2-B until we switched to `hasPermission`); if a future caller needs `->can()`, register via `Gate::policy()` in `AppServiceProvider::boot()`.

---

## 3. Permission Matrix (Blueprint §G)

| Resource | super_admin | ga_admin | unit_approver | requester | (front_office) |
|---|---|---|---|---|---|
| Rooms | V C U D M | V C U D M | V | V | — (no role) |
| Room Facilities | V C U D M | V C U D M | V | V | — (no role) |
| Room Block Schedules | V C U D M | V C U D M | V | V | — (no role) |

Codes: `rooms.view`, `rooms.create`, `rooms.update`, `rooms.delete`, `rooms.manage-blocks`. Facilities and blocks reuse `rooms.*` (Dec-19).

---

## 4. Detailed Design (per shipped pattern)

**Controllers** (`App\Http\Controllers\Admin`): `RoomController` ✅, `FacilityController`, `RoomBlockController` — each thin (index/create/edit → views).

**Livewire** (`App\Livewire\Admin`): `RoomList`/`RoomForm` ✅; `FacilityList`/`FacilityForm`; `RoomBlockForm` (Dec-17). Authorize via `hasPermission()`; validate inline.

**Policies**: `RoomPolicy` ✅, `FacilityPolicy` ✅ (tested §G spec).

**Action**: `BlockRoomAction` (S2-E) — transactional, `Room::lockForUpdate()`, hard-block conflict against `{submitted, approved}` (no buffer), resolution choices per §H.7 (cancel-block / cancel-bookings via `CancelBookingAction->execute($booking, $actor, reason:)` / adjust). Throws a new `RoomBlockConflictException` carrying the conflicts. Writes `RoomBlockCreatedNotification` + audit.

**Views**: `admin/{rooms✅,facilities,room-blocks}/{index,create,edit}` + `livewire/admin/*`. Mirror `admin/users/*`; Bahasa Indonesia; enum `->label()` for display; indigo palette to match the shipped admin UI.

**Operating hours** (S2-D): 7-row grid on room edit; unique `(room_id, day_of_week)`; `open_time`/`close_time` are raw strings (no cast); required unless `is_closed`, `close > open`.

**Room↔facility assignment** (S2-C.2): `RoomFacilityItem` (quantity ≥ 1, `is_operational`, notes); unique `(room_id, room_facility_id)`.

---

## 5. Test Plan (Struktur §G targets)

| File | Target | State |
|---|---|---|
| `Unit/Policies/RoomPolicyTest` | 20+ | ✅ 24 |
| `Unit/Policies/FacilityPolicyTest` | 10 | ✅ 10 |
| `Feature/Admin/RoomManagementTest` | — | ✅ 15 |
| `Feature/Admin/FacilityManagementTest` | — | S2-C.1 |
| `Feature/Admin/RoomBlockTest` + `Unit/Actions/BlockRoomActionTest` | — | S2-E |

**Enum-cast assertion invariant:** `rooms.status`/`rooms.approval_mode` store `.value` strings, cast on read. Assert against enum **cases** (`RoomStatus::Archived`), never strings.

---

## 6. Decision Log entries to record (Blueprint §B)
Dec-15 (non-destructive deactivation) · Dec-16 (block conflict = {submitted,approved}) · Dec-17 (RoomBlockForm Livewire) · Dec-18 (Livewire admin CRUD, not Blade) · Dec-19 (facilities reuse rooms.*) · Dec-20 (role set diverges from §G; system_admin roomless, no front_office) · Dec-21 (authz via middleware + hasPermission; policies as tested spec).

---

## 7. Definition of Done (Sprint 2)
All pieces merged; `RoomManagement`/`FacilityManagement`/`RoomBlock` feature tests + `RoomPolicyTest`(24)/`FacilityPolicyTest`(10) green; suite green; PHPStan L5 + Pint clean. Admins can manage rooms, facilities + assignments, operating hours, and blocks per §G; non-admins read-only. Roadmap reconciliation §1 Sprint 2 flips ❌ → ✅. Dec-15..21 recorded in the Decision Log.

---

*Internal Use Only • BPJS Kesehatan • Sprint 2 Room Management Spec v1.1*
