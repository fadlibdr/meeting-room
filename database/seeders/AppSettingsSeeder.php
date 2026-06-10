<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'booking.default_buffer_minutes',
                'value' => '15',
                'data_type' => 'integer',
                'label' => 'Waktu Jeda antar Booking (menit)',
                'description' => 'Default jeda waktu setelah booking selesai sebelum slot tersedia kembali. Ruang yang memiliki nilai buffer khusus akan menggunakan nilai tersebut; jika nilai per-ruang adalah 0, sistem akan menggunakan default ini.',
                'group' => 'booking',
                'is_editable' => true,
            ],
            [
                'key' => 'booking.draft_purge_after_days',
                'value' => '7',
                'data_type' => 'integer',
                'label' => 'Hapus Draft Setelah (hari)',
                'description' => 'Booking dengan status draft yang tidak pernah disubmit akan dihapus otomatis setelah jumlah hari ini.',
                'group' => 'booking',
                'is_editable' => true,
            ],
            [
                'key' => 'booking.auto_release_grace_minutes',
                'value' => '15',
                'data_type' => 'integer',
                'label' => 'Toleransi Auto-Release (menit)',
                'description' => 'Reservasi disetujui yang sedang berlangsung namun belum check-in akan dilepas otomatis (dibatalkan, ruang dikembalikan) setelah toleransi ini sejak waktu mulai.',
                'group' => 'booking',
                'is_editable' => true,
            ],
            [
                'key' => 'notifications.send_email_default',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Kirim Email Notifikasi (default)',
                'description' => 'Apakah sistem mengirim email notifikasi untuk perubahan status booking secara default. Pengguna dapat mengoverride per akun di Phase 2.',
                'group' => 'notifications',
                'is_editable' => true,
            ],
            [
                'key' => 'system.maintenance_mode',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Mode Pemeliharaan',
                'description' => 'Aktifkan untuk menutup akses booking sementara saat pemeliharaan sistem. Pengguna admin tetap dapat akses.',
                'group' => 'system',
                'is_editable' => true,
            ],

            // ── User account policy ──
            [
                'key' => 'users.email_domain_restriction',
                'value' => '1',
                'data_type' => 'boolean',
                'label' => 'Batasi Domain Email',
                'description' => 'Jika aktif, email akun pengguna harus menggunakan domain yang diizinkan di bawah.',
                'group' => 'users',
                'is_editable' => true,
            ],
            [
                'key' => 'users.email_domain',
                'value' => 'bpjs-kesehatan.go.id',
                'data_type' => 'string',
                'label' => 'Domain Email yang Diizinkan',
                'description' => 'Domain (tanpa @) yang wajib digunakan email akun pengguna saat pembatasan aktif. Contoh: bpjs-kesehatan.go.id.',
                'group' => 'users',
                'is_editable' => true,
            ],

            // ── Email transport (managed in Settings → Email; defaults from .env at seed time) ──
            [
                'key' => 'email.mailer',
                'value' => (string) env('MAIL_MAILER', 'log'),
                'data_type' => 'string',
                'label' => 'Mailer',
                'description' => 'Driver pengiriman email — gunakan "smtp" untuk mengirim email sungguhan, atau "log" untuk mencatat ke berkas log.',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.host',
                'value' => env('MAIL_HOST'),
                'data_type' => 'string',
                'label' => 'Host SMTP',
                'description' => 'Alamat server SMTP (mis. smtp.bpjs-kesehatan.go.id).',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.port',
                'value' => (string) env('MAIL_PORT', 587),
                'data_type' => 'integer',
                'label' => 'Port SMTP',
                'description' => 'Port server SMTP (mis. 587 untuk STARTTLS, 465 untuk SSL).',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.username',
                'value' => env('MAIL_USERNAME'),
                'data_type' => 'string',
                'label' => 'Username SMTP',
                'description' => 'Nama pengguna untuk autentikasi SMTP.',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.password',
                'value' => null,
                'data_type' => 'encrypted',
                'label' => 'Password SMTP',
                'description' => 'Kata sandi SMTP. Disimpan terenkripsi; biarkan kosong saat menyunting untuk tidak mengubah. Jika kosong, sistem memakai nilai dari .env.',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.encryption',
                'value' => env('MAIL_SCHEME'),
                'data_type' => 'string',
                'label' => 'Skema SMTP',
                'description' => 'Skema koneksi SMTP — "smtps" untuk SSL (port 465), atau kosongkan agar terdeteksi otomatis dari port.',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.from_address',
                'value' => env('MAIL_FROM_ADDRESS'),
                'data_type' => 'string',
                'label' => 'Alamat Pengirim',
                'description' => 'Alamat email yang muncul sebagai pengirim.',
                'group' => 'email',
                'is_editable' => true,
            ],
            [
                'key' => 'email.from_name',
                'value' => env('MAIL_FROM_NAME', 'Meeting Room BPJS Kesehatan'),
                'data_type' => 'string',
                'label' => 'Nama Pengirim',
                'description' => 'Nama tampilan pengirim email.',
                'group' => 'email',
                'is_editable' => true,
            ],

            // --- System (Stage 3) ---
            [
                'key' => 'system.tenancy_enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Mode Multi-Penyewa (Tenancy)',
                'description' => 'PERINGATAN: Mengaktifkan isolasi data multi-penyewa untuk seluruh platform. Hanya aktifkan saat hendak melayani lebih dari satu organisasi. Data yang ada tetap berada di penyewa default (BPJS Kesehatan).',
                'group' => 'system',
                'is_editable' => true,
            ],
            [
                'key' => 'system.max_booking_duration_hours',
                'value' => (string) env('MEETING_ROOM_MAX_DURATION_HOURS', 8),
                'data_type' => 'integer',
                'label' => 'Durasi Maksimal Rapat (jam)',
                'description' => 'Batas durasi satu reservasi. Reservasi yang melebihi batas ini ditolak saat validasi.',
                'group' => 'system',
                'is_editable' => true,
            ],

            // --- SSO (Stage 3 F.1 — Microsoft Entra ID) ---
            [
                'key' => 'sso.enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Aktifkan SSO Microsoft',
                'description' => 'Tampilkan tombol "Masuk dengan Microsoft" dan aktifkan login OIDC Entra ID. Memerlukan Tenant/Client ID/Secret di bawah.',
                'group' => 'sso',
                'is_editable' => true,
            ],
            [
                'key' => 'sso.auto_provision',
                'value' => '1',
                'data_type' => 'boolean',
                'label' => 'Buat Akun Otomatis (SSO)',
                'description' => 'Buat akun pengguna secara otomatis saat login SSO pertama kali. Jika nonaktif, hanya pengguna terdaftar yang dapat masuk via SSO.',
                'group' => 'sso',
                'is_editable' => true,
            ],
            [
                'key' => 'sso.default_role',
                'value' => 'requester',
                'data_type' => 'string',
                'label' => 'Peran Default SSO',
                'description' => 'Kode peran yang diberikan ke pengguna SSO baru bila tidak ada grup AD yang cocok (mis. requester).',
                'group' => 'sso',
                'is_editable' => true,
            ],
            [
                'key' => 'sso.azure_tenant_id',
                'value' => env('AZURE_TENANT_ID'),
                'data_type' => 'string',
                'label' => 'Azure Tenant ID',
                'description' => 'Directory (tenant) ID dari app registration Entra ID.',
                'group' => 'sso',
                'is_editable' => true,
            ],
            [
                'key' => 'sso.azure_client_id',
                'value' => env('AZURE_CLIENT_ID'),
                'data_type' => 'string',
                'label' => 'Azure Client ID',
                'description' => 'Application (client) ID dari app registration Entra ID.',
                'group' => 'sso',
                'is_editable' => true,
            ],
            [
                'key' => 'sso.azure_client_secret',
                'value' => null,
                'data_type' => 'encrypted',
                'label' => 'Azure Client Secret',
                'description' => 'Client secret Entra ID. Disimpan terenkripsi; biarkan kosong saat menyunting untuk tidak mengubah. Jika kosong, sistem memakai nilai dari .env.',
                'group' => 'sso',
                'is_editable' => true,
            ],

            // --- Calendar sync (Stage 3 F.2b/c) ---
            [
                'key' => 'calendar.sync_enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Aktifkan Sinkronisasi Kalender',
                'description' => 'Dorong perubahan reservasi (disetujui/diubah/dibatalkan) ke kalender Outlook/Google pengguna. Memerlukan kredensial penyedia.',
                'group' => 'calendar',
                'is_editable' => true,
            ],
            [
                'key' => 'calendar.consent_mode',
                'value' => 'delegated',
                'data_type' => 'string',
                'label' => 'Mode Izin Kalender',
                'description' => 'delegated (tiap pengguna menghubungkan kalendernya) atau application (satu kredensial aplikasi menulis ke semua kalender via persetujuan admin).',
                'group' => 'calendar',
                'is_editable' => true,
            ],
            [
                'key' => 'calendar.microsoft_enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Sinkronisasi Microsoft (Graph)',
                'description' => 'Aktifkan penulisan event ke kalender Outlook/M365. Memakai kredensial Azure di grup SSO.',
                'group' => 'calendar',
                'is_editable' => true,
            ],
            [
                'key' => 'calendar.google_enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Sinkronisasi Google',
                'description' => 'Aktifkan penulisan event ke Google Calendar. Memerlukan Client ID/Secret Google di bawah.',
                'group' => 'calendar',
                'is_editable' => true,
            ],
            [
                'key' => 'calendar.google_client_id',
                'value' => env('GOOGLE_CLIENT_ID'),
                'data_type' => 'string',
                'label' => 'Google Client ID',
                'description' => 'OAuth client ID dari project Google Cloud.',
                'group' => 'calendar',
                'is_editable' => true,
            ],
            [
                'key' => 'calendar.google_client_secret',
                'value' => null,
                'data_type' => 'encrypted',
                'label' => 'Google Client Secret',
                'description' => 'OAuth client secret Google. Disimpan terenkripsi; biarkan kosong saat menyunting untuk tidak mengubah. Jika kosong, sistem memakai nilai dari .env.',
                'group' => 'calendar',
                'is_editable' => true,
            ],

            // --- Telegram notifications ---
            [
                'key' => 'telegram.enabled',
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Aktifkan Notifikasi Telegram',
                'description' => 'Kirim notifikasi reservasi via bot Telegram kepada pengguna yang telah mengisi Chat ID Telegram di profil mereka. Memerlukan Token Bot di bawah.',
                'group' => 'telegram',
                'is_editable' => true,
            ],
            [
                'key' => 'telegram.bot_token',
                'value' => null,
                'data_type' => 'encrypted',
                'label' => 'Token Bot Telegram',
                'description' => 'Token bot dari @BotFather. Disimpan terenkripsi; biarkan kosong saat menyunting untuk tidak mengubah. Jika kosong, sistem memakai nilai dari .env.',
                'group' => 'telegram',
                'is_editable' => true,
            ],
            [
                'key' => 'telegram.bot_username',
                'value' => null,
                'data_type' => 'string',
                'label' => 'Username Bot Telegram',
                'description' => 'Username bot tanpa tanda @ (mis. SirraNotif_bot). Dipakai untuk tautan "Hubungkan Telegram" di profil.',
                'group' => 'telegram',
                'is_editable' => true,
            ],
            [
                'key' => 'telegram.webhook_secret',
                'value' => null,
                'data_type' => 'encrypted',
                'label' => 'Secret Webhook Telegram',
                'description' => 'Segmen rahasia pada URL webhook (mis. string acak panjang). Diperlukan agar penangkapan Chat ID otomatis via /start berfungsi. Jalankan "php artisan telegram:set-webhook" setelah mengubah.',
                'group' => 'telegram',
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $setting) {
            $existing = AppSetting::query()->where('key', $setting['key'])->first();

            if ($existing === null) {
                AppSetting::create($setting);

                continue;
            }

            // Idempotent: sync metadata but PRESERVE the stored value so that
            // re-seeding (the deploy runs this with --force) never wipes an
            // admin-edited setting — critical for the email/SMTP config.
            $existing->update([
                'data_type' => $setting['data_type'],
                'label' => $setting['label'],
                'description' => $setting['description'] ?? null,
                'group' => $setting['group'],
                'is_editable' => $setting['is_editable'],
            ]);
        }
    }
}
