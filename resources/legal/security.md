# Keamanan

> **Halaman ini sengaja apa adanya.** Kami menyatakan kontrol yang benar-benar
> berjalan **dan** kontrol yang belum ada. Kami tidak mengklaim sertifikasi atau
> pengujian yang belum kami lakukan.

_Postur per: lihat tanggal rilis pada halaman [Catatan Rilis](/changelog) bila tersedia._

## Yang sudah berjalan

- **Enkripsi transport:** seluruh trafik melalui HTTPS.
- **Header keamanan dasar** diterapkan pada setiap respons (mis. nosniff,
  frame-ancestors, referrer-policy).
- **Kontrol akses berbasis peran (RBAC)** dengan izin granular.
- **Isolasi data multi-penyewa tingkat baris (row-level)** dengan global scope
  dan penyetempelan otomatis `tenant_id` saat penulisan.
- **Rahasia terenkripsi di basis data** (kredensial SMTP/OAuth) bergantung pada
  `APP_KEY` yang berada di luar cakupan cadangan basis data.
- **Log audit & korelasi permintaan** untuk penelusuran tindakan.
- **Cadangan basis data** dengan opsi penyalinan luar-lokasi (offsite) dan
  pemangkasan retensi; uji pemulihan didokumentasikan dalam runbook.
- **Penyimpanan waktu dalam UTC**, ditampilkan dalam zona waktu Jakarta.
- **Hak subjek data**: ekspor dan anonimisasi/penghapusan mandiri.

## Yang BELUM dilakukan (per saat ini)

- **Belum ada uji penetrasi (pen-test)** independen — termasuk verifikasi khusus
  **isolasi antar-penyewa**.
- **Belum ada uji beban (load test) tercatat** pada konfigurasi multi-penyewa.
- **Belum ada sertifikasi SOC 2 / ISO 27001.** Kontrol bergaya SOC 2 merupakan
  jalur proses/audit, bukan sekadar fitur perangkat lunak.
- **Belum ada sign-off kesiapan operasional formal** (D-12 keamanan / D-8 beban /
  D-4 operasional) untuk peluncuran multi-penyewa publik.
- **Target SLA formal** belum ditetapkan.

## Residensi data

Bawaan: satu basis data dengan isolasi tingkat baris. Untuk kebutuhan residensi/
keterpisahan fisik tersedia opsi **database-per-tenant** sebagai konfigurasi khusus.

## Melaporkan kerentanan

Jika Anda menemukan dugaan kerentanan, hubungi tim melalui formulir dukungan di
dalam aplikasi atau kontak DPO. Mohon jangan mengungkap secara publik sebelum kami
sempat menanggapi.
