<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\Unit;
use App\Models\User;
use App\Support\CsvSanitizer;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export for admin parameters — users, units, rooms, facilities, settings.
 *
 * Each entity is gated by its natural view permission. Encrypted settings are
 * masked (secrets are never exported in plaintext).
 */
class ParameterExportController extends Controller
{
    /** entity => required permission */
    private const PERMISSIONS = [
        'users' => 'users.view',
        'units' => 'users.view',
        'rooms' => 'rooms.view',
        'facilities' => 'rooms.view',
        'settings' => 'app-settings.view',
    ];

    public function download(string $entity): StreamedResponse
    {
        abort_unless(array_key_exists($entity, self::PERMISSIONS), 404);

        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasPermission(self::PERMISSIONS[$entity]), 403);

        [$header, $rows] = $this->dataFor($entity);

        $filename = 'export-'.$entity.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $header);
            foreach ($rows as $row) {
                // Neutralize spreadsheet formula injection in user-controlled
                // cells (names, emails, locations, etc.).
                fputcsv($out, array_map(
                    static fn ($cell): string => CsvSanitizer::cell((string) ($cell ?? '')),
                    $row,
                ));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: array<int, string>, 1: iterable<int, array<int, string|int|null>>}
     */
    private function dataFor(string $entity): array
    {
        return match ($entity) {
            'users' => $this->users(),
            'units' => $this->units(),
            'rooms' => $this->rooms(),
            'facilities' => $this->facilities(),
            'settings' => $this->settings(),
            default => [[], []],
        };
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>} */
    private function users(): array
    {
        $rows = User::query()->with(['unit:id,name', 'roles:id,name'])->orderBy('name')->get()
            ->map(fn (User $u): array => [
                $u->id, $u->name, $u->email, $u->employee_no,
                $u->unit instanceof Unit ? $u->unit->name : null,
                $u->job_title, $u->is_active ? 'aktif' : 'nonaktif',
                $u->roles->pluck('name')->implode(', '),
            ])->all();

        return [['ID', 'Nama', 'Email', 'No. Pegawai', 'Unit', 'Jabatan', 'Status', 'Peran'], $rows];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>} */
    private function units(): array
    {
        $rows = Unit::query()->with('parent:id,name')->orderBy('name')->get()
            ->map(fn (Unit $u): array => [
                $u->id, $u->code, $u->name,
                $u->parent instanceof Unit ? $u->parent->name : null,
                $u->is_active ? 'aktif' : 'nonaktif',
            ])->all();

        return [['ID', 'Kode', 'Nama', 'Induk', 'Status'], $rows];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>} */
    private function rooms(): array
    {
        $rows = Room::query()->orderBy('name')->get()
            ->map(fn (Room $r): array => [$r->id, $r->code, $r->name, $r->capacity, $r->location, $r->floor, $r->status->value])->all();

        return [['ID', 'Kode', 'Nama', 'Kapasitas', 'Lokasi', 'Lantai', 'Status'], $rows];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>} */
    private function facilities(): array
    {
        $rows = RoomFacility::query()->orderBy('name')->get()
            ->map(fn (RoomFacility $f): array => [$f->id, $f->code, $f->name, $f->category, $f->is_active ? 'aktif' : 'nonaktif'])->all();

        return [['ID', 'Kode', 'Nama', 'Kategori', 'Status'], $rows];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>} */
    private function settings(): array
    {
        $rows = AppSetting::query()->orderBy('group')->orderBy('key')->get()
            ->map(function (AppSetting $s): array {
                $value = $s->data_type === 'encrypted'
                    ? (blank($s->value) ? '' : '••••••')
                    : (string) ($s->value ?? '');

                return [$s->key, $s->group, $s->data_type, $value];
            })->all();

        return [['Kunci', 'Grup', 'Tipe', 'Nilai'], $rows];
    }
}
