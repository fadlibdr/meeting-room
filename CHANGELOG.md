# Catatan Rilis

Riwayat perubahan Sistem Pemesanan Ruang Rapat BPJS Kesehatan. Versi mengikuti
penomoran semantik (vMAJOR.MINOR.PATCH). Entri terbaru di atas.

> Catatan: fitur multi-penyewa (tenancy) dan beberapa integrasi dirilis dalam
> keadaan **nonaktif** (flag-gated) hingga peninjauan kesiapan selesai.

---

## Stage 4 — Produktisasi & kepercayaan

### v1.51.0 — Bantuan dalam aplikasi
- Formulir "Bantuan & Dukungan": kirim pertanyaan/laporan langsung dari aplikasi; tercatat dan diteruskan ke tim dukungan.

### v1.50.0 — Persetujuan cookie
- Banner persetujuan cookie. Skrip non-esensial (analitik) hanya aktif setelah Anda menyetujui (bawaan: nonaktif).

### v1.49.0 — Penegakan retensi data
- Perintah terjadwal untuk menganonimkan data pribadi yang melewati masa retensi (UU PDP). Mode aman (dry-run) secara bawaan.

### v1.48.0 — Halaman hukum & kepercayaan
- Halaman publik Syarat & Ketentuan, Kebijakan Privasi, DPA, dan Keamanan (kerangka menunggu peninjauan hukum).

### v1.43.0–v1.47.0 — Fondasi multi-penyewa (nonaktif)
- Isolasi data tingkat baris, penyiapan penyewa mandiri, white-label per penyewa, dan konsol penyedia. Dirilis dalam keadaan nonaktif.

### v1.35.0–v1.42.0 — Arsitektur multi-penyewa
- Penyiapan skema `tenant_id`, resolusi penyewa, konteks penyewa untuk antrean/penjadwalan, dan isolasi antar-penyewa.

### v1.32.0 — Hak subjek data (UU PDP)
- Ekspor dan anonimisasi/penghapusan data pribadi mandiri.

### v1.33.0 — Observabilitas
- ID korelasi permintaan dan kanal log JSON untuk penelusuran.

### v1.31.0 — Pengerasan keamanan
- Header keamanan dasar di setiap respons dan pemeriksaan dependensi (SCA) di CI.

---

## Stage 3 — Integrasi & platform

### v1.30.0 — Sinkronisasi kalender dua arah
- Alur koneksi per pengguna (Microsoft/Google) dengan penyegaran token (scaffold, flag-gated).

### v1.27.0 — Langganan kalender (.ics)
- Umpan kalender ber-token agar reservasi tampil di aplikasi kalender Anda.

### v1.26.0 — SSO Microsoft Entra ID
- Login OIDC via Entra ID / Azure AD (flag-gated).

### v1.21.0–v1.25.0 — Sumber daya yang dapat dipesan
- Generalisasi "ruang" menjadi "sumber daya" yang dapat dipesan (mis. kendaraan, peralatan), termasuk CRUD admin dan alur pemesanan.

### v1.18.0–v1.20.0 — API publik & laporan
- API publik (Sanctum) + webhook + dokumentasi OpenAPI, serta laporan terjadwal dan umpan BI.

### v1.17.0 — Kebijakan persetujuan
- UI admin untuk kebijakan persetujuan dan delegasi.

---

_Untuk pertanyaan, gunakan formulir Bantuan di dalam aplikasi._
