# SOC 2 / ISO 27001 — Code Controls Implementation Plan

> **Scope honesty.** SOC 2 and ISO 27001 certify an **organization**, not an app. SOC 2
> Type II requires 3–12 months of operating evidence reviewed by a CPA firm; ISO 27001
> requires an ISMS (risk assessment, Statement of Applicability, internal audit) certified
> by an accredited body. ~60–70% of both is organizational (policies, training, HR security,
> vendor management, access reviews, incident response) plus infrastructure. This document
> covers **only the technical controls implementable in the SIRRA codebase** — the subset
> that makes the app *audit-ready* and produces evidence. It does not, by itself, make
> anyone "compliant."

App: SIRRA — Laravel 11 + Livewire 3 meeting-room booking. Prod: booking.pi2.co.id (v1.77.0).

## Configurability requirement

**Every control is operator-configurable from `/admin/settings`** via a new `security`
settings group (`app_settings` rows, resolved through `SettingsService::get()`), consistent
with existing settings like `booking.auto_release_enabled`. Defaults are the **secure**
value. Toggling a control off is logged and, where disabling weakens compliance, the
setting description says so — these are operational kill-switches, not invitations to
turn security off.

| Setting key (`group = security`) | Type | Default | Controls |
|---|---|---|---|
| `security.audit_logging_enabled` | boolean | `1` | Release A — security event logging |
| `security.audit_log_retention_days` | integer | `365` | Release A — log retention window |
| `security.password_min_length` | integer | `12` | Release B — password policy |
| `security.password_require_mixed_case` | boolean | `1` | Release B |
| `security.password_require_numbers` | boolean | `1` | Release B |
| `security.password_require_symbols` | boolean | `0` | Release B |
| `security.password_check_breached` | boolean | `1` | Release B — HIBP k-anonymity check |
| `security.session_idle_timeout_minutes` | integer | `30` | Release B — idle logout |
| `security.session_absolute_timeout_minutes` | integer | `480` | Release B — absolute cap |
| `security.mfa_enabled` | boolean | `1` | Release C — TOTP feature available |
| `security.mfa_enforced` | boolean | `0` | Release C — require for all users |
| `security.mfa_enforced_for_privileged` | boolean | `0` | Release C — require for admin roles (opt-in: enable after admins enrol, to avoid forcing on deploy) |
| `security.feed_token_encryption` | (structural) | always on | Release D — see note |
| `security.inactive_account_days` | integer | `90` | Release E — access-review flagging |
| `security.dependency_scanning` | (CI) | always on | Release E — see note |

**Structural controls (no on/off toggle — disabling would be a security anti-pattern):**
data-at-rest encryption (Release D) and CI dependency scanning (Release E) are always on.
Release D exposes no toggle; Release E's scanning lives in CI, not runtime settings. This is
called out so "all configurable" doesn't translate into a switch that turns off encryption.

## Controls

### Release A — Audit foundation
- **A1. Security-event audit logging** (CC7.2 / ISO A.8.15). Extend `ActivityLogger` to a
  `security` module via an event subscriber on `Login`/`Logout`/`Failed`/`Lockout` + explicit
  `log()` calls at role/permission edits, user create/edit, password changes, email changes,
  settings changes. The `activity_logs` table already has actor/ip/user_agent/old/new/context.
  Gated by `security.audit_logging_enabled`.
- **A2. Audit-log immutability + retention** (CC7.3). `ActivityLog` `updating`/`deleting`
  guards (append-only); `EnforceDataRetention` archives/prunes beyond
  `security.audit_log_retention_days`.

### Release B — Auth hardening
- **B1. Password policy + breach check** (CC6.1 / A.5.17). Laravel `Password` rule built from
  the `security.password_*` settings, incl. `uncompromised()` (HIBP). Applied at every
  set/reset-password path.
- **B2. Session idle + absolute timeout** (CC6.1 / A.8.5). `SessionTimeout` middleware reading
  `security.session_*_timeout_minutes`.

### Release C — MFA / TOTP (CC6.1 / A.8.5)
`pragmarx/google2fa-qrcode`. New `users` columns `two_factor_secret`,
`two_factor_recovery_codes` (encrypted casts), `two_factor_confirmed_at`. Enrollment page
(QR + verify), post-login challenge step (hooked into the Volt `LoginForm::authenticate()`
flow before `Session::regenerate()`), recovery codes, enforcement via `security.mfa_*`.
Feature-flagged by `security.mfa_enabled`. Enroll/challenge/recovery events logged (Release A).

### Release D — Data-at-rest (CC6.1 / A.8.24)
Calendar-feed token (and telegram link token if displayed): `*_token_hash` indexed column for
lookup + `encrypted` cast for display (the deferred pen-test item). Migration nulls existing
tokens (prod has ~none → users re-subscribe). No runtime toggle (structural).

### Release E — Evidence tooling
- **E1. Access-review report** (CC6.2/6.3 / A.5.18). Admin read-only report: user → roles,
  permissions, `last_login_at`, active/inactive (flagged past `security.inactive_account_days`),
  MFA status. CSV-exportable for quarterly access reviews.
- **E2. Dependency vulnerability scanning** (CC7.1 / A.8.8). `composer audit` +
  `npm audit --audit-level=high` — already present as the `sca` job in
  `.github/workflows/ci.yml`.

## Sequencing
A → B → C → D → E. Each a Q-locked minor release (Pint · PHPStan L5 · tests ×3 flag-off +
flag-on), deployed on request, same flow as all prior work. Release A first so every later
control's events are captured in the audit trail.

## Out of scope (tracked here for the auditor roadmap, not implementable in code)
- **Infrastructure:** DB encryption-at-rest (InnoDB/LUKS), centralized log aggregation +
  alerting, offsite backups + **tested DR/failover** (single-server today is an availability
  gap), WAF/firewall, MFA on SSH, patch management.
- **Organizational:** the ISMS, risk assessment + treatment + SoA, the policy set, security
  awareness training, HR security, vendor risk, quarterly access reviews, incident response,
  management review, and the auditor engagement + evidence window.
