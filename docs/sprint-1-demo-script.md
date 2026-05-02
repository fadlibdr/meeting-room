# Sprint 1 Demo Script — BPJS Meeting Room Booking System

**Audience:** Mixed (technical + business stakeholders at BPJS Kesehatan)
**Duration:** 15-20 min presentation + 10 min Q&A
**Demo URL:** https://booking.pi2.co.id
**Document version:** 1.0 (covers Sprint 1A through 1F)

---

## Pre-Demo Checklist

Run **15 minutes before** stakeholders join the call.

### Environment prep

- [ ] SSH to staging: `ssh bpjs-staging`
- [ ] Run `~/staging-reset.sh` — type `reset` to confirm
- [ ] Verify reset completed: 10 users, 50 bookings, 0 audit logs
- [ ] Check site is up: `curl -s -o /dev/null -w "%{http_code}\n" https://booking.pi2.co.id/login` should return `200`

### Browser prep

- [ ] Open **fresh incognito/private browser window** (avoids saved sessions, cached views)
- [ ] Browse to https://booking.pi2.co.id/login
- [ ] Verify login page renders cleanly — Indonesian labels visible, no SSL warnings
- [ ] Open a **second tab** with these credentials saved (private viewing):
  - `superadmin@bpjs-kesehatan.go.id` / `password` (primary demo account)
  - `dewi.lestari@bpjs-kesehatan.go.id` / `password` (for Beat 6 role contrast)
  - `hari.nugroho@bpjs-kesehatan.go.id` / `password` (inactive — for Beat 2 lockout demo)

### Workspace prep

- [ ] Close other browser tabs — Slack, email, distracting sites
- [ ] Mute notifications (system-wide if possible)
- [ ] Test screen share resolution if remote — fonts should be readable
- [ ] Have `~/SCRIPTS_README.md` open in a separate tab as reference if questions arise
- [ ] Have water nearby

### Mental prep

- [ ] Read through this script once
- [ ] Note: it's OK to deviate from the words. The structure (7 beats, timing) matters more than exact phrasing
- [ ] Note: if something breaks mid-demo, see Fallback Plans at end of document

---

## Demo Arc — 7 Beats

| # | Beat | Duration | Key moment |
|---|------|----------|------------|
| 1 | What this is | 1 min | Verbal context, no app yet |
| 2 | Login as Super Admin | 1 min | Optional inactive-user reject demo |
| 3 | Dashboard tour | 2 min | Stats panels + Recent Activity widget |
| 4 | User management | 4 min | **Yellow password banner** (showpiece) |
| 5 | Audit trail in action | 2 min | Recent Activity widget shows new entries |
| 6 | Role-aware navigation | 2 min | Same DB, different visible surface |
| 7 | What's coming next | 1 min | Sprint 2 + Phase 2 + Phase 3 roadmap |
| — | Q&A | 10 min | Anticipated questions below |

Total demo time: ~13 min walkthrough + 10 min Q&A = 23 min

---

## Beat 1 — "What this is" (1 min)

**Don't open the app yet.** Set context first.

### Say:

> "Today I'm showing the foundation of the new internal meeting room booking system for BPJS Kesehatan. This replaces the current Excel-and-email back-and-forth that GA team handles for room scheduling.
>
> Built as a Laravel monolith — single deployment, single database, audit-ready from day one. We chose this over a microservices approach because the operational footprint matches what BPJS internal IT can run on existing VPS infrastructure.
>
> What you'll see today is Sprint 1 — authentication, role management, audit trail, and user provisioning. Sprint 2, which starts next week, builds the actual booking flow."

### Why this beat matters

- Sets expectations: this is foundation, not the booking feature itself
- Frames technical choices in BPJS-realistic terms (existing VPS, internal IT capability)
- Signals the rest of the roadmap exists

---

## Beat 2 — Login as Super Admin (1 min)

Open https://booking.pi2.co.id/login in the prepared incognito window.

### Say:

> "Session-based authentication. Username and password, with three security layers: 5-attempt lockout per email address, account-active checking, and email domain validation against `@bpjs-kesehatan.go.id`."

### Optional — Inactive user demo

If time permits and you want to show robustness:

1. In a separate incognito tab, try logging in as `hari.nugroho@bpjs-kesehatan.go.id` / `password`
2. Result: Indonesian error message "Akun Anda telah dinonaktifkan. Silakan hubungi administrator."

> "We don't delete users — Decision Dec-06 in the blueprint. Deactivated accounts can't login but their history is preserved for audit. This matters for BPJS because employee access changes over time and the audit trail needs to remain coherent."

### Continue

Login as `superadmin@bpjs-kesehatan.go.id` / `password`. Lands on `/dashboard`.

> "And we're in. Session is authenticated, user record loaded, roles and permissions cached server-side."

### Common pitfall

**Don't** show the lockout (5 failed attempts) live — it actually locks the account for the demo. If asked, describe it verbally: "5 failed attempts triggers a 15-minute lockout window."

---

## Beat 3 — Dashboard Tour (2 min)

After login, on `/dashboard`. Walk through each section visible to Super Admin.

### Welcome banner

> "Personalized welcome with the user's name. Small detail, but it sets context for who's logged in — useful in BPJS shared workstation environments."

### Stats panels (3 cards)

> "These three cards are role-aware: Total Users, Available Rooms, and Pending Approvals. Super Admin sees all three because the role has permissions for users, rooms, and bookings approval. A regular requester would see none of these — same code, different visible surface based on permissions."

Specific data to point out:
- **Total Users: 10** — that's our seed data. Production would show actual headcount.
- **Available Rooms: 8** — across multiple BPJS office floors and locations
- **Pending Approvals:** varies (depends on seed)

### Recent Activity widget

> "This is the audit trail surface for admins. Every important action — user creation, role changes, future bookings, approvals — appears here automatically. Updates every 30 seconds without page reload, using Livewire's polling mechanism.
>
> Right now it's empty because the database was just reset for this demo. We'll see entries appear shortly when we provision a new user."

### Why this beat matters

- Shows role-awareness without saying it explicitly
- Introduces the audit trail concept before demonstrating it
- The "30 second auto-refresh" is a small but credibility-building detail

---

## Beat 4 — User Management (4 min — the showpiece)

Click "Manage Users" in the top nav.

### Filter demonstration (1 min)

On `/admin/users`. Point out:

> "Ten users seeded for the demo, representing a typical BPJS organizational mix — Super Admin, System Admin, GA Admin, Unit Approvers for SDM and IT departments, and regular Requesters."

**Demo 1: Search**

Type `dewi` in the search box. Table filters live as you type.

> "Search filters by name and email together. Useful when GA receives a name from someone but doesn't remember the exact email format."

Clear search.

**Demo 2: Role filter**

Change role filter dropdown to "Requester". Table shows only requesters.

> "Filter by role — useful for compliance reports. 'Show me all active requesters in IT unit' becomes a one-click query."

Reset filters.

### Create new user (2 min) — THE SHOWPIECE

Click "+ Tambah Pengguna" button.

> "User provisioning workflow. Watch closely — there's a small detail that matters for security."

Fill in the form:

- **Nama:** `Demo Test User`
- **Email:** `demo.test@bpjs-kesehatan.go.id`
- **Unit:** select any (e.g., IT)
- **Roles:** check Requester
- **Status:** leave Active

Click "Buat Pengguna".

### THE MOMENT: Yellow banner appears with generated 12-character password

### Say:

> "Notice — the system generates a random 12-character password and displays it ONCE in this banner. Admin reads it to the user verbally, in person or by phone.
>
> The password is hashed with bcrypt in the database immediately. We don't store the cleartext anywhere — it lives only in this displayed banner during this admin session.
>
> This pattern is intentional. It means even if the database is leaked, the only people who ever saw the cleartext are the two humans in that initial conversation."

### Anticipated question and answer

**Q: "What about email delivery of the password?"**

**A:**
> "Coming in Sprint 2. We'll add an opt-in 'send via email' button that uses a tokenized link — user clicks it, sets their own password, no cleartext ever in transit. The verbal handoff stays as the audit-clean default; email is the convenience option for users who aren't reachable in person."

### Deactivate user (45 sec)

Click "Kembali ke daftar pengguna" to return to the user list.

Find "Demo Test User" in the table — should be at the bottom or sortable to top.

Click "Nonaktifkan" button on the row. Browser confirms with dialog. Click OK.

User row updates: status badge turns red "Nonaktif", Aktif filter no longer shows them.

### Say:

> "User is now deactivated. Notice — we never delete users. Decision Dec-06 in our blueprint. This preserves audit history. If we deleted, the question 'who created this booking last March?' would have a NULL answer for any deactivated user.
>
> The Nonaktifkan action itself just gets logged — we'll see it in the audit trail in the next beat."

### Edit user (45 sec)

Find an active user (e.g., Dewi Lestari). Click "Edit" on her row.

> "Edit form is pre-filled with the user's current data. You can change name, email, unit, role assignments, or active status. Submit goes back to the list with a success message — no separate password banner since we're not creating."

Show the form briefly. Don't actually submit changes — just demonstrate the form is there. Click "Batal" to cancel.

### Why Beat 4 matters

- Yellow banner is the **most demo-able security feature** in the system. Every audit-conscious stakeholder appreciates "we don't store cleartext passwords."
- Deactivate-vs-delete shows architectural maturity (Dec-06 decision, audit-preserving)
- Filter demonstrations show the table isn't just static — it's a real working tool

---

## Beat 5 — Audit Trail in Action (2 min)

Click "Dashboard" in the navigation bar.

The Recent Activity widget should now show entries:

- "User Demo Test User provisioned" (a few minutes ago)
- "User Demo Test User deactivated" (a few minutes ago)

If the widget hasn't auto-refreshed yet, it will within 30 seconds. You can optionally click anywhere on the page to trigger a Livewire interaction that updates immediately.

### Say:

> "There are our audit entries. Created automatically — no extra code in the user controller, no manual logging call. This is wired through Laravel observers: when a User model fires its 'created' or 'deactivated' event, the UserObserver fires automatically and writes to activity_logs.
>
> What you see here in the widget: actor name, the action, timestamp.
>
> The full record in the database also captures: IP address, user agent, before/after state of the user record. So if someone asks 'who changed Pak Budi's permissions on April 15?' — we have the answer with full forensic detail.
>
> This is the foundation for BPJS audit compliance. Every important action gets recorded automatically."

### Optional — Show role change audit

If time permits, navigate to a user's edit page, change their role assignment, save, return to dashboard. The new audit entry "User X role assigned [role]" appears in the widget.

### Why this beat matters

- Connects Beat 4 (creating user) to Beat 5 (seeing the audit) — reinforces "the system tracks everything"
- BPJS as a public health insurance body has compliance obligations. This is the credibility moment.
- "Automatic" is the key word — admins can't forget to log; the system does it for them

---

## Beat 6 — Role-Aware Navigation (2 min)

Click the user dropdown (top right of the page) → "Logout".

You're back at the login page.

Login as `dewi.lestari@bpjs-kesehatan.go.id` / `password`.

### Say:

> "Same login screen, same database, but watch what changes."

### Point out the differences

**Top navigation:**
- Super Admin saw: Dashboard, Manage Users, Bookings, Approvals, Rooms, Manage Rooms
- Dewi (Requester) sees: Dashboard, Bookings (and that's it for now — full booking UI ships in Sprint 2)

**Dashboard:**
- Welcome banner: present (any logged-in user gets one)
- Stats panels: NONE visible (no `users.view`, `rooms.view`, or `bookings.approve` permissions)
- Recent Activity widget: NOT visible (no `activity-logs.view` permission)

### Say:

> "Pak Dewi is a regular Requester at BPJS. Same codebase, same database — but the visible interface is dramatically different because of role-based permission gating.
>
> This isn't security through obscurity. The navigation hides options because Dewi *can't* perform those actions even if she URL-hacked her way to them — every controller action and Livewire component is also gated by Policies. The hidden nav is just UX, not the actual security boundary.
>
> Different roles have a meaningful design contract: see only what's relevant to your job. Reduces clutter, reduces accidental clicks, reduces user confusion."

### Optional — Show Manager role for variety

Logout, login as `ga.admin@bpjs-kesehatan.go.id` / `password`.

> "GA Admin has a middle-ground view — sees rooms, bookings, approvals, but not user management. Different role, different surface."

### Why this beat matters

- Demonstrates the RBAC system actually works, not just talked about
- Same-code-same-database point quietly reinforces "this is one system, not multiple"
- "UX vs security boundary" distinction shows architectural maturity

---

## Beat 7 — What's Coming Next (1 min)

Logout. Show the login page one final time.

### Say:

> "What you've seen today is Sprint 1 — the foundation: session authentication, role-based access control, automatic audit trail, and user provisioning. All audit-ready, all aligned with the architectural decisions documented in our blueprint.
>
> Sprint 2, starting next week, builds the actual booking experience:
> - Calendar UI for selecting available rooms and times
> - Conflict detection — two people can't double-book the same room
> - Approval workflow — bookings flow to the right approver based on room and unit
>
> Phase 2, after MVP launch:
> - Recurring meetings — weekly, biweekly, monthly standing meetings
> - Email notifications for approvals and reminders
> - Calendar exports to Outlook and Google Calendar so external invitees can see them
>
> Phase 3, for after the system has run in production for a while:
> - SSO/LDAP integration with BPJS Active Directory — single sign-on across internal systems
> - Multi-step approval workflows for high-stakes rooms
> - Advanced analytics on room utilization
>
> All architectural decisions documented — 13 reconciliation decisions in our blueprint guide every choice. If you have technical questions about why we made certain calls — for instance, why we didn't use Spatie Permission package, or why we chose UTC datetime storage — that doc has the trail."

### Why this beat matters

- Closes the loop: today is foundation, future has clear scope
- Names specific Phase 2/3 features so stakeholders aren't guessing
- The "decisions documented" line invites technical questions to the doc, not just "trust me"

---

## Q&A — Anticipated Questions

Stakeholders will ask. Here are prepared answers for the most likely.

### Technical questions

**Q: "Why Laravel and not Spring Boot / Node.js / .NET?"**

**A:**
> "Three reasons: Laravel matches BPJS's existing internal IT skill set, has strong audit-trail libraries built in via observers, and runs comfortably on the VPS profile we have available. We considered Spring Boot — heavier ops overhead. We considered Node/Express — less mature audit ecosystem. Laravel hit the sweet spot for this organization."

**Q: "What about disaster recovery and backups?"**

**A:**
> "Sprint 6 covers DR. Daily database dumps to off-site storage, attachments backed up separately, restore tested monthly. Currently in development — staging is not yet production-grade DR. The process is documented and we'll exercise it before go-live."

**Q: "How does this integrate with our existing Active Directory / LDAP?"**

**A:**
> "Phase 3 — SSO/LDAP integration is on the roadmap. For now, users authenticate against a local user table that GA admins provision. The schema is designed to accept federated identities later without migration. When SSO arrives, we'll add an `external_id` column and a federation source. Existing local users remain compatible."

**Q: "What's the audit log retention policy?"**

**A:**
> "Activity logs are kept indefinitely in the active table currently — sub-second query time even with hundreds of thousands of rows. Sprint 6 introduces a tiered retention policy: hot storage for 90 days, then archived to cold storage in S3-compatible storage. Final retention period to be set per UU PDP guidance and BPJS legal review. We err on the side of keeping more, not less."

**Q: "How is performance? Will it scale?"**

**A:**
> "Designed for ~500-1000 BPJS internal users. The booking conflict detection is the hot path — we benchmarked it at sub-50ms even with 10,000 historical bookings. If we ever exceed that scale, the architecture supports horizontal scaling: stateless app, sticky session at load balancer, single primary database. We're not at that scale today."

**Q: "What about mobile?"**

**A:**
> "MVP is responsive web — works on mobile browsers. A native mobile app is not in scope. The vast majority of BPJS internal users book from desktop or laptop. If mobile becomes a major use case post-launch, we'd consider a Progressive Web App rather than native — much lower maintenance burden."

### Process questions

**Q: "When can users start using this?"**

**A:**
> "Production launch target is end of Sprint 6, roughly 8-10 weeks from now. We'll do UAT with a pilot group from GA team in Sprint 5 and adjust based on feedback. Production rollout will be gradual — single department first, then wider."

**Q: "What if we want to add a feature you didn't mention?"**

**A:**
> "Document it as a Phase 2 or Phase 3 candidate, and we'll evaluate scope and timing. The architecture is intentionally extensible — most features can be added without rework. Things to flag early: anything requiring schema changes, anything requiring external system integration, anything affecting audit guarantees."

**Q: "Who's responsible for ongoing maintenance after launch?"**

**A:**
> "TBD — that's a conversation for after Sprint 6. The codebase is documented, deployment is scripted, the runbook for operations exists. Either the development team continues with bug-fix support and Phase 2 features, or BPJS internal IT takes over with knowledge transfer. Both paths are workable."

### Curveball questions

**Q: "Can we see the source code?"**

**A:** Sure. Show `https://github.com/fadlibdr/meeting-room` (or wherever the repo lives — adjust based on access policies).

**Q: "How much does this cost to run?"**

**A:**
> "Single VPS at a major Indonesian cloud provider, around 2GB RAM, 40GB disk — costs about Rp 200,000 to Rp 500,000 per month depending on provider. Negligible against the productivity savings from automated room booking."

**Q: "What if the developer leaves?"**

**A:**
> "Architectural decisions documented in blueprint v3. Code follows Laravel conventions — any Laravel developer can read it. Key business logic isolated in service classes with tests. Deployment is scripted. The system is intentionally not 'tribal knowledge'."

---

## Fallback Plans — When Things Go Wrong

### Scenario: Dashboard returns 500 error mid-demo

**Verbal recovery:**
> "Looks like we have a hiccup — let me check the logs and we'll come back to this."

**Behind the scenes:**
1. SSH to staging from a separate terminal
2. `tail -50 /var/www/meeting-room/storage/logs/laravel.log`
3. Common causes:
   - Permissions issue: `sudo chown -R deployer:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache`
   - Stale cache: `php artisan optimize:clear && php artisan config:cache && sudo systemctl reload php8.3-fpm`
4. While fixing, pivot the demo to the codebase architecture or skip ahead

### Scenario: Internet flakes during screen share

- Have **screenshots** of all 7 beats saved as a PDF backup
- Talk through screenshots while connection recovers
- "I have screenshots if connectivity remains an issue — let me share those instead."

### Scenario: Stakeholder asks about a feature that doesn't exist yet

- "Great question — that's specifically scoped for Sprint X / Phase Y. The schema is designed to support it. Happy to walk through the technical approach after the demo, or in our follow-up."
- Don't fake it. Acknowledge the gap, point to the roadmap.
- If asked: "When?" — give honest estimate or "We'll prioritize based on feedback today."

### Scenario: Stakeholder challenges an architectural decision

- "That's a fair question. We chose [X] because [reason]. Decision Dec-Y in our blueprint documents the trade-off. If you want to revisit — we can, but it would mean [X impact on schedule / scope]."
- Don't be defensive. Decisions are revisitable; the blueprint isn't sacred.

### Scenario: Demo runs long and you're at 25 min with Q&A still open

- Acknowledge time: "I want to be respectful of everyone's time. Let me park remaining questions for offline — I'll send a follow-up doc with answers."
- Offer: "Anyone want to schedule a deep-dive on a specific area?"
- Don't rush through Q&A. Better to defer one question well than answer five badly.

### Scenario: A stakeholder requests an immediate change ("add this button")

- "I hear you. Let me note that down and we'll evaluate scope after this session. Is this a hard requirement for launch, or a nice-to-have?"
- Don't commit to changes mid-demo. Calmly defer.

---

## Post-Demo Cleanup

After the demo ends:

- [ ] Note any feedback or follow-up questions from stakeholders in a doc
- [ ] If anything broke during demo, file an issue immediately while it's fresh
- [ ] If stakeholders requested a feature, log it in the appropriate sprint/phase backlog
- [ ] Send thank-you email summarizing key points and next steps within 24 hours
- [ ] Update this script with lessons learned for future demos

---

## Appendix: Quick Reference Card

Print this section, keep visible during demo.

| Beat | URL | Action | Key phrase |
|------|-----|--------|------------|
| 1 | (none) | Verbal context | "foundation, audit-ready, monolith" |
| 2 | /login | Login as superadmin | "session, lockout, active-account" |
| 3 | /dashboard | Tour | "role-aware, audit trail, 30-second refresh" |
| 4 | /admin/users | Filter, create, deactivate | "yellow banner, hashed immediately, never deleted" |
| 5 | /dashboard | Show audit | "automatic, observers, full forensic record" |
| 6 | /login dewi | Compare nav | "same DB, different visible surface, UX not security" |
| 7 | /login | Roadmap | "Sprint 2 booking, Phase 2 recurring, Phase 3 SSO" |

### Demo accounts

- `superadmin@bpjs-kesehatan.go.id` / `password` — primary
- `dewi.lestari@bpjs-kesehatan.go.id` / `password` — Beat 6 contrast
- `hari.nugroho@bpjs-kesehatan.go.id` / `password` — inactive demo
- `ga.admin@bpjs-kesehatan.go.id` / `password` — optional middle-ground role

### Decision references (if asked)

- **Dec-02:** RBAC in-house, not Spatie — full control, audit-ready
- **Dec-06:** No soft delete — status-based deactivation, audit-preserving
- **Dec-09:** UTC datetime in DB — multi-timezone-ready
- **Dec-13:** Recurring booking out of MVP — schema placeholder ready

---

*Document version 1.0. Last updated: end of Sprint 1F.*
*For Sprint 2 demo, copy this template, update beats, save as `sprint-2-demo-script.md`.*
