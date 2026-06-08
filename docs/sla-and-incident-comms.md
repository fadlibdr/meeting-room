# SLA Definitions & Incident Communications (Stage 4g.4)

_Internal Use Only • BPJS Kesehatan • 8 Jun 2026_

**Status: DRAFT templates.** Targets below are **placeholders** pending business
sign-off — no formal SLA is committed yet (the `/legal/security` page says so).
These templates pair with the in-app support form (4g.1), the status page (4g.3),
the backups runbook (`docs/runbook-backups.md`), and the user/admin guides.

---

## 1. Severity definitions

| Sev | Definition | Public status maps to |
|---|---|---|
| **SEV1** | Service unavailable for most users (e.g. cannot log in / book) | `down` |
| **SEV2** | Major feature degraded or unavailable; workaround exists | `degraded` |
| **SEV3** | Minor/cosmetic; limited user impact | `up` (tracked internally) |

> The public status page exposes only **up / degraded / down** — never internal
> component detail (consistent with the shallow `/up` decision).

## 2. Draft SLA targets — `[TO BE CONFIRMED BY BUSINESS]`

| Metric | Draft target |
|---|---|
| Monthly uptime | `[TBC]` (e.g. 99.5%) |
| SEV1 response | `[TBC]` (e.g. 30 min) |
| SEV2 response | `[TBC]` (e.g. 4 business hours) |
| SEV3 response | `[TBC]` (e.g. 2 business days) |
| Support hours | `[TBC]` (e.g. 08:00–17:00 WIB, hari kerja) |

## 3. Incident response — quick flow

1. **Detect** — health-check alert, user report (support form), or status check.
2. **Triage** — assign severity (above); open an incident record.
3. **Communicate** — post initial status (template §4); set status page.
4. **Mitigate/resolve** — fix or roll back (see deployment runbook).
5. **Close** — set status `up`; send resolution note (template §4).
6. **Postmortem** — for SEV1/SEV2, blameless postmortem within `[TBC]` days.

## 4. Communication templates

### 4.1 Initial notification (internal + affected users)
```
[GANGGUAN LAYANAN — {SEV}] {ringkasan singkat}
Mulai: {waktu WIB}
Dampak: {apa yang tidak berfungsi, untuk siapa}
Status: Sedang diselidiki.
Pembaruan berikutnya: {waktu}.
```

### 4.2 Update
```
[PEMBARUAN — {SEV}] {ringkasan}
{apa yang sudah diketahui / langkah yang sedang dilakukan}
Status: {Diselidiki | Teridentifikasi | Sedang diperbaiki | Dipantau}.
Pembaruan berikutnya: {waktu}.
```

### 4.3 Resolution
```
[PULIH — {SEV}] {ringkasan}
Pulih: {waktu WIB}. Durasi: {durasi}.
Penyebab (sementara): {1 kalimat}.
Tindak lanjut: postmortem {tanggal} (SEV1/SEV2).
```

### 4.4 Postmortem skeleton (SEV1/SEV2)
```
# Postmortem — {judul} ({tanggal})
- Ringkasan & dampak
- Linimasa (deteksi → pulih)
- Akar penyebab
- Yang berjalan baik / kurang baik
- Tindakan perbaikan (pemilik, tenggat)
```

## 5. Help-centre (starter)

The user/admin guides (`docs/user-guide.md`, `docs/admin-guide.md`) are the help
centre seed. Surface them from the in-app support form (`SUPPORT_HELP_CENTER_URL`)
when a hosted help centre exists.
