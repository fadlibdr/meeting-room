# User Guide — Meeting Room BPJS Kesehatan

A short guide for everyday users: booking a room or resource, getting it
approved, and checking in. The interface is in Bahasa Indonesia (switchable to
English from the profile menu).

## Signing in
- Go to the app URL and sign in with your email + password, or **Masuk dengan
  Microsoft** if SSO is enabled.
- Switch language any time from the avatar menu (top-right) → ID / EN.

## Making a booking
1. **Kalender** or **Reservasi → Buat Reservasi**.
2. Fill the title (Judul Rapat), agenda (optional), and number of attendees.
3. Pick the **start/end time** (24-hour). Bookings are stored in UTC and shown in
   Jakarta time.
4. Choose the **resource type** (Ruangan / Peralatan / Kendaraan / Meja Kerja),
   then pick a resource from the availability picker:
   - **Hijau** = available · **Merah** = clashes (it shows the conflict) ·
     a capacity hint appears if attendees exceed capacity (advisory).
5. **Buat / Kirim**. Depending on the resource's approval setting your booking is
   either **auto-approved** or **submitted for approval**.

### Recurring bookings
On the create form, toggle **Berulang**, choose frequency (daily/weekly/monthly),
interval, and an end (after N occurrences or until a date). Occurrences that
clash are skipped automatically.

## Approvals (if you are an approver)
- **Persetujuan** (sidebar, with a pending count badge) lists bookings awaiting
  your decision.
- Open one → **Setujui** or **Tolak** (rejection needs a reason). Multi-step
  chains route to the next approver automatically; you'll be notified when it's
  your turn.
- Out of office? An admin can set an **approval delegation** so your queue routes
  to a delegate for a date range.

## Check-in
- **QR**: each confirmed booking has a QR code; scan it at the room to check in
  (the link is time-limited).
- **Front desk**: front-office staff can check you in manually from **Front Desk**.
- **No-show auto-release**: if nobody checks in within the grace period, the slot
  is auto-released so others can book it.

## Your bookings
- **Reservasi** lists your bookings. From a booking you can **edit** (a submitted
  booking returns to draft and must be re-submitted), **reschedule** (creates a
  new booking and cancels the old), or **cancel**.
- **Download .ics**: each booking can be added to your calendar.

## Calendar subscription (see all your bookings in Outlook/Google/Apple)
- Avatar menu → **Langganan Kalender** → copy the subscription URL and add it as a
  "subscribe by URL" calendar. It updates automatically. You can rotate the URL
  if it leaks. If two-way sync is enabled, you can instead **Hubungkan** your
  Outlook/Google calendar for live event creation.

## Your data (UU PDP)
- Avatar menu → **Unduh Data Pribadi** downloads a JSON copy of your personal
  data (profile + bookings). To have your data erased, contact an administrator.

## API access (for integrations)
- Avatar menu → **Token API** to create a personal access token (shown once).
  Browsable API docs are at **Dokumentasi API**. See
  `docs/api-v1-resource-id-migration.md` if you integrated before the
  `room_id`→`resource_id` change.
