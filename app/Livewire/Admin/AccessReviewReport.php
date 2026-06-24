<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\SettingsService;
use App\Support\CsvSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Access-review report (SOC 2 CC6.2/CC6.3 / ISO 27001 A.5.18): every user with
 * their roles, active state, MFA status and last login — flagging accounts idle
 * past security.inactive_account_days. CSV-exportable as periodic-review evidence.
 */
class AccessReviewReport extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->guard();
    }

    private function guard(): void
    {
        abort_unless(auth()->user()?->hasPermission('users.view'), 403);
    }

    private function thresholdDays(): int
    {
        return max(1, (int) app(SettingsService::class)->get('security.inactive_account_days', 90));
    }

    /**
     * @return list<array{user: User, roles: string, active: bool, mfa: bool, last_login_at: Carbon|null, inactive: bool}>
     */
    private function rows(): array
    {
        $cutoff = now()->subDays($this->thresholdDays());
        $term = trim($this->search);

        return User::query()
            ->with('roles:id,name')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => [
                'user' => $u,
                'roles' => $u->roles->pluck('name')->implode(', '),
                'active' => $u->is_active,
                'mfa' => $u->hasTwoFactorEnabled(),
                'last_login_at' => $u->last_login_at,
                'inactive' => $u->last_login_at === null || $u->last_login_at->lt($cutoff),
            ])
            ->all();
    }

    public function export(): StreamedResponse
    {
        $this->guard();

        $rows = $this->rows();
        $filename = 'access-review-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nama', 'Email', 'Peran', 'Status', '2FA', 'Login Terakhir', 'Tidak Aktif']);
            foreach ($rows as $r) {
                /** @var User $u */
                $u = $r['user'];
                fputcsv($out, array_map(
                    static fn (string $cell): string => CsvSanitizer::cell($cell),
                    [
                        $u->name,
                        $u->email,
                        $r['roles'],
                        $r['active'] ? 'aktif' : 'nonaktif',
                        $r['mfa'] ? 'ya' : 'tidak',
                        $r['last_login_at']?->format('Y-m-d H:i') ?? 'belum pernah',
                        $r['inactive'] ? 'ya' : 'tidak',
                    ],
                ));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        return view('livewire.admin.access-review-report', [
            'rows' => $this->rows(),
            'thresholdDays' => $this->thresholdDays(),
        ])->layout('layouts.app', [
            'title' => __('Tinjauan Akses'),
            'subtitle' => __('Pengguna, peran, status 2FA & login terakhir untuk tinjauan akses berkala'),
        ]);
    }
}
