<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AnonymizeUserAction;
use App\Models\User;
use App\Services\PersonalDataExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * UU PDP (Stage 4f) — data-subject rights endpoints.
 *
 * Self-service: a signed-in user can export their own personal data.
 * Admin (users.update): export or anonymise (erase) any user when handling a
 * data-subject request.
 */
class DataSubjectController extends Controller
{
    public function exportMine(PersonalDataExporter $exporter): Response
    {
        $user = $this->currentUser();

        return $this->jsonDownload($exporter->export($user), $exporter->filename($user));
    }

    public function export(int $userId, PersonalDataExporter $exporter): Response
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($userId);

        return $this->jsonDownload($exporter->export($user), $exporter->filename($user));
    }

    public function anonymize(int $userId, AnonymizeUserAction $action): RedirectResponse
    {
        $this->authorizeAdmin();
        $actor = $this->currentUser();

        if ($actor->id === $userId) {
            return back()->withErrors(['anonymize' => __('Anda tidak dapat menganonimkan akun Anda sendiri.')]);
        }

        $user = User::findOrFail($userId);
        $action->execute($user, $actor->id);

        return redirect()->route('admin.users.index')
            ->with('status', __('Data pribadi pengguna telah dianonimkan.'));
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->currentUser()->hasPermission('users.update'), 403);
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonDownload(array $data, string $filename): Response
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
