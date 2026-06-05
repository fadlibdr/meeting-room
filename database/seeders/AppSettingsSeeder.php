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
