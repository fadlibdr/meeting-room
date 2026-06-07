# API v1 — Migration Notice: `room_id` → `resource_id`

**Audience:** anyone integrating with the BPJS Kesehatan Meeting Room public API (`/api/v1`).
**Released in:** `v1.25.0` (rename) + `v1.28.0` (booking of non-room resources).
**Severity:** **Breaking for response readers.** Non-breaking for writers (a deprecated alias is accepted).

---

## Why this changed

The system can now book more than meeting rooms — equipment, vehicles, and hot desks
are all schedulable "resources" through the same API. The field that used to identify
the room a booking belonged to (`room_id`) has been renamed to **`resource_id`**, and the
nested `room` object in responses is now **`resource`**. The value is the same kind of id;
only the name changed (and it may now point at a non-room resource).

---

## What you must change

### 1. Reading booking responses — **action required**

The `GET /api/v1/bookings` and `POST /api/v1/bookings` responses renamed the nested object
`room` → `resource`.

**Before**
```json
{
  "data": {
    "id": 1234,
    "booking_code": "BKG-20260607-AB12",
    "subject": "Rapat Koordinasi",
    "status": "approved",
    "room": { "id": 7, "name": "Ruang Garuda 1" },
    "attendee_count": 8,
    "starts_at": "2026-06-08T02:00:00+00:00",
    "ends_at": "2026-06-08T03:00:00+00:00"
  }
}
```

**After**
```json
{
  "data": {
    "id": 1234,
    "booking_code": "BKG-20260607-AB12",
    "subject": "Rapat Koordinasi",
    "status": "approved",
    "resource": { "id": 7, "name": "Ruang Garuda 1" },
    "attendee_count": 8,
    "starts_at": "2026-06-08T02:00:00+00:00",
    "ends_at": "2026-06-08T03:00:00+00:00"
  }
}
```

➡️ Update any code reading `data.room` / `data.room.id` to `data.resource` / `data.resource.id`.

### 2. Availability endpoint — **action required**

`GET /api/v1/rooms/{room}/availability` renamed the response field `room_id` → `resource_id`.
The URL path is unchanged.

**Before** `{ "data": { "room_id": 7, "available": true, ... } }`
**After**  `{ "data": { "resource_id": 7, "available": true, ... } }`

### 3. Webhook payloads — **action required**

The `booking` object in every webhook event renamed `room_id` → `resource_id`.

**Before** `"booking": { "id": 1234, "room_id": 7, ... }`
**After**  `"booking": { "id": 1234, "resource_id": 7, ... }`

(Headers and signature scheme are unchanged: `X-Webhook-Signature: sha256=…` over the raw body.)

### 4. Creating a booking — **no change required (yet)**

`POST /api/v1/bookings` now prefers `resource_id`, but **still accepts the legacy `room_id`
as a deprecated alias**, so existing write integrations keep working:

```jsonc
// Preferred
{ "resource_id": 7, "subject": "Rapat", "attendee_count": 8,
  "starts_at": "2026-06-08T02:00:00Z", "ends_at": "2026-06-08T03:00:00Z" }

// Still accepted (deprecated) — please migrate
{ "room_id": 7, "subject": "Rapat", "attendee_count": 8,
  "starts_at": "2026-06-08T02:00:00Z", "ends_at": "2026-06-08T03:00:00Z" }
```

`resource_id` may target any active resource (room **or** equipment/vehicle/desk).

---

## Recommended migration steps

1. **Now:** update response/webhook readers to use `resource` / `resource_id` (breaking).
2. **Soon:** switch `POST` payloads from `room_id` to `resource_id` (the alias is deprecated and may be removed in a future major version).
3. Re-fetch the OpenAPI spec at `GET /api/v1` docs (`/api-docs`) — it documents `resource_id`, marks `room_id` `deprecated: true`, and describes the webhook payload.

## Questions
Contact the GA / system administrator team. The interactive spec is browsable at
`/api-docs` (authenticated).
