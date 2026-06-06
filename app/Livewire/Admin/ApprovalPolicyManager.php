<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\ApprovalStepType;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Stage 3 B (UI) — manage approval policies and their ordered steps.
 *
 * Gated by app-settings.update (super_admin / system_admin). Steps are stored
 * as a flat form array and re-created on save (delete-and-recreate keeps the
 * sequence contiguous and avoids partial-update edge cases).
 */
class ApprovalPolicyManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /** @var list<array{type: string, role_id: ?int, approver_user_id: ?int}> */
    public array $steps = [];

    public string $feedback = '';

    private function guard(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->hasPermission('app-settings.update')) {
            abort(403);
        }
    }

    public function newPolicy(): void
    {
        $this->guard();
        $this->reset(['editingId', 'name', 'description', 'steps']);
        $this->isActive = true;
        $this->steps = [$this->blankStep()];
        $this->showForm = true;
    }

    public function editPolicy(int $id): void
    {
        $this->guard();
        $policy = ApprovalPolicy::with('steps')->findOrFail($id);

        $this->editingId = $policy->id;
        $this->name = $policy->name;
        $this->description = $policy->description ?? '';
        $this->isActive = $policy->is_active;
        $this->steps = $policy->steps->map(fn (ApprovalPolicyStep $s): array => [
            'type' => $s->approver_type->value,
            'role_id' => $s->role_id,
            'approver_user_id' => $s->approver_user_id,
        ])->values()->all();

        if ($this->steps === []) {
            $this->steps = [$this->blankStep()];
        }
        $this->showForm = true;
    }

    public function addStep(): void
    {
        $this->steps[] = $this->blankStep();
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
        if ($this->steps === []) {
            $this->steps = [$this->blankStep()];
        }
    }

    public function save(): void
    {
        $this->guard();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('approval_policies', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.type' => ['required', Rule::enum(ApprovalStepType::class)],
            'steps.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'steps.*.approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Per-step: the chosen target must be present.
        foreach ($validated['steps'] as $i => $step) {
            if ($step['type'] === ApprovalStepType::Role->value && empty($step['role_id'])) {
                throw ValidationException::withMessages(["steps.$i.role_id" => __('Pilih peran untuk langkah ini.')]);
            }
            if ($step['type'] === ApprovalStepType::User->value && empty($step['approver_user_id'])) {
                throw ValidationException::withMessages(["steps.$i.approver_user_id" => __('Pilih pengguna untuk langkah ini.')]);
            }
        }

        DB::transaction(function () use ($validated): void {
            $policy = $this->editingId !== null
                ? ApprovalPolicy::findOrFail($this->editingId)
                : new ApprovalPolicy;

            $policy->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'is_active' => $validated['isActive'],
            ])->save();

            $policy->steps()->delete();
            foreach ($validated['steps'] as $i => $step) {
                $type = ApprovalStepType::from($step['type']);
                $policy->steps()->create([
                    'sequence_no' => $i + 1,
                    'approver_type' => $type->value,
                    'role_id' => $type === ApprovalStepType::Role ? $step['role_id'] : null,
                    'approver_user_id' => $type === ApprovalStepType::User ? $step['approver_user_id'] : null,
                ]);
            }
        });

        $this->feedback = $this->editingId !== null
            ? __('Kebijakan persetujuan diperbarui.')
            : __('Kebijakan persetujuan dibuat.');
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $this->guard();
        ApprovalPolicy::findOrFail($id)->delete();
        $this->feedback = __('Kebijakan persetujuan dihapus.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    /**
     * @return array{type: string, role_id: null, approver_user_id: null}
     */
    private function blankStep(): array
    {
        return ['type' => ApprovalStepType::UnitApprover->value, 'role_id' => null, 'approver_user_id' => null];
    }

    public function render(): View
    {
        return view('livewire.admin.approval-policy-manager', [
            'policies' => ApprovalPolicy::withCount('steps')->orderBy('name')->get(),
            'stepTypes' => ApprovalStepType::cases(),
            'roles' => Role::whereHas('permissions', fn ($q) => $q->where('code', 'bookings.approve'))->orderBy('name')->get(),
            'approverUsers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ])->layout('layouts.app', [
            'title' => __('Kebijakan Persetujuan'),
            'subtitle' => __('Rantai persetujuan multi-langkah'),
        ]);
    }
}
