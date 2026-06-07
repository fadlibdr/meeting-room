# Admin Guide — Meeting Room BPJS Kesehatan

For administrators (GA Admin / System Admin / Super Admin). Covers managing
bookable resources, approval flows, settings, exports, and activating SSO +
calendar sync. Menu items appear based on your role's permissions.

## Rooms & resources
- **Kelola Ruangan** (`rooms.create`/`update`): create/edit meeting rooms — code,
  name, capacity, location, status, approval mode, per-room booking buffer.
  Inactive/archived rooms stop accepting bookings without deleting history.
- **Fasilitas**: facility catalogue assignable to rooms.
- **Sumber Daya** (Resources): manage non-room bookable types — **Peralatan /
  Kendaraan / Meja Kerja** — with type, code, name, capacity, status, approval
  mode. These book through the same engine as rooms.
- **Blokir Ruangan**: block a room/resource for a window (maintenance, events);
  blocks clash with bookings and can force-cancel conflicting ones.

## Approval flows
- **Mode Approval** per room/resource: `None` (auto-approve), `UnitApprover`,
  `GaAdmin`.
- **Kebijakan Persetujuan** (`app-settings.update`): configurable multi-step
  approval **chains** (policy → ordered steps). A room/unit policy overrides the
  legacy mode; an empty chain = auto-approve.
- **Delegasi Persetujuan**: route an approver's queue to a delegate for a date
  range (out-of-office).

## Settings (`/admin/settings`)
All runtime config is here (DB-backed, overrides `.env`):
- **Reservasi**: default buffer, draft purge, auto-release grace, max duration.
- **Notifikasi / Sistem / Pengguna**: email-default, maintenance mode, email
  domain restriction.
- **Email (SMTP)**: transport; the password is write-only (blank = keep). Use
  **Kirim Email Uji** to verify before enabling.
- **SSO Microsoft**: tenant/client id + client secret (secret write-only), default
  role, **Aktifkan SSO**. See activation below.
- **Sinkronisasi Kalender**: enable, consent mode, Microsoft/Google enable +
  Google client id/secret.

> Secrets entered here are encrypted in the DB (decryptable with `APP_KEY`). Keep
> `APP_KEY` out of the DB-backup blast radius.

## Reports & exports
- **Laporan Utilisasi**: per-room/peak/per-unit utilization.
- **Exports**: booking exports (CSV/XLSX) — large ones queue; download when ready.
- **Scheduled reports / BI feed**: `reports:send` (weekly/monthly email) and
  `reports:bi-export` (CSV snapshot) run on the scheduler.

## Activating SSO (Microsoft Entra ID)
1. In Entra, register an app; add redirect URI
   `https://<host>/auth/azure/callback`; grant OIDC (openid/profile/email);
   create a client secret.
2. In **Settings → SSO Microsoft**: enter Tenant ID, Client ID, Client Secret;
   set default role; optionally map AD group object-ids to roles (env
   `SSO_GROUP_*`); toggle **Aktifkan SSO**.
3. The "Masuk dengan Microsoft" button appears immediately. New users are
   JIT-provisioned; AD groups (when present) drive roles.

## Activating two-way calendar sync
1. Entra app: add Graph `Calendars.ReadWrite` + redirect URI
   `https://<host>/calendar/connect/microsoft/callback`. Google: GCP OAuth client
   + redirect `…/calendar/connect/google/callback`.
2. **Settings → Sinkronisasi Kalender**: enter creds, choose consent mode, enable
   Microsoft/Google.
3. Users connect their own calendar via **Langganan Kalender → Hubungkan**
   (delegated mode). Approved/updated/cancelled bookings then sync to their
   calendar.

## Data-subject requests (UU PDP)
- On the **Pengguna** list: **Data** downloads a user's personal-data JSON;
  **Anonimkan** erases their PII (keeps bookings for integrity, audit-logged).
  You cannot anonymise your own account.

## Audit & health
- **Log Aktivitas**: audit trail of privileged actions.
- Health: `/up` + `php artisan system:health-check`. Logs carry an
  `X-Request-Id` correlation id; set `LOG_STACK=json` for structured logs.
- Backups: see `docs/runbook-backups.md`.
