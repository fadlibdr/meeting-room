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
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
